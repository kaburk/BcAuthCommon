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
 *   AuthLoginLogService::write('login_success', userId: 1, prefix: 'Admin', authSource: 'social:google', request: $request);
 *   AuthLoginLogService::write('login_failure', prefix: 'Admin', authSource: 'password', request: $request, detail: 'bad credentials');
 *
 * event 種別（推奨値）:
 *   - login_success   ログイン成功
 *   - login_failure   ログイン失敗（パスワード不一致 / ユーザー未登録 等）
 *   - logout          ログアウト
 *   - link_cancel     ソーシャル連携キャンセル
 */
class AuthLoginLogService
{
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
        ?string $detail = null,
    ): void {
        try {
            $table = TableRegistry::getTableLocator()->get('BcAuthCommon.AuthLoginLogs');
            $entity = $table->newEntity([
                'user_id'    => $userId,
                'prefix'     => $prefix,
                'auth_source' => $authSource,
                'event'      => $event,
                'ip_address' => $request?->clientIp(),
                'user_agent' => $request ? substr((string)$request->getHeaderLine('User-Agent'), 0, 255) : null,
                'detail'     => $detail,
            ]);
            $table->save($entity);
        } catch (\Throwable) {
            // ログ記録失敗は認証フローを止めない
        }
    }
}
