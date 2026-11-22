<?php
declare(strict_types=1);

namespace OCA\Tagebuch\Controller;

use OCA\Tagebuch\Service\CorsOriginPolicy;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

/**
 * Separate unauthenticated preflight handler: browsers do not send credentials
 * with OPTIONS, so user-id-dependent diary controllers cannot handle it.
 */
class CorsController extends OCSController {
	public function __construct(string $appName, IRequest $request, private CorsOriginPolicy $origins) {
		parent::__construct($appName, $request);
	}

	#[PublicPage]
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function preflight(string $path): Response {
		$response = new Response();
		$response->addHeader('Vary', 'Origin');
		$methods = match ($path) {
			'entries', 'state/vault' => 'GET',
			'entry' => 'POST',
			default => null,
		};
		$origin = $this->request->getHeader('Origin');
		if ($methods === null || !$this->origins->allows($origin) || $origin === '') {
			$response->setStatus(403);
			return $response;
		}
		$response->addHeader('Access-Control-Allow-Origin', $origin);
		$response->addHeader('Access-Control-Allow-Methods', $methods);
		$response->addHeader('Access-Control-Allow-Headers', 'Authorization, Content-Type, Accept, OCS-APIRequest');
		$response->addHeader('Access-Control-Max-Age', '600');
		$response->addHeader('Access-Control-Allow-Credentials', 'false');
		return $response;
	}
}
