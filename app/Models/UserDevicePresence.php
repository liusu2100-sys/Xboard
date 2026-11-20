<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDevicePresence extends Model
{
    protected $table = 'v2_user_device_presence';
    protected $dateFormat = 'U';
    protected $guarded = ['id'];
    protected $casts = [
        'is_online' => 'boolean',
        'first_seen_at' => 'integer',
        'last_seen_at' => 'integer',
        'last_offline_at' => 'integer',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(UserDevice::class, 'device_id');
    }

    /** @return BelongsTo<Server, $this> */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'server_id');
    }
}
