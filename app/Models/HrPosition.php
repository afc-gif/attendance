<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrPosition extends Model
{
    use SoftDeletes;

    protected $fillable = ['department_id', 'company', 'title', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function department()
    {
        return $this->belongsTo(HrDepartment::class, 'department_id');
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'position_id');
    }
}
