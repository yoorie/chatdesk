<?php

use App\Models\Plan;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Plan::query()->each(function (Plan $plan): void {
            $modules = array_values(array_filter(
                $plan->modules ?? [],
                fn ($module) => $module !== 'inbox'
            ));

            if ($modules !== ($plan->modules ?? [])) {
                $plan->forceFill(['modules' => $modules])->save();
            }
        });
    }

    public function down(): void
    {
        $planKeys = ['starter', 'pro', 'enterprise'];

        Plan::query()
            ->whereIn('key', $planKeys)
            ->each(function (Plan $plan): void {
                $modules = $plan->modules ?? [];

                if (! in_array('inbox', $modules, true)) {
                    $modules[] = 'inbox';
                }

                $plan->forceFill(['modules' => array_values(array_unique($modules))])->save();
            });
    }
};
