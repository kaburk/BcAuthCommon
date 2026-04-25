<?php
declare(strict_types=1);

namespace BcAuthCommon\Model\Entity;

use Cake\ORM\Entity;

/**
 * BcAuthLoginLog Entity
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $username
 * @property string $prefix
 * @property string $auth_source
 * @property string $event
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $referer
 * @property string|null $request_path
 * @property string|null $detail
 * @property \Cake\I18n\DateTime $created
 */
class BcAuthLoginLog extends Entity
{
    protected array $_accessible = [
        'user_id'    => true,
        'username'   => true,
        'prefix'     => true,
        'auth_source' => true,
        'event'      => true,
        'ip_address' => true,
        'user_agent' => true,
        'referer'    => true,
        'request_path' => true,
        'detail'     => true,
    ];
}
