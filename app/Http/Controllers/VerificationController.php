<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\IdCard;
use App\Models\VerificationLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Public, rate-limited verification of QR codes and document numbers.
 * Shows only what is needed to confirm authenticity — never contact
 * details, date of birth, parents or photos.
 */
class VerificationController extends Controller
{
    public function index(): View
    {
        return view('verify.index');
    }

    /** Manual lookup by document number (as printed on the card/certificate). */
    public function lookup(Request $request): RedirectResponse|View
    {
        $data = $request->validate([
            'type' => ['required', 'in:certificate,id'],
            'number' => ['required', 'string', 'max:60'],
        ]);
        $number = trim($data['number']);

        $document = $this->tenant()->withoutScope(fn () => $data['type'] === 'certificate'
            ? Certificate::where('certificate_number', $number)->first()
            : IdCard::where('card_number', $number)->first());

        return $this->result($request, $data['type'] === 'certificate' ? 'certificate' : 'id_card', $number, $document);
    }

    public function certificate(Request $request, string $code): View
    {
        $document = $this->tenant()->withoutScope(fn () => Certificate::where('verification_code', $code)->first());

        return $this->result($request, 'certificate', $code, $document);
    }

    public function idCard(Request $request, string $code): View
    {
        $document = $this->tenant()->withoutScope(fn () => IdCard::where('verification_code', $code)->first());

        return $this->result($request, 'id_card', $code, $document);
    }

    private function result(Request $request, string $type, string $lookup, IdCard|Certificate|null $document): View
    {
        $result = match (true) {
            ! $document => 'not_found',
            $document->isValid() => 'valid',
            default => 'invalid',
        };

        VerificationLog::forceCreate([
            'document_type' => $type,
            'lookup' => Str::limit($lookup, 78, ''),
            'document_id' => $document?->id,
            'school_id' => $document?->school_id,
            'result' => $result,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ]);

        $details = null;
        if ($document) {
            $details = $this->tenant()->withoutScope(fn () => $document instanceof Certificate
                ? $this->certificateDetails($document)
                : $this->cardDetails($document));
        }

        return view('verify.result', compact('type', 'result', 'details', 'document'));
    }

    /** @return array<string, mixed> */
    private function cardDetails(IdCard $card): array
    {
        $card->load(['school:id,name,logo_path', 'academicYear:id,name', 'holder']);
        $holder = $card->holder;

        return [
            'school' => $card->school,
            'Name' => $holder?->full_name,
            'Type' => $card->holderKind().' ID card',
            'ID number' => $card->card_number,
            $card->holder_type === 'staff' ? 'Position' : 'Class' => $card->holder_type === 'staff' ? $holder?->job_title : $holder?->classLabel(),
            'Academic year' => $card->academicYear?->name,
            'Date issued' => format_date($card->issued_at),
            'Valid until' => format_date($card->expires_at),
            'Status' => match (true) {
                $card->status === 'active' && $card->isValid() => 'Active',
                $card->status === 'active' => 'Expired',
                default => ucfirst($card->status),
            },
        ];
    }

    /** @return array<string, mixed> */
    private function certificateDetails(Certificate $certificate): array
    {
        $certificate->load(['school:id,name,logo_path', 'academicYear:id,name']);

        return [
            'school' => $certificate->school,
            'Recipient' => $certificate->recipient_name,
            'Type' => $certificate->title,
            'Programme' => $certificate->program,
            'Certificate number' => $certificate->certificate_number,
            'Academic year' => $certificate->academicYear?->name,
            'Date issued' => format_date($certificate->issued_on),
            'Status' => $certificate->isValid() ? 'Valid' : 'Revoked',
        ];
    }
}
