<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeEmploymentHistory extends Model
{
    protected $fillable = [
        'employee_id', 'company', 'department_id', 'position_id',
        'effective_date', 'event_type', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return ['effective_date' => 'date'];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
