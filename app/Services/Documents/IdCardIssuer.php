<?php

namespace App\Services\Documents;

use App\Models\AcademicYear;
use App\Models\IdCard;
use App\Models\IdCardTemplate;
use App\Models\Staff;
use App\Models\Student;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class IdCardIssuer
{
    public const MODE_REUSE = 'reuse';

    public const MODE_NEW = 'new';

    public function __construct(private NumberGenerator $numbers, private AuditLogger $audit) {}

    /**
     * Issue (or reuse) the ID card of a student/staff member.
     *
     * reuse: an active card for the same holder and academic year is reprinted with the chosen template.
     * new:   existing active cards are marked "replaced" and a new number is allocated.
     */
    public function issue(Student|Staff $holder, IdCardTemplate $template, ?AcademicYear $year, string $mode = self::MODE_REUSE, ?int $userId = null): IdCard
    {
        $expectedType = $holder instanceof Staff ? IdCardTemplate::TYPE_STAFF : IdCardTemplate::TYPE_STUDENT;
        if ($template->type !== $expectedType) {
            throw new InvalidArgumentException('The template type does not match the card holder.');
        }
        if ($template->school_id !== null && $template->school_id !== $holder->school_id) {
            throw new InvalidArgumentException('The template belongs to another school.');
        }

        return DB::transaction(function () use ($holder, $template, $year, $mode, $userId) {
            $existing = IdCard::forSchool($holder->school_id)
                ->whereMorphedTo('holder', $holder)
                ->where('status', 'active')
                ->when($year, fn ($q) => $q->where('academic_year_id', $year->id))
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($existing && $mode === self::MODE_REUSE) {
                $existing->forceFill(['template_id' => $template->id])->save();

                return $existing;
            }

            if ($existing) {
                IdCard::forSchool($holder->school_id)->whereMorphedTo('holder', $holder)
                    ->where('status', 'active')->update(['status' => 'replaced']);
            }

            $school = $holder->school;
            $isStaff = $holder instanceof Staff;
            $format = $isStaff
                ? ($school->staff_id_format ?: setting('default_staff_id_format'))
                : ($school->student_id_format ?: setting('default_student_id_format'));

            $number = $this->numbers->next(
                $school,
                $isStaff ? NumberGenerator::TYPE_STAFF_ID : NumberGenerator::TYPE_STUDENT_ID,
                $format,
                $year?->name,
                fn (string $candidate) => IdCard::withoutGlobalScopes()->where('card_number', $candidate)->exists(),
            );

            $expires = ($year?->end_date ?? now()->addMonths((int) setting('id_card_validity_months', 12)))->toDateString();

            $card = new IdCard;
            $card->forceFill([
                'school_id' => $holder->school_id,
                'holder_type' => $holder->getMorphClass(),
                'holder_id' => $holder->getKey(),
                'template_id' => $template->id,
                'academic_year_id' => $year?->id,
                'card_number' => $number,
                'verification_code' => self::verificationCode(),
                'issued_at' => now(),
                'expires_at' => $expires,
                'status' => 'active',
                'issued_by' => $userId,
            ])->save();

            $this->audit->log('id_card.generated', $card, "Issued ID {$number} to {$holder->full_name}", [], $holder->school_id, $userId);

            return $card;
        });
    }

    public static function verificationCode(): string
    {
        // 20 random alphanumerics ≈ 119 bits: unguessable, so QR URLs cannot be enumerated.
        return Str::random(20);
    }
}
