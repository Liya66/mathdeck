<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$container = require __DIR__ . '/../config/container.php';

MathDeck\Http\AppFactory::create($container, debug: getenv('APP_DEBUG') === '1')->run();
