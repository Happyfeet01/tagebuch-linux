<?php

declare(strict_types=1);

namespace OCA\Tagebuch\Tests\Unit;

use OCA\Tagebuch\Service\MailInParser;
use PHPUnit\Framework\TestCase;

class MailInParserTest extends TestCase {
	private const TOKEN = '0123456789abcdef0123456789abcdef';

	public function testExtractTokenFromToHeader(): void {
		$raw = "From: lars@example.com\r\nTo: tagebuch+" . self::TOKEN . "@cloud.example\r\nSubject: Re: Tagebuch\r\n\r\nHallo\r\n";
		$this->assertSame(self::TOKEN, MailInParser::extractToken($raw));
	}

	public function testExtractTokenFromSubject(): void {
		$raw = "From: lars@example.com\r\nSubject: [tagebuch:" . strtoupper(self::TOKEN) . "]\r\n\r\nHallo\r\n";
		$this->assertSame(self::TOKEN, MailInParser::extractToken($raw));
	}

	public function testExtractTokenMissing(): void {
		$this->assertNull(MailInParser::extractToken("From: a@b.c\r\nSubject: Hallo\r\n\r\nText\r\n"));
	}

	public function testExtractBodyPlain(): void {
		$raw = "From: a@b.c\r\nSubject: x\r\n\r\nErste Zeile\nZweite Zeile\n";
		$this->assertSame("Erste Zeile\nZweite Zeile", MailInParser::extractBody($raw));
	}

	public function testExtractBodyQuotedPrintable(): void {
		$raw = "Content-Type: text/plain; charset=utf-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\nGr=C3=BC=C3=9Fe\n";
		$this->assertSame('Grüße', MailInParser::extractBody($raw));
	}

	public function testExtractBodyBase64(): void {
		$raw = "Content-Transfer-Encoding: base64\r\n\r\n" . base64_encode("Hallo Welt") . "\r\n";
		$this->assertSame('Hallo Welt', MailInParser::extractBody($raw));
	}

	public function testExtractBodyHtmlIsStripped(): void {
		$raw = "Content-Type: text/html; charset=utf-8\r\n\r\n<p>Hallo <b>Welt</b></p>\n";
		$this->assertSame('Hallo Welt', MailInParser::extractBody($raw));
	}

	public function testExtractFrom(): void {
		$raw = "From: Lars <lars@example.com>\r\n\r\nText\r\n";
		$this->assertSame('lars@example.com', MailInParser::extractFrom($raw));
	}
}
