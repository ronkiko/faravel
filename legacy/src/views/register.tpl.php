<div class="login">
  <h1 class="title">[ @title ]</h1>

  <div class="error-message">[ @error ]</div>

  <form method="post" action="/register.php" class="form">
    <div class="group">
      <label for="username">Username</label>
      <input type="text" id="username" name="username" value="[ @username ]" required tabindex="1">
    </div>

    <div class="group">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required tabindex="2">
    </div>

    <div class="group">
      <label for="confirm">Confirm Password</label>
      <input type="password" id="confirm" name="confirm" required tabindex="3">
    </div>

    <input type="hidden" name="csrf_token" value="[ @csrf_token ]">

    <button type="submit" class="button" tabindex="5">Register</button>

    <p class="register-link">
      Already have an account? <a href="/login.php">Login</a>
    </p>
  </form>
</div>
