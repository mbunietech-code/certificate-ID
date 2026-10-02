<?php

namespace App\Models;

use App\Models\Concerns\IsPerson;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'employee_number', 'first_name', 'middle_name', 'last_name', 'gender', 'date_of_birth', 'job_title',
    'department', 'phone', 'email', 'employment_status',
])]
class Staff extends Model
{
    use BelongsToSchool, HasFactory, IsPerson, SoftDeletes;

    protected $table = 'staff';

    public const STATUSES = ['active', 'on_leave', 'suspended', 'terminated', 'retired'];

    protected function casts(): array
    {
        return ['date_of_birth' => 'date'];
    }

    public function idCards(): MorphMany
    {
        return $this->morphMany(IdCard::class, 'holder');
    }

    /** @param  array<string, mixed>  $filters */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['search'] ?? null, fn ($q, $term) => $this->applyPersonSearch($q, $term, 'employee_number'))
            ->when($filters['department'] ?? null, fn ($q, $v) => $q->where('department', $v))
            ->when($filters['gender'] ?? null, fn ($q, $v) => $q->where('gender', $v))
            ->when($filters['employment_status'] ?? null, fn ($q, $v) => $q->where('employment_status', $v));
    }
}
