<?php

declare(strict_types=1);

use OCP\Util;

$entry = 'tagebuch-main-0-3-18';

Util::addScript(\OCA\Tagebuch\AppInfo\Application::APP_ID, $entry);
Util::addStyle(\OCA\Tagebuch\AppInfo\Application::APP_ID, $entry);

$token = $_['token'] ?? '';
$de = str_starts_with($l->getLanguageCode(), 'de');
?>

<div id="tagebuch" data-token="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>"></div>

<template id="tagebuch-support-template"
    data-url="<?php p(\OCP\Server::get(\OCP\IURLGenerator::class)->linkToRoute('tagebuch.supportNotice.dismiss')); ?>"
    data-token="<?php p($_['requesttoken']); ?>">
    <aside id="tagebuch-support-notice" class="developer-support-notice" hidden aria-labelledby="tagebuch-support-title">
        <strong id="tagebuch-support-title"><?php p($de ? 'Unterstützung des Entwicklers' : 'Support the developer'); ?></strong>
        <p><?php p($de ? 'Dir gefällt die App? Ich freue mich über einen Kaffee.' : 'Enjoying the app? You can buy me a coffee.'); ?></p>
        <p class="support-links">
            <a href="https://buymeacoffee.com/sly5ir9ov" target="_blank" rel="noopener noreferrer">Buy Me a Coffee</a>
            <a href="https://paypal.me/LarsM1980" target="_blank" rel="noopener noreferrer">PayPal</a>
            <a href="https://ko-fi.com/dermueller" target="_blank" rel="noopener noreferrer">Ko-fi</a>
        </p>
        <button type="button" data-duration="session"><?php p($de ? 'Schließen' : 'Close'); ?></button>
        <button type="button" data-duration="permanent"><?php p($de ? 'Nicht wieder anzeigen' : 'Don’t show again'); ?></button>
        <p role="status" hidden><?php p($de ? 'Speichern fehlgeschlagen. Bitte erneut versuchen.' : 'Could not save. Please try again.'); ?></p>
    </aside>
</template>
