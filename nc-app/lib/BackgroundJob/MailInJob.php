<?php

declare(strict_types=1);

namespace OCA\Tagebuch\BackgroundJob;

use OCA\Tagebuch\AppInfo\Application;
use OCA\Tagebuch\Service\EntryService;
use OCA\Tagebuch\Service\MailInParser;
use OCA\Tagebuch\Service\TokenService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use Psr\Log\LoggerInterface;
use OCP\IUserSession;

class MailInJob extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private IConfig $config,
		private IRootFolder $rootFolder,
		private IUserSession $userSession,
		private TokenService $tokens,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
		$this->setInterval(300);
		$this->setTimeSensitivity(self::TIME_SENSITIVE);
	}

	protected function run($argument): void {
		if (!function_exists('imap_open')) {
			return;
		}
		$host = $this->config->getAppValue(Application::APP_ID, 'imap_host', '');
		$user = $this->config->getAppValue(Application::APP_ID, 'imap_user', '');
		$password = $this->config->getAppValue(Application::APP_ID, 'imap_password', '');
		if ($host === '' || $user === '') {
			return;
		}
		$port = (int) $this->config->getAppValue(Application::APP_ID, 'imap_port', '993');
		$ssl = $this->config->getAppValue(Application::APP_ID, 'imap_ssl', '1') !== '0';
		$mailbox = sprintf('{%s:%d/imap%s}INBOX', $host, $port, $ssl ? '/ssl/novalidate-cert' : '');

		$stream = @imap_open($mailbox, $user, $password);
		if ($stream === false) {
			$this->logger->warning('Tagebuch Mail-in: imap_open fehlgeschlagen (' . $mailbox . ')', ['app' => Application::APP_ID]);
			return;
		}

		$ids = imap_search($stream, 'UNSEEN');
		if (is_array($ids)) {
			foreach ($ids as $id) {
				try {
					$raw = imap_fetchheader($stream, $id) . "\r\n" . imap_body($stream, $id);
					$token = MailInParser::extractToken($raw);
					$info = $token !== null ? $this->tokens->verify($token) : null;
					if ($info === null) {
						continue;
					}
					$body = MailInParser::extractBody($raw);
					if (trim($body) === '') {
						continue;
					}
					$this->storeMailEntry($info['user'], $info['date'], $body);
					imap_setflag_full($stream, (string) $id, '\\Seen');
				} catch (\Throwable $e) {
					$this->logger->error('Tagebuch Mail-in: Verarbeitung von Mail ' . $id . ' fehlgeschlagen: ' . $e->getMessage(), ['app' => Application::APP_ID]);
				}
			}
		}
		imap_close($stream);
	}

	private function storeMailEntry(string $userId, string $date, string $body): void {
		$service = (new EntryService($this->rootFolder, $this->userSession))->forUser($userId);
		$existing = $service->entriesForDay($date);
		$time = count($existing) > 0 ? date('H:i') : null;
		$entry = $service->getEntry($date, $time) ?? ['date' => $date, 'time' => $time];
		$entry['body'] = trim((string) ($entry['body'] ?? '')) . ($entry['body'] !== '' ? "\n\n" : '') . $body;
		if (trim((string) ($entry['heading'] ?? '')) === '') {
			$entry['heading'] = 'Per E-Mail';
		}
		$service->saveEntry($entry);
	}
}
