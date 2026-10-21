<?php

namespace App\Core\Modules;

use Illuminate\Support\Collection;

class ModuleRegistry
{
    protected array $modules = [];

    /**
     * Register a module.
     */
    public function register(array $definition): void
    {
        $this->modules[$definition['key']] = $definition;
    }

    /**
     * Get all registered modules.
     */
    public function all(): Collection
    {
        return collect($this->modules);
    }

    /**
     * Get a specific module by key.
     */
    public function get(string $key): ?array
    {
        return $this->modules[$key] ?? null;
    }

    /**
     * Get enabled modules for a account.
     * A module is enabled when it is on for the install and not switched off for the organisation.
     */
    public function getEnabledForAccount($account): Collection
    {
        $moduleAccess = app(\App\Core\Modules\ModuleAccess::class);
        $effectiveModuleKeys = $moduleAccess->getEffectiveModules($account);

        return $this->all()->filter(fn ($module) => in_array($module['key'], $effectiveModuleKeys, true));
    }

    /**
     * Get navigation items for enabled modules.
     * Only shows navigation for modules that are both on the plan AND enabled in account.
     */
    public function getNavigationForAccount($account): array
    {
        $enabledModules = $this->getEnabledForAccount($account);

        $navItems = [];

        foreach ($enabledModules as $module) {
            if (isset($module['nav']) && is_array($module['nav'])) {
                foreach ($module['nav'] as $navItem) {
                    // Only include nav items if the module is enabled at platform level
                    $moduleKey = $module['key'];
                    $platformModule = \App\Models\Module::where('key', $moduleKey)->first();

                    if ($platformModule && $platformModule->is_enabled) {
                        $navItems[] = $navItem;
                    }
                }
            }
        }

        return $navItems;
    }
}
