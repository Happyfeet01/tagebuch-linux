<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

if (!interface_exists(\OCP\IConfig::class)) {
	require_once __DIR__ . '/stubs/OCP/IConfig.php';
}
if (!class_exists(\OCP\AppFramework\Db\Entity::class)) {
	require_once __DIR__ . '/stubs/OCP/AppFramework/Db/Entity.php';
}
