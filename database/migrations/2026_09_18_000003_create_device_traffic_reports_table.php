<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('v2_device_traffic_report')) {
            Schema::create('v2_device_traffic_report', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('server_id');
                $table->char('report_id', 64);
                $table->unsignedInteger('record_count')->default(0);
                $table->unsignedInteger('created_at');
                $table->unsignedInteger('updated_at');

                $table->unique(['server_id', 'report_id'], 'uq_device_traffic_report');
                $table->index('created_at', 'idx_device_traffic_report_created');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_device_traffic_report');
    }
};
