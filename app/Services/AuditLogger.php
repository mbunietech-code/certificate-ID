<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\School;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Throwable;

class AuditLogger
{
    public function __construct(private TenantContext $tenant) {}

    /**
     * @param  array<string, mixed>  $properties
     */
    public function log(string $action, ?Model $entity = null, ?string $description = null, array $properties = [], ?int $schoolId = null, ?int $userId = null): void
    {
        try {
            $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

            $schoolId ??= $entity?->getAttribute('school_id') ?? $this->tenant->user()?->school_id ?? $this->tenant->scopedSchoolId();
            if ($entity instanceof School) {
                $schoolId = $entity->id;
            }

            AuditLog::forceCreate([
                'user_id' => $userId ?? $this->tenant->user()?->id,
                'school_id' => $schoolId,
                'action' => $action,
                'entity_type' => $entity ? Str::snake(class_basename($entity)) : null,
                'entity_id' => $entity?->getKey(),
                'description' => $description ? Str::limit($description, 250) : null,
                'properties' => $properties ?: null,
                'ip_address' => $request?->ip(),
                'user_agent' => $request ? Str::limit((string) $request->userAgent(), 250, '') : null,
            ]);
        } catch (Throwable $e) {
            // Auditing must never break the user's action.
            report($e);
        }
    }
}
