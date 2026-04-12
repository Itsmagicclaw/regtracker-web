<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScraperHealth extends Model
{
    protected $table = 'scraper_health';

    protected $fillable = [
        'source_id',
        'status',
        'run_at',
        'run_duration_ms',
        'records_fetched',
        'changes_detected',
        'error_message',
    ];

    protected $casts = [
        'run_at'           => 'datetime',
        'run_duration_ms'  => 'integer',
        'records_fetched'  => 'integer',
        'changes_detected' => 'integer',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(RegulatorySource::class, 'source_id');
    }
}
