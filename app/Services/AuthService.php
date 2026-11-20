<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService
{
    private const SESSION_NAME_SEPARATOR = ' · ';

    private User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function generateAuthData(): array
    {
        $sessionMetadata = $this->makeSessionMetadata();
        $token = $this->user->createToken(
            $sessionMetadata['device_name'],
            ['*'],
            now()->addYear()
        );
        $this->storeSessionMetadata($token->accessToken, $sessionMetadata);

        // Format token: remove ID prefix and add Bearer
        $tokenParts = explode('|', $token->plainTextToken);
        $formattedToken = 'Bearer ' . ($tokenParts[1] ?? $tokenParts[0]);

        return [
            'token' => $this->user->token,
            'auth_data' => $formattedToken,
            'is_admin' => $this->user->is_admin,
        ];
    }

    public function getSessions(): array
    {
        $currentToken = $this->user->currentAccessToken();
        $this->backfillCurrentTokenMetadata($currentToken);
        $currentTokenId = $currentToken?->id;

        return $this->user->tokens()
            ->orderByDesc('last_used_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($token) use ($currentTokenId) {
                $data = $token->toArray();
                $data['device'] = $this->formatTokenDevice($token);
                $data['ip'] = $this->formatTokenIp($token);
                $data['browser'] = $token->browser ?: null;
                $data['platform'] = $token->platform ?: null;
                $data['user_agent'] = $token->user_agent ?: null;
                $data['login_at'] = $token->created_at;
                $data['last_activity'] = $token->last_used_at ?: ($token->updated_at ?: $token->created_at);
                $data['is_current'] = (int) ((string) $token->id === (string) $currentTokenId);
                $data['is_legacy'] = $this->isLegacySession($token);
                return $data;
            })
            ->toArray();
    }

    public function removeSession(string $sessionId): bool
    {
        $this->user->tokens()->where('id', $sessionId)->delete();
        return true;
    }

    public function removeAllSessions(): bool
    {
        $this->user->tokens()->delete();
        return true;
    }

    private function makeSessionMetadata(): array
    {
        $userAgent = (string) request()->userAgent();
        $client = $this->parseClientInfo($userAgent);
        $ip = $this->resolveClientIp();
        $deviceName = $client['device_name'] ?: '未知设备';

        return [
            'ip_address' => $ip,
            'user_agent' => Str::limit($userAgent, 512, ''),
            'device_name' => Str::limit($deviceName, 120, ''),
            'browser' => $client['browser'],
            'platform' => $client['platform'],
        ];
    }

    private function storeSessionMetadata(PersonalAccessToken $token, array $metadata, bool $onlyMissing = false): void
    {
        if (!$this->supportsSessionMetadata()) {
            return;
        }

        $payload = [];
        foreach (['ip_address', 'user_agent', 'device_name', 'browser', 'platform'] as $key) {
            if ($onlyMissing && filled($token->{$key})) {
                continue;
            }
            $payload[$key] = $metadata[$key] ?: null;
        }

        if ($payload === []) {
            return;
        }

        $token->forceFill($payload)->save();
    }

    private function backfillCurrentTokenMetadata($token): void
    {
        if (!$token instanceof PersonalAccessToken || !$this->supportsSessionMetadata()) {
            return;
        }

        if (filled($token->ip_address) && filled($token->user_agent) && filled($token->device_name)) {
            return;
        }

        $this->storeSessionMetadata($token, $this->makeSessionMetadata(), true);
        $token->refresh();
    }

    private function supportsSessionMetadata(): bool
    {
        static $supports = null;
        if ($supports !== null) {
            return $supports;
        }

        return $supports = Schema::hasColumn('personal_access_tokens', 'ip_address');
    }

    private function formatTokenDevice($token): string
    {
        if (filled($token->device_name)) {
            return (string) $token->device_name;
        }

        $name = (string) $token->name;
        if ($name === '' || preg_match('/^[A-Za-z0-9]{20}$/', $name)) {
            return '登录会话 #' . $token->id;
        }

        return explode(self::SESSION_NAME_SEPARATOR, $name, 2)[0] ?: $name;
    }

    private function formatTokenIp($token): ?string
    {
        if (filled($token->ip_address)) {
            return (string) $token->ip_address;
        }

        $name = (string) $token->name;
        if (!str_contains($name, self::SESSION_NAME_SEPARATOR)) {
            return null;
        }

        return explode(self::SESSION_NAME_SEPARATOR, $name, 2)[1] ?: null;
    }

    private function isLegacySession($token): bool
    {
        return blank($token->ip_address) && blank($token->user_agent) && !str_contains((string) $token->name, self::SESSION_NAME_SEPARATOR);
    }

    private function parseClientInfo(string $userAgent): array
    {
        $browser = match (true) {
            str_contains($userAgent, 'MicroMessenger/') => 'WeChat',
            str_contains($userAgent, 'Edg/') || str_contains($userAgent, 'EdgiOS/') || str_contains($userAgent, 'EdgA/') => 'Edge',
            str_contains($userAgent, 'OPR/') || str_contains($userAgent, 'Opera/') => 'Opera',
            str_contains($userAgent, 'Firefox/') || str_contains($userAgent, 'FxiOS/') => 'Firefox',
            str_contains($userAgent, 'Chrome/') || str_contains($userAgent, 'CriOS/') => 'Chrome',
            str_contains($userAgent, 'Safari/') => 'Safari',
            $userAgent === '' => '未知浏览器',
            default => 'Browser'
        };

        $platform = match (true) {
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Mac OS X') || str_contains($userAgent, 'Macintosh') => 'macOS',
            str_contains($userAgent, 'iPhone') => 'iPhone',
            str_contains($userAgent, 'iPad') => 'iPad',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'Linux') => 'Linux',
            $userAgent === '' => '未知系统',
            default => 'Unknown Device'
        };

        return [
            'browser' => $browser,
            'platform' => $platform,
            'device_name' => "{$browser} on {$platform}",
        ];
    }

    private function resolveClientIp(): ?string
    {
        $request = request();
        foreach (['CF-Connecting-IP', 'X-Real-IP', 'Client-IP'] as $header) {
            $ip = $this->firstValidIp((string) $request->headers->get($header, ''));
            if ($ip) {
                return $ip;
            }
        }

        $forwardedFor = (string) $request->headers->get('X-Forwarded-For', '');
        foreach (explode(',', $forwardedFor) as $candidate) {
            $ip = $this->firstValidIp($candidate);
            if ($ip) {
                return $ip;
            }
        }

        return $this->firstValidIp((string) $request->ip());
    }

    private function firstValidIp(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $value = trim($value, '[]');
        if (str_contains($value, ':') && substr_count($value, ':') === 1 && str_contains($value, '.')) {
            [$value] = explode(':', $value, 2);
        }

        return filter_var($value, FILTER_VALIDATE_IP) ? $value : null;
    }

    public static function findUserByBearerToken(string $bearerToken): ?User
    {
        $token = str_replace('Bearer ', '', $bearerToken);
        
        $accessToken = PersonalAccessToken::findToken($token);
        
        $tokenable = $accessToken?->tokenable;
        
        return $tokenable instanceof User ? $tokenable : null;
    }

    /**
     * 解密认证数据
     *
     * @param string $authorization
     * @return array|null 用户数据或null
     */
    public static function decryptAuthData(string $authorization): ?array
    {
        $user = self::findUserByBearerToken($authorization);
        
        if (!$user) {
            return null;
        }
        
        return [
            'id' => $user->id,
            'email' => $user->email,
            'is_admin' => (bool)$user->is_admin,
            'is_staff' => (bool)$user->is_staff
        ];
    }
}
