<?php

namespace App\Http\Controllers\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\UserChangePassword;
use App\Http\Requests\User\UserTransfer;
use App\Http\Requests\User\UserUpdate;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Auth\LoginService;
use App\Services\AuthService;
use App\Services\Plugin\HookManager;
use App\Services\UserService;
use App\Utils\CacheKey;
use App\Utils\Helper;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    protected $loginService;

    public function __construct(
        LoginService $loginService
    ) {
        $this->loginService = $loginService;
    }

    public function getActiveSession(Request $request)
    {
        $user = $request->user();
        $authService = new AuthService($user);
        return $this->success($authService->getSessions());
    }

    public function removeActiveSession(Request $request)
    {
        $user = $request->user();
        $authService = new AuthService($user);
        return $this->success($authService->removeSession($request->input('session_id')));
    }

    public function checkLogin(Request $request)
    {
        $data = [
            'is_login' => $request->user()?->id ? true : false
        ];
        if ($request->user()?->is_admin) {
            $data['is_admin'] = true;
        }
        return $this->success($data);
    }

    public function changePassword(UserChangePassword $request)
    {
        $user = $request->user();
        if (
            !Helper::multiPasswordVerify(
                $user->password_algo,
                $user->password_salt,
                $request->input('old_password'),
                $user->password
            )
        ) {
            return $this->fail([400, __('The old password is wrong')]);
        }
        $user->password = password_hash($request->input('new_password'), PASSWORD_DEFAULT);
        $user->password_algo = NULL;
        $user->password_salt = NULL;
        if (!$user->save()) {
            return $this->fail([400, __('Save failed')]);
        }
        
        $currentToken = $user->currentAccessToken();
        if ($currentToken) {
            $user->tokens()->where('id', '!=', $currentToken->id)->delete();
        } else {
            $user->tokens()->delete();
        }
        
        return $this->success(true);
    }

    public function info(Request $request)
    {
        $user = User::where('id', $request->user()->id)
            ->select([
                'email',
                'transfer_enable',
                'last_login_at',
                'created_at',
                'banned',
                'remind_expire',
                'remind_traffic',
                'expired_at',
                'balance',
                'commission_balance',
                'plan_id',
                'discount',
                'commission_rate',
                'telegram_id',
                'uuid'
            ])
            ->first();
        if (!$user) {
            return $this->fail([400, __('The user does not exist')]);
        }
        $user['avatar_url'] = 'https://cdn.v2ex.com/gravatar/' . md5($user->email) . '?s=64&d=identicon';
        return $this->success($user);
    }

    public function getStat(Request $request)
    {
        $stat = [
            Order::where('status', 0)
                ->where('user_id', $request->user()->id)
                ->count(),
            Ticket::where('status', 0)
                ->where('user_id', $request->user()->id)
                ->count(),
            User::where('invite_user_id', $request->user()->id)
                ->count()
        ];
        return $this->success($stat);
    }

    public function getSubscribe(Request $request)
    {
        $user = User::where('id', $request->user()->id)
            ->select([
                'plan_id',
                'token',
                'expired_at',
                'u',
                'd',
                'transfer_enable',
                'email',
                'uuid',
                'device_limit',
                'speed_limit',
                'next_reset_at'
            ])
            ->first();
        if (!$user) {
            return $this->fail([400, __('The user does not exist')]);
        }
        if ($user->plan_id) {
            $user['plan'] = Plan::find($user->plan_id);
            if (!$user['plan']) {
                return $this->fail([400, __('Subscription plan does not exist')]);
            }
        }
        $user['subscribe_url'] = Helper::getSubscribeUrl($user['token']);
        $userService = new UserService();
        $user['reset_day'] = $userService->getResetDay($user);
        $user = HookManager::filter('user.subscribe.response', $user);
        return $this->success($user);
    }

    public function getSubscribeQrCode(Request $request)
    {
        $request->validate([
            'target' => ['nullable', 'string', 'in:raw,clash,meta,flclash,stash,surge,surfboard,sing-box,hiddify,nekobox,v2rayng,loon,shadowrocket'],
            'mode' => ['nullable', 'string', 'in:subscribe,import'],
            'size' => ['nullable', 'integer', 'min:240', 'max:800'],
            'use_current_host' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $target = strtolower((string) $request->input('target', 'flclash'));
        $mode = strtolower((string) $request->input('mode', 'subscribe'));
        $size = (int) $request->input('size', 420);

        $subscribeUrl = Helper::getSubscribeUrl($user->token);
        $subscribeUrl = $this->normalizeSubscribeUrlForRequest(
            $subscribeUrl,
            $request,
            $request->boolean('use_current_host')
        );
        $targetSubscribeUrl = $target === 'raw'
            ? $subscribeUrl
            : $this->appendQueryParameter($subscribeUrl, 'flag', $target);

        $appName = admin_setting('app_name', 'Xboard');
        $importUrl = $this->buildSubscribeImportUrl($target, $targetSubscribeUrl, $appName);
        $qrContent = $mode === 'import' ? $importUrl : $targetSubscribeUrl;
        $cacheKey = 'user:subscribe_qrcode:' . sha1($qrContent . '|' . $size);
        $image = Cache::remember($cacheKey, now()->addDay(), function () use ($qrContent, $size) {
            $svg = $this->makeSubscribeQrSvg($qrContent, $size);
            return 'data:image/svg+xml;base64,' . base64_encode($svg);
        });

        return $this->success([
            'image' => $image,
            'content' => $qrContent,
            'subscribe_url' => $targetSubscribeUrl,
            'import_url' => $importUrl,
            'target' => $target,
            'mode' => $mode,
        ]);
    }

    public function resetSecurity(Request $request)
    {
        $user = $request->user();
        $user->uuid = Helper::guid(true);
        $user->token = Helper::guid();
        if (!$user->save()) {
            return $this->fail([400, __('Reset failed')]);
        }
        return $this->success(Helper::getSubscribeUrl($user->token));
    }

    public function update(UserUpdate $request)
    {
        $updateData = $request->only([
            'remind_expire',
            'remind_traffic'
        ]);

        $user = $request->user();
        try {
            $user->update($updateData);
        } catch (\Exception $e) {
            return $this->fail([400, __('Save failed')]);
        }

        return $this->success(true);
    }

    private function makeSubscribeQrSvg(string $content, int $size): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, 4),
            new SvgImageBackEnd()
        );

        return (new Writer($renderer))->writeString(
            $content,
            'UTF-8',
            ErrorCorrectionLevel::L()
        );
    }

    private function normalizeSubscribeUrlForRequest(string $url, Request $request, bool $preferRequestHost = false): string
    {
        $parts = parse_url($url);
        if (!$parts || empty($parts['host'])) {
            return $url;
        }

        $host = trim((string) $parts['host'], '[]');
        if (!$preferRequestHost && !in_array($host, ['localhost', '127.0.0.1', '0.0.0.0', '::1'], true)) {
            return $url;
        }

        $originParts = parse_url($request->getSchemeAndHttpHost());
        if (!$originParts || empty($originParts['host'])) {
            return $url;
        }

        $parts['scheme'] = $originParts['scheme'] ?? $request->getScheme();
        $parts['host'] = $originParts['host'];

        if (isset($originParts['port'])) {
            $parts['port'] = $originParts['port'];
        } else {
            unset($parts['port']);
        }

        return $this->buildUrlFromParts($parts);
    }

    private function appendQueryParameter(string $url, string $key, string $value): string
    {
        $parts = parse_url($url);
        if (!$parts) {
            $joiner = str_contains($url, '?') ? '&' : '?';
            return $url . $joiner . rawurlencode($key) . '=' . rawurlencode($value);
        }

        parse_str($parts['query'] ?? '', $query);
        $query[$key] = $value;
        $parts['query'] = http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        return $this->buildUrlFromParts($parts);
    }

    private function buildSubscribeImportUrl(string $target, string $subscribeUrl, string $appName): string
    {
        $encodedUrl = rawurlencode($subscribeUrl);
        $encodedName = rawurlencode($appName);
        $base64Url = rtrim(strtr(base64_encode($subscribeUrl), '+/', '-_'), '=');

        return match ($target) {
            'shadowrocket' => 'shadowrocket://add/sub://' . $base64Url . '?remark=' . $encodedName,
            'stash' => 'stash://install-config?url=' . $encodedUrl . '&name=' . $encodedName,
            'surge' => 'surge:///install-config?url=' . $encodedUrl . '&name=' . $encodedName,
            'surfboard' => 'surfboard:///install-config?url=' . $encodedUrl . '&name=' . $encodedName,
            'sing-box' => 'sing-box://import-remote-profile?url=' . $encodedUrl . '#' . $encodedName,
            'hiddify' => 'hiddify://import/' . $encodedUrl . '#' . $encodedName,
            'v2rayng' => 'v2rayng://install-sub?url=' . $encodedUrl . '&name=' . $encodedName,
            'loon' => 'loon://import?sub=' . $encodedUrl,
            'raw' => $subscribeUrl,
            default => 'clash://install-config?url=' . $encodedUrl . '&name=' . $encodedName,
        };
    }

    private function buildUrlFromParts(array $parts): string
    {
        $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $user = $parts['user'] ?? '';
        $pass = isset($parts['pass']) ? ':' . $parts['pass'] : '';
        $auth = $user !== '' ? $user . $pass . '@' : '';
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return $scheme . $auth . $host . $port . $path . $query . $fragment;
    }

    public function transfer(UserTransfer $request)
    {
        $amount = $request->input('transfer_amount');
        try {
            DB::transaction(function () use ($request, $amount) {
                $user = User::lockForUpdate()->find($request->user()->id);
                if (!$user) {
                    throw new \Exception(__('The user does not exist'));
                }
                if ($amount > $user->commission_balance) {
                    throw new \Exception(__('Insufficient commission balance'));
                }
                $user->commission_balance -= $amount;
                $user->balance += $amount;
                if (!$user->save()) {
                    throw new \Exception(__('Transfer failed'));
                }
            });
        } catch (\Exception $e) {
            return $this->fail([400, $e->getMessage()]);
        }
        return $this->success(true);
    }

    public function getQuickLoginUrl(Request $request)
    {
        $user = $request->user();

        $url = $this->loginService->generateQuickLoginUrl($user, $request->input('redirect'));
        return $this->success($url);
    }
}
