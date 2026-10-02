<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests;

    protected function tenant(): TenantContext
    {
        return app(TenantContext::class);
    }

    /** @param  array<string, mixed>  $properties */
    protected function audit(string $action, ?Model $entity = null, ?string $description = null, array $properties = []): void
    {
        app(AuditLogger::class)->log($action, $entity, $description, $properties);
    }
}
