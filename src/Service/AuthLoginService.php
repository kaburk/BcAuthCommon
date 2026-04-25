<?php
declare(strict_types=1);

namespace BcAuthCommon\Service;

use BcAuthCommon\Service\AuthLoginLogService;
use BcAuthGuard\Service\BcAuthGuardService;
use BaserCore\Event\BcEventDispatcher;
use BaserCore\Service\SiteConfigsService;
use BaserCore\Service\TwoFactorAuthenticationsService;
use BaserCore\Service\UsersService;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\Routing\Router;
use RuntimeException;

class AuthLoginService implements AuthLoginServiceInterface
{
    public function __construct(
        private ?AuthRedirectServiceInterface $redirectService = null,
        private ?UsersService $usersService = null,
        private ?SiteConfigsService $siteConfigsService = null,
        private ?TwoFactorAuthenticationsService $twoFactorService = null,
    ) {
        $this->redirectService = $this->redirectService ?? new AuthRedirectService();
        $this->usersService = $this->usersService ?? new UsersService();
        $this->siteConfigsService = $this->siteConfigsService ?? new SiteConfigsService();
        $this->twoFactorService = $this->twoFactorService ?? new TwoFactorAuthenticationsService();
    }

    public function login(array $params, ServerRequest $request, Response $response): AuthLoginResult
    {
        $userId = (int)($params['user_id'] ?? 0);
        $prefix = (string)($params['prefix'] ?? 'Admin');
        $saved = (bool)($params['saved'] ?? false);
        $authSource = (string)($params['auth_source'] ?? 'unknown');
        $clientIp = (string)($params['client_ip'] ?? '');
        if (!filter_var($clientIp, FILTER_VALIDATE_IP)) {
            $clientIp = '';
        }

        if (!$userId) {
            throw new RuntimeException('user_id が必要です。');
        }

        $user = $this->usersService->get($userId);
        if (!$user) {
            throw new RuntimeException('ユーザーを取得できません。');
        }

        $this->assertAllowedLogin($prefix, (string) $user->email, $request, $authSource, $clientIp);

        $redirectUrl = $this->redirectService->resolve($params['redirect'] ?? null, $prefix);

        if ($this->requiresTwoFactor($prefix)) {
            try {
                $this->twoFactorService->send($user->id, $user->email);
            } catch (\Throwable $e) {
            }

            $request->getSession()->write('TwoFactorAuth.' . $prefix, [
                'user_id' => $user->id,
                'email' => $user->email,
                'saved' => $saved,
                'date' => date('Y-m-d H:i:s'),
                'redirect' => $redirectUrl,
                'auth_source' => $authSource,
            ]);

            $loginCodeUrl = Router::url([
                'plugin' => 'BaserCore',
                'prefix' => 'Admin',
                'controller' => 'Users',
                'action' => 'login_code',
            ], true);
            if ($redirectUrl) {
                $loginCodeUrl .= '?redirect=' . urlencode($redirectUrl);
            }

            return new AuthLoginResult('two_factor_required', $loginCodeUrl, $request, $response);
        }

        $requestForLogin = $request->withParam('prefix', $prefix);
        $requestForLog = $requestForLogin;
        if ($clientIp !== '') {
            $requestForLog = $requestForLog->withEnv('REMOTE_ADDR', $clientIp);
        }
        $result = $this->usersService->login($requestForLogin, $response, $user->id);
        if (!$result) {
            throw new RuntimeException('ログイン状態の確立に失敗しました。');
        }

        $request = $result['request'];
        $response = $result['response'];

        BcEventDispatcher::dispatch('afterLogin', $this->usersService, [
            'user' => $user,
            'loginRedirect' => $redirectUrl,
            'authSource' => $authSource,
            'clientIp' => ($clientIp !== '' ? $clientIp : null),
        ], [
            'layer' => 'Controller',
            'plugin' => 'BaserCore',
            'class' => 'Users',
        ]);

        $this->usersService->removeLoginKey($user->id);
        if ($request->is('https') && $saved) {
            $response = $this->usersService->setCookieAutoLoginKey($response, $user->id);
        }

        $request->getSession()->write('BcAuthCommon.authSource.' . $prefix, $authSource);

        AuthLoginLogService::writeWithContext(
            event: 'login_success',
            userId: $user->id,
            prefix: $prefix,
            authSource: $authSource,
            username: (string) $user->email,
            request: $requestForLog,
            context: [
                'request_path' => (string) $requestForLog->getRequestTarget(),
                'referer' => (string) $requestForLog->getHeaderLine('Referer'),
            ],
        );

        return new AuthLoginResult('completed', $redirectUrl, $request, $response);
    }

    private function requiresTwoFactor(string $prefix): bool
    {
        if (!in_array($prefix, ['Admin', 'Api/Admin'], true)) {
            return false;
        }
        return (bool)$this->siteConfigsService->getValue('use_two_factor_authentication');
    }

    private function assertAllowedLogin(string $prefix, string $username, ServerRequest $request, string $authSource, string $clientIp = ''): void
    {
        if ($prefix !== 'Admin' || !class_exists(BcAuthGuardService::class)) {
            return;
        }

        $guardService = new BcAuthGuardService();
        $ipAddress = $clientIp !== '' ? $clientIp : (string) AuthLoginLogService::getRequestIp($request);
        if (!$guardService->isBlockedIp($ipAddress)) {
            return;
        }

        $guardService->recordBlockedIpDenied(
            'Admin',
            mb_strtolower(trim($username)),
            $ipAddress,
            $request,
            [
                'auth_source' => $authSource,
                'request_path' => (string) $request->getRequestTarget(),
                'referer' => (string) $request->getHeaderLine('Referer'),
            ]
        );

        throw new RuntimeException(__d('baser_core', '申し訳ありませんが、ログインを制限しています。'), 403);
    }
}
