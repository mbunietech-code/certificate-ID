<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An issued certificate. Created only by App\Services\Documents\CertificateIssuer.
 */
class Certificate extends Model
{
    use BelongsToSchool;

    protected $guarded = ['*'];

    public const STATUSES = ['valid', 'revoked'];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'last_printed_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplate::class, 'template_id')->withTrashed();
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function isValid(): bool
    {
        return $this->status === 'valid';
    }

    public function verificationUrl(): string
    {
        return verification_url('certificate', $this->verification_code);
    }
}
