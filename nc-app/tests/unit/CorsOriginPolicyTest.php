<?php
declare(strict_types=1);

namespace OCA\Tagebuch\Tests\Unit;

use OCA\Tagebuch\Service\CorsOriginPolicy;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

class CorsOriginPolicyTest extends TestCase {
	private function policy(string $origins): CorsOriginPolicy {
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')
			->with('tagebuch', 'cors_allowed_origins', '')
			->willReturn($origins);
		return new CorsOriginPolicy($config);
	}

	public function testDenyCrossOriginByDefault(): void {
		self::assertFalse($this->policy('')->allows('https://evil.example'));
	}

	public function testExactAllowlistAndNoWildcard(): void {
		$policy = $this->policy('https://app.example, https://other.example');
		self::assertTrue($policy->allows('https://app.example'));
		self::assertTrue($policy->allows('https://other.example'));
		self::assertFalse($policy->allows('https://app.example.evil.example'));
		self::assertFalse($policy->allows('http://app.example'));
		self::assertFalse($policy->allows('https://evil.example'));
	}

	public function testNonBrowserClientWithoutOriginUnchanged(): void {
		self::assertTrue($this->policy('')->allows(''));
	}
}
