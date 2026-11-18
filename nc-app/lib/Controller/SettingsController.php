<?php

declare(strict_types=1);

namespace OCA\Tagebuch\Controller;

use OCA\Tagebuch\AppInfo\Application;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * @psalm-suppress UnusedClass
 */
class SettingsController extends OCSController {
	private const USER_KEYS = ['reminder_enabled', 'theme', 'accent', 'font', 'photo_folder'];

	public function __construct(
		string $appName,
		IRequest $request,
		private IConfig $config,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	private function userId(): string {
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new \RuntimeException('Kein angemeldeter Benutzer');
		}
		return $user->getUID();
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/settings')]
	public function get(): DataResponse {
		$uid = $this->userId();
		$values = [];
		foreach (self::USER_KEYS as $key) {
			$values[$key] = $this->config->getUserValue($uid, Application::APP_ID, $key, '');
		}
		if ($values['theme'] === '') {
			$values['theme'] = 'system';
		}
		$user = $this->userSession->getUser();
		$values['email'] = $user?->getEMailAddress() ?? '';
		return new DataResponse($values);
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/settings')]
	public function set(?string $reminder_enabled = null, ?string $theme = null, ?string $accent = null, ?string $font = null, ?string $photo_folder = null): DataResponse {
		$uid = $this->userId();
		$values = [];
		foreach (['reminder_enabled' => $reminder_enabled, 'theme' => $theme, 'accent' => $accent, 'font' => $font, 'photo_folder' => $photo_folder] as $key => $value) {
			if ($value !== null) {
				$this->config->setUserValue($uid, Application::APP_ID, $key, $value);
				$values[$key] = $value;
			}
		}
		return new DataResponse($values);
	}
}
