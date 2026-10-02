<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Always accessed through its (tenant-scoped) PrintJob. */
class PrintJobItem extends Model
{
    protected $guarded = ['*'];

    public function printJob(): BelongsTo
    {
        return $this->belongsTo(PrintJob::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    public function printable(): MorphTo
    {
        return $this->morphTo();
    }
}
