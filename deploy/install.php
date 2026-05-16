<?php
/**
 * BizSpine first-run web installer (no SSH or Composer required on the server).
 * Place in public_html/BizSpine/install.php — included in the release ZIP.
 */
declare(strict_types=1);

$webRoot = __DIR__;
$backendRoot = null;
$error = null;
$success = null;
$step = 'form';

try {
    $home = dirname($webRoot, 2);
    $candidate = $home . DIRECTORY_SEPARATOR . 'bizspine-backend';
    if (is_file($candidate . '/public/index.php')) {
        $backendRoot = $candidate;
    }
} catch (Throwable) {
    // fall through
}

$autoload = $backendRoot ? $backendRoot . '/vendor/autoload.php' : '';
if ($backendRoot && is_file($autoload)) {
    require $autoload;
    require $backendRoot . '/tools/install_lib.php';
    require $backendRoot . '/tools/seed_demo_data.php';
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function install_detect_site_origin(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '/install.php';
    $base = rtrim(str_replace('\\', '/', dirname($script)), '/');

    return $scheme . '://' . $host . $base;
}

$requirements = [
    'php' => version_compare(PHP_VERSION, '8.0.0', '>='),
    'pdo_sqlite' => extension_loaded('pdo_sqlite'),
    'mbstring' => extension_loaded('mbstring'),
    'json' => extension_loaded('json'),
    'backend' => $backendRoot !== null && is_file($autoload),
];

$allRequirementsMet = !in_array(false, $requirements, true);

if ($backendRoot && $allRequirementsMet) {
    try {
        $config = bizspine_load_config($backendRoot);
        $dbPath = bizspine_resolve_db_path($config, $backendRoot);
        $pdo = is_file($dbPath) ? bizspine_connect($dbPath) : null;
        if (bizspine_is_installed($webRoot, $pdo)) {
            $step = 'done';
            $reasons = [];
            if (is_file(bizspine_installed_lock_path($webRoot))) {
                $reasons[] = 'install lock file (.bizspine-installed) exists';
            }
            if ($pdo !== null && bizspine_database_has_users($pdo)) {
                $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
                $reasons[] = "database already has {$count} user(s) at " . basename($dbPath);
            }
            $success = 'BizSpine is already installed'
                . ($reasons ? ' (' . implode('; ', $reasons) . ').' : '.')
                . ' To run setup again, delete protected/db/database.sqlite in bizspine-backend'
                . ' and .bizspine-installed in this folder, then refresh.';
        }
    } catch (Throwable $e) {
        // fresh install — database may not exist yet
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'form' && $allRequirementsMet && $backendRoot) {
    $siteOrigin = rtrim(trim($_POST['site_origin'] ?? ''), '/');
    $storeName = trim($_POST['store_name'] ?? 'My Store');
    $adminEmail = trim($_POST['admin_email'] ?? '');
    $adminPassword = (string) ($_POST['admin_password'] ?? '');
    $loadDemo = isset($_POST['load_demo']);
    $adminName = trim($_POST['admin_name'] ?? 'Site Administrator');

    if ($siteOrigin === '' || !filter_var($siteOrigin, FILTER_VALIDATE_URL)) {
        $error = 'Enter a valid site URL (e.g. https://yourdomain.com/BizSpine).';
    } elseif (!$loadDemo && ($adminEmail === '' || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL))) {
        $error = 'Enter a valid administrator email.';
    } elseif (!$loadDemo && strlen($adminPassword) < 8) {
        $error = 'Administrator password must be at least 8 characters.';
    } elseif ($storeName === '') {
        $error = 'Store name is required.';
    } else {
        try {
            $writableWarnings = bizspine_ensure_writable_paths($backendRoot);
            if ($writableWarnings !== []) {
                throw new RuntimeException(
                    'These folders must be writable by PHP: ' . implode(', ', $writableWarnings)
                );
            }

            $config = bizspine_load_config($backendRoot);
            $dbPath = bizspine_resolve_db_path($config, $backendRoot);

            if (!is_file($dbPath)) {
                bizspine_run_migrations($backendRoot);
            } elseif (!bizspine_database_has_users(bizspine_connect($dbPath))) {
                bizspine_run_migrations($backendRoot);
            }

            $pdo = bizspine_connect(bizspine_resolve_db_path(bizspine_load_config($backendRoot), $backendRoot));

            if (bizspine_database_has_users($pdo)) {
                throw new RuntimeException('Database already has users. Installation was already completed.');
            }

            if ($loadDemo) {
                bizspine_seed_demo_database($pdo);
                bizspine_apply_settings_updates($pdo, ['store_name' => $storeName]);
            } else {
                bizspine_create_admin_user($pdo, $adminEmail, $adminPassword, $adminName);
                bizspine_apply_settings_updates($pdo, [
                    'store_name' => $storeName,
                    'store_email' => $adminEmail,
                ]);
            }

            $jwtSecret = bizspine_generate_secret();
            bizspine_write_env_file($backendRoot, $jwtSecret);
            bizspine_write_install_local_config($backendRoot, $siteOrigin, $storeName);

            bizspine_mark_installed($webRoot, [
                'site_origin' => $siteOrigin,
                'demo_data' => $loadDemo,
            ]);

            $step = 'complete';
            $success = $loadDemo
                ? 'Installation complete with demo data. Sign in with admin@bizspine.example / ' . BIZSPINE_DEMO_PASSWORD
                : 'Installation complete. Sign in with your administrator account.';
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$defaultOrigin = install_detect_site_origin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>BizSpine Setup</title>
  <style>
    :root { --bg: #0f1419; --card: #1a2332; --text: #e8eef4; --muted: #8fa3b8; --accent: #3d9cf5; --ok: #3ecf8e; --err: #f07178; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: system-ui, sans-serif; background: var(--bg); color: var(--text); line-height: 1.5; }
    .wrap { max-width: 520px; margin: 2rem auto; padding: 0 1rem; }
    h1 { font-size: 1.5rem; margin: 0 0 0.25rem; }
    .sub { color: var(--muted); margin-bottom: 1.5rem; }
    .card { background: var(--card); border-radius: 12px; padding: 1.5rem; margin-bottom: 1rem; }
    label { display: block; font-size: 0.85rem; color: var(--muted); margin: 1rem 0 0.35rem; }
    input[type=text], input[type=email], input[type=password], input[type=url] {
      width: 100%; padding: 0.6rem 0.75rem; border: 1px solid #2d3d52; border-radius: 8px;
      background: #0f1419; color: var(--text); font-size: 1rem;
    }
    .check { display: flex; align-items: center; gap: 0.5rem; margin-top: 1rem; }
    .check input { width: auto; }
    button {
      margin-top: 1.25rem; width: 100%; padding: 0.75rem; border: 0; border-radius: 8px;
      background: var(--accent); color: #fff; font-size: 1rem; font-weight: 600; cursor: pointer;
    }
    button:disabled { opacity: 0.5; cursor: not-allowed; }
    .msg-ok { color: var(--ok); }
    .msg-err { color: var(--err); }
    ul.req { list-style: none; padding: 0; margin: 0; }
    ul.req li::before { content: "✓ "; color: var(--ok); }
    ul.req li.bad::before { content: "✗ "; color: var(--err); }
    a { color: var(--accent); }
    code { background: #0f1419; padding: 0.1rem 0.35rem; border-radius: 4px; font-size: 0.9em; }
  </style>
</head>
<body>
  <div class="wrap">
    <h1>BizSpine Setup</h1>
    <p class="sub">Browser-based installer for shared hosting (no SSH required).</p>

    <?php if ($step === 'done'): ?>
      <div class="card">
        <p class="msg-ok"><?= h($success) ?></p>
        <p><a href="./">Open your store</a> · <a href="./admin">Admin dashboard</a></p>
      </div>
    <?php elseif ($step === 'complete'): ?>
      <div class="card">
        <p class="msg-ok"><?= h($success) ?></p>
        <p><strong>Important:</strong> Delete <code>install.php</code> from your server now that setup is finished.</p>
        <p><a href="./">Open your store</a> · <a href="./admin">Admin dashboard</a></p>
        <?php if (!empty($_POST['load_demo'])): ?>
          <p class="sub">Demo logins: admin@bizspine.example, staff@bizspine.example — password <code><?= h(BIZSPINE_DEMO_PASSWORD) ?></code></p>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="card">
        <h2 style="margin:0 0 0.75rem;font-size:1rem;">Server checks</h2>
        <ul class="req">
          <li class="<?= $requirements['php'] ? '' : 'bad' ?>">PHP 8.0+ (<?= h(PHP_VERSION) ?>)</li>
          <li class="<?= $requirements['pdo_sqlite'] ? '' : 'bad' ?>">PDO SQLite</li>
          <li class="<?= $requirements['mbstring'] ? '' : 'bad' ?>">mbstring</li>
          <li class="<?= $requirements['json'] ? '' : 'bad' ?>">json</li>
          <li class="<?= $requirements['backend'] ? '' : 'bad' ?>">bizspine-backend folder (with vendor/)</li>
        </ul>
        <?php if (!$requirements['backend']): ?>
          <p class="msg-err" style="margin-top:1rem;">Upload the <code>bizspine-backend</code> folder from the ZIP next to <code>public_html</code>, then refresh.</p>
        <?php endif; ?>
      </div>

      <?php if ($error): ?>
        <p class="msg-err"><?= h($error) ?></p>
      <?php endif; ?>

      <form method="post" class="card">
        <label for="site_origin">Your store URL</label>
        <input type="url" id="site_origin" name="site_origin" required
               value="<?= h($_POST['site_origin'] ?? $defaultOrigin) ?>"
               placeholder="https://yourdomain.com/BizSpine">

        <label for="store_name">Store name</label>
        <input type="text" id="store_name" name="store_name" required
               value="<?= h($_POST['store_name'] ?? 'My Store') ?>">

        <label for="admin_email">Administrator email</label>
        <input type="email" id="admin_email" name="admin_email" required
               value="<?= h($_POST['admin_email'] ?? '') ?>">

        <label for="admin_name">Administrator display name</label>
        <input type="text" id="admin_name" name="admin_name"
               value="<?= h($_POST['admin_name'] ?? 'Site Administrator') ?>">

        <label for="admin_password">Administrator password</label>
        <input type="password" id="admin_password" name="admin_password" required minlength="8"
               autocomplete="new-password">

        <label class="check">
          <input type="checkbox" name="load_demo" value="1"
            <?= isset($_POST['load_demo']) ? 'checked' : '' ?>>
          Load demo products, orders, and sample accounts
        </label>

        <button type="submit" <?= $allRequirementsMet ? '' : 'disabled' ?>>Install BizSpine</button>
      </form>

      <p class="sub">See <a href="INSTALL.html">INSTALL.html</a> for upload steps.</p>
    <?php endif; ?>
  </div>
</body>
</html>
