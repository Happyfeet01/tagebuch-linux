<?php

declare(strict_types=1);

namespace OCA\Tagebuch\Tests\Unit;

use OCA\Tagebuch\AppInfo\Application;
use OCA\Tagebuch\BackgroundJob\MailInJob;
use OCA\Tagebuch\BackgroundJob\ReminderJob;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\BackgroundJob\IJobList;
use OCP\IServerContainer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ApplicationTest extends TestCase {
	private function bootContext(IJobList&MockObject $jobList): IBootContext {
		$server = $this->createMock(IServerContainer::class);
		$server->method('get')->with(IJobList::class)->willReturn($jobList);
		$boot = $this->createMock(IBootContext::class);
		$boot->method('getServerContainer')->willReturn($server);
		return $boot;
	}

	public function testRegisterToleratesContextWithoutRegisterBackgroundJob(): void {
		$context = $this->createMock(IRegistrationContext::class);
		$this->assertFalse(method_exists($context, 'registerBackgroundJob'));
		(new Application())->register($context);
		$this->assertTrue(true);
	}

	public function testBootRegistersJobsViaJobList(): void {
		$jobList = $this->createMock(IJobList::class);
		$jobList->method('has')->willReturn(false);
		$added = [];
		$jobList->method('add')->willReturnCallback(static function (string $job, $argument) use (&$added): void {
			$added[] = [$job, $argument];
		});
		(new Application())->boot($this->bootContext($jobList));
		$this->assertSame([[ReminderJob::class, null], [MailInJob::class, null]], $added);
	}

	public function testBootSkipsAlreadyRegisteredJobs(): void {
		$jobList = $this->createMock(IJobList::class);
		$jobList->method('has')->with(ReminderJob::class, null)->willReturn(true);
		$jobList->method('has')->with(MailInJob::class, null)->willReturn(true);
		$jobList->expects($this->never())->method('add');
		(new Application())->boot($this->bootContext($jobList));
	}
}
