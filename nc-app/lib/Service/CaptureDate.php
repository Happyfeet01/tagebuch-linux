<?php

declare(strict_types=1);

namespace OCA\Tagebuch\Service;

use OCP\Files\File;
use OCP\ICache;
use OCP\ICacheFactory;
use Symfony\Component\Process\Process;

class CaptureDate {
	private ICache $cache;

	public function __construct(ICacheFactory $factory) {
		$this->cache = $factory->createDistributed('tagebuch-capture-date');
	}

	public function fromFile(File $file): ?string {
		$tags = $this->metadata($file);
		foreach (['DateTimeOriginal', 'CreationDate', 'CreateDate'] as $tag) {
			if (preg_match('/^(\d{4}):(\d{2}):(\d{2})\b/', (string)($tags[$tag] ?? ''), $m)
				&& checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
				return "$m[1]-$m[2]-$m[3]";
			}
		}
		return null;
	}

	public function coordinates(File $file): ?array {
		$tags = $this->metadata($file);
		if (!is_numeric($tags['GPSLatitude'] ?? null) || !is_numeric($tags['GPSLongitude'] ?? null)) {
			return null;
		}
		$lat = (float)$tags['GPSLatitude'];
		$lon = (float)$tags['GPSLongitude'];
		if (abs($lat) > 90 || abs($lon) > 180) return null;
		if (($tags['GPSLatitudeRef'] ?? '') === 'S') $lat = -abs($lat);
		if (($tags['GPSLongitudeRef'] ?? '') === 'W') $lon = -abs($lon);
		return ['lat' => $lat, 'lon' => $lon];
	}

	private function metadata(File $file): array {
		$binary = '/usr/bin/exiftool';
		if (!is_executable($binary) || !function_exists('proc_open')) return [];
		$key = hash('sha256', 'metadata:' . $file->getId() . ':' . $file->getEtag());
		$cached = $this->cache->get($key);
		if (is_array($cached)) return $cached;
		$stream = null;
		try {
			$stream = $file->fopen('r');
			if (!is_resource($stream)) {
				return [];
			}
			$process = new Process([$binary, '-config', '', '-j', '-n', '-GPSLatitude', '-GPSLongitude', '-GPSLatitudeRef', '-GPSLongitudeRef', '-DateTimeOriginal', '-CreationDate', '-CreateDate', '-']);
			$process->setInput($stream);
			$process->setTimeout(15);
			if ($process->run() !== 0) {
				return [];
			}
			$tags = json_decode($process->getOutput(), true)[0] ?? [];
			unset($tags['SourceFile']);
			$this->cache->set($key, $tags, 604800);
			return $tags;
		} catch (\Exception) {
			return [];
		} finally {
			if (is_resource($stream)) {
				fclose($stream);
			}
		}
	}
}
