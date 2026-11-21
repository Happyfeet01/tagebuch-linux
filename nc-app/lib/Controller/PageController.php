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

	private function journalPage(string $app, string $template, array $params): TemplateResponse {
		$response = new TemplateResponse($app, $template, $params);
		$policy = new \OCP\AppFramework\Http\ContentSecurityPolicy();
		$policy->addAllowedImageDomain('https://tile.openstreetmap.org');
		$response->setContentSecurityPolicy($policy);
		return $response;
	}

	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[OpenAPI(OpenAPI::SCOPE_IGNORE)]
	#[FrontpageRoute(verb: 'GET', url: '/')]
	public function index(): TemplateResponse {
		Util::addHeader('link', [
			'rel' => 'manifest',
			'href' => $this->urlGenerator->linkToRouteAbsolute('tagebuch.page.manifest'),
			'crossorigin' => 'use-credentials',
		]);
		return $this->journalPage(Application::APP_ID, 'index', ['token' => '']);
	}

	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[OpenAPI(OpenAPI::SCOPE_IGNORE)]
	#[FrontpageRoute(verb: 'GET', url: '/new')]
	public function newEntry(string $t = ''): TemplateResponse {
		Util::addHeader('link', [
			'rel' => 'manifest',
			'href' => $this->urlGenerator->linkToRouteAbsolute('tagebuch.page.manifest'),
			'crossorigin' => 'use-credentials',
		]);
		$info = $t !== '' ? $this->tokens->verify($t) : null;
		return $this->journalPage(Application::APP_ID, 'index', [
			'token' => $info !== null ? $t : '',
		]);
	}

	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[OpenAPI(OpenAPI::SCOPE_IGNORE)]
	public function manifest(): JSONResponse {
		$icons = [];
		foreach ([192, 512] as $size) {
			$icons[] = [
				'src' => $this->urlGenerator->imagePath(Application::APP_ID, "journal-$size.png"),
				'sizes' => $size . 'x' . $size,
				'type' => 'image/png',
			];
		}
		$startUrl = $this->urlGenerator->linkToRouteAbsolute('tagebuch.page.index');
		return new JSONResponse([
			'name' => 'Tagebuch',
			'short_name' => 'Tagebuch',
			'description' => 'Privates Tagebuch in deiner Nextcloud',
			'start_url' => $startUrl,
			'display' => 'standalone',
			'background_color' => '#1f2d3d',
			'theme_color' => '#3584e4',
			'icons' => $icons,
		], Http::STATUS_OK, ['Cache-Control' => 'no-cache']);
	}

	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[OpenAPI(OpenAPI::SCOPE_IGNORE)]
	public function sw(): StreamResponse {
		return ContentResponse::stream(PwaScript::js(), 'application/javascript', null, true);
	}
}
