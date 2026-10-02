<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

#[Fillable(['name', 'start_date', 'end_date', 'is_current', 'status'])]
class AcademicYear extends Model
{
    use BelongsToSchool, HasFactory;

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_current' => 'boolean',
        ];
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    /** Make this the only current year of its school. */
    public function markCurrent(): void
    {
        DB::transaction(function () {
            static::forSchool($this->school_id)->where('id', '!=', $this->id)->update(['is_current' => false]);
            $this->forceFill(['is_current' => true])->save();
        });
    }
}
