<?php
declare (strict_types = 1);

namespace app\service;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\BeforeValidException;
use think\facade\Config;
use think\facade\Cache;
use UnexpectedValueException;
use DomainException;

class JwtService
{
    protected string $secret;
    protected int $expire;
    protected int $refreshExpire;
    protected string $algorithm;
    protected string $blacklistPrefix;
    /** SDK 会话 JWT 有效期（秒），默认 1 小时，用于注入后 APK 的卡密校验鉴权 */
    protected int $sdkSessionExpire;

    public function __construct()
    {
        $this->secret         = Config::get('jwt.secret');
        $this->expire         = (int) Config::get('jwt.expire', 7200);
        $this->refreshExpire  = (int) Config::get('jwt.refresh_expire', 604800);
        $this->algorithm      = Config::get('jwt.algorithm', 'HS256');
        $this->blacklistPrefix = Config::get('jwt.blacklist_prefix', 'jwt_blacklist:');
        $this->sdkSessionExpire = (int) Config::get('jwt.sdk_session_expire', 3600);

        if (empty($this->secret) || strlen($this->secret) < 32) {
            throw new \RuntimeException('JWT secret must be configured and at least 32 characters long');
        }
    }

    public function generateToken(array $payload, bool $isRefresh = false): string
    {
        $now = time();
        $exp = $isRefresh ? $this->refreshExpire : $this->expire;

        $tokenPayload = array_merge($payload, [
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $exp,
            'type' => $isRefresh ? 'refresh' : 'access',
            'jti' => $this->generateJti(),
        ]);

        return JWT::encode($tokenPayload, $this->secret, $this->algorithm);
    }

    public function verifyToken(string $token, bool $isRefresh = false): ?array
    {
        try {
            if ($this->isBlacklisted($token)) {
                return null;
            }

            $decoded = JWT::decode($token, new Key($this->secret, $this->algorithm));
            $payload = (array) $decoded;

            if ($isRefresh && ($payload['type'] ?? '') !== 'refresh') {
                return null;
            }

            if (!$isRefresh && ($payload['type'] ?? '') !== 'access') {
                return null;
            }

            return $payload;
        } catch (ExpiredException $e) {
            return null;
        } catch (SignatureInvalidException $e) {
            return null;
        } catch (BeforeValidException $e) {
            return null;
        } catch (UnexpectedValueException $e) {
            return null;
        } catch (DomainException $e) {
            return null;
        } catch (\InvalidArgumentException $e) {
            return null;
        }
    }

    public function refreshToken(string $refreshToken): ?array
    {
        $payload = $this->verifyToken($refreshToken, true);
        if (!$payload) {
            return null;
        }

        $accessPayload = [
            'user_id'     => $payload['user_id'],
            'username'    => $payload['username'],
            'role_type'   => $payload['role_type'],
            'merchant_id' => $payload['merchant_id'] ?? null,
        ];

        $newAccessToken = $this->generateToken($accessPayload, false);
        $newRefreshToken = $this->generateToken($accessPayload, true);

        $this->addToBlacklist($refreshToken, $payload['exp'] - time());

        return [
            'access_token'  => $newAccessToken,
            'refresh_token' => $newRefreshToken,
            'expires_in'    => $this->expire,
        ];
    }

    public function addToBlacklist(string $token, int $ttl = null): bool
    {
        $payload = $this->getPayloadWithoutVerify($token);
        if (!$payload) {
            return false;
        }

        $jti = $payload['jti'] ?? md5($token);
        $exp = $payload['exp'] ?? time() + $this->expire;
        $ttl = $ttl ?? max(0, $exp - time());

        if ($ttl <= 0) {
            return true;
        }

        Cache::store('redis')->set($this->blacklistPrefix . $jti, 1, $ttl);
        return true;
    }

    public function isBlacklisted(string $token): bool
    {
        // 先验签再用 payload['jti'] 查询黑名单 (I5)
        // 防止攻击者构造任意 jti 探测哪些已加入黑名单
        try {
            $payload = JWT::decode($token, new Key($this->secret, $this->algorithm ?: 'HS256'));
            $jti = $payload->jti ?? null;
            if (!$jti) {
                return false;
            }
            return Cache::store('redis')->has($this->blacklistPrefix . $jti);
        } catch (\Exception $e) {
            // 验签失败/格式错误/过期 → 视为已失效 (拒绝)
            return true;
        }
    }

    protected function getPayloadWithoutVerify(string $token): ?array
    {
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return null;
            }
            $payload = JWT::jsonDecode(JWT::urlsafeB64Decode($parts[1]));
            return (array) $payload;
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function generateJti(): string
    {
        return bin2hex(random_bytes(16));
    }

    public function parseTokenFromHeader(string $header): ?string
    {
        $prefix = Config::get('jwt.prefix', 'Bearer ');
        if (str_starts_with($header, $prefix)) {
            return trim(substr($header, strlen($prefix)));
        }
        return null;
    }

    /**
     * 签发 SDK 会话 JWT（Task 3 / 支撑 C2）。
     *
     * 用于注入后 APK 的卡密校验鉴权：SDK 用 task_token 调用 /api/v1/sdk/auth 换取本 JWT，
     * 后续卡密校验请求通过 Authorization: Bearer <token> 鉴权（无需 app_secret）。
     *
     * payload 设计：
     * - task_id / app_id / merchant_id / app_key：业务上下文
     * - type='sdk_session'：与 access/refresh 区分，校验时严格匹配
     * - 不含 app_secret（C2 修复核心）
     *
     * @param array $claims 必须包含 task_id / app_id / merchant_id / app_key
     * @return string JWT
     */
    public function signSdkSession(array $claims): string
    {
        $now = time();
        $payload = array_merge($claims, [
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $this->sdkSessionExpire,
            'type' => 'sdk_session',
            'jti' => $this->generateJti(),
        ]);

        return JWT::encode($payload, $this->secret, $this->algorithm);
    }

    /**
     * 校验 SDK 会话 JWT。
     *
     * @param string $token Bearer token
     * @return array|null 成功返回 payload，失败返回 null
     */
    public function verifySdkSession(string $token): ?array
    {
        try {
            if ($this->isBlacklisted($token)) {
                return null;
            }

            $decoded = JWT::decode($token, new Key($this->secret, $this->algorithm));
            $payload = (array) $decoded;

            if (($payload['type'] ?? '') !== 'sdk_session') {
                return null;
            }

            return $payload;
        } catch (ExpiredException $e) {
            return null;
        } catch (SignatureInvalidException $e) {
            return null;
        } catch (BeforeValidException $e) {
            return null;
        } catch (UnexpectedValueException $e) {
            return null;
        } catch (DomainException $e) {
            return null;
        } catch (\InvalidArgumentException $e) {
            return null;
        }
    }

    /** SDK 会话 JWT 有效期（秒） */
    public function getSdkSessionExpire(): int
    {
        return $this->sdkSessionExpire;
    }
}
