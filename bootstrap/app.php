<?php

use App\Http\Middleware\SetLocale;
use App\Http\Middleware\RequireAdminPermission;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [SetLocale::class]);
        $middleware->alias([
            'admin.permission' => RequireAdminPermission::class,
        ]);
        $middleware->redirectGuestsTo(fn (\Illuminate\Http\Request $request) => str_starts_with($request->path(), 'account')
            ? route('store.account.login')
            : route('admin.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
