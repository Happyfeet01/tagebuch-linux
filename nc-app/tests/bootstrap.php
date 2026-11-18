<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$stubs = [
	\OCP\IConfig::class => __DIR__ . '/stubs/OCP/IConfig.php',
	\OCP\AppFramework\App::class => __DIR__ . '/stubs/OCP/AppFramework/App.php',
	\OCP\AppFramework\Bootstrap\IBootstrap::class => __DIR__ . '/stubs/OCP/AppFramework/Bootstrap/IBootstrap.php',
	\OCP\AppFramework\Bootstrap\IRegistrationContext::class => __DIR__ . '/stubs/OCP/AppFramework/Bootstrap/IRegistrationContext.php',
	\OCP\AppFramework\Bootstrap\IBootContext::class => __DIR__ . '/stubs/OCP/AppFramework/Bootstrap/IBootContext.php',
	\OCP\AppFramework\Db\Entity::class => __DIR__ . '/stubs/OCP/AppFramework/Db/Entity.php',
];
foreach ($stubs as $class => $file) {
	if (!class_exists($class) && !interface_exists($class)) {
		require_once $file;
	}
}
