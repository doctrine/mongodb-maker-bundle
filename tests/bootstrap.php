<?php

declare(strict_types=1);

use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\Filesystem\Filesystem;

ErrorHandler::register(null, false);

(new Filesystem())->remove(__DIR__ . '/tmp/cache');
