<?php

namespace App\Services;

use App\Models\Employee;

class EmployeeIdService
{
    public function generate(string $company): string
    {
        if (! in_array($company, ['ARTSCI', 'AFC'], true)) {
            throw new \InvalidArgumentException('Invalid employee company.');
        }

        $prefix = $company === 'ARTSCI' ? 'ART' : 'AFC';

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $employeeId = $prefix.'-'.random_int(100000, 999999);

            if (! Employee::withTrashed()->where('employee_id', $employeeId)->exists()) {
                return $employeeId;
            }
        }

        throw new \RuntimeException('Unable to generate a unique employee ID.');
    }
}
