<?php

namespace resuelveFormulas;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class ResuelveFormulasServiceProvider extends ServiceProvider
{

  public function register()
  {
    $this->mergeConfigFrom(__DIR__.'/../config/formulas.php', 'resuelveFormulas');
  }

  public function boot()
  {
    $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
    $this->loadViewsFrom(__DIR__.'/../resources/views', 'resuelveFormulas');
    // $router = $this->app->make(Router::class);
    $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

    if ($this->app->runningInConsole()) {
      // Publish assets
      $this->publishes([__DIR__.'/../resources/assets' => public_path('resuelveFormulas')], 'assets');
      // Publish views
      $this->publishes([ __DIR__.'/../resources/views' => resource_path('views/vendor/resuelveFormulas')], 'views');
      // Publish config
      $this->publishes([__DIR__.'/../config/formulas.php' => config_path('resuelveFormulas.php')], 'config');

    }

  }

}
