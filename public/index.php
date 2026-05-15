<?php
declare(strict_types=1);
define('ROOT_PATH', dirname(__DIR__));
define('PUBLIC_PATH', __DIR__);

require ROOT_PATH . '/vendor/autoload.php';

use App\Core\App;

$app = new App();
$app->run();
