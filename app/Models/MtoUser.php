<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\HasApiTokens;

class MtoUser extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'mto_users';

    protected $fillable = [
        'mto_profile_id',
        'name',
        'email',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    public function mtoProfile(): BelongsTo
    {
        return $this->belongsTo(MtoProfile::class, 'mto_profile_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
