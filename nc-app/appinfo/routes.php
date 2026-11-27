<?php

declare(strict_types=1);

return [
	'routes' => [
    ['name' => 'supportNotice#status', 'url' => '/support-notice', 'verb' => 'GET'],
    ['name' => 'supportNotice#dismiss', 'url' => '/support-notice', 'verb' => 'POST'],
		['name' => 'page#manifest', 'url' => '/manifest.webmanifest', 'verb' => 'GET'],
		['name' => 'page#sw', 'url' => '/sw.js', 'verb' => 'GET'],
	],
];
