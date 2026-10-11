<?php

namespace App\Providers;

use App\Cart\ShoppingCart;
use App\Jobs\ImportProducts;
use App\Listeners\MergeGuestCart;
use App\Models\User;
use App\Payments\FakePaymentGateway;
use App\Payments\PaymentGateway;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ShoppingCart::class, function (Application $app): ShoppingCart {
            $user = $app->make('auth')->user();

            return new ShoppingCart($app->make(Session::class), $user instanceof User ? $user : null);
        });

        $this->app->singleton(PaymentGateway::class, fn (): PaymentGateway => new FakePaymentGateway(
            (int) config('services.payments.fake_latency_ms'),
        ));
    }

    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureRateLimiting();
        $this->configureDevCommands();

        Event::listen(Login::class, MergeGuestCart::class);
    }

    protected function configureDevCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        DevCommands::except('queue');
        DevCommands::artisan('queue:listen --queue=default,'.ImportProducts::QUEUE.' --tries=1 --timeout=0', 'queues');
        DevCommands::artisan('schedule:work', 'scheduler');
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(app()->isProduction());

        Model::preventLazyLoading(! app()->isProduction());
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function configureAuthorization(): void
    {
        Gate::define('access-admin', fn (User $user): bool => $user->isAdmin());
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('catalog', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));

        RateLimiter::for('cart', fn (Request $request) => Limit::perMinute(30)->by(
            (string) ($request->user()?->id ?: $request->session()->getId() ?: $request->ip()),
        ));

        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(10)->by(
            (string) ($request->user()?->id ?: $request->ip()),
        ));
    }
}
