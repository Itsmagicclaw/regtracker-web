<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegulatorySource extends Model
{
    protected $table = 'regulatory_sources';

    protected $fillable = [
        'name',
        'type',
        'jurisdiction',
        'source_url',
        'check_frequency_hours',
        'last_checked_at',
        'last_changed_at',
        'last_status',
        'is_active',
    ];

    protected $casts = [
        'check_frequency_hours' => 'integer',
        'is_active' => 'boolean',
        'last_checked_at' => 'datetime',
        'last_changed_at' => 'datetime',
    ];

    public function snapshots(): HasMany
    {
        return $this->hasMany(RawSnapshot::class, 'source_id');
    }

    public function changes(): HasMany
    {
        return $this->hasMany(DetectedChange::class, 'source_id');
    }

    public function health(): HasMany
    {
        return $this->hasMany(ScraperHealth::class, 'source_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
