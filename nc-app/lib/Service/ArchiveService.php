<?php
declare(strict_types=1);

namespace OCA\Tagebuch\Service;

use OCA\Tagebuch\Db\Entry;
use OCA\Tagebuch\Db\EntryMapper;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\ITempManager;
use ZipArchive;

class ArchiveService {
	private const PREFS = ['reminder_enabled', 'theme', 'font', 'accent', 'photo_folder'];
	public function __construct(private EntryService $entries, private EntryMapper $mapper,
		private IRootFolder $root, private IDBConnection $db, private IConfig $config,
		private ITempManager $temp, private string $userId) {}

	private function stateRows(): array {
		$q = $this->db->getQueryBuilder();
		$q->select('state_key', 'payload')->from('tagebuch_state')
			->where($q->expr()->eq('user_id', $q->createNamedParameter($this->userId)));
		return $q->executeQuery()->fetchAll();
	}

	private function checkPath(string $date, string $path): void {
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date) || !str_starts_with($path, $date . '-assets/')
			|| substr_count($path, '/') !== 1 || str_contains($path, '\\')
			|| in_array(basename($path), ['', '.', '..'], true)) {
			throw new \InvalidArgumentException('Ungültiger Anhangspfad.');
		}
	}

	public function export(): string {
		$file = $this->temp->getTemporaryFile('.zip');
		$zip = new ZipArchive();
		if ($zip->open($file, ZipArchive::OVERWRITE | ZipArchive::CREATE) !== true) throw new \RuntimeException('ZIP nicht verfügbar.');
		$entries = $this->entries->listEntries();
		$state = $this->stateRows();
		if (array_filter($entries, fn ($e) => $e['encrypted']) && !in_array('vault', array_column($state, 'state_key'), true)) {
			throw new \InvalidArgumentException('Bitte zuerst im ursprünglichen Browser den Tresor entsperren.');
		}
		$files = [];
		foreach ($entries as $entry) {
			foreach ($entry['attachments'] as $path) {
				$this->checkPath($entry['date'], $path);
				if (isset($files[$path])) continue;
				$content = $this->entries->getAttachment($entry['date'], basename($path))['content'];
				$hash = hash('sha256', $content);
				if (!in_array($hash, $files, true) && !$zip->addFromString('files/' . $hash, $content)) throw new \RuntimeException('Anhang nicht gesichert.');
				$files[$path] = $hash;
			}
		}
		// Drafts may reference files that have not yet been committed to an entry.
		// Store the journal's date-based attachment folders, including encrypted draft files.
		$home = $this->root->getUserFolder($this->userId);
		if ($home->nodeExists(EntryService::ASSETS_DIR)) {
			$assets = $home->get(EntryService::ASSETS_DIR);
			if ($assets instanceof Folder) foreach ($assets->getDirectoryListing() as $dir) {
				if (!$dir instanceof Folder || !preg_match('/^(\d{4}-\d{2}-\d{2})-assets$/D', $dir->getName(), $m)) continue;
				foreach ($dir->getDirectoryListing() as $node) {
					if (!$node instanceof \OCP\Files\File) continue;
					$path = $dir->getName() . '/' . $node->getName();
					if (isset($files[$path])) continue;
					$content = $node->getContent();
					$hash = hash('sha256', $content);
					if (!in_array($hash, $files, true) && !$zip->addFromString('files/' . $hash, $content)) throw new \RuntimeException('Anhang nicht gesichert.');
					$files[$path] = $hash;
				}
			}
		}
		$templates = [];
		foreach ($this->entries->listTemplates() as $name) $templates[$name] = $this->entries->getTemplate($name);
		$prefs = [];
		foreach (self::PREFS as $key) $prefs[$key] = $this->config->getUserValue($this->userId, 'tagebuch', $key, '');
		$manifest = ['format' => 'nextcloud-tagebuch/1', 'entries' => $entries, 'files' => $files,
			'templates' => $templates, 'state' => $state, 'preferences' => $prefs];
		if (!$zip->addFromString('journal.json', json_encode($manifest, JSON_THROW_ON_ERROR)) || !$zip->close()) throw new \RuntimeException('ZIP unvollständig.');
		return $file;
	}

	public function restore(string $file): int {
		$zip = new ZipArchive();
		if ($zip->open($file) !== true) throw new \InvalidArgumentException('ZIP nicht lesbar.');
		$manifest = json_decode((string)$zip->getFromName('journal.json'), true);
		if (($manifest['format'] ?? '') !== 'nextcloud-tagebuch/1') throw new \InvalidArgumentException('Unbekanntes Sicherungsformat.');
		foreach (['entries','files','state','templates','preferences'] as $key) {
			if (!is_array($manifest[$key] ?? null)) throw new \InvalidArgumentException('Sicherung unvollständig.');
		}
		foreach ($manifest['files'] as $path => $hash) {
			$this->checkPath(substr($path, 0, 10), $path);
			if (!is_string($hash) || !preg_match('/^[0-9a-f]{64}$/D', $hash)) throw new \InvalidArgumentException('Ungültige Prüfsumme.');
			$content = $zip->getFromName('files/' . $hash);
			if ($content === false || hash('sha256', $content) !== $hash) throw new \InvalidArgumentException('Anhang beschädigt.');
		}
		$home = $this->root->getUserFolder($this->userId);
		$this->db->beginTransaction();
		$created = [];
		try {
			if ($this->entries->listEntries() || $this->entries->listTemplates() || $this->stateRows()) throw new \InvalidArgumentException('Die Wiederherstellung benötigt ein leeres Tagebuch ohne Tresor oder Entwürfe.');
			$ids = [];
			foreach ($manifest['entries'] as $row) {
				if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $row['date'] ?? '')) throw new \InvalidArgumentException('Ungültiges Eintragsdatum.');
				foreach ($row['attachments'] as $path) {
					$this->checkPath($row['date'], $path);
					if (!isset($manifest['files'][$path])) throw new \InvalidArgumentException('Anhang fehlt.');
				}
				$old = $row['id'];
				unset($row['id']);
				$ids[$old] = $this->mapper->insert(Entry::fromArray($this->userId, $row))->getId();
			}
			foreach ($manifest['templates'] as $name => $body) $this->entries->saveTemplate($name, $body);
			foreach ($manifest['state'] as $row) {
				$key = $row['state_key'];
				if (preg_match('/^(draft-\d{4}-\d{2}-\d{2}-)(\d+|new)$/D', $key, $match)) {
					if ($match[2] !== 'new') {
						if (!isset($ids[$match[2]])) continue;
						$key = $match[1] . $ids[$match[2]];
					}
				} elseif ($key !== 'vault') throw new \InvalidArgumentException('Ungültiger Sicherungseintrag.');
				$q = $this->db->getQueryBuilder();
				$q->insert('tagebuch_state')->values(['user_id' => $q->createNamedParameter($this->userId),
					'state_key' => $q->createNamedParameter($key), 'payload' => $q->createNamedParameter($row['payload']),
					'revision' => $q->createNamedParameter(bin2hex(random_bytes(16)))])->executeStatement();
			}
			if ($manifest['files']) {
				$assets = $home->nodeExists(EntryService::ASSETS_DIR) ? $home->get(EntryService::ASSETS_DIR) : $home->newFolder(EntryService::ASSETS_DIR);
				foreach ($manifest['files'] as $path => $hash) {
					[$folder, $name] = explode('/', $path);
					$dir = $assets->nodeExists($folder) ? $assets->get($folder) : $assets->newFolder($folder);
					if ($dir->nodeExists($name)) {
						if (hash('sha256', $dir->get($name)->getContent()) !== $hash) throw new \InvalidArgumentException('Vorhandene Datei weicht ab.');
						continue;
					}
					$created[] = $dir->newFile($name, $zip->getFromName('files/' . $hash));
				}
			}
			foreach (self::PREFS as $key) {
				if (isset($manifest['preferences'][$key])) $this->config->setUserValue($this->userId, 'tagebuch', $key, (string)$manifest['preferences'][$key]);
			}
			$this->db->commit();
		} catch (\Throwable $e) {
			try { foreach (array_reverse($created) as $node) $node->delete(); }
			finally { $this->db->rollBack(); }
			throw $e;
		} finally { $zip->close(); }
		return count($ids);
	}
}
