<?php

declare(strict_types=1);

return [
	'routes' => [
		['name' => 'page#manifest', 'url' => '/manifest.webmanifest', 'verb' => 'GET'],
		['name' => 'page#sw', 'url' => '/sw.js', 'verb' => 'GET'],
	],
];
