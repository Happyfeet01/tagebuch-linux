<?php

declare(strict_types=1);

namespace OCA\Tagebuch\BackgroundJob;

use OCA\Tagebuch\AppInfo\Application;
use OCA\Tagebuch\Service\MailService;
use OCA\Tagebuch\Service\ReminderTime;
use OCA\Tagebuch\Service\TokenService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IConfig;
use Psr\Log\LoggerInterface;
use OCP\IUser;
use OCP\IUserManager;

class ReminderJob extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private IConfig $config,
		private IUserManager $userManager,
		private MailService $mailService,
		private ReminderTime $schedule,
		private TokenService $tokens,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
		$this->setInterval(300);
		$this->setAllowParallelRuns(false);
		$this->setTimeSensitivity(self::TIME_SENSITIVE);
	}

	protected function run($argument): void {
		$now = new \DateTimeImmutable('now');
		$this->userManager->callForSeenUsers(function (IUser $user) use ($now): void {
			$uid = $user->getUID();
			if ($this->config->getUserValue($uid, Application::APP_ID, 'reminder_enabled', '0') !== '1') {
				return;
			}
			$today = $this->schedule->dueDate($uid, $now);
			if ($today === null || $this->config->getUserValue($uid, Application::APP_ID, 'reminder_sent_date', '') === $today) {
				return;
			}
			$email = $user->getEMailAddress();
			if ($email === null || $email === '') {
				return;
			}
			try {
				$token = $this->tokens->createToken($uid, $today);
				$this->mailService->sendReminder($uid, $email, $today, $token);
				$this->config->setUserValue($uid, Application::APP_ID, 'reminder_sent_date', $today);
			} catch (\Throwable $e) {
				$this->logger->error('Tagebuch: Erinnerungs-Mail an ' . $uid . ' fehlgeschlagen: ' . $e->getMessage(), ['app' => Application::APP_ID]);
			}
		});
	}
}
