<div class="login">
  <h1 class="title">[ @title ]</h1>

  <div class="info-message">
    <p>[ !message ]</p>

    <div class="centered" style="margin-top: 1.5em">
      <form method="post" action="/activate.php">
        <input type="hidden" name="csrf_token" value="[ @csrf_token ]">
        <input type="hidden" name="confirm" value="1">
        <button type="submit" class="button">
          Confirm activation
        </button>
      </form>
    </div>
  </div>
</div>
