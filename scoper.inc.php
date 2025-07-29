<?php

declare(strict_types=1);

use Isolated\Symfony\Component\Finder\Finder;

return [
	'prefix' => 'FirstElement\\SwiftIndex',
	'finders' => [
		Finder::create()
			->files()
			->in('vendor')
			->name('*.php')
	],
	'patchers' => [
	],
	'exclude-namespaces' => [
		'Composer',
		'Psr\\Log',
		'Psr\\Http\\Message',
		'Psr\\Http\\Client',
		'Psr\\Cache',
	],
	'expose-global-constants' => true,
	'expose-global-classes'   => true,
	'expose-global-functions' => true,
];
