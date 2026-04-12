<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MtoProfile extends Model
{
    protected $table = 'mto_profiles';

    protected $fillable = [
        'mto_name',
        'primary_contact_name',
        'primary_contact_email',
        'license_jurisdictions',
        'active_corridors',
        'license_types',
        'notification_email',
        'notification_preference',
        'created_by_admin',
        'is_active',
    ];

    protected $casts = [
        'license_jurisdictions' => 'array',
        'active_corridors' => 'array',
        'license_types' => 'array',
        'is_active' => 'boolean',
    ];

    public function mtoUsers(): HasMany
    {
        return $this->hasMany(MtoUser::class, 'mto_profile_id');
    }

    public function mtoAlerts(): HasMany
    {
        return $this->hasMany(MtoAlert::class, 'mto_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeLicensedIn($query, string $jurisdiction)
    {
        return $query->whereJsonContains('license_jurisdictions', $jurisdiction);
    }

    public function scopeOperatingIn($query, string $corridor)
    {
        return $query->whereJsonContains('active_corridors', $corridor);
    }
}
