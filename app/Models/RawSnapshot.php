<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RawSnapshot extends Model
{
    protected $table = 'raw_snapshots';

    protected $fillable = [
        'source_id',
        'snapshot_at',
        'content_hash',
        'raw_content',
        'file_size_bytes',
        'record_count',
        'status',
    ];

    protected $casts = [
        'snapshot_at' => 'datetime',
        'file_size_bytes' => 'integer',
        'record_count' => 'integer',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(RegulatorySource::class, 'source_id');
    }
}
