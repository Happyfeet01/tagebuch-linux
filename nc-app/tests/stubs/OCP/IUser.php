<?php

declare(strict_types=1);

namespace OCP;

/**
 * Minimaler Stub fuer Standalone-Unit-Tests (ohne NC-Server).
 */
interface IUser {
	public function getUID(): string;

	public function getEMailAddress(): ?string;
}
