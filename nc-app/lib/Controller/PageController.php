<?php

declare(strict_types=1);

namespace OCA\Tagebuch\Controller;

use OCA\Tagebuch\AppInfo\Application;
use OCA\Tagebuch\Service\TokenService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\TextResponse;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\Util;

/**
 * @psalm-suppress UnusedClass
 */
class PageController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private IURLGenerator $urlGenerator,
		private TokenService $tokens,
	) {
		parent::__construct($appName, $request);
	}

	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[OpenAPI(OpenAPI::SCOPE_IGNORE)]
	#[FrontpageRoute(verb: 'GET', url: '/')]
	public function index(): TemplateResponse {
		Util::addHeader('link', [
			'rel' => 'manifest',
			'href' => $this->urlGenerator->linkToRouteAbsolute('tagebuch.page.manifest'),
		]);
		return new TemplateResponse(Application::APP_ID, 'index', ['token' => '']);
	}

	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[OpenAPI(OpenAPI::SCOPE_IGNORE)]
	#[FrontpageRoute(verb: 'GET', url: '/new')]
	public function newEntry(string $t = ''): TemplateResponse {
		Util::addHeader('link', [
			'rel' => 'manifest',
			'href' => $this->urlGenerator->linkToRouteAbsolute('tagebuch.page.manifest'),
		]);
		$info = $t !== '' ? $this->tokens->verify($t) : null;
		return new TemplateResponse(Application::APP_ID, 'index', [
			'token' => $info !== null ? $t : '',
		]);
	}

	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[OpenAPI(OpenAPI::SCOPE_IGNORE)]
	public function manifest(): JSONResponse {
		$icon = $this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath(Application::APP_ID, 'app.svg'));
		$startUrl = $this->urlGenerator->getAbsoluteURL('/apps/tagebuch/');
		return new JSONResponse([
			'name' => 'Tagebuch',
			'short_name' => 'Tagebuch',
			'description' => 'Privates Tagebuch in deiner Nextcloud',
			'start_url' => $startUrl,
			'display' => 'standalone',
			'background_color' => '#1f2d3d',
			'theme_color' => '#3584e4',
			'icons' => [
				['src' => $icon, 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any'],
			],
		]);
	}

	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[OpenAPI(OpenAPI::SCOPE_IGNORE)]
	public function sw(): TextResponse {
		$js = <<<'JS'
const CACHE = 'tagebuch-v1';
self.addEventListener('install', (event) => {
	self.skipWaiting();
});
self.addEventListener('activate', (event) => {
	event.waitUntil(self.clients.claim());
});
self.addEventListener('fetch', (event) => {
	const url = new URL(event.request.url);
	if (url.pathname.startsWith('/apps/tagebuch/')) {
		event.respondWith(
			caches.match(event.request).then((cached) => cached || fetch(event.request).then((response) => {
				const copy = response.clone();
				caches.open(CACHE).then((cache) => cache.put(event.request, copy));
				return response;
			}))
		);
	}
});
JS;
		return new TextResponse($js, Http::STATUS_OK, ['Content-Type' => 'application/javascript']);
	}
}
