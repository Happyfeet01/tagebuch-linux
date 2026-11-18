<?php

declare(strict_types=1);

use OCP\Util;

Util::addScript(\OCA\Tagebuch\AppInfo\Application::APP_ID, 'tagebuch-main');
Util::addStyle(\OCA\Tagebuch\AppInfo\Application::APP_ID, 'tagebuch-main');

$token = $_['token'] ?? '';
?>

<div id="tagebuch" data-token="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>"></div>
