<?php
declare(strict_types=1);

namespace BcAuthCommon\Service;

interface AuthRedirectServiceInterface
{
    public function resolve(?string $redirect, string $prefix): string;
}
