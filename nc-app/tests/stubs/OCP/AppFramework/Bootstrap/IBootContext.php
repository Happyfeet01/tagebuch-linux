<?php

declare(strict_types=1);

namespace OCP\AppFramework\Bootstrap;

use OCP\IServerContainer;

/**
 * Minimaler Stub fuer Standalone-Unit-Tests (ohne NC-Server).
 */
interface IBootContext {
	public function getServerContainer(): IServerContainer;
}
