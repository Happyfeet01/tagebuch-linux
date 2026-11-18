<?php

declare(strict_types=1);

namespace OCA\Tagebuch\Service;

use OCA\Tagebuch\AppInfo\Application;
use OCP\IConfig;

class TokenService {
	private const TOKEN_TTL = 7 * 86400;

	public function __construct(
		private IConfig $config,
	) {
	}

	private function secret(): string {
		$secret = $this->config->getAppValue(Application::APP_ID, 'token_secret', '');
		if ($secret === '') {
			$secret = bin2hex(random_bytes(32));
			$this->config->setAppValue(Application::APP_ID, 'token_secret', $secret);
		}
		return $secret;
	}

	public function createToken(string $userId, string $date): string {
		$token = bin2hex(random_bytes(16));
		$value = $userId . '|' . $date . '|' . (time() + self::TOKEN_TTL);
		$this->config->setAppValue(Application::APP_ID, 'token_' . $token, $value);
		return $token;
	}

	public function verify(string $token): ?array {
		if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
			return null;
		}
		$value = $this->config->getAppValue(Application::APP_ID, 'token_' . $token, '');
		if ($value === '') {
			return null;
		}
		$parts = explode('|', $value);
		if (count($parts) !== 3) {
			return null;
		}
		[$userId, $date, $expires] = $parts;
		if ((int) $expires < time()) {
			$this->config->deleteAppValue(Application::APP_ID, 'token_' . $token);
			return null;
		}
		return ['user' => $userId, 'date' => $date];
	}

	public function replyAddress(string $token): string {
		$domain = (string) $this->config->getSystemValue('mail_domain', 'localhost');
		return 'tagebuch+' . $token . '@' . $domain;
	}

	public function sign(string $userId, string $date): string {
		return hash_hmac('sha256', $userId . '|' . $date, $this->secret());
	}
}
