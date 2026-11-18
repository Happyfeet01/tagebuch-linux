<?php

declare(strict_types=1);

namespace OCA\Tagebuch\Service;

final class MailInParser {
	public static function extractToken(string $raw): ?string {
		if (preg_match('/tagebuch\+([a-f0-9]{32})@/i', $raw, $m) === 1) {
			return strtolower($m[1]);
		}
		if (preg_match('/\[tagebuch:([a-f0-9]{32})\]/i', $raw, $m) === 1) {
			return strtolower($m[1]);
		}
		return null;
	}

	public static function extractBody(string $raw): string {
		$raw = str_replace("\r\n", "\n", $raw);
		$parts = explode("\n\n", $raw, 2);
		$headers = $parts[0];
		$body = $parts[1] ?? '';

		$encoding = '7bit';
		if (preg_match('/^Content-Transfer-Encoding:\s*(\S+)/mi', $headers, $m) === 1) {
			$encoding = strtolower(trim($m[1]));
		}
		if ($encoding === 'quoted-printable') {
			$body = quoted_printable_decode($body);
		} elseif ($encoding === 'base64') {
			$body = (string) base64_decode($body);
		}

		if (preg_match('/^Content-Type:\s*text\/html/mi', $headers) === 1) {
			$body = html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		}

		if (preg_match('/charset=["\']?([\w-]+)/i', $headers, $m) === 1
			&& strtoupper($m[1]) !== 'UTF-8'
			&& function_exists('mb_convert_encoding')) {
			$converted = @mb_convert_encoding($body, 'UTF-8', $m[1]);
			if (is_string($converted)) {
				$body = $converted;
			}
		}

		return trim($body);
	}

	public static function extractFrom(string $raw): string {
		$raw = str_replace("\r\n", "\n", $raw);
		$headers = explode("\n\n", $raw, 2)[0];
		if (preg_match('/^From:\s*(.+)$/mi', $headers, $m) === 1) {
			if (preg_match('/<([^>]+)>/', $m[1], $addr) === 1) {
				return trim($addr[1]);
			}
			return trim($m[1]);
		}
		return '';
	}
}
