<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\RequireSchoolContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active' => EnsureAccountIsActive::class,
            'school.selected' => RequireSchoolContext::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Expired form (e.g. a phone that kept the login page open): send the user back to try again
        // instead of showing the "419 Page Expired" error page.
        $exceptions->render(function (HttpException $e, Request $request) {
            if (! $e->getPrevious() instanceof TokenMismatchException || $request->expectsJson()) {
                return null;
            }

            $message = 'Your session expired. Please try again.';
            $response = redirect()->back(fallback: route('login'))
                ->withInput($request->except(['_token', 'password', 'password_confirmation', 'current_password']));

            // The login page shows field errors only; other pages show the flash message.
            return $request->is('login') ? $response->withErrors(['email' => $message]) : $response->with('error', $message);
        });
    })->create();
