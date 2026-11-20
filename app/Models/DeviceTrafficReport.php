<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceTrafficReport extends Model
{
    protected $table = 'v2_device_traffic_report';
    protected $dateFormat = 'U';
    protected $guarded = ['id'];
}
