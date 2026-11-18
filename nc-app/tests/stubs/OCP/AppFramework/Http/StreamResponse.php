<?php

declare(strict_types=1);

namespace OCP\AppFramework\Http;

use OCP\AppFramework\Http;

/**
 * Minimaler Stub fuer Standalone-Unit-Tests (ohne NC-Server).
 */
class StreamResponse extends Response {
	private $filePath;

	public function __construct($filePath, int $status = Http::STATUS_OK, array $headers = []) {
		parent::__construct($status, $headers);
		$this->filePath = $filePath;
	}
}
