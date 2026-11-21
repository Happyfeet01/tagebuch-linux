<?php

declare(strict_types=1);

namespace OCA\Tagebuch\Tests\Unit;

use OCA\Tagebuch\Service\PhotoService;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PhotoServiceTest extends TestCase {
	private IConfig&MockObject $config;
	private IRootFolder&MockObject $rootFolder;
	private Folder&MockObject $userFolder;

	private function service(string $photoFolder = 'Fotos'): PhotoService {
		$this->config = $this->createMock(IConfig::class);
		$this->config->method('getUserValue')->willReturn($photoFolder);
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('lars');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->userFolder = $this->createMock(Folder::class);
		$this->rootFolder->method('getUserFolder')->with('lars')->willReturn($this->userFolder);
		return new PhotoService($this->rootFolder, $session, $this->config, 'lars');
	}

	private function folder(string $name, array $listing = [], array $exists = [], array $children = []): Folder&MockObject {
		$f = $this->createMock(Folder::class);
		$f->method('getName')->willReturn($name);
		$f->method('getDirectoryListing')->willReturn($listing);
		$f->method('nodeExists')->willReturnCallback(static fn (string $p): bool => in_array($p, $exists, true));
		$f->method('get')->willReturnCallback(static fn (string $p): Node => $children[$p] ?? throw new \RuntimeException('unexpected get: ' . $p));
		return $f;
	}

	private function file(string $name, string $content = 'DATA'): File&MockObject {
		$f = $this->createMock(File::class);
		$f->method('getName')->willReturn($name);
		$f->method('getContent')->willReturn($content);
		return $f;
	}

	public function testPhotosForDayPrefersDaySubfolderAndFiltersMedia(): void {
		$svc = $this->service('Fotos');
		$jpg = $this->file('a.jpg');
		$mp4 = $this->file('b.mp4', 'VID');
		$txt = $this->file('c.txt', 'NOPE');
		$dayFolder = $this->folder('18', [$jpg, $mp4, $txt]);
		$monthFolder = $this->folder('11', [$this->file('monat.jpg')], ['18'], ['18' => $dayFolder]);
		$year2024 = $this->folder('2024');
		$year2025 = $this->folder('2025');
		$notAFolder = $this->file('liesmich.txt');
		$root = $this->folder('Fotos', [$year2024, $year2025, $notAFolder], ['2024/11', '2025/11'], ['2024/11' => $monthFolder, '2025/11' => $this->folder('11')]);
		$this->userFolder->method('nodeExists')->willReturnCallback(static fn (string $p): bool => $p === 'Fotos');
		$this->userFolder->method('get')->willReturnCallback(static fn (string $p): Node => $p === 'Fotos' ? $root : throw new \RuntimeException('unexpected: ' . $p));

		$photos = $svc->photosForDay('2025-11-18');
		$this->assertCount(2, $photos);
		$this->assertSame('Fotos/2024/11/18/a.jpg', $photos[0]['path']);
		$this->assertSame('a.jpg', $photos[0]['name']);
		$this->assertSame('2024', $photos[0]['year']);
		$this->assertSame('Fotos/2024/11/18/b.mp4', $photos[1]['path']);
	}

	public function testPhotosForDayFallsBackToMonthFolder(): void {
		$svc = $this->service('Fotos');
		$monthFolder = $this->folder('11', [$this->file('m.jpg')], [], []);
		$year2023 = $this->folder('2023');
		$root = $this->folder('Fotos', [$year2023], ['2023/11'], ['2023/11' => $monthFolder]);
		$this->userFolder->method('nodeExists')->willReturnCallback(static fn (string $p): bool => $p === 'Fotos');
		$this->userFolder->method('get')->willReturnCallback(static fn (string $p): Node => $p === 'Fotos' ? $root : throw new \RuntimeException('unexpected: ' . $p));

		$photos = $svc->photosForDay('2025-11-18');
		$this->assertCount(1, $photos);
		$this->assertSame('Fotos/2023/11/m.jpg', $photos[0]['path']);
	}

	public function testSelectedDayFiltersDatesInMonthFolder(): void {
		$svc = $this->service();
		$month = $this->folder('11', [$this->file('IMG_20261121_0700.jpg'), $this->file('2026-11-20.jpg')]);
		$root = $this->folder('Fotos', [$this->folder('2026')], ['2026/11'], ['2026/11' => $month]);
		$this->userFolder->method('nodeExists')->willReturn(true);
		$this->userFolder->method('get')->willReturn($root);
		self::assertSame(['IMG_20261121_0700.jpg'], array_column($svc->photosForDay('2026-11-21', true), 'name'));
	}

	public function testPhotosForDayEmptyWithoutFolderSetting(): void {
		$svc = $this->service('');
		$this->assertSame([], $svc->photosForDay('2025-11-18'));
	}

	public function testPhotosForDayMissingRootFolder(): void {
		$svc = $this->service('Fotos');
		$this->userFolder->method('nodeExists')->willReturn(false);
		$this->assertSame([], $svc->photosForDay('2025-11-18'));
	}

	public function testGetPhotoReturnsContentAndMime(): void {
		$svc = $this->service('Fotos');
		$photo = $this->file('a.jpg', 'BILD');
		$this->userFolder->method('nodeExists')->willReturnCallback(static fn (string $p): bool => $p === 'Fotos/2024/11/18/a.jpg');
		$this->userFolder->method('get')->willReturnCallback(static fn (string $p): Node => $p === 'Fotos/2024/11/18/a.jpg' ? $photo : throw new \RuntimeException('unexpected: ' . $p));
		$result = $svc->getPhoto('Fotos/2024/11/18/a.jpg');
		$this->assertSame('BILD', $result['content']);
		$this->assertSame('image/jpeg', $result['mime']);
	}

	public function testGetPhotoRejectsTraversalOutsideFolderAndNonMedia(): void {
		$svc = $this->service('Fotos');
		$this->expectException(\InvalidArgumentException::class);
		$svc->getPhoto('../config/config.php');
	}

	public function testGetPhotoRejectsNonMediaExtension(): void {
		$svc = $this->service('Fotos');
		$this->expectException(\InvalidArgumentException::class);
		$svc->getPhoto('Fotos/2024/11/18/notes.txt');
	}

	public function testGetPhotoRejectsPathOutsidePhotoFolder(): void {
		$svc = $this->service('Fotos');
		$this->expectException(\InvalidArgumentException::class);
		$svc->getPhoto('Dokumente/2024/11/18/a.jpg');
	}

	public function testMimeFor(): void {
		$this->assertSame('image/png', PhotoService::mimeFor('x.PNG'));
		$this->assertSame('video/quicktime', PhotoService::mimeFor('film.mov'));
		$this->assertSame('application/octet-stream', PhotoService::mimeFor('datei.bin'));
	}
}
