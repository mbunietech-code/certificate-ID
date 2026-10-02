<?php

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class SchoolScope implements Scope
{
    public function __construct(private bool $includeGlobal = false) {}

    public function apply(Builder $builder, Model $model): void
    {
        $schoolId = app(TenantContext::class)->scopedSchoolId();

        if ($schoolId === null) {
            return;
        }

        $column = $model->qualifyColumn('school_id');

        if ($this->includeGlobal) {
            $builder->where(fn (Builder $q) => $q->where($column, $schoolId)->orWhereNull($column));
        } else {
            $builder->where($column, $schoolId);
        }
    }
}
