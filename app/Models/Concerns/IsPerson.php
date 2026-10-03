<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/** Shared name/photo helpers for students and staff. */
trait IsPerson
{
    public function getFullNameAttribute(): string
    {
        return trim(preg_replace('/\s+/', ' ', "{$this->first_name} {$this->middle_name} {$this->last_name}"));
    }

    public function photoUrl(): ?string
    {
        return public_storage_url($this->photo_path);
    }

    public function initials(): string
    {
        return mb_strtoupper(mb_substr((string) $this->first_name, 0, 1).mb_substr((string) $this->last_name, 0, 1));
    }

    /** Name search across first/middle/last plus the given number column. */
    protected function applyPersonSearch(Builder $query, string $term, string $numberColumn): Builder
    {
        $term = trim($term);
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(function (Builder $q) use ($like, $term, $numberColumn) {
            $q->where($numberColumn, 'like', $like)
                ->orWhere('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('middle_name', 'like', $like);

            // "John Doe" style searches
            $parts = preg_split('/\s+/', $term);
            if (count($parts) >= 2) {
                $q->orWhere(fn (Builder $q2) => $q2->where('first_name', 'like', $parts[0].'%')
                    ->where('last_name', 'like', end($parts).'%'));
            }
        });
    }
}
