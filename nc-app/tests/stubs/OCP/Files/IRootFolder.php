<?php

declare(strict_types=1);

namespace OCP\Files;

/**
 * Minimaler Stub fuer Standalone-Unit-Tests (ohne NC-Server).
 */
interface IRootFolder {
	public function getUserFolder(string $userId): Folder;
}
