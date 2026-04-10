<?php
declare(strict_types=1);

namespace BcAuthCommon\Service;

use Cake\Http\Response;
use Cake\Http\ServerRequest;

interface AuthLoginServiceInterface
{
    public function login(array $params, ServerRequest $request, Response $response): AuthLoginResult;
}
