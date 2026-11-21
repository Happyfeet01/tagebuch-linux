<?php

declare(strict_types=1);

namespace OCA\Tagebuch\Controller;

use OCA\Tagebuch\AppInfo\Application;
use OCA\Tagebuch\Service\MailService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * @psalm-suppress UnusedClass
 */
class AdminController extends OCSController {
	private const MAIL_KEYS = ['imap_host', 'imap_port', 'imap_ssl', 'imap_user', 'imap_password'];

	public function __construct(
		string $appName,
		IRequest $request,
		private IConfig $config,
		private IUserSession $userSession,
		private MailService $mailService,
	) {
		parent::__construct($appName, $request);
	}

	#[ApiRoute(verb: 'GET', url: '/api/admin/mail')]
	public function getMail(): DataResponse {
		$values = [];
		foreach (self::MAIL_KEYS as $key) {
			$value = $this->config->getAppValue(Application::APP_ID, $key, '');
			$values[$key] = $key === 'imap_password' ? ($value !== '' ? '********' : '') : $value;
		}
		$values['smtp_from'] = $this->mailService->configuredSender() ?? '';
		return new DataResponse($values);
	}

	#[ApiRoute(verb: 'POST', url: '/api/admin/mail')]
	public function setMail(
		?string $imap_host = null,
		?string $imap_port = null,
		?string $imap_ssl = null,
		?string $imap_user = null,
		?string $imap_password = null,
	): DataResponse {
		foreach (['imap_host' => $imap_host, 'imap_port' => $imap_port, 'imap_ssl' => $imap_ssl, 'imap_user' => $imap_user, 'imap_password' => $imap_password] as $key => $value) {
			if ($value !== null && $value !== '' && $value !== '********') {
				$this->config->setAppValue(Application::APP_ID, $key, $value);
			}
		}
		return new DataResponse(['ok' => true]);
	}

	#[ApiRoute(verb: 'POST', url: '/api/admin/mail/test')]
	public function testMail(): DataResponse {
		$user = $this->userSession->getUser();
		$email = $user?->getEMailAddress();
		if ($email === null || $email === '') {
			return new DataResponse(['error' => 'Keine E-Mail-Adresse am NC-Benutzer hinterlegt'], Http::STATUS_BAD_REQUEST);
		}
		try {
			$this->mailService->sendTestMail($email);
			return new DataResponse(['ok' => true, 'to' => $email]);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
