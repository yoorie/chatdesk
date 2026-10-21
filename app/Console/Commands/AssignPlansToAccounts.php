<?php

namespace App\Console\Commands;

use App\Core\Billing\SubscriptionService;
use App\Models\Account;
use App\Models\Plan;
use Illuminate\Console\Command;

class AssignPlansToAccounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'accounts:assign-plans 
                            {--plan= : The plan key to assign (defaults to DEFAULT_PLAN_KEY or starter)}
                            {--dry-run : Show what would be done without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign plans to accounts that don\'t have a subscription';

    /**
     * Execute the console command.
     */
    public function handle(SubscriptionService $subscriptionService): int
    {
        $planKey = $this->option('plan') ?? config('billing.default_plan_key', 'starter');
        $dryRun = $this->option('dry-run');

        $this->info('Finding accounts without subscriptions...');

        // Find accounts without subscriptions
        $accounts = Account::whereDoesntHave('subscription')->get();

        if ($accounts->isEmpty()) {
            $this->info('No accounts found without subscriptions.');

            return Command::SUCCESS;
        }

        $this->info("Found {$accounts->count()} account(s) without subscriptions.");

        // Find the plan
        $plan = Plan::where('key', $planKey)
            ->where('is_active', true)
            ->first();

        if (! $plan) {
            $plan = Plan::where('is_active', true)
                ->whereNotNull('price_monthly')
                ->where('price_monthly', '>', 0)
                ->orderBy('price_monthly', 'asc')
                ->orderBy('sort_order')
                ->first();

            if (! $plan) {
                $this->error('No suitable paid plan found. Please create a plan first.');

                return Command::FAILURE;
            }

            $this->warn("Plan '{$planKey}' not found. Using plan '{$plan->key}' ({$plan->name}) instead.");
        } else {
            $this->info("Using plan: {$plan->name} ({$plan->key})");
        }

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        $bar = $this->output->createProgressBar($accounts->count());
        $bar->start();

        $successCount = 0;
        $errorCount = 0;

        foreach ($accounts as $account) {
            try {
                if (! $dryRun) {
                    $owner = $account->owner;

                    if ($plan->trial_days > 0) {
                        $subscriptionService->startTrial($account, $plan, $owner);
                    } else {
                        $subscriptionService->changePlan($account, $plan, $owner, null);
                    }
                }

                $successCount++;
            } catch (\Exception $e) {
                $errorCount++;
                $this->newLine();
                $this->error("Failed to assign plan to account '{$account->name}' (ID: {$account->id}): {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if ($dryRun) {
            $this->info("Would assign plan to {$successCount} account(s).");
        } else {
            $this->info("Successfully assigned plan to {$successCount} account(s).");
            if ($errorCount > 0) {
                $this->warn("Failed to assign plan to {$errorCount} account(s).");
            }
        }

        return Command::SUCCESS;
    }
}
