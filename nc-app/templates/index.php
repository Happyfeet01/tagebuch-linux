<?php

declare(strict_types=1);

use OCP\Util;

$version = (string)simplexml_load_file(__DIR__ . '/../appinfo/info.xml')->version;
$entry = 'tagebuch-main-' . str_replace('.', '-', $version);

Util::addScript(\OCA\Tagebuch\AppInfo\Application::APP_ID, $entry);
Util::addStyle(\OCA\Tagebuch\AppInfo\Application::APP_ID, $entry);

$token = $_['token'] ?? '';
?>

<div id="tagebuch" data-token="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>"></div>
