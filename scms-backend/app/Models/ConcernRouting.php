<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConcernRouting extends Model
{
    use HasFactory;

    protected $table = 'concern_routing';

    protected $fillable = [
        'concern_id',
        'action',
        'performed_by',
        'notes',
    ];

    /**
     * Get the concern this routing entry belongs to.
     */
    public function concern(): BelongsTo
    {
        return $this->belongsTo(Concern::class, 'concern_id', 'concern_id');
    }

    /**
     * Get the user who performed this action.
     */
    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
