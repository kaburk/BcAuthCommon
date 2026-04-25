<?php
declare(strict_types=1);

namespace BcAuthCommon;

use BaserCore\BcPlugin;
use BcAuthCommon\Event\BcAuthCommonControllerEventListener;
use Cake\Core\PluginApplicationInterface;
use Cake\Event\EventManager;

class BcAuthCommonPlugin extends BcPlugin
{
	public function bootstrap(PluginApplicationInterface $app): void
	{
		parent::bootstrap($app);
		EventManager::instance()->on(new BcAuthCommonControllerEventListener());
	}
}
