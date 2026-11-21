<?php

declare(strict_types=1);

namespace OCA\Tagebuch\Service;

class PwaScript {
	public static function js(): string {
		return <<<'JS'
self.addEventListener('install', (event) => {
	event.waitUntil(self.skipWaiting());
});
self.addEventListener('activate', (event) => {
	event.waitUntil(
		Promise.all(['tagebuch-v1', 'tagebuch-v2'].map((key) => caches.delete(key)))
			.then(() => self.clients.claim())
	);
});
JS;
	}
}
