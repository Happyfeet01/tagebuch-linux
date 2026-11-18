<?php

declare(strict_types=1);

namespace OCA\Tagebuch\Tests\Unit;

use OCA\Tagebuch\Http\ContentResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\StreamResponse;
use PHPUnit\Framework\TestCase;

class ContentResponseTest extends TestCase {
	private static function readProp(object $obj, string $class, string $prop) {
		$rp = new \ReflectionProperty($class, $prop);
		$rp->setAccessible(true);
		return $rp->getValue($obj);
	}

	private static function headersOf(Response $res): array {
		return self::readProp($res, Response::class, 'headers');
	}

	public function testStreamServesContentWithTypeAndNoCache(): void {
		$res = ContentResponse::stream('console.log(1);', 'application/javascript', null, true);
		$this->assertInstanceOf(StreamResponse::class, $res);
		$headers = self::headersOf($res);
		$this->assertSame('application/javascript', $headers['Content-Type']);
		$this->assertSame('no-cache', $headers['Cache-Control']);
		$this->assertArrayNotHasKey('Content-Disposition', $headers);
		$stream = self::readProp($res, StreamResponse::class, 'filePath');
		$this->assertIsResource($stream);
		rewind($stream);
		$this->assertSame('console.log(1);', stream_get_contents($stream));
		fclose($stream);
	}

	public function testDownloadAddsSanitizedDisposition(): void {
		$res = ContentResponse::stream('BILD', 'image/jpeg', 'fo"to\\1.jpg');
		$headers = self::headersOf($res);
		$this->assertSame('image/jpeg', $headers['Content-Type']);
		$this->assertSame('attachment; filename="foto1.jpg"', $headers['Content-Disposition']);
		$this->assertArrayNotHasKey('Cache-Control', $headers);
		$stream = self::readProp($res, StreamResponse::class, 'filePath');
		rewind($stream);
		$this->assertSame('BILD', stream_get_contents($stream));
		fclose($stream);
	}

	public function testBinaryContentSurvives(): void {
		$bin = random_bytes(4096);
		$res = ContentResponse::stream($bin, 'application/octet-stream', 'a.enc');
		$stream = self::readProp($res, StreamResponse::class, 'filePath');
		rewind($stream);
		$this->assertSame($bin, stream_get_contents($stream));
		fclose($stream);
	}
}
