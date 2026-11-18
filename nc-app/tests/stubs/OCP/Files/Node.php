<?php

declare(strict_types=1);

namespace OCP\Files;

/**
 * Minimaler Stub fuer Standalone-Unit-Tests (ohne NC-Server).
 * In der echten Nextcloud wird das OCP-Interface aus dem Server geladen.
 */
interface Node {
	public function getName(): string;

	public function getPath(): string;
}
