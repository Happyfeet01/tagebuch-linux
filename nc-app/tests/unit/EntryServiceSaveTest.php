<?php

declare(strict_types=1);

namespace OCA\Tagebuch\Tests\Unit;

use OCA\Tagebuch\Db\Entry;
use OCA\Tagebuch\Db\EntryMapper;
use OCA\Tagebuch\Db\TemplateMapper;
use OCA\Tagebuch\Service\EntryService;
use OCP\Files\IRootFolder;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class EntryServiceSaveTest extends TestCase {
	private EntryMapper&MockObject $entryMapper;
	private EntryService $service;

	private function setUp(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('lars');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$this->entryMapper = $this->createMock(EntryMapper::class);
		$templateMapper = $this->createMock(TemplateMapper::class);
		$this->service = new EntryService(
			$this->createMock(IRootFolder::class),
			$session,
			$this->entryMapper,
			$templateMapper,
			'lars'
		);
	}

	public function testSaveWithoutIdInsertsAndSetsCreated(): void {
		$this->entryMapper->method('findById')->willReturn(null);
		$this->entryMapper->method('saveEntry')->willReturnCallback(static fn (Entry $e): Entry => $e);
		$result = $this->service->saveEntry(['date' => '2026-11-18', 'body' => 'Hallo']);
		$this->assertNull($result['id']);
		$this->assertSame('2026-11-18', $result['date']);
		$this->assertSame('Hallo', $result['body']);
		$this->assertNotEmpty($result['created']);
		$this->assertNotEmpty($result['modified']);
	}

	public function testSaveWithIdUpdatesAndPreservesCreated(): void {
		$existing = Entry::fromArray('lars', ['date' => '2026-01-01', 'body' => 'alt', 'created' => '2026-01-01T08:00:00+00:00']);
		$existing->setId(42);
		$this->entryMapper->method('findById')->with('lars', 42)->willReturn($existing);
		$saved = [];
		$this->entryMapper->method('saveEntry')->willReturnCallback(static function (Entry $e) use (&$saved): Entry {
			$saved[] = $e;
			return $e;
		});
		$result = $this->service->saveEntry(['id' => 42, 'date' => '2026-11-18', 'body' => 'neu']);
		$this->assertSame(42, $result['id']);
		$this->assertSame('2026-01-01T08:00:00+00:00', $result['created'], 'created darf beim Update nicht ueberschrieben werden');
		$this->assertSame('neu', $saved[0]->getBody());
	}

	public function testSaveWithUnknownIdFallsBackToInsert(): void {
		$this->entryMapper->method('findById')->willReturn(null);
		$saved = [];
		$this->entryMapper->method('saveEntry')->willReturnCallback(static function (Entry $e) use (&$saved): Entry {
			$saved[] = $e;
			return $e;
		});
		$result = $this->service->saveEntry(['id' => 999, 'date' => '2026-11-18', 'body' => 'x']);
		$this->assertNull($saved[0]->getId(), 'unbekannte id muss als Insert (id=null) gespeichert werden');
		$this->assertNull($result['id']);
	}

	public function testSaveRejectsMissingDateAndBadRating(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->service->saveEntry(['body' => 'ohne Datum']);
	}

	public function testSaveRejectsRatingOutOfRange(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->service->saveEntry(['date' => '2026-11-18', 'rating' => 9]);
	}
}
