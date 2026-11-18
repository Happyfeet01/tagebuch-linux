<?php

declare(strict_types=1);

namespace OCA\Tagebuch\BackgroundJob;

use OCA\Tagebuch\AppInfo\Application;
use OCA\Tagebuch\Service\MailService;
use OCA\Tagebuch\Service\TokenService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IConfig;
use OCP\ILogger;
use OCP\IUser;
use OCP\IUserManager;

class ReminderJob extends TimedJob {
	private const REMINDER_HOUR = 20;

	public function __construct(
		ITimeFactory $time,
		private IConfig $config,
		private IUserManager $userManager,
		private MailService $mailService,
		private TokenService $tokens,
		private ILogger $logger,
	) {
		parent::__construct($time);
		$this->setTimeSensitivity(self::TIME_SENSITIVE);
	}

	protected function run($argument): void {
		$now = new \DateTimeImmutable('now');
		$next = $now->setTime(self::REMINDER_HOUR, 0, 0);
		if ($next <= $now) {
			$next = $next->modify('+1 day');
		}
		$this->setInterval(max(60, $next->getTimestamp() - $now->getTimestamp()));

		if ((int) $now->format('H') !== self::REMINDER_HOUR) {
			return;
		}
		$today = $now->format('Y-m-d');

		$this->userManager->callForSeenUsers(function (IUser $user) use ($today): void {
			$uid = $user->getUID();
			if ($this->config->getUserValue($uid, Application::APP_ID, 'reminder_enabled', '0') !== '1') {
				return;
			}
			if ($this->config->getUserValue($uid, Application::APP_ID, 'reminder_sent_date', '') === $today) {
				return;
			}
			$email = $user->getEMailAddress();
			if ($email === null || $email === '') {
				return;
			}
			$token = $this->tokens->createToken($uid, $today);
			try {
				$this->mailService->sendReminder($uid, $email, $today, $token);
				$this->config->setUserValue($uid, Application::APP_ID, 'reminder_sent_date', $today);
			} catch (\Throwable $e) {
				$this->logger->error('Tagebuch: Erinnerungs-Mail an ' . $uid . ' fehlgeschlagen: ' . $e->getMessage(), ['app' => Application::APP_ID]);
			}
		});
	}
}
