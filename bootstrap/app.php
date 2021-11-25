<?php

declare(strict_types=1);

require_once "./vendor/autoload.php";

use Astaroth\Foundation\Application;

$app = new Application();
$app->run(dirname(__DIR__));