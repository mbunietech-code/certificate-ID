<?php

namespace App\Providers;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\IdCard;
use App\Models\IdCardTemplate;
use App\Models\PrintJob;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use App\Policies\TemplatePolicy;
use App\Services\AuditLogger;
use App\Services\Documents\CodeImageService;
use App\Services\Documents\TemplateRenderer;
use App\Services\Settings;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(TenantContext::class);
        $this->app->singleton(Settings::class);
        $this->app->scoped(CodeImageService::class);
        $this->app->scoped(TemplateRenderer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        Relation::enforceMorphMap([
            'student' => Student::class,
            'staff' => Staff::class,
            'id_card' => IdCard::class,
            'certificate' => Certificate::class,
            'school' => School::class,
            'user' => User::class,
            'print_job' => PrintJob::class,
            'id_card_template' => IdCardTemplate::class,
            'certificate_template' => CertificateTemplate::class,
        ]);

        // Both template controllers share one base class, so bind by parameter name.
        // resolveRouteBinding applies the tenant global scope: other schools' templates 404.
        Route::model('idTemplate', IdCardTemplate::class);
        Route::model('certTemplate', CertificateTemplate::class);

        $this->registerGates();
        $this->registerRateLimiters();
        $this->registerAuthAuditing();

        Paginator::defaultView('components.pagination');

        View::composer('layouts.app', function ($view) {
            $tenant = app(TenantContext::class);
            $user = $tenant->user();
            $view->with([
                'activeSchool' => $tenant->school(),
                'switchableSchools' => $user?->isSuperAdmin() ? School::orderBy('name')->get(['id', 'name', 'school_code', 'status']) : collect(),
            ]);
        });
    }

    private function registerGates(): void
    {
        // The super admin passes every ability (including policies).
        Gate::before(fn (User $user) => $user->isSuperAdmin() ? true : null);

        Gate::policy(IdCardTemplate::class, TemplatePolicy::class);
        Gate::policy(CertificateTemplate::class, TemplatePolicy::class);

        foreach (config('access.permissions') as $permissions) {
            foreach (array_keys($permissions) as $slug) {
                Gate::define($slug, fn (User $user) => $user->hasPermission($slug));
            }
        }
    }

    private function registerRateLimiters(): void
    {
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by($request->ip()),
        ]);

        RateLimiter::for('verification', fn (Request $request) => [
            Limit::perMinute(30)->by($request->ip()),
            Limit::perDay(1000)->by($request->ip()),
        ]);
    }

    private function registerAuthAuditing(): void
    {
        Event::listen(function (Login $event) {
            /** @var User $user */
            $user = $event->user;
            $user->forceFill(['last_login_at' => now(), 'last_login_ip' => request()->ip()])->saveQuietly();
            app(AuditLogger::class)->log('auth.login', $user, "{$user->email} signed in", [], $user->school_id, $user->id);
        });

        Event::listen(function (Logout $event) {
            if ($event->user) {
                app(AuditLogger::class)->log('auth.logout', $event->user, "{$event->user->email} signed out", [], $event->user->school_id, $event->user->id);
            }
        });

        Event::listen(function (Failed $event) {
            app(AuditLogger::class)->log('auth.failed', null, 'Failed sign-in for '.($event->credentials['email'] ?? 'unknown'), [], $event->user?->school_id, $event->user?->id);
        });
    }
}
