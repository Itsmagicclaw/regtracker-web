<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class DetectedChange extends Model
{
    protected $table = 'detected_changes';

    protected $fillable = [
        'source_id',
        'detected_at',
        'change_type',
        'severity',
        'title',
        'plain_english_summary',
        'raw_diff_content',
        'affected_jurisdictions',
        'affected_corridors',
        'effective_date',
        'deadline',
        'source_reference',
        'source_url',
        'qa_confidence_score',
        'qa_status',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'detected_at' => 'datetime',
        'effective_date' => 'date',
        'deadline' => 'date',
        'approved_at' => 'datetime',
        'qa_confidence_score' => 'float',
        'affected_jurisdictions' => 'array',
        'affected_corridors' => 'array',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(RegulatorySource::class, 'source_id');
    }

    public function actionItems(): HasMany
    {
        return $this->hasMany(ActionItem::class, 'change_id');
    }

    public function mtoAlerts(): HasMany
    {
        return $this->hasMany(MtoAlert::class, 'change_id');
    }

    public function sanctionsEntries(): HasMany
    {
        return $this->hasMany(SanctionsEntry::class, 'change_id');
    }

    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }

    public function scopeHigh($query)
    {
        return $query->where('severity', 'high');
    }

    public function scopePending($query)
    {
        return $query->where('qa_status', 'pending');
    }
}
