<?php

declare(strict_types=1);

namespace OCA\Tagebuch\Tests\Unit;

use OCA\Tagebuch\Service\CaptureDate;
use OCP\Files\File;
use OCP\ICache;
use OCP\ICacheFactory;
use PHPUnit\Framework\TestCase;

class CaptureDateTest extends TestCase {
	public function testReadsOriginalDateFromUndatedImage(): void {
		if (!is_executable('/usr/bin/exiftool')) {
			self::markTestSkipped('ExifTool fehlt.');
		}
		// Minimal TIFF with one ExifIFD containing DateTimeOriginal.
		$image = 'II' . pack('vV', 42, 8) . pack('vvvVVV', 1, 0x8769, 4, 1, 26, 0)
			. pack('vvvVVV', 1, 0x9003, 2, 20, 44, 0) . "2023:11:18 17:45:00\0";
		$stream = fopen('php://temp', 'w+b');
		fwrite($stream, $image);
		rewind($stream);
		$file = $this->createMock(File::class);
		$file->method('fopen')->willReturn($stream);
		$cache = $this->createMock(ICache::class);
		$cache->method('get')->willReturn(null);
		$cache->expects(self::once())->method('set')->with(self::anything(), '2023-11-18', 604800);
		$factory = $this->createMock(ICacheFactory::class);
		$factory->method('createDistributed')->willReturn($cache);
		self::assertSame('2023-11-18', (new CaptureDate($factory))->fromFile($file));
	}
}
