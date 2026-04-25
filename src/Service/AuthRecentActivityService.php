<?php
declare(strict_types=1);

namespace BcAuthCommon\Service;

use BaserCore\Service\DblogsService;

class AuthRecentActivityService
{
    public function recordLogin(string $username, $subject = null): void
    {
        $this->record(
            __d('baser_core', '{0} がログインしました。', $username ?: __d('baser_core', '不明ユーザー')),
            $subject
        );
    }

    public function recordLogout(string $username, $subject = null): void
    {
        $this->record(
            __d('baser_core', '{0} がログアウトしました。', $username ?: __d('baser_core', '不明ユーザー')),
            $subject
        );
    }

    public function record(string $message, $subject = null): void
    {
        if (is_object($subject) && property_exists($subject, 'BcMessage') && $subject->BcMessage) {
            $subject->BcMessage->setInfo($message, true, false);
            return;
        }

        try {
            (new DblogsService())->create(['message' => $message]);
        } catch (\Throwable) {
        }
    }
}
