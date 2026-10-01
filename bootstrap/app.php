<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
  ->withRouting(
    web: __DIR__ . '/../routes/web.php',
    commands: __DIR__ . '/../routes/console.php',
    health: '/up',
  )
  ->withMiddleware(function (Middleware $middleware): void {
    // JSON/mobile/partner/ussd clients use bearer or gateway secrets — not session CSRF.
    $middleware->validateCsrfTokens(except: [
      'api/v1/mobile/*',
      'api/v1/partner/*',
      'api/v1/channels/ussd/*',
      'api/v1/public/sms/*',
    ]);
    $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
    $middleware->alias([
      'set.locale' => \App\Http\Middleware\SetLocale::class,
      'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
      'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
      'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
      'integration.client' => \App\Http\Middleware\AuthenticateIntegrationClient::class,
      'mobile.token' => \App\Http\Middleware\AuthenticateMobileApiToken::class,
      'ussd.gateway' => \App\Http\Middleware\VerifyUssdGatewaySecret::class,
      'admin.portal' => \App\Http\Middleware\EnsureAdminPortalAccess::class,
    ]);
  })
  ->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->render(function (\InvalidArgumentException $e, \Illuminate\Http\Request $request) {
      if ($request->is('api/*') || $request->expectsJson()) {
        return response()->json(['message' => $e->getMessage()], 422);
      }
    });
  })
  ->create();
