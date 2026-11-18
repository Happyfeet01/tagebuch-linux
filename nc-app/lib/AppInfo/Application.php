<?php

declare(strict_types=1);

namespace OCA\Tagebuch\AppInfo;

use OCA\Tagebuch\BackgroundJob\MailInJob;
use OCA\Tagebuch\BackgroundJob\ReminderJob;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\BackgroundJob\IJobList;

class Application extends App implements IBootstrap {
	public const APP_ID = 'tagebuch';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		// Ab NC 35 stellt der Coordinator dem register()-Aufruf ein anonymes
		// IRegistrationContext ohne registerBackgroundJob() bereit – daher Guard.
		if (method_exists($context, 'registerBackgroundJob')) {
			$context->registerBackgroundJob(ReminderJob::class);
			$context->registerBackgroundJob(MailInJob::class);
		}
	}

	public function boot(IBootContext $context): void {
		// Funktioniert auf allen NC-Versionen (31–35): IJobList::add() dedupliziert.
		$jobList = $context->getServerContainer()->get(IJobList::class);
		foreach ([ReminderJob::class, MailInJob::class] as $job) {
			if (!$jobList->has($job)) {
				$jobList->add($job);
			}
		}
	}
}
