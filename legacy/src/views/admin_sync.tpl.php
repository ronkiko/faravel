<style>
/* Всплывающее уведомление в admin.css */
</style>

<div id="sync_started" class="notify-message">Synchronization started</div>
<div id="process_started" class="notify-message">Processing started</div>
<div id="mainlog_cleared" class="notify-message">Main log cleared</div>
<div id="errorlog_cleared" class="notify-message">Error log cleared</div>


<style>
/* Основной стиль */
.admin-panel {
  display: flex;
  min-height: 80vh;
  font-family: sans-serif;
  border-top: 1px solid #ccc;
  flex-direction: row;
}

.admin-tabs {
  width: 200px;
  border-right: 1px solid #ccc;
  padding: 1rem;
  background: #f9f9f9;
  box-sizing: border-box;
}

.admin-tabs label {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px;
  margin-bottom: 6px;
  background: #eaeaea;
  border: 1px solid #ccc;
  cursor: pointer;
  border-radius: 4px;
  font-weight: 500;
  text-align: left;
  white-space: nowrap;
}

.admin-tabs label:hover {
  background: #ddd;
}

.admin-content {
  flex: 1;
  padding: 1rem 1.5rem;
  box-sizing: border-box;
}

input[name="tab"] {
  display: none;
}
.tab-content {
  display: none;
}
input[name="tab"]:checked + .tab-content {
  display: block;
}

.page-title {
  font-size: 1.6em;
  font-weight: bold;
  margin: 1rem 2rem 0.5rem;
  display: flex;
  align-items: center;
}

/* Кнопка очистки и синхронизации */
.clear-icon, .sync-icon {
  background: none;
  border: none;
  cursor: pointer;
  font-size: 1rem;
  margin-left: 8px;
  position: relative;
  padding: 0;
}

.clear-icon:hover::after, .sync-icon:hover::after {
  content: attr(data-tooltip);
  position: absolute;
  top: 110%;
  left: 50%;
  transform: translateX(-50%);
  background: #444;
  color: #fff;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 0.75rem;
  white-space: nowrap;
  z-index: 999;
}

.log-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

/* Мобильная версия */
@media (max-width: 600px) {
  .admin-panel {
    flex-direction: column;
  }

  .admin-tabs {
    width: 100%;
    display: flex;
    flex-direction: row;
    justify-content: space-around;
    border-right: none;
    border-bottom: 1px solid #ccc;
    position: sticky;
    top: 58px;
    background: #f9f9f9;
    z-index: 10;
    padding: 0.5rem 0;
  }

  .admin-tabs label {
    margin: 0 4px;
    padding: 8px;
    font-size: 1.4rem;
    background: none;
    border: none;
    flex-direction: column;
    justify-content: center;
    position: relative;
  }

  .admin-tabs label span {
    display: none;
  }

  .admin-tabs label::after {
    content: attr(data-tooltip);
    position: absolute;
    bottom: -22px;
    left: 50%;
    transform: translateX(-50%);
    background: #333;
    color: #fff;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.75rem;
    white-space: nowrap;
    opacity: 0;
    transition: opacity 0.2s ease;
    pointer-events: none;
  }

  .admin-tabs label:hover::after {
    opacity: 1;
  }

  .log-header {
    flex-direction: row;
    justify-content: space-between;
    align-items: center;
  }
}
</style>

<h1 class="page-title">
  Admin: Sync
  <form method="post" style="display:inline">
    <input type="hidden" name="csrf_token" value="[ @csrf_token ]">
    <button name="trigger_outbound" class="sync-icon" data-tooltip="Sync now!">🔄</button>
  </form>

  <form method="post" style="display:inline">
    <input type="hidden" name="csrf_token" value="[ @csrf_token ]">
    <button name="trigger_process" class="sync-icon" data-tooltip="Process Inbound">🚀</button>
  </form>

  <form method="post" style="display:inline">
    <input type="hidden" name="csrf_token" value="[ @csrf_token ]">
    <button name="trigger_scheduler" class="sync-icon" data-tooltip="Scheduler">🔧</button>
  </form>
</h1>

<div class="admin-panel">
  <div class="admin-tabs">
    <label for="tab1" data-tooltip="Inbound">📥 <span>Inbound</span></label>
    <label for="tab2" data-tooltip="Outbound">📤 <span>Outbound</span></label>
    <label for="tab3" data-tooltip="Main Log">📜 <span>Main Log</span></label>
    <label for="tab4" data-tooltip="Error Log">❌ <span>Error Log</span></label>
  </div>

  <div class="admin-content">
    <input type="radio" name="tab" id="tab1" checked>
    <div class="tab-content">
      <h2>📥 Inbound Events</h2>
      [ !inbound_html ]
    </div>

    <input type="radio" name="tab" id="tab2">
    <div class="tab-content">
      <h2>📤 Outbound Events</h2>
      [ !outbound_html ]
    </div>

    <input type="radio" name="tab" id="tab3">
    <div class="tab-content">
      <div class="log-header">
        <h2>📜 Main Log
          <form method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="[ @csrf_token ]">
            <button name="clear_main" class="clear-icon" data-tooltip="Clear Log">🧹</button>
          </form>
        </h2>
      </div>
      <pre style="background:#f8f8f8;padding:10px;border:1px solid #ccc;overflow:auto;height:300px">
[ !logs_main ]
      </pre>
    </div>

    <input type="radio" name="tab" id="tab4">
    <div class="tab-content">
      <div class="log-header">
        <h2>❌ Error Log
          <form method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="[ @csrf_token ]">
            <button name="clear_errors" class="clear-icon" data-tooltip="Clear Log">🧹</button>
          </form>
        </h2>
      </div>
      <pre style="background:#fff0f0;padding:10px;border:1px solid #f00;overflow:auto;height:300px">
[ !logs_errors ]
      </pre>
    </div>
  </div>
</div>
