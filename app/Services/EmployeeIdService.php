<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Support\Facades\DB;

class EmployeeIdService
{
    public function generate(string $company): string
    {
        if (! in_array($company, ['ARTSCI', 'AFC'], true)) {
            throw new \InvalidArgumentException('Invalid employee company.');
        }

        return DB::transaction(function () use ($company) {
            $prefix = $company === 'ARTSCI' ? 'ART' : 'AFC';
            $last = Employee::withTrashed()
                ->where('company', $company)
                ->where('employee_id', 'like', $prefix.'-%')
                ->lockForUpdate()
                ->orderByRaw('LENGTH(employee_id) DESC')
                ->orderByDesc('employee_id')
                ->value('employee_id');

            $next = $last ? ((int) substr($last, strrpos($last, '-') + 1)) + 1 : 1;

            do {
                $employeeId = $prefix.'-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
                $exists = Employee::withTrashed()->where('employee_id', $employeeId)->exists();
                $next++;
            } while ($exists);

            return $employeeId;
        });
    }
}
