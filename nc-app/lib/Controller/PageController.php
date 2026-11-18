<?php

declare(strict_types=1);

namespace OCA\Tagebuch\Controller;

use OCA\Tagebuch\AppInfo\Application;
use OCA\Tagebuch\Http\ContentResponse;
use OCA\Tagebuch\Service\PwaScript;
use OCA\Tagebuch\Service\TokenService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\StreamResponse;
use OCP\AppFramework\Http\TemplateResponse;
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
		], Http::STATUS_OK, ['Cache-Control' => 'no-cache']);
	}

	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[OpenAPI(OpenAPI::SCOPE_IGNORE)]
	public function sw(): StreamResponse {
		return ContentResponse::stream(PwaScript::js(), 'application/javascript', null, true);
	}
}
