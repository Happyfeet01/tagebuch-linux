<?php

declare(strict_types=1);

namespace OCA\Tagebuch\AppInfo;

use OCA\Tagebuch\BackgroundJob\MailInJob;
use OCA\Tagebuch\BackgroundJob\ReminderJob;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {
	public const APP_ID = 'tagebuch';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerBackgroundJob(ReminderJob::class);
		$context->registerBackgroundJob(MailInJob::class);
	}

	public function boot(IBootContext $context): void {
	}
}
