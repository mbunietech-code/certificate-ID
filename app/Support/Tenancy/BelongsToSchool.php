<?php

namespace App\Support\Tenancy;

use App\Models\School;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant isolation for school-owned models.
 *
 * Every query is filtered to the school resolved by TenantContext, and
 * school_id is always assigned server-side on create. A model may set
 * `protected static bool $allowsGlobalRecords = true` to also expose
 * rows with school_id = NULL (global templates); such a model created
 * with no school in context is stored as a global record.
 *
 * @mixin Model
 */
trait BelongsToSchool
{
    public static function bootBelongsToSchool(): void
    {
        $includeGlobal = property_exists(static::class, 'allowsGlobalRecords') && static::$allowsGlobalRecords;

        static::addGlobalScope('school', new SchoolScope($includeGlobal));

        static::creating(function (Model $model) use ($includeGlobal) {
            if ($model->getAttribute('school_id') !== null) {
                // Only trusted server code sets school_id explicitly; still refuse a school
                // that differs from the caller's locked school.
                $scoped = app(TenantContext::class)->scopedSchoolId();
                if ($scoped !== null && (int) $model->getAttribute('school_id') !== $scoped) {
                    throw new \LogicException('Refusing to create a record for another school.');
                }

                return;
            }

            $context = app(TenantContext::class);

            // Global-capable models created with no school in context become global records.
            if ($includeGlobal && $context->scopedSchoolId() === null) {
                return;
            }

            $model->setAttribute('school_id', $context->schoolIdForWrite());
        });
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function scopeForSchool(Builder $query, int $schoolId): Builder
    {
        return $query->withoutGlobalScope('school')->where($this->qualifyColumn('school_id'), $schoolId);
    }
}
