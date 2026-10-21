import { spawn, spawnSync } from 'node:child_process';
import { copyFileSync, existsSync, mkdirSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const host = '127.0.0.1';
const port = Number(process.env.LOCAL_PORT || 8000);
if (!Number.isInteger(port) || port < 1024 || port > 65535) throw new Error('LOCAL_PORT must be between 1024 and 65535.');
const url = `http://${host}:${port}`;
const database = resolve(root, 'database/database.sqlite');
const env = { ...process.env, APP_ENV: 'local', APP_DEBUG: 'true', APP_URL: url, ASSET_URL: url,
    LOCAL_SERVER_URL: url, DB_CONNECTION: 'sqlite', DB_DATABASE: database, MAIL_MAILER: 'log' };

function artisan(...args) {
    const result = spawnSync('php', ['artisan', ...args], { cwd: root, env, stdio: 'inherit' });
    if (result.error) throw result.error;
    if (result.status !== 0) process.exit(result.status || 1);
}

if (!existsSync(resolve(root, 'vendor/autoload.php'))) throw new Error('Run composer install first.');
if (!existsSync(resolve(root, '.env'))) {
    copyFileSync(resolve(root, '.env.example'), resolve(root, '.env'));
    artisan('key:generate', '--no-interaction');
}
if (existsSync(resolve(root, 'bootstrap/cache/config.php'))) {
    throw new Error('Local development requires uncached configuration. Run php artisan config:clear first.');
}
mkdirSync(dirname(database), { recursive: true });
if (!existsSync(database)) writeFileSync(database, '');
const backupDir = resolve(root, 'storage/app/private/local-backups');
mkdirSync(backupDir, { recursive: true });
// SQLite creates a consistent snapshot even if another local process is connected.
const backup = resolve(backupDir, `database-${new Date().toISOString().replaceAll(':', '-')}.sqlite`);
const snapshot = spawnSync('php', ['-r', '$db = new SQLite3($argv[1]); $db->backup(new SQLite3($argv[2]));', database, backup], { cwd: root, env, stdio: 'inherit' });
if (snapshot.status !== 0) throw new Error('Could not back up the local database.');
artisan('migrate', '--no-interaction');

console.log(`\nApp: ${url}\nUsing the local SQLite database. Saved server credentials are unchanged.\n`);
const children = [
    spawn('php', ['artisan', 'serve', `--host=${host}`, `--port=${port}`], { cwd: root, env, stdio: 'inherit' }),
    spawn(process.execPath, ['node_modules/vite/bin/vite.js', '--host', host], { cwd: root, env, stdio: 'inherit' }),
];
let stopping = false;
function stop(code = 0) {
    if (stopping) return;
    stopping = true;
    children.forEach((child) => child.kill('SIGTERM'));
    process.exitCode = code;
}
children.forEach((child) => {
    child.on('error', (error) => { console.error(error.message); stop(1); });
    child.on('exit', (code) => stop(code || 0));
});
process.on('SIGINT', () => stop());
process.on('SIGTERM', () => stop());
