<?php

declare(strict_types=1);

namespace OCA\Tagebuch\Service;

final class MarkdownCodec {
	public static function filenameFor(string $date, ?string $time): string {
		if ($time !== null && $time !== '') {
			return $date . '_' . str_replace(':', '-', $time) . '.md';
		}
		return $date . '.md';
	}

	public static function parseFilename(string $name): ?array {
		if (preg_match('/^(\d{4}-\d{2}-\d{2})(?:_(\d{2})-(\d{2}))?\.md$/', $name, $m) === 1) {
			return [
				'date' => $m[1],
				'time' => isset($m[2]) && $m[2] !== '' ? $m[2] . ':' . $m[3] : null,
			];
		}
		return null;
	}

	public static function serialize(array $entry): string {
		$lines = ['---', 'version: 1', 'date: ' . $entry['date']];
		if (!empty($entry['time'])) {
			$lines[] = 'time: ' . self::scalar((string) $entry['time']);
		}
		if (!empty($entry['created'])) {
			$lines[] = 'created: ' . self::scalar((string) $entry['created']);
		}
		if (!empty($entry['modified'])) {
			$lines[] = 'modified: ' . self::scalar((string) $entry['modified']);
		}
		if (!empty($entry['heading'])) {
			$lines[] = 'heading: ' . self::scalar((string) $entry['heading']);
		}
		if (isset($entry['rating']) && $entry['rating'] !== null) {
			$lines[] = 'rating: ' . (int) $entry['rating'];
		}
		if (!empty($entry['tags']) && is_array($entry['tags'])) {
			$lines[] = 'tags:';
			foreach ($entry['tags'] as $tag) {
				$lines[] = '- ' . self::scalar((string) $tag);
			}
		}
		if (!empty($entry['trackers']) && is_array($entry['trackers'])) {
			$lines[] = 'trackers:';
			foreach ($entry['trackers'] as $key => $value) {
				$valueStr = is_scalar($value) ? (string) $value : (string) json_encode($value);
				$lines[] = '  ' . self::scalar((string) $key) . ': ' . self::scalar($valueStr);
			}
		}
		if (!empty($entry['location']) && is_array($entry['location'])) {
			$lines[] = 'location:';
			$lines[] = '  name: ' . self::scalar((string) ($entry['location']['name'] ?? ''));
			if (isset($entry['location']['lat'])) {
				$lines[] = '  lat: ' . $entry['location']['lat'];
			}
			if (isset($entry['location']['lon'])) {
				$lines[] = '  lon: ' . $entry['location']['lon'];
			}
		}
		if (!empty($entry['attachments']) && is_array($entry['attachments'])) {
			$lines[] = 'attachments:';
			foreach ($entry['attachments'] as $attachment) {
				$lines[] = '- ' . self::scalar((string) $attachment);
			}
		}
		$lines[] = '---';
		$body = isset($entry['body']) ? rtrim((string) $entry['body'], "\n") : '';
		return implode("\n", $lines) . "\n\n" . $body . "\n";
	}

	public static function parse(string $text, ?string $filename = null): array {
		$meta = [];
		$body = $text;
		$stripped = ltrim($text, "\n");
		if (str_starts_with($stripped, '---')) {
			$end = strpos($stripped, "\n---", 3);
			if ($end === false) {
				throw new \InvalidArgumentException('Frontmatter nicht geschlossen (--- fehlt)');
			}
			$front = substr($stripped, 4, $end - 4);
			$body = substr($stripped, $end + 4);
			$meta = self::parseYaml($front);
			$body = trim($body, "\n");
		}
		$entry = [
			'date' => isset($meta['date']) ? self::str($meta['date']) : null,
			'time' => isset($meta['time']) ? self::str($meta['time']) : null,
			'heading' => isset($meta['heading']) ? self::str($meta['heading']) : '',
			'body' => $body,
			'rating' => isset($meta['rating']) && $meta['rating'] !== null ? (int) $meta['rating'] : null,
			'tags' => isset($meta['tags']) && is_array($meta['tags']) ? array_map('strval', $meta['tags']) : [],
			'trackers' => isset($meta['trackers']) && is_array($meta['trackers']) ? $meta['trackers'] : [],
			'location' => isset($meta['location']) && is_array($meta['location']) ? $meta['location'] : null,
			'attachments' => isset($meta['attachments']) && is_array($meta['attachments']) ? array_map('strval', $meta['attachments']) : [],
			'created' => isset($meta['created']) ? self::str($meta['created']) : null,
			'modified' => isset($meta['modified']) ? self::str($meta['modified']) : null,
		];
		if ($filename !== null) {
			$parsed = self::parseFilename($filename);
			if ($parsed !== null) {
				if ($entry['date'] === null) {
					$entry['date'] = $parsed['date'];
				}
				if ($entry['time'] === null) {
					$entry['time'] = $parsed['time'];
				}
			}
		}
		if ($entry['date'] === null || $entry['date'] === '') {
			throw new \InvalidArgumentException('Eintrag ohne Datum (Dateiname oder Frontmatter fehlt)');
		}
		return $entry;
	}

