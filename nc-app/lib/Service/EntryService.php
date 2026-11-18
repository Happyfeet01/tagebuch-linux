<?php

declare(strict_types=1);

namespace OCA\Tagebuch\Service;

use OCA\Tagebuch\Db\Entry;
use OCA\Tagebuch\Db\EntryMapper;
use OCA\Tagebuch\Db\TemplateMapper;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IUserSession;

class EntryService {
	public const ASSETS_DIR = 'Tagebuch';

	public function __construct(
		private IRootFolder $rootFolder,
		private IUserSession $userSession,
		private EntryMapper $entryMapper,
		private TemplateMapper $templateMapper,
		private ?string $fixedUserId = null,
	) {
	}

	public function forUser(string $userId): self {
		return new self($this->rootFolder, $this->userSession, $this->entryMapper, $this->templateMapper, $userId);
	}

	private function userId(): string {
		if ($this->fixedUserId !== null && $this->fixedUserId !== '') {
			return $this->fixedUserId;
		}
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new \RuntimeException('Kein angemeldeter Benutzer');
		}
		return $user->getUID();
	}

	/**
	 * @return array<int, array>
	 */
	public function listEntries(?string $from = null, ?string $to = null): array {
		return array_map(static fn (Entry $e): array => $e->jsonSerialize(), $this->entryMapper->findAll($this->userId(), $from, $to));
	}

	/**
	 * @return array<int, array>
	 */
	public function entriesForDay(string $date): array {
		return array_map(static fn (Entry $e): array => $e->jsonSerialize(), $this->entryMapper->findForDay($this->userId(), $date));
	}

	public function getEntry(string $date, ?string $time = null): ?array {
		$entry = $this->entryMapper->findOne($this->userId(), $date, $time);
		if ($entry !== null) {
			return $entry->jsonSerialize();
		}
		$day = $this->entriesForDay($date);
		return count($day) === 1 ? $day[0] : null;
	}

	public function saveEntry(array $entry): array {
		if (empty($entry['date'])) {
			throw new \InvalidArgumentException('Datum fehlt');
		}
		$rating = $entry['rating'] ?? null;
		if ($rating !== null && ((int) $rating < 1 || (int) $rating > 5)) {
			throw new \InvalidArgumentException('rating muss zwischen 1 und 5 liegen');
		}
		if (!empty($entry['id'])) {
			$existing = $this->entryMapper->findById($this->userId(), (int) $entry['id']);
			if ($existing === null) {
				$entry['id'] = null;
			} elseif (empty($entry['created'])) {
				$entry['created'] = $existing->getCreatedAt();
			}
		}
		$now = (new \DateTimeImmutable('now'))->format('Y-m-d\TH:i:sP');
		if (empty($entry['created'])) {
			$entry['created'] = $now;
		}
		$entry['modified'] = $now;
		$entity = Entry::fromArray($this->userId(), $entry);
		$saved = $this->entryMapper->saveEntry($entity);
		return $saved->jsonSerialize();
	}

	public function deleteEntry(string $date, ?string $time): bool {
		return $this->entryMapper->deleteOne($this->userId(), $date, $time);
	}

	public function search(string $query, ?string $tag = null, ?string $since = null, ?string $until = null, ?int $minRating = null): array {
		return array_map(
			static fn (Entry $e): array => $e->jsonSerialize(),
			$this->entryMapper->search($this->userId(), $query, $tag, $since, $until, $minRating)
		);
	}

	public function onThisDay(?string $today = null): array {
		$today = $today ?? date('Y-m-d');
		$monthDay = substr($today, 5, 5);
		return array_values(array_filter(
			$this->listEntries(),
			static fn (array $e): bool => $e['date'] !== $today && substr($e['date'], 5, 5) === $monthDay
		));
	}

	public function allTags(): array {
		$tags = [];
		foreach ($this->entryMapper->findAll($this->userId()) as $entry) {
			foreach ((json_decode($entry->getTags(), true) ?: []) as $tag) {
				$tags[$tag] = true;
			}
		}
		$tags = array_keys($tags);
		usort($tags, static fn (string $a, string $b): int => strcasecmp($a, $b));
		return $tags;
	}

	public function stats(): array {
		$entries = $this->listEntries();
		$days = array_values(array_unique(array_map(static fn (array $e): string => $e['date'], $entries)));
		sort($days);
		$today = date('Y-m-d');
		$current = 0;
		$cursor = in_array($today, $days, true) ? $today : date('Y-m-d', strtotime('-1 day'));
		while (in_array($cursor, $days, true)) {
			$current++;
			$cursor = date('Y-m-d', strtotime($cursor . ' -1 day'));
		}
		$longest = 0;
		$run = 0;
		$prev = null;
		foreach ($days as $day) {
			if ($prev !== null && date('Y-m-d', strtotime($prev . ' +1 day')) === $day) {
				$run++;
			} else {
				$run = 1;
			}
			$longest = max($longest, $run);
			$prev = $day;
		}
		$ratings = array_values(array_filter(array_map(static fn (array $e) => $e['rating'], $entries)));
		$perMonth = [];
		foreach ($entries as $entry) {
			$month = substr($entry['date'], 0, 7);
			$perMonth[$month] = ($perMonth[$month] ?? 0) + 1;
		}
		ksort($perMonth);
		return [
			'total_entries' => count($entries),
			'total_days' => count($days),
			'total_words' => array_sum(array_map(static fn (array $e): int => str_word_count($e['heading'] . ' ' . $e['body']), $entries)),
			'current_streak' => $current,
			'longest_streak' => $longest,
			'average_rating' => count($ratings) > 0 ? round(array_sum($ratings) / count($ratings), 2) : null,
			'per_month' => $perMonth,
		];
	}

	public function addAttachment(string $date, string $filename, string $content): string {
		$userFolder = $this->rootFolder->getUserFolder($this->userId());
		if (!$userFolder->nodeExists(self::ASSETS_DIR)) {
			$userFolder->newFolder(self::ASSETS_DIR);
		}
		$folder = $userFolder->get(self::ASSETS_DIR);
		if (!$folder instanceof Folder) {
			throw new \RuntimeException('Assets-Ordner fehlt');
		}
		$assetsName = $date . '-assets';
		if (!$folder->nodeExists($assetsName)) {
			$folder->newFolder($assetsName);
		}
		$assets = $folder->get($assetsName);
		if (!$assets instanceof Folder) {
			throw new \RuntimeException('Assets-Ordner fehlt');
		}
		$name = basename($filename);
		$counter = 1;
		while ($assets->nodeExists($name)) {
			$path = pathinfo($filename);
			$name = $path['filename'] . '-' . $counter . (isset($path['extension']) ? '.' . $path['extension'] : '');
			$counter++;
		}
		$assets->newFile($name, $content);
		return $assetsName . '/' . $name;
	}

	/**
	 * @return array{content: string, mime: string}
	 */
	public function getAttachment(string $date, string $name): array {
		$name = basename($name);
		$rel = self::ASSETS_DIR . '/' . $date . '-assets/' . $name;
		$userFolder = $this->rootFolder->getUserFolder($this->userId());
		if (!$userFolder->nodeExists($rel)) {
			throw new \InvalidArgumentException('Anhang nicht gefunden');
		}
		$node = $userFolder->get($rel);
		if (!$node instanceof File) {
			throw new \InvalidArgumentException('Keine Datei');
		}
		return ['content' => (string) $node->getContent(), 'mime' => PhotoService::mimeFor($node->getName())];
	}

	public function listTemplates(): array {
		return array_map(static fn ($t): string => $t->getName(), $this->templateMapper->findAllForUser($this->userId()));
	}

	public function getTemplate(string $name): string {
		$template = $this->templateMapper->findByName($this->userId(), basename($name));
		if ($template === null) {
			throw new \InvalidArgumentException('Vorlage nicht gefunden: ' . $name);
		}
		return $template->getBody();
	}

	public function saveTemplate(string $name, string $body): string {
		$name = basename($name);
		if (!str_ends_with($name, '.md')) {
			$name .= '.md';
		}
		return $this->templateMapper->saveTemplate($this->userId(), $name, $body)->getName();
	}
}
