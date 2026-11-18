<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('v2_user_device')) {
            Schema::create('v2_user_device', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id');
                $table->char('device_key', 64);
                $table->string('identity_source', 24)->default('ip');
                $table->string('name', 120)->nullable();
                $table->string('model', 120)->nullable();
                $table->string('client_name', 80)->nullable();
                $table->string('client_version', 40)->nullable();
                $table->string('platform', 80)->nullable();
                $table->string('user_agent', 512)->nullable();
                $table->string('last_ip', 45)->nullable();
                $table->string('region', 180)->nullable();
                $table->unsignedBigInteger('last_server_id')->nullable();
                $table->unsignedBigInteger('u')->default(0);
                $table->unsignedBigInteger('d')->default(0);
                $table->unsignedBigInteger('charged_u')->default(0);
                $table->unsignedBigInteger('charged_d')->default(0);
                $table->unsignedInteger('first_seen_at');
                $table->unsignedInteger('last_seen_at');
                $table->unsignedInteger('last_subscription_at')->nullable();
                $table->unsignedInteger('traffic_updated_at')->nullable();
                $table->unsignedInteger('blocked_until')->nullable();
                $table->json('metadata')->nullable();
                $table->unsignedInteger('created_at');
                $table->unsignedInteger('updated_at');

                $table->unique(['user_id', 'device_key'], 'uq_user_device_key');
                $table->index(['user_id', 'last_seen_at'], 'idx_user_device_seen');
                $table->index('blocked_until', 'idx_user_device_blocked');
            });
        }

        if (!Schema::hasTable('v2_user_device_presence')) {
            Schema::create('v2_user_device_presence', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('device_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('server_id');
                $table->string('ip', 45)->nullable();
                $table->boolean('is_online')->default(false);
                $table->unsignedInteger('first_seen_at');
                $table->unsignedInteger('last_seen_at');
                $table->unsignedInteger('last_offline_at')->nullable();
                $table->unsignedInteger('created_at');
                $table->unsignedInteger('updated_at');

                $table->unique(['device_id', 'server_id'], 'uq_device_server_presence');
                $table->index(['user_id', 'server_id', 'is_online'], 'idx_user_server_online');
                $table->index(['is_online', 'last_seen_at'], 'idx_device_presence_alive');
            });
        }

        if (!Schema::hasTable('v2_stat_device')) {
            Schema::create('v2_stat_device', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('device_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('server_id');
                $table->decimal('server_rate', 10, 3)->default(1);
                $table->unsignedBigInteger('u')->default(0);
                $table->unsignedBigInteger('d')->default(0);
                $table->unsignedBigInteger('charged_u')->default(0);
                $table->unsignedBigInteger('charged_d')->default(0);
                $table->unsignedInteger('record_at');
                $table->unsignedInteger('created_at');
                $table->unsignedInteger('updated_at');

                $table->unique(
                    ['device_id', 'server_id', 'server_rate', 'record_at'],
                    'uq_stat_device_day'
                );
                $table->index(['user_id', 'record_at'], 'idx_stat_device_user_day');
                $table->index(['device_id', 'record_at'], 'idx_stat_device_device_day');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('v2_stat_device');
        Schema::dropIfExists('v2_user_device_presence');
        Schema::dropIfExists('v2_user_device');
    }
};
