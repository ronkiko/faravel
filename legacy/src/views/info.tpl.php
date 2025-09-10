<div class="login">
  <h1 class="title">[ @title ]</h1>

  <div class="info-message">
    <p>[ !message ]</p>

    <div class="centered">
      <p class="username">[ @username ]</p>

      <form method="post" action="/activate.php">
        <input type="hidden" name="csrf_token" value="[ @csrf_token ]">
        <input type="hidden" name="username" value="[ @username ]">
        <input type="hidden" name="id" value="[ @uuid ]">
        <button type="submit" class="button">
          Continue
        </button>
      </form>
    </div>
  </div>
</div>
