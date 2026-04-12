<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class ActionItem extends Model
{
    protected $table = 'action_items';

    protected $fillable = [
        'change_id',
        'action_order',
        'action_text',
        'category',
        'applies_to_jurisdictions',
        'applies_to_corridors',
        'is_required',
        'deadline_days',
    ];

    protected $casts = [
        'action_order' => 'integer',
        'is_required' => 'boolean',
        'deadline_days' => 'integer',
        'applies_to_jurisdictions' => 'array',
        'applies_to_corridors' => 'array',
    ];

    public function change(): BelongsTo
    {
        return $this->belongsTo(DetectedChange::class, 'change_id');
    }

    public function mtoActionProgress(): HasMany
    {
        return $this->hasMany(MtoActionProgress::class, 'action_item_id');
    }

    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    public function scopeOptional($query)
    {
        return $query->where('is_required', false);
    }
}
