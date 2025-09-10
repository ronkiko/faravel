<div class="login">
  <h1 class="title">[ @title ]</h1>

  <div class="error-message">[ @error ]</div>

  <form method="post" action="/login.php" class="form">
    <div class="group">
      <label for="username">Username</label>
      <input type="text" id="username" name="username" value="" required tabindex="1">
    </div>

    <div class="group">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required tabindex="2">
    </div>

    <input type="hidden" name="csrf_token" value="[ @csrf_token ]">

    <button type="submit" class="button" tabindex="3">Login</button>

    <p class="register-link">
      Don't have an account? <a href="/register.php">Register</a>
    </p>
  </form>
</div>
