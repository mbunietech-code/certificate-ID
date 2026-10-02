<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'school_code', 'name', 'short_name', 'registration_number', 'address', 'region', 'district', 'ward',
    'phone', 'email', 'website', 'logo_path', 'principal_name', 'principal_signature_path', 'school_stamp_path',
    'primary_color', 'secondary_color', 'student_id_format', 'staff_id_format', 'certificate_number_format', 'status',
])]
class School extends Model
{
    use HasFactory;

    public const STATUSES = ['active', 'inactive'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }

    public function academicYears(): HasMany
    {
        return $this->hasMany(AcademicYear::class);
    }

    public function currentAcademicYear(): HasOne
    {
        return $this->hasOne(AcademicYear::class)->where('is_current', true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function displayName(): string
    {
        return $this->short_name ?: $this->name;
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }
}
