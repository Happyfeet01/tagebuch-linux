<?php

declare(strict_types=1);

namespace OCA\Tagebuch\Http;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\StreamResponse;

/**
 * Baut eine StreamResponse aus einem String-Inhalt.
 * Hintergrund: OCP\AppFramework\Http\TextResponse existiert ab NC 35 nicht mehr,
 * und DownloadResponse hat dort keine Content-Signatur mehr – StreamResponse
 * mit php://temp-Ressource ist die stabile Variante (NC 31–35).
 */
class ContentResponse {
	public static function stream(string $content, string $contentType, ?string $downloadName = null, bool $noCache = false): StreamResponse {
		$stream = fopen('php://temp', 'rb+');
		if ($stream === false) {
			throw new \RuntimeException('php://temp nicht verfuegbar');
		}
		fwrite($stream, $content);
		rewind($stream);
		$headers = ['Content-Type' => $contentType];
		if ($noCache) {
			$headers['Cache-Control'] = 'no-cache';
		}
		if ($downloadName !== null && $downloadName !== '') {
			$safe = str_replace(['"', '\\', '/'], '', $downloadName);
			$headers['Content-Disposition'] = 'attachment; filename="' . $safe . '"';
		}
		return new StreamResponse($stream, Http::STATUS_OK, $headers);
	}
}
