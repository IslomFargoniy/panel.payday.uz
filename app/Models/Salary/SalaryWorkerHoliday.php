<?php

namespace App\Models\Salary;

use Illuminate\Database\Eloquent\Model;

class SalaryWorkerHoliday extends Model
{
    protected $fillable = [
        'salary_id',
        'from',
        'to',
        'comment',
    ];

    public function salary()
    {
        return $this->belongsTo(Salary::class, 'salary_id');
    }
}
