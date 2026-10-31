<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassList extends Model
{
    protected $fillable = [
        'student_id_number',
        'academic_unit_id',
    ];

    public function academicUnit()
    {
        return $this->belongsTo(AcademicUnit::class);
    }
}
