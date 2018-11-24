<?php declare(strict_types=1);

if (!$loader = include __DIR__ . '/../vendor/autoload.php') {
	echo 'Install dependencies using `composer update --dev`';
	exit(1);
}

Tester\Environment::setup();
