<?php #index.php
require __DIR__ . '/bootstrap.php';

$content = '';
for ($i = 1; $i <= 100; $i++) {
    $content .= "<p>Hello world line $i</p>";
}

draw('Test Page', $content);
