<?php

declare(strict_types=1);

$loader = require __DIR__ . '/../vendor/autoload.php';

$stubs = [
	\OCP\IConfig::class => __DIR__ . '/stubs/OCP/IConfig.php',
	\OCP\IUser::class => __DIR__ . '/stubs/OCP/IUser.php',
	\OCP\IUserSession::class => __DIR__ . '/stubs/OCP/IUserSession.php',
	\OCP\IServerContainer::class => __DIR__ . '/stubs/OCP/IServerContainer.php',
	\OCP\BackgroundJob\IJobList::class => __DIR__ . '/stubs/OCP/BackgroundJob/IJobList.php',
	\OCP\Files\Node::class => __DIR__ . '/stubs/OCP/Files/Node.php',
	\OCP\Files\File::class => __DIR__ . '/stubs/OCP/Files/File.php',
	\OCP\Files\Folder::class => __DIR__ . '/stubs/OCP/Files/Folder.php',
	\OCP\Files\IRootFolder::class => __DIR__ . '/stubs/OCP/Files/IRootFolder.php',
	\OCP\AppFramework\App::class => __DIR__ . '/stubs/OCP/AppFramework/App.php',
	\OCP\AppFramework\Http::class => __DIR__ . '/stubs/OCP/AppFramework/Http.php',
	\OCP\AppFramework\Http\Response::class => __DIR__ . '/stubs/OCP/AppFramework/Http/Response.php',
	\OCP\AppFramework\Http\StreamResponse::class => __DIR__ . '/stubs/OCP/AppFramework/Http/StreamResponse.php',
	\OCP\AppFramework\Bootstrap\IBootstrap::class => __DIR__ . '/stubs/OCP/AppFramework/Bootstrap/IBootstrap.php',
	\OCP\AppFramework\Bootstrap\IRegistrationContext::class => __DIR__ . '/stubs/OCP/AppFramework/Bootstrap/IRegistrationContext.php',
	\OCP\AppFramework\Bootstrap\IBootContext::class => __DIR__ . '/stubs/OCP/AppFramework/Bootstrap/IBootContext.php',
	\OCP\AppFramework\Db\Entity::class => __DIR__ . '/stubs/OCP/AppFramework/Db/Entity.php',
	\OCP\AppFramework\Db\QBMapper::class => __DIR__ . '/stubs/OCP/AppFramework/Db/QBMapper.php',
];
foreach ($stubs as $class => $file) {
	if (!class_exists($class) && !interface_exists($class)) {
		require_once $file;
	}
}

// Load framework contracts not covered by the off-server test doubles.
$loader->addPsr4('OCP\\', __DIR__ . '/../vendor/nextcloud/ocp/OCP');
