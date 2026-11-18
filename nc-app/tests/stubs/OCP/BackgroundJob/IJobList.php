<?php

declare(strict_types=1);

namespace OCP\BackgroundJob;

/**
 * Minimaler Stub fuer Standalone-Unit-Tests (ohne NC-Server).
 * Strenger als das echte OCP-Interface: Die konkrete OC\BackgroundJob\JobList
 * verlangt in NC 35 bei has()/add() zwingend 2 Argumente (kein Default),
 * daher hier ebenfalls ohne Default – so faellt ein falscher Aufruf im Test auf.
 */
interface IJobList {
	public function add(string $class, $argument): void;

	public function has(string $class, $argument): bool;

	public function remove(string $class, $argument): void;
}
