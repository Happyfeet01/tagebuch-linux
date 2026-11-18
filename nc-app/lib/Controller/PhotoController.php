<?php

declare(strict_types=1);

namespace OCA\Tagebuch\Controller;

use OCA\Tagebuch\Service\PhotoService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\DownloadResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

/**
 * @psalm-suppress UnusedClass
 */
class PhotoController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private PhotoService $photos,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/on-this-day/photos')]
	public function onThisDayPhotos(): DataResponse {
		return new DataResponse($this->photos->photosForDay(date('Y-m-d')));
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/photo')]
	public function photo(string $path = ''): Response {
		try {
			$p = $this->photos->getPhoto($path);
			return new DownloadResponse($p['content'], basename($path), $p['mime']);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}
}
