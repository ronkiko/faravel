<?php // v0.5.1
/* public/install/index.php
Unified installer working through Faravel kernel.
*/

use Faravel\Support\Facades\DB;
use App\Console\Migrations\MigrationRunner;
use App\Console\Seeders\SeederRunner;

// Bootstrap application
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = new App\Http\Kernel($app);

session_start();

$step = $_GET['step'] ?? '1';

// ================== STEP 1: CONFIG FORM ==================
if ($step === '1') {
    $defaults = [
        'host' => 'mysql',
        'port' => '3306',
        'name' => 'forum',
        'user' => 'user',
        'pass' => '',
    ];
    $db = $_SESSION['db'] ?? $defaults;
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Installation — Step 1</title>
        <style>
            body { font-family: sans-serif; margin: 2em; }
            .container { max-width: 600px; margin:auto; }
            label { display:block; margin:8px 0; }
            input[type=text], input[type=password] { width:100%; padding:6px; }
            button { padding:8px 20px; margin-top:10px; }
        </style>
    </head>
    <body>
    <div class="container">
        <h1>Step 1: Configuration</h1>
        <form method="post" action="?step=2">
            <fieldset>
                <legend>Database Settings</legend>
                <label>Host: <input type="text" name="db_host" value="<?= htmlspecialchars($db['host']) ?>" required></label>
                <label>Port: <input type="text" name="db_port" value="<?= htmlspecialchars($db['port']) ?>" required></label>
                <label>Database: <input type="text" name="db_name" value="<?= htmlspecialchars($db['name']) ?>" required></label>
                <label>User: <input type="text" name="db_user" value="<?= htmlspecialchars($db['user']) ?>" required></label>
                <label>Password: <input type="password" name="db_pass" value="<?= htmlspecialchars($db['pass']) ?>"></label>
            </fieldset>
            <fieldset>
                <legend>Other Settings</legend>
                <p>(Reserved for future configuration options)</p>
            </fieldset>
            <button type="submit">Continue</button>
        </form>
    </div>
    </body>
    </html>
    <?php
    exit;
}

// ================== STEP 2: CREATE OR DROP DB ==================
if ($step === '2' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['db'] = [
        'host' => trim($_POST['db_host']),
        'port' => trim($_POST['db_port']),
        'name' => trim($_POST['db_name']),
        'user' => trim($_POST['db_user']),
        'pass' => trim($_POST['db_pass']),
    ];
    $dbConf = $_SESSION['db'];

    try {
        // Connect without selecting database
        $pdo = new PDO(
            "mysql:host={$dbConf['host']};port={$dbConf['port']}",
            $dbConf['user'],
            $dbConf['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $exists = $pdo->query("SHOW DATABASES LIKE " . $pdo->quote($dbConf['name']))->fetch();

        if ($exists && empty($_POST['force'])) {
            ?>
            <h1>Step 2: Database already exists</h1>
            <p>Database <b><?= htmlspecialchars($dbConf['name']) ?></b> already exists. Do you want to drop it and reinstall?</p>
            <form method="post" action="?step=2">
                <input type="hidden" name="db_host" value="<?= htmlspecialchars($dbConf['host']) ?>">
                <input type="hidden" name="db_port" value="<?= htmlspecialchars($dbConf['port']) ?>">
                <input type="hidden" name="db_name" value="<?= htmlspecialchars($dbConf['name']) ?>">
                <input type="hidden" name="db_user" value="<?= htmlspecialchars($dbConf['user']) ?>">
                <input type="hidden" name="db_pass" value="<?= htmlspecialchars($dbConf['pass']) ?>">
                <button type="submit" name="force" value="1">Yes, drop and reinstall</button>
                <a href="?step=1">← Back</a>
            </form>
            <?php
            exit;
        }

        if ($exists && !empty($_POST['force'])) {
            $pdo->exec("DROP DATABASE `{$dbConf['name']}`");
        }

        if (!$exists || !empty($_POST['force'])) {
            $pdo->exec("CREATE DATABASE `{$dbConf['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }

        // Now reconnect Faravel DB to the new database
        DB::reconnect([
            'driver'   => 'mysql',
            'host'     => $dbConf['host'],
            'port'     => $dbConf['port'],
            'database' => $dbConf['name'],
            'username' => $dbConf['user'],
            'password' => $dbConf['pass'],
            'charset'  => 'utf8mb4',
            'collation'=> 'utf8mb4_unicode_ci',
        ]);

        // Run migrations
        $migrator = new MigrationRunner(DB::getFacadeRoot(), __DIR__ . '/../../database/migrations');
        $migrator->migrate();

        // Run seeders
        $seeder = new SeederRunner(DB::getFacadeRoot(), __DIR__ . '/../../database/seeders');
        $seeder->seed();

        header('Location: ?step=3');
        exit;

    } catch (Throwable $e) {
        ?>
        <h1>Installation error</h1>
        <p style="color:red"><?= htmlspecialchars($e->getMessage()) ?></p>
        <p><a href="?step=1">← Back to configuration</a></p>
        <?php
        exit;
    }
}

// ================== STEP 3: FINISH ==================
if ($step === '3') {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head><meta charset="UTF-8"><title>Installation — Finished</title></head>
    <body>
        <h1>Step 3: Finished</h1>
        <p>🎉 Installation completed successfully!</p>
        <p><a href="/index.php">Go to the main page</a></p>
        <p><b>Important:</b> Remove the <code>public/install/</code> directory manually.</p>
    </body>
    </html>
    <?php
    exit;
}
