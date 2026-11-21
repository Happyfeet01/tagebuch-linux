<?php

declare(strict_types=1);

namespace OCA\Tagebuch\Service;

use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\Mail\IMailer;

class MailService {
	public function __construct(
		private IMailer $mailer,
		private IConfig $config,
		private IURLGenerator $urlGenerator,
		private TokenService $tokens,
	) {
	}

	public function configuredSender(): ?string {
		$address = trim((string)$this->config->getSystemValue('mail_smtpname', ''));
		return filter_var($address, FILTER_VALIDATE_EMAIL) !== false ? $address : null;
	}

	private function fromAddress(): string {
		return $this->configuredSender() ?? throw new \RuntimeException(
			'Für den Tagebuchversand muss mail_smtpname in der config.php eine E-Mail-Adresse sein.'
		);
	}

	public function sendReminder(string $userId, string $email, string $date, string $token): void {
		$link = $this->urlGenerator->getAbsoluteURL('/apps/tagebuch/new?t=' . $token);
		$replyTo = $this->tokens->replyAddress($token);
		$subject = 'Tagebuch: Zeit für deinen Eintrag (' . $date . ')';
		$plain = "Hallo $userId,\n\n"
			. "es ist wieder Zeit für deinen Tagebucheintrag am $date.\n\n"
			. "Direkt im Browser öffnen:\n$link\n\n"
			. "Oder antworte einfach auf diese E-Mail an $replyTo – "
			. "deine Antwort wird als Eintrag für den $date gespeichert.\n";
		$html = '<p>Hallo ' . htmlspecialchars($userId, ENT_QUOTES, 'UTF-8') . ',</p>'
			. '<p>es ist wieder Zeit für deinen Tagebucheintrag am <strong>' . htmlspecialchars($date, ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
			. '<p><a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">Eintrag im Browser öffnen</a></p>'
			. '<p>Oder antworte auf diese E-Mail an <code>' . htmlspecialchars($replyTo, ENT_QUOTES, 'UTF-8') . '</code> – '
			. 'die Antwort wird als Eintrag gespeichert.</p>';

		$message = $this->mailer->createMessage();
		$message->setTo([$email]);
		$message->setFrom([$this->fromAddress()]);
		$message->setReplyTo([$replyTo]);
		$message->setSubject($subject);
		$message->setPlainBody($plain);
		$message->setHtmlBody($html);
		if ($this->mailer->send($message) !== []) {
			throw new \RuntimeException('Der Mailserver hat den Empfänger abgelehnt.');
		}
	}

	public function sendTestMail(string $email): void {
		$message = $this->mailer->createMessage();
		$message->setTo([$email]);
		$message->setFrom([$this->fromAddress()]);
		$message->setSubject('Tagebuch: Test-Mail über die Nextcloud-SMTP-Einstellungen');
		$message->setPlainBody("Wenn du das liest, funktioniert der Versand über die in den Nextcloud-Systemeinstellungen konfigurierten Mailserver-Einstellungen.\n");
		if ($this->mailer->send($message) !== []) {
			throw new \RuntimeException('Der Mailserver hat den Empfänger abgelehnt.');
		}
	}
}
