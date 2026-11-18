<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatDevice extends Model
{
    protected $table = 'v2_stat_device';
    protected $dateFormat = 'U';
    protected $guarded = ['id'];
    protected $casts = [
        'server_rate' => 'float',
        'u' => 'integer',
        'd' => 'integer',
        'charged_u' => 'integer',
        'charged_d' => 'integer',
        'record_at' => 'integer',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    /** @return BelongsTo<UserDevice, $this> */
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
