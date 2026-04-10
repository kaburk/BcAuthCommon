<?php
declare(strict_types=1);

namespace BcAuthCommon\Service;

use Cake\Core\Configure;
use Cake\Routing\Router;

class AuthRedirectService implements AuthRedirectServiceInterface
{
    public function resolve(?string $redirect, string $prefix): string
    {
        $default = $this->getDefaultRedirect($prefix);
        if (!$redirect) {
            return $default;
        }

        $redirect = trim($redirect);
        if ($redirect === '' || str_starts_with($redirect, '//')) {
            return $default;
        }

        $scheme = parse_url($redirect, PHP_URL_SCHEME);
        if ($scheme && !in_array(strtolower($scheme), ['http', 'https'], true)) {
            return $default;
        }

        if ($scheme) {
            $request = Router::getRequest();
            $host = parse_url($redirect, PHP_URL_HOST);
            if (!$request || !$host || $host !== $request->getUri()->getHost()) {
                return $default;
            }
        }

        return $redirect;
    }

    private function getDefaultRedirect(string $prefix): string
    {
        return Router::url(Configure::read('BcPrefixAuth.' . $prefix . '.loginRedirect') ?? '/', true);
    }
}
