<?php

declare(strict_types=1);

namespace OCA\Tagebuch\Tests\Unit;

use OCA\Tagebuch\Service\MailService;
use OCA\Tagebuch\Service\TokenService;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\Mail\IMailer;
use OCP\Mail\IMessage;
use PHPUnit\Framework\TestCase;

class MailSenderTest extends TestCase {
	public function testBothMessagesUseTheAddressFromSmtpLogin(): void {
		$config = $this->createMock(IConfig::class);
		$config->method('getSystemValue')->willReturnMap([['mail_smtpname', '', 'sender@example.org']]);
		$message = $this->createMock(IMessage::class);
		$message->expects(self::exactly(2))->method('setFrom')->with(['sender@example.org']);
		$mailer = $this->createMock(IMailer::class);
		$mailer->method('createMessage')->willReturn($message);
		$mailer->expects(self::exactly(2))->method('send')->with($message)->willReturn([]);
		$service = new MailService($mailer, $config, $this->createStub(IURLGenerator::class), $this->createStub(TokenService::class));
		$service->sendTestMail('recipient@example.org');
		$service->sendReminder('user', 'recipient@example.org', '2026-11-21', 'token');
	}

	public function testInvalidLoginDoesNotSend(): void {
		$config = $this->createStub(IConfig::class);
		$config->method('getSystemValue')->willReturn('username');
		$mailer = $this->createMock(IMailer::class);
		$mailer->method('createMessage')->willReturn($this->createStub(IMessage::class));
		$mailer->expects(self::never())->method('send');
		$service = new MailService($mailer, $config, $this->createStub(IURLGenerator::class), $this->createStub(TokenService::class));
		$this->expectException(\RuntimeException::class);
		$service->sendTestMail('recipient@example.org');
	}
}
