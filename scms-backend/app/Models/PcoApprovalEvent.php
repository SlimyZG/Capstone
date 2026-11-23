<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PcoApprovalEvent extends Model
{
    use HasFactory;

    protected $table = 'pco_approval_events';

    protected $fillable = [
        'pco_approval_request_id',
        'concern_id',
        'event_type',
        'actor_id',
        'actor_name',
        'actor_role',
        'actor_office_name',
        'notes',
    ];

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(PcoApprovalRequest::class, 'pco_approval_request_id');
    }

    public function concern(): BelongsTo
    {
        return $this->belongsTo(Concern::class, 'concern_id', 'concern_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
