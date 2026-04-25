<?php
declare(strict_types=1);

namespace BcAuthCommon\Event;

use BaserCore\Event\BcControllerEventListener;
use BcAuthCommon\Service\AuthRecentActivityService;
use BcAuthCommon\Service\AuthLoginLogService;
use Cake\Event\EventInterface;
use Cake\Routing\Router;

class BcAuthCommonControllerEventListener extends BcControllerEventListener
{
    public $events = [
        'BaserCore.Users.afterLogin',
        'BaserCore.Users.beforeRedirect',
    ];

    public function baserCoreUsersAfterLogin(EventInterface $event): void
    {
        $request = $this->resolveRequest($event);
        if (!$request || (string) $request->getParam('prefix') !== 'Admin') {
            return;
        }

        $user = $event->getData('user');
        if (!$user) {
            return;
        }

        $username = mb_strtolower(trim((string) ($user->email ?? '')));
        $authSource = $this->resolveAuthSource($request, $event, 'Admin');
        $eventClientIp = (string) ($event->getData('clientIp') ?? '');
        $clientIp = filter_var($eventClientIp, FILTER_VALIDATE_IP)
            ? $eventClientIp
            : (string) AuthLoginLogService::getRequestIp($request);

        $request->getSession()->write('BcAuthCommon.lastLogin.Admin', [
            'user_id' => (int) ($user->id ?? 0),
            'username' => $username,
            'auth_source' => $authSource,
            'client_ip' => $clientIp,
        ]);

        if ($authSource === 'password') {
            AuthLoginLogService::writeWithContext(
                event: 'login_success',
                userId: (int) ($user->id ?? 0) ?: null,
                prefix: 'Admin',
                authSource: $authSource,
                username: $username,
                request: $request->withEnv('REMOTE_ADDR', $clientIp),
                context: [
                    'request_path' => (string) $request->getRequestTarget(),
                    'referer' => (string) $request->getHeaderLine('Referer'),
                ],
            );
        }

        (new AuthRecentActivityService())->recordLogin($username, $event->getSubject());
    }

    public function baserCoreUsersBeforeRedirect(EventInterface $event): void
    {
        $controller = $event->getSubject();
        $request = $controller->getRequest();
        if ((string) $request->getParam('prefix') !== 'Admin') {
            return;
        }
        if ((string) $request->getParam('action') !== 'logout') {
            return;
        }

        $user = $controller->Authentication->getIdentity();
        $lastLogin = (array) $request->getSession()->read('BcAuthCommon.lastLogin.Admin');

        if ($user) {
            $userId = (int) ($user->id ?? 0);
            $username = mb_strtolower(trim((string) ($user->email ?? '')));
        } else {
            $userId = (int) ($lastLogin['user_id'] ?? 0);
            $username = mb_strtolower(trim((string) ($lastLogin['username'] ?? '')));
        }
        if ($username === '') {
            return;
        }

        $logoutRequest = $request;
        $lastLoginClientIp = (string) ($lastLogin['client_ip'] ?? '');
        if (filter_var($lastLoginClientIp, FILTER_VALIDATE_IP)) {
            $logoutRequest = $request->withEnv('REMOTE_ADDR', $lastLoginClientIp);
        }

        AuthLoginLogService::writeWithContext(
            event: 'logout',
            userId: $userId ?: null,
            prefix: 'Admin',
            authSource: $this->resolveAuthSource($request, null, 'Admin', $lastLogin),
            username: $username,
            request: $logoutRequest,
            context: [
                'request_path' => (string) $request->getRequestTarget(),
                'referer' => (string) $request->getHeaderLine('Referer'),
            ],
        );

        (new AuthRecentActivityService())->recordLogout($username, $controller);
        $request->getSession()->delete('BcAuthCommon.lastLogin.Admin');
        $request->getSession()->delete('BcAuthCommon.authSource.Admin');
    }

    private function resolveAuthSource($request, ?EventInterface $event, string $prefix, array $lastLogin = []): string
    {
        $eventAuthSource = $event ? (string) ($event->getData('authSource') ?? '') : '';
        if ($eventAuthSource !== '') {
            return $eventAuthSource;
        }
        $sessionAuthSource = (string) ($request->getSession()->read('BcAuthCommon.authSource.' . $prefix) ?? '');
        if ($sessionAuthSource !== '') {
            return $sessionAuthSource;
        }
        $lastLoginAuthSource = (string) ($lastLogin['auth_source'] ?? '');
        if ($lastLoginAuthSource !== '') {
            return $lastLoginAuthSource;
        }
        return 'password';
    }

    private function resolveRequest(EventInterface $event)
    {
        $subject = $event->getSubject();
        if ($subject && method_exists($subject, 'getRequest')) {
            return $subject->getRequest();
        }
        return Router::getRequest();
    }
}
