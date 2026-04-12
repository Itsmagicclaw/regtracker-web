<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SanctionsEntry extends Model
{
    protected $table = 'sanctions_entries';

    protected $fillable = [
        'change_id',
        'list_source',
        'entry_type',
        'primary_name',
        'aliases',
        'date_added',
        'date_removed',
        'reason',
        'raw_data',
        'is_active',
    ];

    protected $casts = [
        'aliases' => 'array',
        'raw_data' => 'array',
        'is_active' => 'boolean',
        'date_added' => 'date',
        'date_removed' => 'date',
    ];

    public function change(): BelongsTo
    {
        return $this->belongsTo(DetectedChange::class, 'change_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeBySource($query, string $source)
    {
        return $query->where('list_source', $source);
    }

    public function scopeIndividuals($query)
    {
        return $query->where('entry_type', 'individual');
    }

    public function scopeEntities($query)
    {
        return $query->where('entry_type', 'entity');
    }
}
