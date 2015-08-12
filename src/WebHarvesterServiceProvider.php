<?php
namespace Malahierba\WebHarvester;

use Illuminate\Support\ServiceProvider;

class WebHarvesterServiceProvider extends ServiceProvider {

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app['webharvester'] = $this->app->share(function($app)
        {
            return new WebHarvester;
        });
    }
    
    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/config/webharvester.php' => config_path('webharvester.php'),
        ]);
    }
}
