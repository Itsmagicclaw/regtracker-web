<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class MtoAlert extends Model
{
    protected $table = 'mto_alerts';

    protected $fillable = [
        'mto_id',
        'change_id',
        'alerted_at',
        'alerted_via',
        'email_opened_at',
        'dashboard_viewed_at',
    ];

    protected $casts = [
        'alerted_at' => 'datetime',
        'email_opened_at' => 'datetime',
        'dashboard_viewed_at' => 'datetime',
    ];

    public function mtoProfile(): BelongsTo
    {
        return $this->belongsTo(MtoProfile::class, 'mto_id');
    }

    public function change(): BelongsTo
    {
        return $this->belongsTo(DetectedChange::class, 'change_id');
    }

    public function actionProgress(): HasMany
    {
        return $this->hasMany(MtoActionProgress::class, 'mto_alert_id');
    }

    public function scopeInstant($query)
    {
        return $query->whereIn('alerted_via', ['email', 'both']);
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('email_opened_at')->whereNull('dashboard_viewed_at');
    }
}
