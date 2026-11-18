<?php

declare(strict_types=1);

namespace OCP\Files;

/**
 * Minimaler Stub fuer Standalone-Unit-Tests (ohne NC-Server).
 */
interface Folder extends Node {
	public function nodeExists(string $path): bool;

	public function get(string $path): Node;

	/**
	 * @return Node[]
	 */
	public function getDirectoryListing(): array;

	public function newFolder(string $path): Folder;

	public function newFile(string $path, $content = null): File;
}
