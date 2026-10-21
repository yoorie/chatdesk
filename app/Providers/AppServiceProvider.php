<?php

namespace App\Providers;

use App\Console\Commands\OpsBackupDatabaseCommand;
use App\Console\Commands\OpsCleanupRetentionCommand;
use App\Console\Commands\OpsRunMaintenanceCommand;
use App\Modules\WhatsApp\Models\WhatsAppConnection;
use App\Modules\WhatsApp\Policies\WhatsAppConnectionPolicy;
use App\Services\PlatformSettingsService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        WhatsAppConnection::class => WhatsAppConnectionPolicy::class,];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(\App\Services\PlatformSettingStore::class);

        // Register BillingProviderManager as singleton
        $this->app->singleton(\App\Core\Billing\BillingProviderManager::class, function ($app) {
            return new \App\Core\Billing\BillingProviderManager;
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                OpsRunMaintenanceCommand::class,
                OpsBackupDatabaseCommand::class,
                OpsCleanupRetentionCommand::class,
            ]);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Open-source edition: some views and mails still mention pages that only exist in
        // ChatDesk paid plans / Zyptos. Send those links to the dashboard instead of failing.
        \Illuminate\Support\Facades\URL::resolveMissingNamedRoutesUsing(
            fn (string $name) => \Illuminate\Support\Facades\Route::has('app.dashboard') ? url('/app/dashboard') : url('/')
        );

        if (config('demo.enabled')) {
            // Demo sites never send real email, WhatsApp messages or payments.
            \Illuminate\Support\Facades\Event::listen(\Illuminate\Mail\Events\MessageSending::class, fn () => false);
            \Illuminate\Support\Facades\Http::globalRequestMiddleware(function ($request) {
                $host = $request->getUri()->getHost();
                foreach (['graph.facebook.com', 'facebook.com', 'stripe.com', 'razorpay.com', 'paypal.com', 'openai.com', 'anthropic.com'] as $blocked) {
                    if ($host === $blocked || str_ends_with($host, '.'.$blocked)) {
                        throw new \RuntimeException('Disabled in demo');
                    }
                }

                return $request;
            });
        }

        // Keep factory resolution stable for module models (App\Modules\*\Models\*)
        // by mapping to flat factory classes in database/factories.
        Factory::guessFactoryNamesUsing(function (string $modelName): string {
            return 'Database\\Factories\\'.class_basename($modelName).'Factory';
        });

        // Queue workers do not run HTTP middleware, so load runtime platform settings
        // for console/queue processes to keep broadcasting, mail, and integrations consistent.
        if (app()->runningInConsole() && ! $this->app->runningUnitTests()) {
            if ($this->canBootstrapPlatformSettings()) {
                try {
                    /** @var PlatformSettingsService $settingsService */
                    $settingsService = app(PlatformSettingsService::class);
                    $settingsService->applyGeneralConfig();
                    $settingsService->applyLocalization();
                    $settingsService->applyMailConfig();
                    $settingsService->applyPusherConfig();
                    $settingsService->applyWhatsAppConfig();
                    $settingsService->applyGoogleConfig();
                } catch (\Throwable $exception) {
                    \Log::warning('Failed to apply platform settings during console bootstrap', [
                        'error' => $exception->getMessage(),
                    ]);
                }

                Queue::before(function (JobProcessing $event) {
                    try {
                        $settings = app(PlatformSettingsService::class);
                        $settings->applyMailConfig();
                        $settings->applyPusherConfig();
                    } catch (\Throwable $exception) {
                        \Log::warning('Failed to apply platform config before queued job', [
                            'job' => $event->job?->resolveName(),
                            'error' => $exception->getMessage(),
                        ]);
                    }
                });
            }
        }

        // Register policies
        Gate::policy(\App\Modules\Chatbots\Models\Bot::class, \App\Modules\Chatbots\Policies\ChatbotPolicy::class);
        Gate::policy(WhatsAppConnection::class, WhatsAppConnectionPolicy::class);

        Event::listen(NotificationSent::class, [\App\Listeners\TrackNotificationDelivery::class, 'handleSent']);
        Event::listen(NotificationFailed::class, [\App\Listeners\TrackNotificationDelivery::class, 'handleFailed']);
        Event::listen(MessageSent::class, [\App\Listeners\TrackMailDelivery::class, 'handle']);

        // Route model binding for 'plan' parameter - resolve by key (slug) instead of ID
        Route::bind('plan', function ($value) {
            // Try to resolve by key first (slug), fallback to ID for backward compatibility
            $plan = \App\Models\Plan::where('key', $value)->orWhere('id', $value)->first();
            if (! $plan) {
                abort(404, 'Plan not found');
            }

            return $plan;
        });

        // Route model binding for 'subscription' parameter - resolve by slug instead of ID
        Route::bind('subscription', function ($value) {
            // Try to resolve by slug first, fallback to ID for backward compatibility
            $subscription = \App\Models\Subscription::where('slug', $value)->orWhere('id', $value)->first();
            if (! $subscription) {
                abort(404, 'Subscription not found');
            }

            return $subscription;
        });

        // Route model binding for 'template' parameter - resolve by slug instead of ID
        Route::bind('template', function ($value) {
            // Try to resolve by slug first, fallback to ID for backward compatibility
            $template = \App\Modules\WhatsApp\Models\WhatsAppTemplate::where('slug', $value)->orWhere('id', $value)->first();
            if (! $template) {
                abort(404, 'Template not found');
            }

            return $template;
        });

        // Route model binding for 'connection' parameter - resolve by slug instead of ID
        // Route model binding for 'connection' parameter - resolve by slug first, fallback to ID
        Route::bind('connection', function ($value) {
            // Log all binding attempts
            \Log::channel('whatsapp')->info('Route binding attempt', [
                'value' => $value,
                'type' => is_numeric($value) ? 'id' : 'slug',
                'path' => request()->path(),
            ]);

            // Try to resolve by slug first, fallback to ID for backward compatibility
            $connection = \App\Modules\WhatsApp\Models\WhatsAppConnection::where('slug', $value)
                ->orWhere('id', $value)
                ->first();

            if (! $connection) {
                // Log the failure for debugging
                \Log::channel('whatsapp')->error('Connection not found in route binding', [
                    'value' => $value,
                    'type' => is_numeric($value) ? 'id' : 'slug',
                    'path' => request()->path(),
                    'all_connections_count' => \App\Modules\WhatsApp\Models\WhatsAppConnection::count(),
                ]);
                abort(404, 'Connection not found');
            }

            // Ensure connection has a slug (auto-fix)
            if (empty($connection->slug)) {
                $connection->slug = \App\Modules\WhatsApp\Models\WhatsAppConnection::generateSlug($connection);
                $connection->save();
                \Log::channel('whatsapp')->info('Auto-generated slug for connection', [
                    'connection_id' => $connection->id,
                    'new_slug' => $connection->slug,
                ]);
            }

            // Log successful resolution
            \Log::channel('whatsapp')->info('Connection resolved in route binding', [
                'value' => $value,
                'connection_id' => $connection->id,
                'connection_slug' => $connection->slug,
                'resolved_by' => $connection->slug === $value ? 'slug' : 'id',
            ]);

            return $connection;
        });

        // Route model binding for 'campaign' parameter - resolve by slug instead of ID
        Route::bind('campaign', function ($value) {
            // Try to resolve by slug first, fallback to ID for backward compatibility
            $campaign = \App\Modules\Broadcasts\Models\Campaign::where('slug', $value)->orWhere('id', $value)->first();
            if (! $campaign) {
                abort(404, 'Campaign not found');
            }

            return $campaign;
        });

        // Route model binding for 'contact' - resolve only inside the active workspace.
        Route::bind('contact', function ($value) {
            $account = request()->attributes->get('account') ?? current_account();
            $contactModel = \App\Modules\WhatsApp\Models\WhatsAppContact::class;
            $user = request()->user();

            if (! $account && $user) {
                $account = $user->ownedAccounts()->first() ?: $user->accounts()->first();
                if ($account) {
                    request()->attributes->set('account', $account);
                    session(['current_account_id' => $account->id]);
                }
            }

            if (! $account) {
                abort(404, 'Account not found');
            }
            $accountId = (int) $account->id;

            \Log::info('Contact binding attempt', [
                'value' => $value,
                'account_id' => $accountId,
                'user_id' => $user?->id,
            ]);

            // Build candidate values for slug/wa_id (wa_id can be stored with/without country code)
            $candidates = array_unique(array_filter([$value]));
            if (is_string($value) && preg_match('/^\d+$/', $value)) {
                $len = strlen($value);
                if ($len === 10 && ! str_starts_with($value, '91')) {
                    $candidates[] = '91'.$value;
                    $candidates[] = '91'.'9'.$value;
                } elseif ($len === 12 && str_starts_with($value, '91')) {
                    $candidates[] = '9'.$value;
                } elseif ($len === 13 && str_starts_with($value, '919')) {
                    $candidates[] = substr($value, 1);
                }
            }

            $contact = $contactModel::where('account_id', $accountId)
                ->where(function ($q) use ($candidates, $value) {
                    $q->whereIn('slug', $candidates)
                        ->orWhereIn('wa_id', $candidates);
                    if (is_numeric($value) && (int) $value > 0 && (int) $value < 2147483647) {
                        $q->orWhere('id', (int) $value);
                    }
                })->first();

            // Fallback: match by last 10 digits of phone (handles 91 vs 919 and any digit-only wa_id format)
            if (! $contact && is_string($value) && preg_match('/^\d{10,13}$/', $value)) {
                $last10 = substr($value, -10);
                $contact = $contactModel::where('account_id', $accountId)
                    ->where(function ($q) use ($last10) {
                        $q->whereRaw('substr(wa_id, -10) = ?', [$last10])
                            ->orWhereRaw('substr(slug, -10) = ?', [$last10]);
                    })->first();
            }

            if ($contact) {
                \Log::info('Contact binding resolved', [
                    'value' => $value,
                    'contact_id' => $contact->id,
                    'contact_account_id' => $contact->account_id,
                ]);

                return $contact;
            }

            \Log::warning('Contact binding failed', [
                'value' => $value,
                'account_id' => $accountId,
                'candidates' => $candidates,
            ]);
            abort(404, 'Contact not found');
        });

        // Route model binding for 'conversation' - scope to current account so inbox links never 404 for wrong account
        Route::bind('conversation', function ($value) {
            $account = request()->attributes->get('account') ?? current_account();
            $id = is_numeric($value) ? (int) $value : null;
            if ($id === null || $id < 1) {
                \Log::channel('whatsapp')->warning('Conversation binding: invalid id', ['path' => request()->path(), 'value' => $value]);
                abort(404, 'Conversation not found');
            }

            if (! $account) {
                $conversation = \App\Modules\WhatsApp\Models\WhatsAppConversation::find($id);
                $conversationAccount = $conversation?->account;
                $user = request()->user();

                if ($conversation && $conversationAccount && $user?->canAccessAccount($conversationAccount)) {
                    request()->attributes->set('account', $conversationAccount);
                    session(['current_account_id' => $conversationAccount->id]);

                    return $conversation;
                }

                \Log::channel('whatsapp')->warning('Conversation binding: no account resolved', ['path' => request()->path(), 'value' => $value]);
                abort(404, 'Account not found');
            }

            $accountId = (int) $account->id;
            $conversation = \App\Modules\WhatsApp\Models\WhatsAppConversation::where('id', $id)
                ->where('account_id', $accountId)
                ->first();
            if (! $conversation) {
                $existsOtherAccount = \App\Modules\WhatsApp\Models\WhatsAppConversation::where('id', $id)->exists();
                \Log::channel('whatsapp')->info('Conversation binding: not found for account', [
                    'conversation_id' => $id,
                    'account_id' => $accountId,
                    'path' => request()->path(),
                    'exists_other_account' => $existsOtherAccount,
                ]);
                abort(404, 'Conversation not found');
            }

            return $conversation;
        });
    }

    protected function canBootstrapPlatformSettings(): bool
    {
        try {
            return Schema::hasTable('platform_settings');
        } catch (\Throwable) {
            return false;
        }
    }
}
