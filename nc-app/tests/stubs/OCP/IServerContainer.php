<?php

declare(strict_types=1);

namespace OCP;

/**
 * Minimaler Stub fuer Standalone-Unit-Tests (ohne NC-Server).
 */
interface IServerContainer {
	public function get(string $name);

	public function query(string $name);
}
