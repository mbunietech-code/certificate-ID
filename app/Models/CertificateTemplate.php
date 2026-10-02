<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'paper_size', 'orientation', 'width_mm', 'height_mm', 'design_json', 'default_title', 'default_body', 'status', 'is_default'])]
class CertificateTemplate extends Model
{
    use BelongsToSchool, SoftDeletes;

    protected static bool $allowsGlobalRecords = true;

    /** Portrait dimensions in mm; landscape swaps them. */
    public const PAPER_SIZES = [
        'A4' => [210, 297],
        'A5' => [148, 210],
        'A3' => [297, 420],
        'LETTER' => [215.9, 279.4],
        'CUSTOM' => null,
    ];

    protected function casts(): array
    {
        return [
            'design_json' => 'array',
            'is_default' => 'boolean',
            'width_mm' => 'float',
            'height_mm' => 'float',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class, 'template_id');
    }

    public function isGlobal(): bool
    {
        return $this->school_id === null;
    }

    public function holderType(): string
    {
        return 'student';
    }

    public function documentKind(): string
    {
        return 'certificate';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /** @return array{0: float, 1: float} width, height in mm for a paper size + orientation */
    public static function dimensionsFor(string $paper, string $orientation): ?array
    {
        $size = self::PAPER_SIZES[$paper] ?? null;
        if (! $size) {
            return null;
        }

        return $orientation === 'landscape' ? [$size[1], $size[0]] : [$size[0], $size[1]];
    }

    /** @return array<string, mixed> */
    public function sides(): array
    {
        return ['front' => ($this->design_json ?? [])['front'] ?? ['elements' => []]];
    }

    public function sizeLabel(): string
    {
        return $this->paper_size === 'CUSTOM'
            ? "{$this->width_mm} × {$this->height_mm} mm"
            : $this->paper_size.' '.ucfirst($this->orientation);
    }
}
