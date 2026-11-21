<?php

declare(strict_types=1);

namespace OCA\Tagebuch\Tests\Unit;

use OCA\Tagebuch\Service\PwaScript;
use PHPUnit\Framework\TestCase;

class PwaScriptTest extends TestCase {
	public function testRetiresBothOfflineCaches(): void {
		$js = PwaScript::js();
		$this->assertStringContainsString("['tagebuch-v1', 'tagebuch-v2']", $js);
		$this->assertStringContainsString('caches.delete(key)', $js);
		$this->assertStringContainsString('self.clients.claim()', $js);
		$this->assertStringNotContainsString("addEventListener('fetch'", $js);
	}
}
