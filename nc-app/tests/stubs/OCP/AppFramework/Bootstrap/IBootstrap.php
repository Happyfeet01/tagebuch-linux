<?php

declare(strict_types=1);

namespace OCP\AppFramework\Bootstrap;

/**
 * Minimaler Stub für Standalone-Unit-Tests (ohne NC-Server).
 */
interface IBootstrap {
	public function register(IRegistrationContext $context): void;

	public function boot(IBootContext $context): void;
}
