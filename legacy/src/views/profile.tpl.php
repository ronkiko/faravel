<style>
  html, body {
    margin: 0;
    padding: 0;
  }

  main {
    position: relative;
    margin: 0;
    padding: 0;
  }

  .profile-box {
    max-width: 760px;
    margin: 2rem auto;
    padding: 2rem;
    background-color: var(--background-color);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    font-family: system-ui, sans-serif;
  }

  .profile-box h2 {
    font-size: 1.6rem;
    margin-bottom: 1.5rem;
    color: var(--primary-color);
    border-bottom: 1px solid var(--border-color);
    padding-bottom: 0.3rem;
  }

  .profile-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.96rem;
    color: var(--text-color);
  }

  .profile-table tr {
    border-bottom: 1px solid #e0e0e0;
  }

  .profile-table th {
    text-align: left;
    vertical-align: top;
    width: 30%;
    font-weight: 600;
    padding: 8px 12px;
    color: #333;
    white-space: nowrap;
  }

  .profile-table td {
    padding: 8px 12px;
    vertical-align: top;
    color: #222;
  }

  .profile-table td small {
    display: block;
    margin-top: 4px;
    color: #666;
    font-size: 0.85em;
  }

  .profile-signature {
    white-space: pre-wrap;
    font-style: italic;
    color: #333;
    background-color: #fdfdfd;
    padding: 0.6rem;
    border: 1px dashed #ccc;
    border-radius: 4px;
  }

  /* ❓ Вопросик */
  .info-icon {
    margin-left: 6px;
    text-decoration: none;
    font-weight: bold;
    color: var(--primary-color);
    cursor: pointer;
  }

  /* 🧾 Модальное окно поверх <main>, но не всей страницы */
  .modal-info {
    display: none;
    position: absolute;
    top: 0;
    left: 0;
    z-index: 99;
    width: 100%;
    height: 100%;
    background: rgba(32, 32, 32, 0.6);
  }

  main:has(#info-group:target),
  main:has(#info-role:target) {
    overflow: hidden;
  }

  #info-group:target,
  #info-role:target {
    display: flex;
    justify-content: center;
    align-items: flex-start;
  }

  .modal-content {
    background: #fff;
    border-radius: 6px;
    padding: 1.5rem;
    max-width: 768px;
    width: 90%;
    position: relative;
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
    max-height: 90vh;
    margin-top: 3vh;
    overflow-y: auto;
    font-family: system-ui, sans-serif;
  }

  .modal-content h3 {
    margin-top: 0;
    font-size: 1.2rem;
    border-bottom: 1px solid #ccc;
    padding-bottom: 0.5rem;
    color: var(--primary-color);
  }

  .modal-close {
    position: absolute;
    top: 12px;
    right: 16px;
    font-size: 1.2rem;
    text-decoration: none;
    font-weight: bold;
    color: #999;
  }

  .modal-close:hover {
    color: #333;
  }

  /* 📱 Мобильная версия */
  @media (max-width: 768px) {
    .profile-box {
      margin: 0;
      padding: 1rem 0.8rem;
      background-color: white;
      border: none;
      border-radius: 0;
    }

    .profile-box h2 {
      font-size: 1.3rem;
      margin-bottom: 1rem;
    }

    .profile-table {
      font-size: 0.9rem;
    }

    .profile-table th {
      width: 40%;
      padding: 6px 8px;
    }

    .profile-table td {
      padding: 6px 8px;
    }

    .profile-signature {
      padding: 0.5rem;
      font-size: 0.92rem;
    }

    .modal-content {
      width: 95%;
      height: auto;
      max-height: 90vh;
      border-radius: 0;
    }
  }
</style>

<main>
  <div class="profile-box">
    <h2>👤 User Profile</h2>

    <table class="profile-table">
      <tr>
        <th>Username</th>
        <td>[ @username ]</td>
      </tr>
      <tr>
        <th>Registered</th>
        <td>[ @registered_date ]</td>
      </tr>
      <tr>
        <th>Reputation</th>
        <td>[ @reputation ]</td>
      </tr>
      <tr>
        <th>Group <a href="#info-group" class="info-icon">❓</a></th>
        <td>
          [ @group_name ] ([ @group_id ])
          <small>[ @group_description ]</small>
        </td>
      </tr>
      <tr>
        <th>Role <a href="#info-role" class="info-icon">❓</a></th>
        <td>
          [ @role_label ] ([ @role_id ])
          <small>[ @role_description ]</small>
        </td>
      </tr>
      <tr>
        <th>Last Visit</th>
        <td>[ @last_visit_date ]</td>
      </tr>
      <tr>
        <th>Last Post</th>
        <td>[ @last_post_date ]</td>
      </tr>
      <tr>
        <th>Language</th>
        <td>[ @language ]</td>
      </tr>
      <tr>
        <th>Style</th>
        <td>[ @style_name ]</td>
      </tr>
      <tr>
        <th>Title</th>
        <td>[ @title_text ]</td>
      </tr>
      <tr>
        <th>Signature</th>
        <td><div class="profile-signature">[ @signature_html ]</div></td>
      </tr>
    </table>
  </div>

  <!-- Модальное окно для Group -->
  <div id="info-group" class="modal-info">
    <div class="modal-content">
      <a href="#" class="modal-close">×</a>
      <h3>Information: Group</h3>
      <p>[ @info_text_group ]</p>
    </div>
  </div>

  <!-- Модальное окно для Role -->
  <div id="info-role" class="modal-info">
    <div class="modal-content">
      <a href="#" class="modal-close">×</a>
      <h3>Information: Role</h3>
      <p>[ @info_text_role ]</p>
    </div>
  </div>
</main>
