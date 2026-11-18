<?php

declare(strict_types=1);

namespace OCA\Tagebuch\Tests\Unit;

use OCA\Tagebuch\Service\MarkdownCodec;
use PHPUnit\Framework\TestCase;

class MarkdownCodecTest extends TestCase {
	private const PYTHON_SAMPLE = <<<'MD'
---
version: 1
date: 2026-11-18
time: '20:15'
created: 2026-11-18 01:01:32.662517+00:00
modified: 2026-11-18 01:01:32.662517+00:00
heading: Abendrückblick
rating: 4
tags:
- Familie
- Spaziergang
trackers:
  Schlaf: 7
location:
  name: Zuhause
  lat: 47.3769
  lon: 8.5417
attachments:
- 2026-11-18-assets/foto.jpg
---

Heute war ein **guter** Tag.

- Eins
- Zwei
MD;

	public function testParsePythonWrittenFile(): void {
		$entry = MarkdownCodec::parse(self::PYTHON_SAMPLE, '2026-11-18_20-15.md');
		$this->assertSame('2026-11-18', $entry['date']);
		$this->assertSame('20:15', $entry['time']);
		$this->assertSame('Abendrückblick', $entry['heading']);
		$this->assertSame(4, $entry['rating']);
		$this->assertSame(['Familie', 'Spaziergang'], $entry['tags']);
		$this->assertSame(['Schlaf' => '7'], $entry['trackers']);
		$this->assertSame('Zuhause', $entry['location']['name']);
		$this->assertSame('47.3769', (string) $entry['location']['lat']);
		$this->assertSame(['2026-11-18-assets/foto.jpg'], $entry['attachments']);
		$this->assertStringContainsString('Heute war ein **guter** Tag.', $entry['body']);
		$this->assertStringContainsString('- Eins', $entry['body']);
	}

	public function testSerializeParseRoundtrip(): void {
		$entry = [
			'date' => '2026-11-18',
			'time' => '20:15',
			'heading' => 'Abend: mit Sonderzeichen # und "Anführungszeichen"',
			'body' => "Zeile 1\n\nZeile 2",
			'rating' => 5,
			'tags' => ['Arbeit', 'Zürich'],
			'trackers' => ['Schlaf' => 7],
			'location' => ['name' => 'Büro', 'lat' => 47.37, 'lon' => 8.54],
			'attachments' => ['2026-11-18-assets/a.jpg'],
			'created' => '2026-11-18T08:12:00+01:00',
			'modified' => '2026-11-18T20:16:41+01:00',
		];
		$text = MarkdownCodec::serialize($entry);
		$this->assertStringStartsWith('---', $text);
		$parsed = MarkdownCodec::parse($text, '2026-11-18_20-15.md');
		$this->assertSame('2026-11-18', $parsed['date']);
		$this->assertSame('20:15', $parsed['time']);
		$this->assertSame($entry['heading'], $parsed['heading']);
		$this->assertSame(5, $parsed['rating']);
		$this->assertSame(['Arbeit', 'Zürich'], $parsed['tags']);
		$this->assertSame('Zuhause' !== ($parsed['location']['name'] ?? ''), true);
		$this->assertSame('Büro', $parsed['location']['name']);
		$this->assertSame('2026-11-18-assets/a.jpg', $parsed['attachments'][0]);
		$this->assertSame("Zeile 1\n\nZeile 2", $parsed['body']);
	}

	public function testFilenameConventions(): void {
		$this->assertSame('2026-11-18.md', MarkdownCodec::filenameFor('2026-11-18', null));
		$this->assertSame('2026-11-18_20-15.md', MarkdownCodec::filenameFor('2026-11-18', '20:15'));
		$this->assertSame(['date' => '2026-11-18', 'time' => null], MarkdownCodec::parseFilename('2026-11-18.md'));
		$this->assertSame(['date' => '2026-11-18', 'time' => '20:15'], MarkdownCodec::parseFilename('2026-11-18_20-15.md'));
		$this->assertNull(MarkdownCodec::parseFilename('notiz.md'));
		$this->assertNull(MarkdownCodec::parseFilename('2026-11-18.conflict-laptop.md'));
	}

	public function testParseWithoutFrontmatterUsesFilename(): void {
		$entry = MarkdownCodec::parse("# Titel\n\nFreier Text\n", '2026-03-04.md');
		$this->assertSame('2026-03-04', $entry['date']);
		$this->assertNull($entry['time']);
		$this->assertStringContainsString('Freier Text', $entry['body']);
	}

	public function testParseWithoutDateThrows(): void {
		$this->expectException(\InvalidArgumentException::class);
		MarkdownCodec::parse("---\nheading: x\n---\ntext\n", 'foo.md');
	}

	public function testFlowStyleLists(): void {
		$entry = MarkdownCodec::parse("---\ndate: 2026-01-01\ntags: [a, \"b, c\", d]\n---\n\nText\n", '2026-01-01.md');
		$this->assertSame(['a', 'b, c', 'd'], $entry['tags']);
	}
}
