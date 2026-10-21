<?php
/**
 * ChatDesk browser installer for shared hosting (no SSH, Composer or Node needed).
 * Published by Yoorie (https://yoorie.com). Support: support@yoorie.com.
 * Steps: requirements -> database -> install -> admin -> done.
 * After a successful install it writes storage/installed and refuses to run again.
 */
declare(strict_types=1);

$root = realpath(__DIR__.'/../..');
$cfg = require __DIR__.'/config.php';
$lock = $root.'/storage/installed';
session_name('app_installer');
session_start();

function h($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function base_url(): string {
    $https = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/install/index.php'))), '/');
    $path = preg_replace('#/public$#', '', $path);
    return ($https ? 'https' : 'http').'://'.$host.$path;
}
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(16)); }
function check_csrf(): void { if (! hash_equals($_SESSION['csrf'] ?? '', (string) ($_POST['_token'] ?? ''))) { http_response_code(419); exit('Session expired. Reload the page.'); } }
function env_value(string $v): string {
    if ($v === '') return '';
    return preg_match('/^[A-Za-z0-9_.:\/@-]+$/', $v) ? $v : '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $v).'"';
}
function write_env(string $root, array $values): void {
    $file = $root.'/.env';
    $tpl = is_file($file) ? file_get_contents($file) : file_get_contents($root.'/.env.example');
    foreach ($values as $k => $v) {
        $line = $k.'='.env_value((string) $v);
        $tpl = preg_match('/^#?\s*'.preg_quote($k, '/').'=.*$/m', $tpl)
            ? preg_replace('/^#?\s*'.preg_quote($k, '/').'=.*$/m', str_replace(['\\', '$'], ['\\\\', '\\$'], $line), $tpl, 1)
            : rtrim($tpl)."\n".$line."\n";
    }
    if (file_put_contents($file, $tpl) === false) throw new RuntimeException('Could not write .env. Make the project folder writable.');
    @chmod($file, 0640);
}
function boot_laravel(string $root) {
    @unlink($root.'/bootstrap/cache/config.php');
    require_once $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    return $app;
}

if (is_file($lock)) {
    $step = (! empty($_SESSION['done_email']) && ($_GET['step'] ?? '') === 'done') ? 'done' : 'locked';
} else {
    $step = $_GET['step'] ?? 'requirements';
    if ($step === 'done') $step = 'requirements';
}
$errors = [];
$steps = ['requirements' => 'Server', 'database' => 'Database', 'admin' => 'Admin', 'done' => 'Done'];

