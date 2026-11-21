<?php

declare(strict_types=1);

namespace OCA\Tagebuch\Controller;

use OCA\Tagebuch\Http\ContentResponse;
use OCA\Tagebuch\Service\PhotoService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
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
		private \OCP\Calendar\IManager $calendars,
		private string $userId,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/day-context')]
	public function dayContext(string $date, string $calendar = '', string $timezone = 'UTC'): DataResponse {
		try {
			$zone = new \DateTimeZone($timezone);
			$start = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, $zone);
		} catch (\Exception $e) {
			return new DataResponse([], 400);
		}
		if (!$start || $start->format('Y-m-d') !== $date) return new DataResponse([], 400);
		$choices = [];
		$events = [];
		foreach ($this->calendars->getCalendarsForPrincipal('principals/users/' . $this->userId) as $source) {
			$choices[] = ['id' => $source->getUri(), 'name' => $source->getDisplayName()];
			if ($calendar !== '' && $calendar !== $source->getUri()) continue;
			$options = ['types' => ['VEVENT'], 'timerange' => ['start' => $start, 'end' => $start->modify('+1 day')]];
			foreach ($source->search('', [], $options) as $event) {
				foreach ($event['objects'] as $object) {
					if (isset($object['SUMMARY'][0])) $events[] = (string)$object['SUMMARY'][0];
				}
			}
		}
		return new DataResponse(['calendars' => $choices, 'events' => $events, 'photos' => $this->photos->photosForDay($date, true)]);
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/on-this-day/photos')]
	public function onThisDayPhotos(): DataResponse {
		return new DataResponse($this->photos->photosForDay(date('Y-m-d')));
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/photo')]
	public function photo(string $path = '', string $version = ''): Response {
		try {
			$p = $this->photos->getPhoto($path);
			$response = ContentResponse::stream($p['content'], $p['mime'], basename($path));
			if ($version !== '' && $version === $p['etag']) {
				$response->addHeader('Cache-Control', 'private, max-age=86400');
				$response->addHeader('Vary', 'Cookie, Authorization');
			}
			return $response;
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}
}
