<?php

// Isolated synthetic read benchmark, never a production concurrency/capacity claim.
require __DIR__.'/../vendor/autoload.php';
if (getenv('APP_ENV') !== 'testing' || getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:') {
    fwrite(STDERR, "Use APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: CACHE_STORE=array.\n");
    exit(1);
}
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('cache.default') !== 'array' || config('database.connections.sqlite.database') !== ':memory:') {
    throw new RuntimeException('An in-memory database and array cache are required.');
}
Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
$owner = App\Models\User::factory()->create();
$accounts = App\Models\Account::factory()->count(2)->create(['owner_id' => $owner->id]);
foreach ($accounts as $account) {
    $connection = App\Modules\WhatsApp\Models\WhatsAppConnection::factory()->create(['account_id' => $account->id]);
    $contact = App\Modules\WhatsApp\Models\WhatsAppContact::factory()->create(['account_id' => $account->id]);
    $conversations[$account->id] = App\Modules\WhatsApp\Models\WhatsAppConversation::factory()->create(['account_id' => $account->id, 'whatsapp_connection_id' => $connection->id, 'whatsapp_contact_id' => $contact->id]);
}
$now = now()->toDateTimeString();
for ($batch = 0; $batch < 4; $batch++) {
    $rows = [];
    for ($i = 0; $i < 250; $i++) {
        $id = $batch * 250 + $i;
        $rows[] = ['name' => 'Synthetic '.$id, 'email' => 'scale-'.$id.'@example.test', 'password' => $owner->password, 'created_at' => $now, 'updated_at' => $now];
    }
    Illuminate\Support\Facades\DB::table('users')->insert($rows);
}
$members = App\Models\User::where('id', '!=', $owner->id)->pluck('id');
foreach ($members->chunk(250) as $chunk) {
    Illuminate\Support\Facades\DB::table('account_users')->insert($chunk->map(fn ($id) => ['account_id' => $accounts[0]->id, 'user_id' => $id, 'role' => 'member', 'created_at' => $now, 'updated_at' => $now])->all());
}
for ($batch = 0; $batch < 200; $batch++) {
    $rows = [];
    for ($i = 0; $i < 250; $i++) {
        $id = $batch * 250 + $i;
        $account = $accounts[$id % 2];
        $rows[] = ['account_id' => $account->id, 'whatsapp_conversation_id' => $conversations[$account->id]->id, 'meta_message_id' => 'synthetic-'.$id, 'direction' => $id % 3 ? 'outbound' : 'inbound', 'type' => 'text', 'text_body' => 'Synthetic performance fixture', 'status' => $id % 5 ? 'delivered' : 'failed', 'read_at' => null, 'created_at' => $now, 'updated_at' => $now];
    }
    Illuminate\Support\Facades\DB::table('whatsapp_messages')->insert($rows);
}
$user = App\Models\User::findOrFail($members->first());
$permissions = app(App\Services\WorkspacePermissionService::class);
$metrics = app(App\Services\DashboardMessageMetrics::class);
Illuminate\Support\Facades\DB::enableQueryLog();
Illuminate\Support\Facades\DB::flushQueryLog();
$start = hrtime(true);
$permissions->permissionsFor($user, $accounts[0]);
$permissionTime = (hrtime(true) - $start) / 1e6;
$permissionQueries = count(Illuminate\Support\Facades\DB::getQueryLog());
Illuminate\Support\Facades\DB::flushQueryLog();
$start = hrtime(true);
$values = $metrics->forAccount($accounts[0]->id);
$coldTime = (hrtime(true) - $start) / 1e6;
$coldQueries = count(Illuminate\Support\Facades\DB::getQueryLog());
Illuminate\Support\Facades\DB::flushQueryLog();
$warmTimes = [];
for ($i = 0; $i < 30; $i++) {
    $start = hrtime(true);
    $metrics->forAccount($accounts[0]->id);
    $warmTimes[] = (hrtime(true) - $start) / 1e6;
}
sort($warmTimes);
$warmQueries = count(Illuminate\Support\Facades\DB::getQueryLog());
Illuminate\Support\Facades\DB::disableQueryLog();
$plan = Illuminate\Support\Facades\DB::select("EXPLAIN QUERY PLAN SELECT COUNT(*) FROM whatsapp_messages WHERE account_id = ? AND direction = 'inbound' AND read_at IS NULL", [$accounts[0]->id]);
echo json_encode([
    'environment' => 'isolated SQLite memory database; array cache; no HTTP concurrency simulated',
    'users' => App\Models\User::count(), 'members_in_workspace' => $members->count(),
    'messages' => App\Modules\WhatsApp\Models\WhatsAppMessage::count(), 'workspace_message_count' => $values['total'],
    'permission_queries' => $permissionQueries, 'permission_ms' => round($permissionTime, 3),
    'cold_message_metrics_queries' => $coldQueries, 'cold_message_metrics_ms' => round($coldTime, 3),
    'warm_reads' => 30, 'warm_message_queries' => $warmQueries, 'warm_median_ms' => round($warmTimes[15], 3),
    'unread_query_plan' => array_map(fn ($row) => $row->detail, $plan),
], JSON_PRETTY_PRINT).PHP_EOL;
