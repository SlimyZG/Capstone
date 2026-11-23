<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConcernInternalMessage extends Model
{
    use HasFactory;

    protected $table = 'concern_internal_messages';

    protected $fillable = [
        'concern_id',
        'sender_id',
        'sender_name',
        'sender_role',
        'sender_dept_id',
        'sender_academic_unit_id',
        'sender_department_name',
        'message_type',
        'body',
    ];

    public function concern(): BelongsTo
    {
        return $this->belongsTo(Concern::class, 'concern_id', 'concern_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function senderDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'sender_dept_id');
    }

    public function senderAcademicUnit(): BelongsTo
    {
        return $this->belongsTo(AcademicUnit::class, 'sender_academic_unit_id');
    }
}
