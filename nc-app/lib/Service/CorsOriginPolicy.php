<?php
declare(strict_types=1);

namespace OCA\Tagebuch\Service;

use OCP\IConfig;

/**
 * Opt-in browser access to the diary OCS API.
 * Empty Origin means a same-origin/native client; authentication is still required.
 */
class CorsOriginPolicy {
	public function __construct(private IConfig $config) {}

	public function allows(string $origin): bool {
		if ($origin === '') {
			return true;
		}
		$configured = $this->config->getAppValue('tagebuch', 'cors_allowed_origins', '');
		$origins = array_filter(array_map('trim', explode(',', $configured)));
		return in_array($origin, $origins, true);
	}
}
