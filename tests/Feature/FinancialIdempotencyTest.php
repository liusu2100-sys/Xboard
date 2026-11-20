<?php

namespace Tests\Feature;

use App\Console\Commands\CheckCommission;
use App\Exceptions\ApiException;
use App\Jobs\OrderHandleJob;
use App\Models\CommissionLog;
use App\Models\Coupon;
use App\Models\GiftCardCode;
use App\Models\GiftCardTemplate;
use App\Models\GiftCardUsage;
use App\Models\Order;
use App\Models\User;
use App\Services\GiftCardService;
use App\Services\OrderService;
use App\Support\Setting;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class FinancialIdempotencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.financial_test', config('database.connections.sqlite'));
        DB::setDefaultConnection('financial_test');

        Schema::create('v2_coupon', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('limit_use')->nullable();
            $table->integer('created_at');
            $table->integer('updated_at');
        });

        Schema::create('v2_order', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->integer('plan_id')->default(1);
            $table->integer('coupon_id')->nullable();
            $table->integer('coupon_released_at')->nullable();
            $table->string('trade_no', 36)->unique();
            $table->integer('total_amount')->default(0);
            $table->integer('status')->default(Order::STATUS_PENDING);
            $table->integer('balance_amount')->nullable();
            $table->integer('paid_at')->nullable();
            $table->string('callback_no')->nullable();
            $table->integer('invite_user_id')->nullable();
            $table->integer('commission_status')->default(0);
            $table->integer('commission_balance')->default(0);
            $table->integer('actual_commission_balance')->nullable();
            $table->integer('created_at');
            $table->integer('updated_at');
        });

        Schema::create('v2_user', function (Blueprint $table) {
            $table->increments('id');
            $table->string('email')->nullable();
            $table->integer('invite_user_id')->nullable();
            $table->integer('plan_id')->nullable();
            $table->bigInteger('transfer_enable')->default(0);
            $table->integer('device_limit')->default(0);
            $table->integer('balance')->default(0);
            $table->integer('commission_balance')->default(0);
            $table->integer('created_at');
            $table->integer('updated_at');
        });

        Schema::create('v2_commission_log', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('invite_user_id');
            $table->integer('user_id');
            $table->string('trade_no', 36);
            $table->char('idempotency_key', 64)->nullable()->unique();
            $table->integer('order_amount');
            $table->integer('get_amount');
            $table->integer('created_at');
            $table->integer('updated_at');
        });

        Schema::create('v2_plan', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('sort')->default(0);
            $table->integer('created_at');
            $table->integer('updated_at');
        });

        Schema::create('v2_gift_card_template', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('type');
            $table->boolean('status')->default(true);
            $table->json('conditions')->nullable();
            $table->json('rewards');
            $table->json('limits')->nullable();
            $table->json('special_config')->nullable();
            $table->string('icon')->nullable();
            $table->string('background_image')->nullable();
            $table->string('theme_color')->default('#1890ff');
            $table->integer('created_at');
            $table->integer('updated_at');
        });

        Schema::create('v2_gift_card_code', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('template_id');
            $table->string('code')->unique();
            $table->integer('status')->default(0);
            $table->integer('user_id')->nullable();
            $table->integer('used_at')->nullable();
            $table->integer('expires_at')->nullable();
            $table->json('actual_rewards')->nullable();
            $table->integer('usage_count')->default(0);
            $table->integer('max_usage')->default(1);
            $table->integer('created_at');
            $table->integer('updated_at');
        });

        Schema::create('v2_gift_card_usage', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('code_id');
            $table->integer('template_id');
            $table->integer('user_id');
            $table->integer('invite_user_id')->nullable();
            $table->json('rewards_given');
            $table->json('invite_rewards')->nullable();
            $table->integer('user_level_at_use')->nullable();
            $table->integer('plan_id_at_use')->nullable();
            $table->decimal('multiplier_applied', 3, 2)->default(1);
            $table->string('user_agent')->nullable();
            $table->text('notes')->nullable();
            $table->integer('created_at');
        });
    }

    public function test_duplicate_payment_callback_dispatches_order_once(): void
    {
        Bus::fake([OrderHandleJob::class]);
        $order = Order::create([
            'user_id' => 1,
            'trade_no' => 'TEST-PAYMENT-1',
            'status' => Order::STATUS_PENDING,
        ]);

        $this->assertTrue((new OrderService($order))->paid('callback-1'));
        $this->assertTrue((new OrderService($order->fresh()))->paid('callback-1'));

        $this->assertSame(Order::STATUS_PROCESSING, $order->fresh()->status);
        Bus::assertDispatchedSyncTimes(OrderHandleJob::class, 1);
    }

    public function test_duplicate_cancellation_releases_coupon_once(): void
    {
        $coupon = Coupon::create(['limit_use' => 4]);
        $order = Order::create([
            'user_id' => 1,
            'coupon_id' => $coupon->id,
            'trade_no' => 'TEST-CANCEL-1',
            'status' => Order::STATUS_PENDING,
        ]);

        $this->assertTrue((new OrderService($order))->cancel());
        $this->assertFalse((new OrderService($order->fresh()))->cancel());

        $this->assertSame(5, $coupon->fresh()->limit_use);
        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->coupon_released_at);
    }

    public function test_gift_card_cannot_be_redeemed_twice(): void
    {
        $user = User::withoutEvents(fn() => User::create(['email' => 'gift@example.com']));
        $template = GiftCardTemplate::create([
            'name' => 'Idempotency Gift',
            'type' => GiftCardTemplate::TYPE_GENERAL,
            'status' => true,
            'rewards' => [],
        ]);
        $code = GiftCardCode::create([
            'template_id' => $template->id,
            'code' => 'GIFTTEST0001',
            'status' => GiftCardCode::STATUS_UNUSED,
            'usage_count' => 0,
            'max_usage' => 1,
        ]);

        (new GiftCardService($code->code))->setUser($user)->redeem();

        $this->expectException(ApiException::class);
        try {
            (new GiftCardService($code->code))->setUser($user->fresh())->redeem();
        } finally {
            $this->assertSame(1, $code->fresh()->usage_count);
            $this->assertSame(1, GiftCardUsage::count());
        }
    }

    public function test_commission_job_only_pays_an_order_once(): void
    {
        app()->instance(Setting::class, new class extends Setting {
            public function __construct() {}
            public function get(string $key, mixed $default = null): mixed
            {
                return $default;
            }
        });

        $inviter = User::withoutEvents(fn() => User::create(['email' => 'inviter@example.com']));
        $order = Order::create([
            'user_id' => 2,
            'invite_user_id' => $inviter->id,
            'trade_no' => 'TEST-COMMISSION-1',
            'status' => Order::STATUS_COMPLETED,
            'commission_status' => 1,
            'commission_balance' => 100,
        ]);

        $command = app(CheckCommission::class);
        $command->autoPayCommission();
        $command->autoPayCommission();

        $this->assertSame(100, $inviter->fresh()->commission_balance);
        $this->assertSame(2, $order->fresh()->commission_status);
        $this->assertSame(1, CommissionLog::count());
    }
}
