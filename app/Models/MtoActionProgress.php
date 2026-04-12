<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MtoActionProgress extends Model
{
    protected $table = 'mto_action_progress';

    protected $fillable = [
        'mto_alert_id',
        'action_item_id',
        'status',
        'started_at',
        'completed_at',
        'notes',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function mtoAlert(): BelongsTo
    {
        return $this->belongsTo(MtoAlert::class, 'mto_alert_id');
    }

    public function actionItem(): BelongsTo
    {
        return $this->belongsTo(ActionItem::class, 'action_item_id');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
