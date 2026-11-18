<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserDevice extends Model
{
    protected $table = 'v2_user_device';
    protected $dateFormat = 'U';
    protected $guarded = ['id'];
    protected $casts = [
        'metadata' => 'array',
        'first_seen_at' => 'integer',
        'last_seen_at' => 'integer',
        'last_subscription_at' => 'integer',
        'traffic_updated_at' => 'integer',
        'blocked_until' => 'integer',
        'u' => 'integer',
        'd' => 'integer',
        'charged_u' => 'integer',
        'charged_d' => 'integer',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<Server, $this> */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'last_server_id');
    }

    /** @return HasMany<UserDevicePresence, $this> */
    public function presences(): HasMany
    {
        return $this->hasMany(UserDevicePresence::class, 'device_id');
    }

    /** @return HasMany<StatDevice, $this> */
    public function traffic(): HasMany
    {
        return $this->hasMany(StatDevice::class, 'device_id');
    }
}
