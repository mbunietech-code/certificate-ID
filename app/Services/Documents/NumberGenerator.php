<?php

namespace App\Services\Documents;

use App\Models\School;
use Closure;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Generates unique document numbers from per-school formats such as
 * "{CODE}/CERT/{YEAR}/{SEQ:5}" -> "BNG/CERT/2026/00001".
 *
 * Tokens: {CODE} school code, {YEAR} 4-digit year, {YY} 2-digit year,
 * {MM} month, {SEQ:n} zero-padded sequence (n = 1..10).
 * The sequence resets per year when the format contains a year token.
 */
class NumberGenerator
{
    public const TYPE_STUDENT_ID = 'student_id';

    public const TYPE_STAFF_ID = 'staff_id';

    public const TYPE_CERTIFICATE = 'certificate';

    public const TYPE_PRINT_JOB = 'print_job';

    public static function validateFormat(string $format): ?string
    {
        if (! preg_match('/\{SEQ(:\d{1,2})?\}/', $format)) {
            return 'The format must contain a {SEQ} or {SEQ:n} token.';
        }
        if (preg_match_all('/\{([A-Z]+)(?::\d+)?\}/', $format, $m)) {
            $unknown = array_diff($m[1], ['CODE', 'YEAR', 'YY', 'MM', 'SEQ']);
            if ($unknown) {
                return 'Unknown token(s): '.implode(', ', array_map(fn ($t) => '{'.$t.'}', $unknown));
            }
        }
        if (! preg_match('/^[A-Za-z0-9{}:\/\-_. ]+$/', $format)) {
            return 'The format may only contain letters, numbers, tokens and / - _ . characters.';
        }

        return null;
    }

    /** Render a sample number without consuming the sequence. */
    public static function preview(string $format, string $code, ?string $year = null, int $seq = 1): string
    {
        return self::render($format, $code, self::resolveYear($year), $seq);
    }

    /**
     * Reserve the next number. $isTaken guards against collisions with numbers
     * produced by older formats; the counter just advances past them.
     *
     * @param  Closure(string): bool  $isTaken
     */
    public function next(School $school, string $type, string $format, ?string $year, Closure $isTaken): string
    {
        if ($error = self::validateFormat($format)) {
            throw new InvalidArgumentException($error);
        }

        $year = self::resolveYear($year);
        $period = preg_match('/\{(YEAR|YY)\}/', $format) ? $year : 'all';

        return DB::transaction(function () use ($school, $type, $format, $year, $period, $isTaken) {
            DB::table('number_sequences')->insertOrIgnore([
                'school_id' => $school->id,
                'type' => $type,
                'period' => $period,
                'last_value' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $row = DB::table('number_sequences')
                ->where(['school_id' => $school->id, 'type' => $type, 'period' => $period])
                ->lockForUpdate()
                ->first();

            $value = (int) $row->last_value;

            for ($attempt = 0; $attempt < 1000; $attempt++) {
                $value++;
                $number = self::render($format, $school->school_code, $year, $value);
                if (! $isTaken($number)) {
                    DB::table('number_sequences')->where('id', $row->id)->update(['last_value' => $value, 'updated_at' => now()]);

                    return $number;
                }
            }

            throw new RuntimeException("Could not allocate a unique {$type} number for {$school->school_code}.");
        });
    }

    private static function render(string $format, string $code, string $year, int $seq): string
    {
        $out = strtr($format, [
            '{CODE}' => strtoupper($code),
            '{YEAR}' => $year,
            '{YY}' => substr($year, -2),
            '{MM}' => now()->format('m'),
        ]);

        return preg_replace_callback('/\{SEQ(?::(\d{1,2}))?\}/', fn ($m) => str_pad((string) $seq, (int) ($m[1] ?? 1), '0', STR_PAD_LEFT), $out);
    }

    private static function resolveYear(?string $year): string
    {
        if ($year && preg_match('/(\d{4})/', $year, $m)) {
            return $m[1];
        }

        return now()->format('Y');
    }
}
