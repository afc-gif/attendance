<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DeploymentHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeploymentMonitorTest extends TestCase
{
    use RefreshDatabase;

    private string $historyPath;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        $this->historyPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'attendance-deployments-'.Str::uuid().'.jsonl';
        $this->app->instance(DeploymentHistoryService::class, new DeploymentHistoryService($this->historyPath));
    }

    protected function tearDown(): void
    {
        if (is_file($this->historyPath)) {
            unlink($this->historyPath);
        }

        parent::tearDown();
    }

    public function test_admin_can_view_deployment_summary_and_stage_details(): void
    {
        file_put_contents($this->historyPath, implode("\n", [
            json_encode([
                'deployment_id' => 'deploy-1',
                'timestamp' => '2026-11-18T10:00:00.0000000+00:00',
                'status' => 'running',
                'stage' => 'detected',
                'commit' => 'abcdef1234567890',
                'message' => 'New commit detected.',
                'error' => null,
            ], JSON_THROW_ON_ERROR),
            json_encode([
                'deployment_id' => 'deploy-1',
                'timestamp' => '2026-11-18T10:02:00.0000000+00:00',
                'status' => 'failed',
                'stage' => 'health_check',
                'commit' => 'abcdef1234567890',
                'message' => 'Health check failed.',
                'error' => 'HTTP endpoint returned 503.',
            ], JSON_THROW_ON_ERROR),
        ]));

        $admin = User::create([
            'name' => 'Deployment Admin',
            'email' => 'deployment-admin@example.com',
            'password' => 'password123',
            'role' => 'admin',
            'status' => 'approved',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.deployments.index'))
            ->assertOk()
            ->assertSee('Deployment Monitor')
            ->assertSee('Health check failed.')
            ->assertSee('HTTP endpoint returned 503.');

        $this->actingAs($admin)
            ->getJson(route('admin.deployments.data'))
            ->assertOk()
            ->assertJsonPath('current.status', 'failed')
            ->assertJsonPath('current.stage', 'health_check')
            ->assertJsonPath('last_failed.commit', 'abcdef1234567890')
            ->assertJsonPath('deployments.0.events.1.error', 'HTTP endpoint returned 503.');
    }

    public function test_non_admin_cannot_view_deployment_dashboard_or_data(): void
    {
        $staff = User::create([
            'name' => 'Regular Staff',
            'email' => 'deployment-staff@example.com',
            'password' => 'password123',
            'role' => 'afc_staff',
            'status' => 'approved',
        ]);

        $this->actingAs($staff)
            ->get(route('admin.deployments.index'))
            ->assertForbidden();

        $this->actingAs($staff)
            ->getJson(route('admin.deployments.data'))
            ->assertForbidden();
    }
}
