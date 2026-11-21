<?php

declare(strict_types=1);

use OCP\Util;

$entry = 'tagebuch-main-0-3-12';

Util::addScript(\OCA\Tagebuch\AppInfo\Application::APP_ID, $entry);
Util::addStyle(\OCA\Tagebuch\AppInfo\Application::APP_ID, $entry);

$token = $_['token'] ?? '';
?>

<div id="tagebuch" data-token="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>"></div>
