<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;

class DeploymentHistoryService
{
    public function __construct(private readonly ?string $historyPath = null) {}

    public function snapshot(): array
    {
        $deployments = $this->deployments();

        return [
            'current' => $deployments[0] ?? null,
            'last_successful' => collect($deployments)->firstWhere('status', 'succeeded'),
            'last_failed' => collect($deployments)->firstWhere('status', 'failed'),
            'deployments' => array_slice($deployments, 0, 50),
        ];
    }

    private function deployments(): array
    {
        $path = $this->historyPath ?? storage_path('app/private/deployment-events.jsonl');

        if (! is_file($path)) {
            return [];
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('Deployment history could not be read.');
        }

        $deployments = [];
        foreach (explode("\n", $contents) as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            try {
                $record = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                Log::warning('Skipped a malformed deployment history record.', ['line' => $index + 1]);

                continue;
            }

            if (! $this->isValidRecord($record)) {
                Log::warning('Skipped an invalid deployment history record.', ['line' => $index + 1]);

                continue;
            }

            $id = $record['deployment_id'];
            $event = [
                'timestamp' => $record['timestamp'],
                'status' => $record['status'],
                'stage' => $record['stage'],
                'message' => $record['message'],
                'error' => $record['error'] ?? null,
            ];

            if (! isset($deployments[$id])) {
                $deployments[$id] = [
                    'id' => $id,
                    'status' => 'running',
                    'commit' => $record['commit'],
                    'stage' => $record['stage'],
                    'started_at' => $record['timestamp'],
                    'finished_at' => null,
                    'message' => $record['message'],
                    'error' => null,
                    'events' => [],
                    'last_event_at' => $record['timestamp'],
                ];
            }

            $deployments[$id]['events'][] = $event;

            if (strcmp($record['timestamp'], $deployments[$id]['last_event_at']) >= 0) {
                $deployments[$id]['status'] = $record['status'];
                $deployments[$id]['commit'] = $record['commit'];
                $deployments[$id]['stage'] = $record['stage'];
                $deployments[$id]['message'] = $record['message'];
                $deployments[$id]['error'] = $record['error'] ?? null;
                $deployments[$id]['last_event_at'] = $record['timestamp'];

                if (in_array($record['status'], ['succeeded', 'failed'], true)) {
                    $deployments[$id]['finished_at'] = $record['timestamp'];
                }
            }
        }

        foreach ($deployments as &$deployment) {
            unset($deployment['last_event_at']);
            usort($deployment['events'], fn (array $a, array $b) => strcmp($a['timestamp'], $b['timestamp']));
        }
        unset($deployment);

        $deployments = array_values($deployments);
        usort($deployments, fn (array $a, array $b) => strcmp($b['started_at'], $a['started_at']));

        return $deployments;
    }

    private function isValidRecord(mixed $record): bool
    {
        return is_array($record)
            && is_string($record['deployment_id'] ?? null)
            && $record['deployment_id'] !== ''
            && is_string($record['timestamp'] ?? null)
            && $this->isValidTimestamp($record['timestamp'])
            && in_array($record['status'] ?? null, ['running', 'succeeded', 'failed'], true)
            && is_string($record['stage'] ?? null)
            && is_string($record['message'] ?? null)
            && (is_string($record['commit'] ?? null) || ($record['commit'] ?? null) === null)
            && (is_string($record['error'] ?? null) || ($record['error'] ?? null) === null);
    }

    private function isValidTimestamp(string $timestamp): bool
    {
        if (preg_match(
            '/^(?<year>\d{4})-(?<month>\d{2})-(?<day>\d{2})T(?<hour>\d{2}):(?<minute>\d{2}):(?<second>\d{2})(?:\.\d+)?(?<zone>Z|[+-]\d{2}:\d{2})$/D',
            $timestamp,
            $matches
        ) !== 1) {
            return false;
        }

        $year = (int) $matches['year'];
        $month = (int) $matches['month'];
        $day = (int) $matches['day'];
        $hour = (int) $matches['hour'];
        $minute = (int) $matches['minute'];
        $second = (int) $matches['second'];

        if (! checkdate($month, $day, $year)
            || $hour > 23
            || $minute > 59
            || $second > 59) {
            return false;
        }

        if ($matches['zone'] !== 'Z') {
            $zone = $matches['zone'];
            $zoneHours = (int) substr($zone, 1, 2);
            $zoneMinutes = (int) substr($zone, 4, 2);

            if ($zoneHours > 14 || $zoneMinutes > 59 || ($zoneHours === 14 && $zoneMinutes !== 0)) {
                return false;
            }
        }

        return true;
    }
}
