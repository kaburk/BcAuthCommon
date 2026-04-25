<?php
declare(strict_types=1);

namespace BcAuthCommon\Service;

use Cake\Http\ServerRequest;
use Cake\ORM\TableRegistry;

/**
 * AuthLoginLogService
 *
 * 認証イベントを auth_login_logs テーブルに記録する共通サービス。
 *
 * 使い方:
 *   AuthLoginLogService::writeWithContext('login_success', userId: 1, prefix: 'Admin', authSource: 'social:google', username: 'user@example.com', request: $request);
 *   AuthLoginLogService::writeWithContext('login_failure', prefix: 'Admin', authSource: 'password', request: $request, context: ['payload' => ['error' => 'bad credentials']]);
 *   AuthLoginLogService::write('login_success', userId: 1, prefix: 'Admin', authSource: 'social:google', request: $request); // 低レベルAPI
 *
 * event 種別（推奨値）:
 *   - login_success   ログイン成功
 *   - login_failure   ログイン失敗（パスワード不一致 / ユーザー未登録 等）
 *   - logout          ログアウト
 *   - link_cancel     ソーシャル連携キャンセル
 */
class AuthLoginLogService
{
    public static function getRequestIp(?ServerRequest $request): ?string
    {
        if (!$request) {
            return null;
        }

        $ipSource = strtolower(trim((string) env('BC_AUTH_IP_SOURCE', 'remote_addr')));
        $trustProxy = filter_var((string) env('TRUST_PROXY', false), FILTER_VALIDATE_BOOLEAN);

        if ($ipSource === 'forwarded_for' && $trustProxy) {
            $forwardedFor = self::parseForwardedFor((string) $request->getEnv('HTTP_X_FORWARDED_FOR'));
            if (!empty($forwardedFor)) {
                $trustedProxies = self::parseTrustedProxies((string) env('TRUSTED_PROXIES', ''));
                $clientIp = self::extractClientIpFromForwardedFor($forwardedFor, $trustedProxies);
                if ($clientIp !== null) {
                    return $clientIp;
                }
            }

            $realIp = trim((string) $request->getEnv('HTTP_X_REAL_IP'));
            if ($realIp !== '' && filter_var($realIp, FILTER_VALIDATE_IP)) {
                return $realIp;
            }
        }

        $remoteAddress = trim((string) $request->getEnv('REMOTE_ADDR'));
        if ($remoteAddress !== '' && filter_var($remoteAddress, FILTER_VALIDATE_IP)) {
            return $remoteAddress;
        }

        return trim((string) $request->clientIp());
    }

    private static function parseForwardedFor(string $header): array
    {
        if ($header === '') {
            return [];
        }

        $ips = array_map('trim', explode(',', $header));
        $ips = array_filter($ips, static fn($ip) => $ip !== '' && filter_var($ip, FILTER_VALIDATE_IP));
        return array_values($ips);
    }

    private static function parseTrustedProxies(string $trustedProxies): array
    {
        if ($trustedProxies === '') {
            return [];
        }

        $ips = array_map('trim', explode(',', $trustedProxies));
        $ips = array_filter($ips, static fn($ip) => $ip !== '' && filter_var($ip, FILTER_VALIDATE_IP));
        return array_values($ips);
    }

    private static function extractClientIpFromForwardedFor(array $forwardedFor, array $trustedProxies): ?string
    {
        if (empty($forwardedFor)) {
            return null;
        }

        if (empty($trustedProxies)) {
            // TRUST_PROXY=true かつ trusted list 未指定時は、一般的な運用に合わせて先頭IPを採用する
            return $forwardedFor[0];
        }

        for ($i = count($forwardedFor) - 1; $i >= 0; $i--) {
            $ip = $forwardedFor[$i];
            if (!in_array($ip, $trustedProxies, true)) {
                return $ip;
            }
        }

        return $forwardedFor[0];
    }

    /**
     * context を共通フォーマットに整形して監査ログを1件記録する。
     */
    public static function writeWithContext(
        string $event,
        ?int $userId = null,
        string $prefix = 'Admin',
        string $authSource = 'unknown',
        ?string $username = null,
        ?ServerRequest $request = null,
        array $context = [],
    ): void {
        $payload = $context['payload'] ?? null;
        if (is_array($payload)) {
            unset($payload['password'], $payload['password_1'], $payload['password_2']);
        }

        $detail = [];
        if ($payload !== null && $payload !== []) {
            $detail['payload'] = $payload;
        }

        $encodedDetail = json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        $referer = (string) ($context['referer'] ?? ($request ? $request->getHeaderLine('Referer') : ''));
        $requestPath = (string) ($context['request_path'] ?? ($request ? $request->getRequestTarget() : ''));

        self::write(
            event: $event,
            userId: $userId,
            prefix: $prefix,
            authSource: $authSource,
            request: $request,
            username: $username,
            referer: $referer !== '' ? $referer : null,
            requestPath: $requestPath !== '' ? $requestPath : null,
            detail: ($encodedDetail === false) ? null : $encodedDetail
        );
    }

    /**
     * 監査ログを1件記録する。
     *
     * DB が未初期化（テーブル未作成）の場合は例外を握り潰して処理を続行する。
     */
    public static function write(
        string $event,
        ?int $userId = null,
        string $prefix = 'Admin',
        string $authSource = 'unknown',
        ?ServerRequest $request = null,
        ?string $username = null,
        ?string $referer = null,
        ?string $requestPath = null,
        ?string $detail = null,
    ): void {
        try {
            $table = TableRegistry::getTableLocator()->get('BcAuthCommon.BcAuthLoginLogs');
            $entity = $table->newEntity([
                'user_id'    => $userId,
                'username'   => $username,
                'prefix'     => $prefix,
                'auth_source' => $authSource,
                'event'      => $event,
                'ip_address' => self::getRequestIp($request),
                'user_agent' => $request ? mb_substr((string)$request->getHeaderLine('User-Agent'), 0, 255) : null,
                'referer'    => $referer,
                'request_path' => $requestPath,
                'detail'     => $detail,
            ]);
            $table->save($entity);
        } catch (\Throwable) {
            // ログ記録失敗は認証フローを止めない
        }
    }
}
