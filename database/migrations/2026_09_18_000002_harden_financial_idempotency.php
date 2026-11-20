<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('v2_order', 'coupon_released_at')) {
            Schema::table('v2_order', function (Blueprint $table) {
                $table->unsignedInteger('coupon_released_at')->nullable()->after('coupon_id');
            });
        }

        if (!Schema::hasColumn('v2_commission_log', 'idempotency_key')) {
            Schema::table('v2_commission_log', function (Blueprint $table) {
                $table->char('idempotency_key', 64)->nullable()->after('trade_no');
                $table->unique('idempotency_key', 'uq_commission_idempotency');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('v2_commission_log', 'idempotency_key')) {
            Schema::table('v2_commission_log', function (Blueprint $table) {
                $table->dropUnique('uq_commission_idempotency');
                $table->dropColumn('idempotency_key');
            });
        }

        if (Schema::hasColumn('v2_order', 'coupon_released_at')) {
            Schema::table('v2_order', function (Blueprint $table) {
                $table->dropColumn('coupon_released_at');
            });
        }
    }
};
