<?php

declare(strict_types=1);

namespace OCP\AppFramework\Http;

use OCP\AppFramework\Http;

/**
 * Minimaler Stub fuer Standalone-Unit-Tests (ohne NC-Server).
 */
class Response {
	private int $status;
	private array $headers;

	public function __construct(int $status = Http::STATUS_OK, array $headers = []) {
		$this->status = $status;
		$this->headers = $headers;
	}

	public function addHeader($name, $value) {
		$this->headers[$name] = $value;
		return $this;
	}

	public function setHeaders(array $headers): static {
		$this->headers = $headers;
		return $this;
	}

	public function getHeaders(): array {
		return $this->headers;
	}

	public function getStatus(): int {
		return $this->status;
	}

	public function render(): string {
		return '';
	}
}
