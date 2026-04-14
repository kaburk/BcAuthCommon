<?php
declare(strict_types=1);

namespace BcAuthCommon\Service;

/**
 * AuthEntryService
 *
 * 認証入口（ログインボタン）の定義を管理し、順序制御を提供するシングルトンサービス。
 *
 * 各認証プラグインは Plugin::bootstrap() から register() を呼んで自身を登録する。
 * login.php はこのサービスを介して `getOrderedEntries($prefix)` を呼び、
 * 定義された順序でボタン element をレンダリングする。
 *
 * 登録例（BcAuthPasskeyPlugin::bootstrap）:
 *   AuthEntryService::getInstance()->register([
 *       'id'       => 'passkey',
 *       'label'    => 'パスキーでログイン',
 *       'element'  => 'BcAuthPasskey.passkey_login_button',
 *       'prefixes' => ['Admin', 'Front'],
 *       'order'    => 10,
 *   ]);
 */
class AuthEntryService
{
    private static ?self $instance = null;

    /** @var array<string, array<string, mixed>> id をキーとしたエントリのマップ */
    private array $entries = [];

    private function __construct() {}

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * 認証エントリを登録する。
     *
     * @param array{
     *   id: string,
     *   label: string,
     *   element: string,
     *   prefixes: list<string>,
     *   order?: int,
     * } $entry
     */
    public function register(array $entry): void
    {
        $entry['order'] = $entry['order'] ?? 100;
        $this->entries[$entry['id']] = $entry;
    }

    /**
     * 指定 prefix でフィルタして order 順に並べたエントリ一覧を返す。
     *
     * @param string $prefix 'Admin' | 'Front' など
     * @return list<array<string, mixed>>
     */
    public function getOrderedEntries(string $prefix): array
    {
        $filtered = array_filter(
            $this->entries,
            fn(array $e) => in_array($prefix, $e['prefixes'], true)
        );

        usort($filtered, fn(array $a, array $b) => $a['order'] <=> $b['order']);

        return array_values($filtered);
    }

    /**
     * テスト用: レジストリをリセットする
     */
    public function reset(): void
    {
        $this->entries = [];
    }
}