	private static function parseYaml(string $text): array {
		$lines = explode("\n", str_replace("\r\n", "\n", $text));
		return self::parseBlock($lines, 0, count($lines), 0);
	}

	private static function parseBlock(array $lines, int $start, int $end, int $indent): array {
		$result = [];
		$i = $start;
		while ($i < $end) {
			$line = $lines[$i];
			$trimmed = trim($line);
			if ($trimmed === '' || str_starts_with($trimmed, '#')) {
				$i++;
				continue;
			}
			$curIndent = strlen($line) - strlen(ltrim($line, ' '));
			if ($curIndent < $indent) {
				break;
			}
			if (str_starts_with($trimmed, '- ')) {
				$i++;
				continue;
			}
			if (preg_match('/^([^:]+):\s*(.*)$/', $trimmed, $m) === 1) {
				$key = trim($m[1]);
				$value = trim($m[2]);
				if ($value === '') {
					$j = $i + 1;
					$childIndent = null;
					$isList = false;
					$items = [];
					while ($j < $end) {
						$l = $lines[$j];
						if (trim($l) === '') {
							$j++;
							continue;
						}
						$ci = strlen($l) - strlen(ltrim($l, ' '));
						if ($ci <= $curIndent) {
							break;
						}
						if ($childIndent === null) {
							$childIndent = $ci;
							$isList = str_starts_with(trim($l), '- ');
						}
						if ($ci !== $childIndent) {
							break;
						}
						$items[] = $l;
						$j++;
					}
					if ($items === []) {
						$result[$key] = null;
						$i++;
					} elseif ($isList) {
						$list = [];
						foreach ($items as $l) {
							$list[] = self::parseScalar(substr(trim($l), 2));
						}
						$result[$key] = $list;
						$i = $j;
					} else {
						$result[$key] = self::parseBlock($items, 0, count($items), $childIndent);
						$i = $j;
					}
				} else {
					$result[$key] = self::parseScalar($value);
					$i++;
				}
			} else {
				$i++;
			}
		}
		return $result;
	}

	private static function parseScalar(string $value) {
		$value = trim($value);
		if ($value === '') {
			return null;
		}
		if (strlen($value) >= 2 && $value[0] === '"' && str_ends_with($value, '"')) {
			return stripcslashes(substr($value, 1, -1));
		}
		if (strlen($value) >= 2 && $value[0] === "'" && str_ends_with($value, "'")) {
			return str_replace("''", "'", substr($value, 1, -1));
		}
		if ($value[0] === '[' && str_ends_with($value, ']')) {
			$inner = trim(substr($value, 1, -1));
			if ($inner === '') {
				return [];
			}
			return array_map(fn (string $p) => self::parseScalar($p), self::splitFlow($inner));
		}
		$lower = strtolower($value);
		if ($lower === 'null' || $lower === '~') {
			return null;
		}
		if ($lower === 'true') {
			return true;
		}
		if ($lower === 'false') {
			return false;
		}
		return $value;
	}

	private static function splitFlow(string $inner): array {
		$parts = [];
		$depth = 0;
		$current = '';
		$quote = null;
		foreach (str_split($inner) as $ch) {
			if ($quote !== null) {
				$current .= $ch;
				if ($ch === $quote) {
					$quote = null;
				}
				continue;
			}
			if ($ch === '"' || $ch === "'") {
				$quote = $ch;
				$current .= $ch;
				continue;
			}
			if ($ch === '[' || $ch === '{') {
				$depth++;
			}
			if ($ch === ']' || $ch === '}') {
				$depth--;
			}
			if ($ch === ',' && $depth === 0) {
				$parts[] = $current;
				$current = '';
				continue;
			}
			$current .= $ch;
		}
		if (trim($current) !== '') {
			$parts[] = $current;
		}
		return $parts;
	}

	private static function scalar(string $value): string {
		if ($value === '') {
			return '""';
		}
		if (preg_match('/[:#\[\]{},&*!|>\'"%@`\n]/', $value) === 1
			|| preg_match('/^\s|\s$/', $value) === 1
			|| preg_match('/^(true|false|null|~|[-+]?[0-9][0-9.]*)$/i', $value) === 1) {
			return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
		}
		return $value;
	}

	private static function str($value): string {
		return is_scalar($value) ? (string) $value : '';
	}
}