// ---------- Requirements ----------
foreach (['storage/app/public', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache'] as $d) {
    if (! is_dir($root.'/'.$d)) @mkdir($root.'/'.$d, 0775, true);
}
$exts = ['pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json', 'bcmath', 'curl', 'fileinfo', 'gd', 'zip', 'intl', 'sodium'];
$checks = [['PHP '.$cfg['min_php'].'+ (you have '.PHP_VERSION.')', version_compare(PHP_VERSION, $cfg['min_php'], '>=')]];
foreach ($exts as $e) $checks[] = ['PHP extension: '.$e, extension_loaded($e)];
$writable = ['storage', 'storage/app', 'storage/framework', 'storage/logs', 'bootstrap/cache', '.env or project root'];
$perm = [];
foreach ($writable as $w) {
    $p = $w === '.env or project root' ? (is_link($root.'/.env') ? dirname((string) readlink($root.'/.env')) : (is_file($root.'/.env') ? $root.'/.env' : $root)) : $root.'/'.$w;
    $perm[] = [$w, is_writable($p)];
}
$vendorOk = is_file($root.'/vendor/autoload.php');
$buildOk = is_file($root.'/public/build/manifest.json');
$checks[] = ['Bundled vendor/ folder present', $vendorOk];
$checks[] = ['Prebuilt assets (public/build) present', $buildOk];
$reqOk = ! in_array(false, array_column($checks, 1), true) && ! in_array(false, array_column($perm, 1), true);
$next = fn (string $cur) => array_keys($steps)[array_search($cur, array_keys($steps), true) + 1] ?? 'done';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step !== 'locked') {
    check_csrf();
    try {
        if ($step === 'requirements') {
            if (! $reqOk) throw new RuntimeException('Fix the items marked in red first.');
            $_SESSION['req_ok'] = true;
            header('Location: ?step='.$next('requirements')); exit;
        }
        if ($step === 'database') {
            if (empty($_SESSION['req_ok'])) { header('Location: ?step=requirements'); exit; }
            $db = ['host' => trim($_POST['db_host'] ?? 'localhost'), 'port' => (int) ($_POST['db_port'] ?? 3306) ?: 3306,
                'name' => trim($_POST['db_name'] ?? ''), 'user' => trim($_POST['db_user'] ?? ''), 'pass' => (string) ($_POST['db_pass'] ?? '')];
            $_SESSION['db_form'] = array_diff_key($db, ['pass' => 1]);
            if ($db['name'] === '' || $db['user'] === '') throw new RuntimeException('Database name and user are required.');
            $connect = fn (string $host) => new PDO("mysql:host={$host};port={$db['port']};dbname={$db['name']};charset=utf8mb4", $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 8]);
            try {
                $connect($db['host'])->query('SELECT 1');
            } catch (PDOException $e) {
                // "localhost" uses the socket; some hosts only answer on TCP.
                if ($db['host'] !== 'localhost') throw new RuntimeException('Could not connect: check host, name, user and password.');
                try { $connect('127.0.0.1')->query('SELECT 1'); $db['host'] = '127.0.0.1'; }
                catch (PDOException $e2) { throw new RuntimeException('Could not connect: check host, name, user and password.'); }
            }
            write_env($root, [
                'APP_NAME' => $cfg['product_name'], 'APP_ENV' => 'production', 'APP_DEBUG' => 'false',
                'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)), 'APP_URL' => base_url(), 'LOG_LEVEL' => 'warning',
                'DB_CONNECTION' => 'mysql', 'DB_HOST' => $db['host'], 'DB_PORT' => (string) $db['port'],
                'DB_DATABASE' => $db['name'], 'DB_USERNAME' => $db['user'], 'DB_PASSWORD' => $db['pass'],
                'QUEUE_CONNECTION' => 'database', 'CACHE_STORE' => 'database', 'SESSION_DRIVER' => 'database',
                'BROADCAST_CONNECTION' => 'log', 'FILESYSTEM_DISK' => 'local', 'MAIL_MAILER' => 'log',
            ]);
            // Run migrations + base seed in-process (no shell access needed).
            @set_time_limit(600);
            $app = boot_laravel($root);
            $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
            $code = $kernel->call('migrate', ['--force' => true]);
            if ($code !== 0) throw new RuntimeException('Migration failed: '.substr($kernel->output(), -600));
            $kernel->call('db:seed', ['--force' => true]);
            if (! empty($_POST['demo'])) $kernel->call('db:seed', ['--class' => 'Database\\Seeders\\DemoSeeder', '--force' => true]);
            $_SESSION['db_ok'] = true;
            header('Location: ?step=admin'); exit;
        }
        if ($step === 'admin') {
            if (empty($_SESSION['db_ok'])) { header('Location: ?step=database'); exit; }
            $name = trim($_POST['name'] ?? ''); $email = trim($_POST['email'] ?? ''); $pass = (string) ($_POST['password'] ?? '');
            $appName = trim($_POST['app_name'] ?? '') ?: $cfg['product_name'];
            $currency = strtoupper(trim($_POST['currency'] ?? 'USD'));
            $color = preg_match('/^#[0-9a-f]{6}$/i', $_POST['color'] ?? '') ? $_POST['color'] : '#047857';
            if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a name and a valid email.');
            if (strlen($pass) < 10) throw new RuntimeException('Password must be at least 10 characters.');
            if (! preg_match('/^[A-Z]{3}$/', $currency)) throw new RuntimeException('Currency must be a 3-letter code, e.g. USD.');
            write_env($root, ['APP_NAME' => $appName, 'MAIL_FROM_NAME' => $appName]);
            boot_laravel($root);
            $user = App\Models\User::firstOrNew(['email' => $email]);
            $user->forceFill(['name' => $name, 'email' => $email, 'password' => Illuminate\Support\Facades\Hash::make($pass),
                'email_verified_at' => now()])->save();
            // Single organisation: create it once, owned by this admin.
            if (! App\Models\Account::query()->exists()) {
                app(App\Services\AccountProvisioner::class)->create($user, $appName, null, ['workspace_type' => 'business']);
            }
            $S = App\Models\PlatformSetting::class;
            $S::set('branding.platform_name', $appName, 'string', 'branding');
            $S::set('general.platform_name', $appName, 'string', 'general');
            $S::set('general.platform_url', base_url(), 'string', 'general');
            $S::set('general.support_email', $email, 'string', 'general');
            $S::set('branding.primary_color', $color, 'string', 'branding');
            $S::set('payment.default_currency', $currency, 'string', 'payment');
            $S::set('system.external_cron_token', bin2hex(random_bytes(20)), 'string', 'system');
            Illuminate\Support\Facades\DB::table('plans')->update(['currency' => $currency]);
            try { Illuminate\Support\Facades\Artisan::call('optimize:clear'); } catch (Throwable $e) {}
            file_put_contents($lock, json_encode(['installed_at' => date('c'), 'version' => trim((string) @file_get_contents($root.'/VERSION'))]));
            $_SESSION = ['done_email' => $email];
            header('Location: ?step=done'); exit;
        }
    } catch (Throwable $e) {
        $errors[] = $e instanceof RuntimeException ? $e->getMessage() : 'Install error: '.$e->getMessage();
    }
}
$phpBin = PHP_BINDIR.'/php';
$cron = '* * * * * cd '.$root.' && php artisan schedule:run >> /dev/null 2>&1';
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Install · <?= h($cfg['product_name']) ?></title>
<style>
:root{--g:#047857;--b:#e5e7eb;--t:#111827;--m:#6b7280}
*{box-sizing:border-box}body{margin:0;font:15px/1.5 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#f3f4f6;color:var(--t)}
.wrap{max-width:640px;margin:32px auto;padding:0 16px}.card{background:#fff;border:1px solid var(--b);border-radius:12px;padding:24px}
h1{font-size:20px;margin:0 0 4px}p.sub{color:var(--m);margin:0 0 20px}
ol.steps{display:flex;flex-wrap:wrap;gap:6px;list-style:none;padding:0;margin:0 0 16px}
ol.steps li{font-size:13px;padding:4px 10px;border-radius:999px;background:#e5e7eb;color:var(--m)}ol.steps li.on{background:var(--g);color:#fff}
label{display:block;font-weight:600;font-size:14px;margin:12px 0 4px}
input[type=text],input[type=email],input[type=password],input[type=number]{width:100%;padding:9px 11px;border:1px solid #d1d5db;border-radius:8px;font:inherit}
input:focus,button:focus,a.btn:focus{outline:2px solid #34d399;outline-offset:2px}
.row{display:grid;grid-template-columns:1fr 1fr;gap:12px}@media(max-width:520px){.row{grid-template-columns:1fr}}
.btn{display:inline-flex;align-items:center;gap:6px;width:auto;background:var(--g);color:#fff;border:0;border-radius:8px;padding:10px 18px;font:600 14px/1 inherit;cursor:pointer;text-decoration:none;margin-top:18px}
.btn.alt{background:#fff;color:var(--g);border:1px solid var(--g)}
ul.chk{list-style:none;padding:0;margin:0}ul.chk li{display:flex;justify-content:space-between;gap:8px;padding:6px 0;border-bottom:1px solid #f3f4f6;font-size:14px}
.ok{color:var(--g);font-weight:600}.bad{color:#b91c1c;font-weight:600}
.err{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;border-radius:8px;padding:10px 12px;margin-bottom:12px}
code,pre{background:#f3f4f6;border-radius:6px;padding:2px 6px;font-size:13px;overflow-x:auto;word-break:break-all;white-space:pre-wrap}
.hint{color:var(--m);font-size:13px;margin:4px 0 0}.tip{cursor:help;color:var(--m)}
.foot{color:var(--m);font-size:13px;text-align:center;margin:14px 0 0}.foot a{color:inherit}
</style></head><body><main class="wrap"><div class="card">
<h1>Install <?= h($cfg['product_name']) ?></h1><p class="sub">A few quick steps. No SSH needed.</p>
<?php if ($step !== 'locked'): ?><ol class="steps" aria-label="Progress"><?php foreach ($steps as $k => $l): ?><li class="<?= $k === $step ? 'on' : '' ?>"<?= $k === $step ? ' aria-current="step"' : '' ?>><?= h($l) ?></li><?php endforeach ?></ol><?php endif ?>
<?php foreach ($errors as $e): ?><div class="err" role="alert"><?= h($e) ?></div><?php endforeach ?>

<?php if ($step === 'locked'): ?>
  <p>Already installed. To reinstall, delete <code>storage/installed</code> (this does not wipe data).</p>
  <a class="btn" href="../">Open app</a>
<?php elseif ($step === 'requirements'): ?>
  <ul class="chk"><?php foreach ($checks as [$l, $ok]): ?><li><span><?= h($l) ?></span><span class="<?= $ok ? 'ok' : 'bad' ?>"><?= $ok ? '✓ OK' : '✗ Missing' ?></span></li><?php endforeach ?></ul>
  <h2 style="font-size:15px;margin:16px 0 4px">Writable folders</h2>
  <ul class="chk"><?php foreach ($perm as [$l, $ok]): ?><li><span><?= h($l) ?></span><span class="<?= $ok ? 'ok' : 'bad' ?>"><?= $ok ? '✓ Writable' : '✗ Set 755/775' ?></span></li><?php endforeach ?></ul>
  <form method="post"><input type="hidden" name="_token" value="<?= h(csrf()) ?>"><button class="btn" <?= $reqOk ? '' : 'disabled aria-disabled="true" style="opacity:.5"' ?>>Continue →</button></form>
<?php elseif ($step === 'database'): $f = $_SESSION['db_form'] ?? []; ?>
  <form method="post"><input type="hidden" name="_token" value="<?= h(csrf()) ?>">
  <p class="hint">Create a MySQL 5.7+ or MariaDB 10.3+ database and user in cPanel → MySQL Databases first.</p>
  <div class="row"><div><label for="dh">Host</label><input id="dh" type="text" name="db_host" value="<?= h($f['host'] ?? 'localhost') ?>"></div>
  <div><label for="dp">Port</label><input id="dp" type="number" name="db_port" value="<?= h($f['port'] ?? 3306) ?>"></div></div>
  <label for="dn">Database name</label><input id="dn" type="text" name="db_name" value="<?= h($f['name'] ?? '') ?>" required>
  <div class="row"><div><label for="du">User</label><input id="du" type="text" name="db_user" value="<?= h($f['user'] ?? '') ?>" required></div>
  <div><label for="dw">Password</label><input id="dw" type="password" name="db_pass" autocomplete="new-password"></div></div>
  <label style="font-weight:400"><input type="checkbox" name="demo" value="1"> Add demo data <span class="tip" title="Sample workspace for a preview. Owner owner@demo.chatdesk.test, agent agent@demo.chatdesk.test, platform admin admin@demo.chatdesk.test. Password demo1234. Do not use on a live site.">ⓘ</span></label>
  <button class="btn">Install database →</button><p class="hint">Takes up to a minute. Don't close the tab.</p></form>
<?php elseif ($step === 'admin'): ?>
  <form method="post"><input type="hidden" name="_token" value="<?= h(csrf()) ?>">
  <label for="an">App name</label><input id="an" type="text" name="app_name" value="<?= h($cfg['product_name']) ?>">
  <div class="row"><div><label for="cu">Currency <span class="tip" title="3-letter ISO code, e.g. USD, EUR, INR">ⓘ</span></label><input id="cu" type="text" name="currency" value="USD" maxlength="3"></div>
  <div><label for="co">Brand color</label><input id="co" type="text" name="color" value="#047857"></div></div>
  <label for="nm">Admin name</label><input id="nm" type="text" name="name" required>
  <label for="em">Admin email</label><input id="em" type="email" name="email" required>
  <label for="pw">Admin password</label><input id="pw" type="password" name="password" minlength="10" autocomplete="new-password" required>
  <button class="btn">Finish →</button></form>
<?php elseif ($step === 'done'): ?>
  <p class="ok">Installed.</p>
  <p><strong>Add one cron job</strong> (cPanel → Cron Jobs → Once per minute):</p>
  <pre><?= h($cron) ?></pre>
  <p class="hint">It runs the scheduler and the email/campaign queue. No other worker needed.</p>
  <p>Next: Admin → Settings → WhatsApp (Meta app), Mail and Payments. Updates: Admin → System update.</p>
  <a class="btn" href="../login">Log in</a>
<?php endif ?>
</div>
<p class="foot">ChatDesk · <a href="https://yoorie.com">Support</a> · <a href="mailto:support@yoorie.com">support@yoorie.com</a></p>
</main></body></html>
