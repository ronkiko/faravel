<?php
require __DIR__."/vendor/autoload.php";
require __DIR__."/framework/Faravel/helpers.php";
require __DIR__."/public/index.php"; // index сам подгрузит конфиги
use Faravel\Support\Config;
echo json_encode(Config::get("database", []), JSON_PRETTY_PRINT), PHP_EOL;
