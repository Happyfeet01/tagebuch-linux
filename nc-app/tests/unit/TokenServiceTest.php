<?php

declare(strict_types=1);

namespace OCA\Tagebuch\Tests\Unit;

use OCA\Tagebuch\Service\TokenService;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

class TokenServiceTest extends TestCase {
	private function makeService(array &$store): TokenService {
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static fn (string $app, string $key, string $default = ''): string => $store[$key] ?? $default
		);
		$config->method('setAppValue')->willReturnCallback(
			static function (string $app, string $key, string $value) use (&$store): void {
				$store[$key] = $value;
			}
		);
		$config->method('deleteAppValue')->willReturnCallback(
			static function (string $app, string $key) use (&$store): void {
				unset($store[$key]);
			}
		);
		$config->method('getSystemValue')->willReturnCallback(
			static fn (string $key, $default = '') => $key === 'mail_domain' ? 'cloud.example' : $default
		);
		return new TokenService($config);
	}

	public function testCreateAndVerify(): void {
		$store = [];
		$service = $this->makeService($store);
		$token = $service->createToken('lars', '2026-11-18');
		$this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $token);
		$info = $service->verify($token);
		$this->assertSame('lars', $info['user']);
		$this->assertSame('2026-11-18', $info['date']);
	}

	public function testVerifyRejectsUnknownAndMalformed(): void {
		$store = [];
		$service = $this->makeService($store);
		$this->assertNull($service->verify('0123456789abcdef0123456789abcdef'));
		$this->assertNull($service->verify('kein-token'));
		$this->assertNull($service->verify(''));
	}

	public function testVerifyRejectsExpired(): void {
		$store = [];
		$service = $this->makeService($store);
		$token = $service->createToken('lars', '2026-11-18');
		$store['token_' . $token] = 'lars|2026-11-18|' . (time() - 10);
		$this->assertNull($service->verify($token));
		$this->assertArrayNotHasKey('token_' . $token, $store);
	}

	public function testReplyAddressUsesMailDomain(): void {
		$store = [];
		$service = $this->makeService($store);
		$token = $service->createToken('lars', '2026-11-18');
		$this->assertSame('tagebuch+' . $token . '@cloud.example', $service->replyAddress($token));
	}
}
