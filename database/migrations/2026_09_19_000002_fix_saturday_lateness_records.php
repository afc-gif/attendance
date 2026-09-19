<?php

use App\Models\AttendanceRecord;
use App\Models\SalaryDeduction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Re-evaluate all Saturday attendance records
        $records = AttendanceRecord::whereNotNull('check_in_at')->get();

        $tz = config('app.timezone', 'Africa/Lagos');

        foreach ($records as $record) {
            $date = Carbon::parse($record->attendance_date);

            // Only process Saturday records
            if (!$date->isSaturday()) {
                continue;
            }

            $user = User::find($record->user_id);
            if (!$user) {
                continue;
            }

            $profile = $user->staffProfile;
            if ($profile && $profile->isOffDay($date)) {
                // If it's an off-day, should be present
                if ($record->status === 'late') {
                    $record->update(['status' => 'present']);
                    $this->waiveRecordDeduction($record);
                }
                continue;
            }

            $expectedTimeStr = $profile ? $profile->getExpectedResumptionTime($date, '08:00') : '09:00';
            $lateThreshold   = $profile ? $profile->grace_period_minutes : 15;

            $checkInLocal    = Carbon::parse($record->check_in_at)->setTimezone($tz);
            $expectedArrival = Carbon::createFromFormat('Y-m-d H:i', $date->toDateString() . ' ' . $expectedTimeStr, $tz);
            $lateDeadline    = (clone $expectedArrival)->addMinutes($lateThreshold);

            $shouldBeStatus  = $checkInLocal->greaterThan($lateDeadline) ? 'late' : 'present';

            if ($record->status !== $shouldBeStatus) {
                $record->update(['status' => $shouldBeStatus]);

                if ($shouldBeStatus === 'present') {
                    $this->waiveRecordDeduction($record);
                }
            }
        }
    }

    protected function waiveRecordDeduction(AttendanceRecord $record): void
    {
        $deduction = SalaryDeduction::where('attendance_record_id', $record->id)->first();
        if ($deduction && $deduction->status === 'active') {
            $deduction->update([
                'status' => 'waived',
                'waiver_reason' => 'Automated correction: Saturday 09:00 AM resumption rule update',
                'waived_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reversal needed
    }
};
