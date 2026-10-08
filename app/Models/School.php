<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'school_code', 'name', 'short_name', 'registration_number', 'address', 'region', 'district', 'ward',
    'phone', 'email', 'website', 'logo_path', 'principal_name', 'principal_signature_path', 'school_stamp_path',
    'primary_color', 'secondary_color', 'student_id_format', 'staff_id_format', 'certificate_number_format', 'status',
    'smart_school_source', 'smart_school_endpoint_url', 'smart_school_api_token', 'smart_school_database',
])]
class School extends Model
{
    use HasFactory;

    public const STATUSES = ['active', 'inactive'];

    public const SMART_SCHOOL_SOURCES = ['api', 'database'];

    protected $hidden = ['smart_school_api_token'];

    protected function casts(): array
    {
        return [
            'smart_school_api_token' => 'encrypted',
        ];
    }

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
        return public_storage_url($this->logo_path);
    }

    /** Benjamin Mkapa (code "BWM", "BWMHS", …) uses the level-based class form (O-Level "Form I - IV", A-Level combinations). */
    public function usesBenjaStudentForm(): bool
    {
        return str_starts_with(strtoupper((string) $this->school_code), 'BWM');
    }

    public function hasSmartSchoolSource(): bool
    {
        return in_array($this->smart_school_source, self::SMART_SCHOOL_SOURCES, true);
    }
}
