<?php

declare(strict_types=1);

namespace OCA\Tagebuch\Controller;

use OCA\Tagebuch\Service\EntryService;
use OCA\Tagebuch\Service\TokenService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

/**
 * @psalm-suppress UnusedClass
 */
class EntryController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private EntryService $entryService,
		private TokenService $tokens,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/token/{t}')]
	public function tokenInfo(string $t): DataResponse {
		$info = $this->tokens->verify($t);
		if ($info === null) {
			return new DataResponse(['error' => 'Token ungueltig oder abgelaufen'], Http::STATUS_NOT_FOUND);
		}
		return new DataResponse($info);
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/entries')]
	public function list(string $from = '', string $to = ''): DataResponse {
		return new DataResponse($this->entryService->listEntries($from !== '' ? $from : null, $to !== '' ? $to : null));
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/entry')]
	public function get(string $date, string $time = ''): DataResponse {
		$entry = $this->entryService->getEntry($date, $time !== '' ? $time : null);
		if ($entry === null) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
		return new DataResponse($entry);
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/day/{date}')]
	public function day(string $date): DataResponse {
		return new DataResponse($this->entryService->entriesForDay($date));
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/entry')]
	public function save(
		string $date,
		?string $time = null,
		string $heading = '',
		string $body = '',
		?int $rating = null,
		?array $tags = null,
		?array $trackers = null,
		?array $location = null,
		?array $attachments = null,
		?int $encrypted = null,
	): DataResponse {
		try {
			$entry = $this->entryService->saveEntry([
				'date' => $date,
				'time' => $time,
				'heading' => $heading,
				'body' => $body,
				'rating' => $rating,
				'tags' => $tags ?? [],
				'trackers' => $trackers ?? [],
				'location' => $location,
				'attachments' => $attachments ?? [],
				'encrypted' => $encrypted ?? 0,
			]);
			return new DataResponse($entry);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/entry')]
	public function delete(string $date, string $time = ''): DataResponse {
		$ok = $this->entryService->deleteEntry($date, $time !== '' ? $time : null);
		return new DataResponse(['deleted' => $ok], $ok ? Http::STATUS_OK : Http::STATUS_NOT_FOUND);
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/search')]
	public function search(string $q = '', string $tag = '', string $since = '', string $until = '', ?int $min_rating = null): DataResponse {
		return new DataResponse($this->entryService->search(
			$q,
			$tag !== '' ? $tag : null,
			$since !== '' ? $since : null,
			$until !== '' ? $until : null,
			$min_rating,
		));
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/on-this-day')]
	public function onThisDay(): DataResponse {
		return new DataResponse($this->entryService->onThisDay());
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/tags')]
	public function tags(): DataResponse {
		return new DataResponse($this->entryService->allTags());
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/stats')]
	public function stats(): DataResponse {
		return new DataResponse($this->entryService->stats());
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/entry/{date}/attachment')]
	public function attachment(string $date): DataResponse {
		$file = $this->request->getUploadedFile('file');
		if ($file === null || !isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
			return new DataResponse(['error' => 'Keine Datei hochgeladen'], Http::STATUS_BAD_REQUEST);
		}
		$content = file_get_contents($file['tmp_name']);
		if ($content === false) {
			return new DataResponse(['error' => 'Datei nicht lesbar'], Http::STATUS_BAD_REQUEST);
		}
		$rel = $this->entryService->addAttachment($date, $file['name'] ?? 'datei', $content);
		return new DataResponse(['path' => $rel]);
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/templates')]
	public function templates(): DataResponse {
		return new DataResponse($this->entryService->listTemplates());
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/templates/{name}')]
	public function template(string $name): DataResponse {
		try {
			return new DataResponse(['name' => basename($name), 'body' => $this->entryService->getTemplate($name)]);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/templates')]
	public function saveTemplate(string $name, string $body = ''): DataResponse {
		return new DataResponse(['name' => $this->entryService->saveTemplate($name, $body)]);
	}
}
