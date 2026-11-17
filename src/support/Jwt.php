<?php

declare(strict_types=1);

namespace youvision\store\admin\support;

use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT as FirebaseJwt;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use think\facade\Cache;
use think\facade\Config;
use think\Request;

/**
 * JWT 签发、校验、刷新与注销。
 *
 * 基于 firebase/php-jwt，修复了 think-jwt 的刷新判断、
 * 请求头解析、jti 随机数和黑名单前缀等问题。
 */
final class Jwt
{
    public const ENCRYPT_ERROR = 50001;

    public const SECRET_MISS = 50002;

    public const INVALID_TOKEN = 40001;

    public const TOKEN_EXPIRE_LONG = 20002;

    public const TOKEN_LOGOUT = 20003;

    /**
     * 签发 Token
     *
     * @param array<string, mixed> $data 业务载荷
     */
    public static function issue(array $data = []): string
    {
        $config = self::config();
        $secret = self::secret($config);
        $alg = (string) ($config['alg'] ?? 'HS256');
        $now = time();
        $jti = bin2hex(random_bytes(16));

        $payload = [
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + max(1, (int) ($config['ttl'] ?? 604800)),
            'jti' => $jti,
            'data' => $data,
        ];

        $issuer = trim((string) ($config['issuer'] ?? ''));

        if ($issuer !== '') {
            $payload['iss'] = $issuer;
        }

        $audience = trim((string) ($config['audience'] ?? ''));

        if ($audience !== '') {
            $payload['aud'] = $audience;
        }

        try {
            return FirebaseJwt::encode($payload, $secret, $alg, $jti);
        } catch (\Throwable $e) {
            throw new \RuntimeException('JWT 生成失败: ' . $e->getMessage(), self::ENCRYPT_ERROR);
        }
    }

    /**
     * 校验 Token，失败时返回错误码而不抛出异常
     *
     * @return array{code: int, error_code: int, msg: string, data: array<string, mixed>, expires_in?: int, need_refresh?: bool}
     */
    public static function check(string $token): array
    {
        try {
            $claims = self::parse($token, true);
        } catch (\Throwable $e) {
            return [
                'code' => 0,
                'error_code' => (int) $e->getCode() ?: self::INVALID_TOKEN,
                'msg' => $e->getMessage(),
                'data' => [],
            ];
        }

        $expiresIn = (int) ($claims['exp'] ?? 0) - time();
        $threshold = max(0, (int) (self::config()['refresh_threshold'] ?? 36000));

        return [
            'code' => 1,
            'error_code' => 0,
            'msg' => '',
            'data' => (array) ($claims['data'] ?? []),
            'expires_in' => max(0, $expiresIn),
            'need_refresh' => $expiresIn <= $threshold,
        ];
    }

    /**
     * 解析 Token
     *
     * @return array<string, mixed> 完整载荷或业务数据
     *
     * @throws \RuntimeException
     */
    public static function parse(string $token, bool $complete = false): array
    {
        $token = trim($token);

        if ($token === '') {
            throw new \RuntimeException('未提供令牌', self::INVALID_TOKEN);
        }

        $config = self::config();
        $secret = self::secret($config);
        $alg = (string) ($config['alg'] ?? 'HS256');
        $leeway = max(0, (int) ($config['leeway'] ?? 0));
        $oldLeeway = FirebaseJwt::$leeway;
        FirebaseJwt::$leeway = $leeway;

        try {
            $decoded = FirebaseJwt::decode($token, new Key($secret, $alg));
        } catch (ExpiredException $e) {
            throw new \RuntimeException('令牌已过期: ' . $e->getMessage(), self::TOKEN_EXPIRE_LONG);
        } catch (SignatureInvalidException | BeforeValidException | \UnexpectedValueException | \DomainException $e) {
            throw new \RuntimeException('令牌无效: ' . $e->getMessage(), self::INVALID_TOKEN);
        } catch (\Throwable $e) {
            throw new \RuntimeException('令牌解析失败: ' . $e->getMessage(), self::INVALID_TOKEN);
        } finally {
            FirebaseJwt::$leeway = $oldLeeway;
        }

        $claims = self::toArray($decoded);
        $issuer = trim((string) ($config['issuer'] ?? ''));

        if ($issuer !== '' && (string) ($claims['iss'] ?? '') !== $issuer) {
            throw new \RuntimeException('令牌签发者无效', self::INVALID_TOKEN);
        }

        $audience = trim((string) ($config['audience'] ?? ''));

        if ($audience !== '' && (string) ($claims['aud'] ?? '') !== $audience) {
            throw new \RuntimeException('令牌受众无效', self::INVALID_TOKEN);
        }

        $jti = (string) ($claims['jti'] ?? '');

        if ($jti === '') {
            throw new \RuntimeException('令牌缺少唯一标识', self::INVALID_TOKEN);
        }

        if (self::isBlacklisted($jti, $config)) {
            throw new \RuntimeException('令牌已被注销', self::TOKEN_LOGOUT);
        }

        return $complete ? $claims : (array) ($claims['data'] ?? []);
    }

