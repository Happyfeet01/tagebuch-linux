<?php

declare(strict_types=1);

namespace OCA\Tagebuch\Service;

use OCA\Tagebuch\AppInfo\Application;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IUserSession;

class PhotoService {
	private const MEDIA_EXTS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'avif', 'heic', 'heif', 'mp4', 'mov', 'm4v', 'webm', '3gp'];
	private const MAX_PHOTOS = 100;

	public function __construct(
		private IRootFolder $rootFolder,
		private IUserSession $userSession,
		private IConfig $config,
		private ?string $fixedUserId = null,
	) {
	}

	public function forUser(string $userId): self {
		return new self($this->rootFolder, $this->userSession, $this->config, $userId);
	}

	private function userId(): string {
		if ($this->fixedUserId !== null && $this->fixedUserId !== '') {
			return $this->fixedUserId;
		}
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new \RuntimeException('Kein angemeldeter Benutzer');
		}
		return $user->getUID();
	}

	public function photoFolder(): string {
		return trim($this->config->getUserValue($this->userId(), Application::APP_ID, 'photo_folder', ''), '/');
	}

	private static function extOf(string $name): string {
		$pos = strrpos($name, '.');
		return $pos === false ? '' : strtolower(substr($name, $pos + 1));
	}

	public static function mimeFor(string $name): string {
		return match (self::extOf($name)) {
			'jpg', 'jpeg' => 'image/jpeg',
			'png' => 'image/png',
			'gif' => 'image/gif',
			'webp' => 'image/webp',
			'bmp' => 'image/bmp',
			'avif' => 'image/avif',
			'heic' => 'image/heic',
			'heif' => 'image/heif',
			'mp4', 'm4v' => 'video/mp4',
			'mov' => 'video/quicktime',
			'webm' => 'video/webm',
			'3gp' => 'video/3gpp',
			default => 'application/octet-stream',
		};
	}

	private static function isMedia(string $name): bool {
		return in_array(self::extOf($name), self::MEDIA_EXTS, true);
	}

	/**
	 * Medien aus dem Foto-Ordner (Struktur <ordner>/<jahr>/<monat>/ , optional <tag>)
	 * zu Monat/Tag des uebergebenen Datums aus frueheren Jahren.
	 *
	 * @return array<int, array{path: string, name: string, year: string}>
	 */
	public function photosForDay(string $date): array {
		$folder = $this->photoFolder();
		if ($folder === '') {
			return [];
		}
		$userFolder = $this->rootFolder->getUserFolder($this->userId());
		if (!$userFolder->nodeExists($folder)) {
			return [];
		}
		$root = $userFolder->get($folder);
		if (!$root instanceof Folder) {
			return [];
		}
		$year = substr($date, 0, 4);
		$month = substr($date, 5, 2);
		$day = substr($date, 8, 2);
		$photos = [];
		foreach ($root->getDirectoryListing() as $node) {
			if (!$node instanceof Folder) {
				continue;
			}
			$yearDir = $node->getName();
			if (!ctype_digit($yearDir) || $yearDir >= $year) {
				continue;
			}
			if (!$root->nodeExists($yearDir . '/' . $month)) {
				continue;
			}
			$monthFolder = $root->get($yearDir . '/' . $month);
			if (!$monthFolder instanceof Folder) {
				continue;
			}
			$source = $monthFolder;
			$sourcePath = $folder . '/' . $yearDir . '/' . $month;
			if ($monthFolder->nodeExists($day) && $monthFolder->get($day) instanceof Folder) {
				$source = $monthFolder->get($day);
				$sourcePath .= '/' . $day;
			}
			foreach ($source->getDirectoryListing() as $file) {
				if ($file instanceof File && self::isMedia($file->getName())) {
					$photos[] = ['path' => $sourcePath . '/' . $file->getName(), 'name' => $file->getName(), 'year' => $yearDir];
					if (count($photos) >= self::MAX_PHOTOS) {
						break 2;
					}
				}
			}
		}
		usort($photos, static fn (array $a, array $b): int => [$b['year'], $a['name']] <=> [$a['year'], $b['name']]);
		return $photos;
	}

	/**
	 * @return array{content: string, mime: string}
	 */
	public function getPhoto(string $path): array {
		$folder = $this->photoFolder();
		$path = trim($path, '/');
		$prefix = $folder . '/';
		$segments = explode('/', $path);
		if ($folder === '' || !str_starts_with($path, $prefix) || in_array('..', $segments, true) || !self::isMedia($path)) {
			throw new \InvalidArgumentException('Ungueltiger Fotopfad');
		}
		$userFolder = $this->rootFolder->getUserFolder($this->userId());
		if (!$userFolder->nodeExists($path)) {
			throw new \InvalidArgumentException('Datei nicht gefunden');
		}
		$node = $userFolder->get($path);
		if (!$node instanceof File) {
			throw new \InvalidArgumentException('Keine Datei');
		}
		return ['content' => (string) $node->getContent(), 'mime' => self::mimeFor($node->getName())];
	}
}
