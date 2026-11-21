<?php

declare(strict_types=1);

namespace OCA\Tagebuch\Service;

class PwaScript {
	public const CACHE = 'tagebuch-v2';

	/**
	 * Service-Worker: network-first fuer /apps/tagebuch/ (damit App-Updates sofort
	 * greifen), Cache nur als Offline-Fallback. Beim Activate werden alle alten
	 * Caches bis auf self::CACHE geloescht.
	 */
	public static function js(): string {
		$cache = self::CACHE;
		return <<<JS
const CACHE = '{$cache}';
self.addEventListener('install', (event) => {
	self.skipWaiting();
});
self.addEventListener('activate', (event) => {
	event.waitUntil(
		caches.keys()
			.then((keys) => Promise.all(keys.filter((key) => key.startsWith('tagebuch-v') && key !== CACHE).map((key) => caches.delete(key))))
			.then(() => self.clients.claim())
	);
});
self.addEventListener('fetch', (event) => {
	const url = new URL(event.request.url);
	if (url.pathname.startsWith('/apps/tagebuch/')) {
		event.respondWith(
			fetch(event.request)
				.then((response) => {
					const copy = response.clone();
					caches.open(CACHE).then((cache) => cache.put(event.request, copy));
					return response;
				})
				.catch(() => caches.match(event.request))
		);
	}
});
JS;
	}
}
