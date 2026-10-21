<?php

namespace App\Core\Modules;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    protected ModuleRegistry $registry;

    public function __construct($app)
    {
        parent::__construct($app);
        $this->registry = new ModuleRegistry;
    }

    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(ModuleRegistry::class, function () {
            return $this->registry;
        });

        $this->loadModules();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadModuleRoutes();
    }

    /**
     * Load all module definitions.
     */
    protected function loadModules(): void
    {
        $modulesPath = app_path('Modules');

        if (! File::exists($modulesPath)) {
            return;
        }

        $moduleDirs = File::directories($modulesPath);

        foreach ($moduleDirs as $moduleDir) {
            $moduleFile = $moduleDir.'/module.php';

            if (File::exists($moduleFile)) {
                $definition = require $moduleFile;

                if (is_array($definition) && isset($definition['key'])) {
                    $this->registry->register($definition);
                }
            }
        }
    }

    /**
     * Load routes from enabled modules.
     * Note: Routes that need to be inside app routes group should be loaded manually in routes/web.php
     * This method is kept for any global module routes that don't need account context.
     */
    protected function loadModuleRoutes(): void
    {
        // Module routes that need account context are loaded manually in routes/web.php
        // This method can be used for global module routes if needed in the future
    }
}
