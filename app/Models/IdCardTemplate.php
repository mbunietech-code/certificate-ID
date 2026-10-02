<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'type', 'width_mm', 'height_mm', 'orientation', 'dpi', 'has_back', 'design_json', 'status', 'is_default'])]
class IdCardTemplate extends Model
{
    use BelongsToSchool, SoftDeletes;

    protected static bool $allowsGlobalRecords = true;

    public const TYPE_STUDENT = 'STUDENT_ID';

    public const TYPE_STAFF = 'STAFF_ID';

    public const TYPES = [self::TYPE_STUDENT => 'Student ID', self::TYPE_STAFF => 'Staff ID'];

    /** Standard CR80 (ISO/IEC 7810 ID-1) card size in millimetres. */
    public const CR80_WIDTH = 85.60;

    public const CR80_HEIGHT = 53.98;

    protected function casts(): array
    {
        return [
            'design_json' => 'array',
            'has_back' => 'boolean',
            'is_default' => 'boolean',
            'width_mm' => 'float',
            'height_mm' => 'float',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function idCards(): HasMany
    {
        return $this->hasMany(IdCard::class, 'template_id');
    }

    public function isGlobal(): bool
    {
        return $this->school_id === null;
    }

    public function holderType(): string
    {
        return $this->type === self::TYPE_STAFF ? 'staff' : 'student';
    }

    public function documentKind(): string
    {
        return 'id_card';
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /** @return array<string, mixed> */
    public function sides(): array
    {
        $design = $this->design_json ?? [];
        $sides = ['front' => $design['front'] ?? ['elements' => []]];
        if ($this->has_back) {
            $sides['back'] = $design['back'] ?? ['elements' => []];
        }

        return $sides;
    }
}
