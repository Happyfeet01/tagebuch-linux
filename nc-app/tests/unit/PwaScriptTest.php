<?php

declare(strict_types=1);

namespace OCA\Tagebuch\Tests\Unit;

use OCA\Tagebuch\Service\PwaScript;
use PHPUnit\Framework\TestCase;

class PwaScriptTest extends TestCase {
	public function testNetworkFirstWithCacheFallbackOnly(): void {
		$js = PwaScript::js();
		$posFetch = strpos($js, 'fetch(event.request)');
		$posCacheMatch = strpos($js, 'caches.match(event.request)');
		$this->assertNotFalse($posFetch);
		$this->assertNotFalse($posCacheMatch);
		$this->assertLessThan($posCacheMatch, $posFetch, 'Netzwerk muss vor Cache-Fallback kommen (network-first)');
		$this->assertStringContainsString('.catch(() => caches.match(event.request))', $js);
	}

	public function testOldCachesPurgedOnActivate(): void {
		$js = PwaScript::js();
		$this->assertStringContainsString('caches.delete(key)', $js);
		$this->assertStringContainsString('self.skipWaiting()', $js);
		$this->assertStringContainsString('self.clients.claim()', $js);
	}

	public function testScopeAndCacheName(): void {
		$js = PwaScript::js();
		$this->assertStringContainsString("/apps/tagebuch/", $js);
		$this->assertStringContainsString("'" . PwaScript::CACHE . "'", $js);
		$this->assertSame('tagebuch-v2', PwaScript::CACHE);
	}
}
