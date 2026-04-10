<?php
declare(strict_types=1);

namespace BcAuthCommon\Service;

use Cake\Http\Response;
use Cake\Http\ServerRequest;

class AuthLoginResult
{
    public function __construct(
        public readonly string $status,
        public readonly string $redirect_url,
        public readonly ServerRequest $request,
        public readonly Response $response,
    ) {
    }
}
