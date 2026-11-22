<?php

declare(strict_types=1);

return [
	'ocs' => [
		['name' => 'cors#preflight', 'url' => '/api/{path}', 'verb' => 'OPTIONS', 'requirements' => ['path' => '.+']],
	],
	'routes' => [
		['name' => 'page#manifest', 'url' => '/manifest.webmanifest', 'verb' => 'GET'],
		['name' => 'page#sw', 'url' => '/sw.js', 'verb' => 'GET'],
	],
];
