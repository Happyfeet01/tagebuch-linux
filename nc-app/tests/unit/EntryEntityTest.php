<?php

declare(strict_types=1);

namespace OCA\Tagebuch\Tests\Unit;

use OCA\Tagebuch\Db\Entry;
use PHPUnit\Framework\TestCase;

class EntryEntityTest extends TestCase {
	public function testFromArrayAndJsonSerialize(): void {
		$entry = Entry::fromArray('lars', [
			'date' => '2026-11-18',
			'time' => '20:15',
			'heading' => 'Abendrückblick',
			'body' => 'Heute war ein guter Tag.',
			'rating' => 4,
			'tags' => ['Familie', 'Zürich'],
			'trackers' => ['Schlaf' => 7],
			'location' => ['name' => 'Zuhause', 'lat' => 47.3769, 'lon' => 8.5417],
			'attachments' => ['2026-11-18-assets/foto.jpg'],
		]);
		$json = $entry->jsonSerialize();
		$this->assertSame('lars', $entry->getUserId());
		$this->assertSame('2026-11-18', $json['date']);
		$this->assertSame('20:15', $json['time']);
		$this->assertSame(4, $json['rating']);
		$this->assertSame(['Familie', 'Zürich'], $json['tags']);
		$this->assertSame(['Schlaf' => 7], $json['trackers']);
		$this->assertSame('Zuhause', $json['location']['name']);
		$this->assertSame(['2026-11-18-assets/foto.jpg'], $json['attachments']);
	}

	public function testMinimalEntry(): void {
		$entry = Entry::fromArray('lars', ['date' => '2026-01-01', 'body' => 'Nur Text']);
		$json = $entry->jsonSerialize();
		$this->assertNull($json['time']);
		$this->assertNull($json['rating']);
		$this->assertSame([], $json['tags']);
		$this->assertNull($json['location']);
		$this->assertSame('Nur Text', $json['body']);
	}
}
