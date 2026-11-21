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
		$binary = '/usr/bin/exiftool';
		if (!is_executable($binary) || !function_exists('proc_open')) {
			return null;
		}
		$key = hash('sha256', $file->getId() . ':' . $file->getEtag());
		$cached = $this->cache->get($key);
		if (is_string($cached)) {
			return $cached === '' ? null : $cached;
		}
		$stream = null;
		try {
			$stream = $file->fopen('r');
			if (!is_resource($stream)) {
				return null;
			}
			$process = new Process([$binary, '-config', '', '-j', '-DateTimeOriginal', '-CreationDate', '-CreateDate', '-']);
			$process->setInput($stream);
			$process->setTimeout(15);
			if ($process->run() !== 0) {
				return null;
			}
			$tags = json_decode($process->getOutput(), true)[0] ?? [];
			$date = null;
			foreach (['DateTimeOriginal', 'CreationDate', 'CreateDate'] as $tag) {
				if (preg_match('/^(\d{4}):(\d{2}):(\d{2})\b/', (string)($tags[$tag] ?? ''), $m)
					&& checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
					$date = "$m[1]-$m[2]-$m[3]";
					break;
				}
			}
			$this->cache->set($key, $date ?? '', 604800);
			return $date;
		} catch (\Exception) {
			return null;
		} finally {
			if (is_resource($stream)) {
				fclose($stream);
			}
		}
	}
}
