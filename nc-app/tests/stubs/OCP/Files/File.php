<?php

declare(strict_types=1);

namespace OCP\Files;

/**
 * Minimaler Stub fuer Standalone-Unit-Tests (ohne NC-Server).
 */
interface File extends Node {
	public function fopen(string $mode);

	public function getEtag(): string;

	public function getContent();
}
