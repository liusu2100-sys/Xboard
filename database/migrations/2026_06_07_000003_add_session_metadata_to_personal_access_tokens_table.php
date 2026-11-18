<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('personal_access_tokens')) {
            return;
        }

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            if (!Schema::hasColumn('personal_access_tokens', 'ip_address')) {
                $table->string('ip_address', 64)->nullable()->after('name');
            }
            if (!Schema::hasColumn('personal_access_tokens', 'user_agent')) {
                $table->string('user_agent', 512)->nullable()->after('ip_address');
            }
            if (!Schema::hasColumn('personal_access_tokens', 'device_name')) {
                $table->string('device_name', 120)->nullable()->after('user_agent');
            }
            if (!Schema::hasColumn('personal_access_tokens', 'browser')) {
                $table->string('browser', 60)->nullable()->after('device_name');
            }
            if (!Schema::hasColumn('personal_access_tokens', 'platform')) {
                $table->string('platform', 60)->nullable()->after('browser');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('personal_access_tokens')) {
            return;
        }

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            foreach (['platform', 'browser', 'device_name', 'user_agent', 'ip_address'] as $column) {
                if (Schema::hasColumn('personal_access_tokens', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
