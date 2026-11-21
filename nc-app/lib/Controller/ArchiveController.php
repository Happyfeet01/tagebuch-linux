<?php
declare(strict_types=1);
namespace OCA\Tagebuch\Controller;

use OCA\Tagebuch\Service\ArchiveService;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\StreamResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

class ArchiveController extends OCSController {
	public function __construct(string $appName, IRequest $request, private ArchiveService $archives) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/archive')]
	public function download(): Response {
		try {
			return new StreamResponse(fopen($this->archives->export(), 'rb'), 200,
				['Content-Type' => 'application/zip', 'Cache-Control' => 'no-store', 'Content-Disposition' => 'attachment; filename="tagebuch.zip"']);
		} catch (\InvalidArgumentException $e) { return new DataResponse(['error' => $e->getMessage()], 400); }
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/archive')]
	public function restore(): DataResponse {
		$file = $this->request->getUploadedFile('file');
		if (!$file || !is_uploaded_file($file['tmp_name'])) return new DataResponse(['error' => 'Datei fehlt.'], 400);
		try { return new DataResponse(['count' => $this->archives->restore($file['tmp_name'])]); }
		catch (\InvalidArgumentException $e) { return new DataResponse(['error' => $e->getMessage()], 400); }
	}
}
