<header class="site-header">
  <div class="nav-container">
    <input type="checkbox" id="menu-toggle" class="menu-toggle" />
    <label for="menu-toggle" class="menu-icon"><span></span></label>

    <nav class="nav-left menu">
      <a href="/">Home</a>
      <a href="/forum_index.php">Forum</a>
      <?php if (IS_LOGGED_IN): ?>
        <a href="/profile.php">Profile</a>
      <?php endif; ?>
      <a href="/search.php">Search</a>
    </nav>

    <div class="nav-right">
      <?php if (IS_LOGGED_IN): ?>
        <a href="/logout.php?csrf_token=<?= csrf_token(); ?>" class="logout-link">Logout</a>
      <?php else: ?>
        <a href="/login.php">Login</a>
      <?php endif; ?>
    </div>
  </div>
</header>
