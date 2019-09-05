<?php
namespace Spdevs\AuthorizePay;

use Illuminate\Support\ServiceProvider;

class AuthorizePayServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadTranslationsFrom(__DIR__ . '/../lang/en.php', 'authorizepay');
        $this->publishes([
            __DIR__ . '/../config/authorizepay.php' => config_path('authorizepay.php'),
        ]);
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(AuthorizePayService::class, function () {
            return new AuthorizePayService();
        });

        $this->mergeConfigFrom( __DIR__ . '/../config/authorizepay.php', 'authorizepay');
    }


}