    /**
     * 刷新 Token，旧 Token 进入黑名单
     *
     * @return array{code: int, error_code: int, msg: string, data: string}
     */
    public static function refresh(string $token): array
    {
        try {
            $claims = self::parse($token, true);
            self::blacklist($claims, self::config());

            return [
                'code' => 1,
                'error_code' => 0,
                'msg' => '刷新令牌成功',
                'data' => self::issue((array) ($claims['data'] ?? [])),
            ];
        } catch (\Throwable $e) {
            return self::failure($e);
        }
    }

    /**
     * 注销 Token
     *
     * @return array{code: int, error_code: int, msg: string, data: bool}
     */
    public static function logout(string $token): array
    {
        try {
            $claims = self::parse($token, true);
            self::blacklist($claims, self::config());

            return [
                'code' => 1,
                'error_code' => 0,
                'msg' => '注销令牌成功',
                'data' => true,
            ];
        } catch (\Throwable $e) {
            return self::failure($e);
        }
    }

    /**
     * 从请求头提取 Bearer Token
     */
    public static function tokenFromRequest(Request $request): string
    {
        $header = (string) ($request->header('authorization')
            ?: $request->header('HTTP_AUTHORIZATION')
            ?: $request->header('REDIRECT_HTTP_AUTHORIZATION')
            ?: '');
        $header = trim($header);

        if ($header === '') {
            return '';
        }

        if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches) === 1) {
            return trim($matches[1]);
        }

        return $header;
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function secret(array $config = []): string
    {
        $config = $config === [] ? self::config() : $config;
        $secret = trim((string) ($config['secret'] ?? ''));

        if ($secret === '') {
            throw new \RuntimeException('未设置 JWT 密钥', self::SECRET_MISS);
        }

        return $secret;
    }

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        try {
            $config = Config::get('store_admin.jwt', []);
        } catch (\Throwable) {
            return [];
        }

        return is_array($config) ? $config : [];
    }

    /**
     * @return array{code: int, error_code: int, msg: string, data: string|bool}
     */
    private static function failure(\Throwable $e): array
    {
        return [
            'code' => 0,
            'error_code' => (int) $e->getCode() ?: self::INVALID_TOKEN,
            'msg' => $e->getMessage(),
            'data' => false,
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function isBlacklisted(string $jti, array $config): bool
    {
        return Cache::get(self::blacklistKey($jti, $config)) !== null;
    }

    /**
     * @param array<string, mixed> $claims
     * @param array<string, mixed> $config
     */
    private static function blacklist(array $claims, array $config): void
    {
        $jti = (string) ($claims['jti'] ?? '');
        $exp = (int) ($claims['exp'] ?? 0);
        $ttl = max(1, $exp - time() + max(0, (int) ($config['leeway'] ?? 0)));

        Cache::set(self::blacklistKey($jti, $config), 1, $ttl);
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function blacklistKey(string $jti, array $config): string
    {
        return (string) ($config['blacklist_prefix'] ?? 'store_admin:jwt:blacklist:') . $jti;
    }

    /**
     * @param mixed $value
     * @return array<string, mixed>
     */
    private static function toArray($value): array
    {
        if (is_object($value)) {
            $value = get_object_vars($value);
        }

        if (!is_array($value)) {
            return [];
        }

        foreach ($value as $key => $item) {
            if (is_object($item) || is_array($item)) {
                $value[$key] = self::toArray($item);
            }
        }

        return $value;
    }
}