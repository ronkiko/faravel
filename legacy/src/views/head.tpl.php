<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= isset($title) ? htmlspecialchars($title) : 'Forum' ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php
    $styles = get_styles();
    foreach ($styles as $style) {
        echo '<link rel="stylesheet" href="/style/' . htmlspecialchars($style) . '.css">'.PHP_EOL;
    }
  ?>
</head>

<body>
<div class="page-wrapper">
  <div class="site-logo">
    <img src="/style/logo.png" alt="Site Logo" />
  </div>
