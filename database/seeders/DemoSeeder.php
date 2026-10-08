<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\CertificateTemplate;
use App\Models\IdCardTemplate;
use App\Models\Role;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use App\Services\Documents\DesignPresets;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Demo data: Bangulo Secondary School (BNG) and Benjamin William Mkapa High
 * School (BWM) with users for every role, students, staff and templates.
 *
 * All demo accounts use DEMO_PASSWORD from .env (see DEFAULT_PASSWORD below).
 */
class DemoSeeder extends Seeder
{
    public const DEFAULT_PASSWORD = 'Demo@2026';

    private const FIRST_NAMES_F = ['Amina', 'Neema', 'Rehema', 'Zawadi', 'Asha', 'Halima', 'Grace', 'Upendo', 'Mwanaisha', 'Faraja', 'Nasra', 'Saida', 'Esther', 'Agnes', 'Joyce'];

    private const FIRST_NAMES_M = ['Baraka', 'Juma', 'Emmanuel', 'Hamisi', 'Musa', 'Daudi', 'Godfrey', 'Rashidi', 'Idrisa', 'Elia', 'Abdallah', 'Frank', 'Yusuph', 'Peter', 'Salim'];

    private const MIDDLE_NAMES = ['Saidi', 'Hassani', 'Joseph', 'Ally', 'John', 'Omari', 'Paulo', 'Athumani', 'Petro', 'Selemani', null, null];

    private const LAST_NAMES = ['Mwakyusa', 'Kimaro', 'Mushi', 'Massawe', 'Mrema', 'Juma', 'Mbwana', 'Kibona', 'Lyimo', 'Ngowi', 'Komba', 'Mollel', 'Shirima', 'Mtui', 'Swai', 'Temba'];

    public function run(TenantContext $tenant): void
    {
        $password = env('DEMO_PASSWORD', self::DEFAULT_PASSWORD);
        $roles = Role::pluck('id', 'slug');

        $this->user('System Administrator', 'superadmin@demo.local', $password, $roles[Role::SUPER_ADMIN], null);

        $bangulo = $this->bangulo();
        $bwm = $this->benjaminMkapa();

        $this->user('Bangulo Admin', 'admin@bangulo.demo', $password, $roles[Role::SCHOOL_ADMIN], $bangulo->id);
        $this->user('Bangulo Registrar', 'registrar@bangulo.demo', $password, $roles[Role::REGISTRAR], $bangulo->id);
        $this->user('Benjamin Mkapa Admin', 'admin@bwm.demo', $password, $roles[Role::SCHOOL_ADMIN], $bwm->id);
        $this->user('BWM Registrar', 'registrar@bwm.demo', $password, $roles[Role::REGISTRAR], $bwm->id);
        $this->user('BWM Printer', 'printer@bwm.demo', $password, $roles[Role::PRINTER], $bwm->id);
        $this->user('BWM Viewer', 'viewer@bwm.demo', $password, $roles[Role::VIEWER], $bwm->id);

        $this->globalTemplates($tenant);

        $tenant->runFor($bangulo->id, function () use ($bangulo) {
            $year = $this->academicYears(['2025', '2026']);
            $this->students($bangulo, $year, 'O-Level', ['Form I', 'Form II', 'Form III', 'Form IV'], ['A', 'B'], 8, 'BNG');
            $this->staff('BNG', 6);
        });

        $tenant->runFor($bwm->id, function () use ($bwm) {
            $year = $this->academicYears(['2025', '2026']);
            $this->students($bwm, $year, 'O-Level', ['Form I', 'Form II', 'Form III', 'Form IV'], ['A', 'B'], 6, 'BWM');
            $this->students($bwm, $year, 'A-Level', ['Form V', 'Form VI'], ['PCM', 'PCB', 'ECA', 'HGL', 'EGM'], 4, 'BWM');
            $this->staff('BWM', 8);
            $this->bwmTemplates($bwm);
        });
    }

    private function user(string $name, string $email, string $password, int $roleId, ?int $schoolId): void
    {
        $user = User::firstOrNew(['email' => $email]);
        $user->fill(['name' => $name, 'password' => $password, 'status' => 'active']);
        $user->role_id = $roleId;
        $user->school_id = $schoolId;
        $user->email_verified_at = now();
        $user->save();
    }

    private function bangulo(): School
    {
        $school = School::updateOrCreate(['school_code' => 'BNG'], [
            'name' => 'Bangulo Secondary School',
            'short_name' => 'Bangulo SS',
            'registration_number' => 'S.4512',
            'address' => 'P.O. Box 45, Bangulo, Pugu',
            'region' => 'Dar es Salaam',
            'district' => 'Ilala',
            'ward' => 'Pugu',
            'phone' => '+255 754 000 111',
            'email' => 'info@bangulo.sc.tz',
            'website' => 'www.bangulo.sc.tz',
            'principal_name' => 'Mr. Hamisi K. Mbwana',
            'primary_color' => '#14532d',
            'secondary_color' => '#ca8a04',
            'student_id_format' => '{CODE}/{YEAR}/{SEQ:4}',
            'staff_id_format' => '{CODE}/STF/{YEAR}/{SEQ:4}',
            'certificate_number_format' => '{CODE}/CERT/{YEAR}/{SEQ:5}',
            'status' => 'active',
        ]);

        if (! $school->logo_path) {
            $school->logo_path = $this->crest("schools/{$school->id}/logo.png", 'BSS', [20, 83, 45], [202, 138, 4]);
            $school->save();
        }

        return $school;
    }

    private function benjaminMkapa(): School
    {
        $school = School::updateOrCreate(['school_code' => 'BWM'], [
            'name' => 'Benjamin William Mkapa High School',
            'short_name' => 'BWM High School',
            'address' => 'P.O. Box 9071, Dar es Salaam',
            'region' => 'Dar es Salaam',
            'district' => 'Ilala',
            'phone' => '0222 - 184725',
            'email' => 'bwmhs98@gmail.com',
            'website' => 'www.benjamin.sc.tz',
            'principal_name' => 'Headmaster',
            'primary_color' => '#1b626e', // A-Level teal (systems/benja)
            'secondary_color' => '#cc9933', // O-Level gold
            'student_id_format' => '{CODE}/{YEAR}/{SEQ:4}',
            'staff_id_format' => '{CODE}/STF/{YEAR}/{SEQ:4}',
            'certificate_number_format' => '{CODE}/CERT/{YEAR}/{SEQ:5}',
            'status' => 'active',
        ]);

        // Real BWM artwork shipped in database/seeders/assets/bwm.
        $school->logo_path ??= $this->copyAsset('logo.png', "schools/{$school->id}/logo.png");
        $school->principal_signature_path ??= $this->copyAsset('signature.png', "schools/{$school->id}/signature.png");
        $school->save();

        return $school;
    }

    /** @param  array<int, string>  $names  the last one becomes current */
    private function academicYears(array $names): AcademicYear
    {
        $year = null;
        foreach ($names as $name) {
            $year = AcademicYear::firstOrCreate(['name' => $name], [
                'start_date' => "{$name}-01-01", 'end_date' => "{$name}-12-31", 'status' => 'active',
            ]);
        }
        $year->markCurrent();

        return $year;
    }

    /**
     * @param  array<int, string>  $classes
     * @param  array<int, string>  $streamsOrCombinations  A-Level uses combinations as streams
     */
    private function students(School $school, AcademicYear $year, string $level, array $classes, array $streamsOrCombinations, int $perGroup, string $prefix): void
    {
        if (Student::where('level', $level)->exists()) {
            return;
        }

        $currentYear = (int) $year->name;
        $seq = Student::withTrashed()->count();
        foreach ($classes as $classIndex => $class) {
            $yearsLeft = count($classes) - 1 - $classIndex;
            foreach ($streamsOrCombinations as $group) {
                for ($i = 0; $i < $perGroup; $i++) {
                    $seq++;
                    $female = ($seq % 2) === 0;
                    $completion = $currentYear + $yearsLeft;
                    Student::create([
                        'academic_year_id' => $year->id,
                        'admission_number' => sprintf('%s/%d/%04d', $prefix, $completion - ($level === 'A-Level' ? 2 : 4), $seq),
                        'first_name' => ($female ? self::FIRST_NAMES_F : self::FIRST_NAMES_M)[$seq % 15],
                        'middle_name' => self::MIDDLE_NAMES[$seq % count(self::MIDDLE_NAMES)],
                        'last_name' => self::LAST_NAMES[($seq * 7) % count(self::LAST_NAMES)],
                        'gender' => $female ? 'female' : 'male',
                        'date_of_birth' => sprintf('%d-%02d-%02d', $completion - ($level === 'A-Level' ? 19 : 17), ($seq % 12) + 1, ($seq % 27) + 1),
                        'nationality' => 'Tanzanian',
                        'level' => $level,
                        'class_name' => $class,
                        'stream' => $level === 'A-Level' ? null : $group,
                        'combination' => $level === 'A-Level' ? $group : null,
                        'entry_year' => $completion - ($level === 'A-Level' ? 2 : 4),
                        'completion_year' => $completion,
                        'parent_name' => self::FIRST_NAMES_M[($seq + 3) % 15].' '.self::LAST_NAMES[($seq * 7) % count(self::LAST_NAMES)],
                        'parent_phone' => sprintf('+255 7%02d %03d %03d', $seq % 100, ($seq * 37) % 1000, ($seq * 91) % 1000),
                        'address' => $school->district.', '.$school->region,
                        'status' => 'active',
                    ]);
                }
            }
        }
    }

    private function staff(string $prefix, int $count): void
    {
        if (Staff::exists()) {
            return;
        }

        $titles = [['Head of School', 'Administration'], ['Academic Master', 'Administration'], ['Senior Teacher', 'Science'],
            ['Teacher', 'Science'], ['Teacher', 'Arts'], ['Teacher', 'Languages'], ['Bursar', 'Finance'], ['Librarian', 'Library']];
        for ($i = 1; $i <= $count; $i++) {
            $female = $i % 2 === 0;
            [$title, $department] = $titles[($i - 1) % count($titles)];
            Staff::create([
                'employee_number' => sprintf('%s-EMP-%03d', $prefix, $i),
                'first_name' => ($female ? self::FIRST_NAMES_F : self::FIRST_NAMES_M)[($i * 5) % 15],
                'last_name' => self::LAST_NAMES[($i * 3) % count(self::LAST_NAMES)],
                'gender' => $female ? 'female' : 'male',
                'job_title' => $title,
                'department' => $department,
                'phone' => sprintf('+255 71%d %03d %03d', $i, $i * 111 % 1000, $i * 47 % 1000),
                'email' => strtolower($prefix)."staff{$i}@school.demo",
                'employment_status' => 'active',
            ]);
        }
    }

    /** Global templates (school_id NULL) usable by every school. */
    private function globalTemplates(TenantContext $tenant): void
    {
        $tenant->withoutScope(function () {
            foreach (['student_id_classic' => 'Student ID – Classic', 'staff_id_portrait' => 'Staff ID – Portrait'] as $key => $name) {
                $preset = DesignPresets::get($key);
                IdCardTemplate::whereNull('school_id')->firstOrCreate(['name' => $name], [
                    'type' => $preset['type'], 'width_mm' => $preset['width'], 'height_mm' => $preset['height'],
                    'orientation' => $preset['orientation'], 'dpi' => 300, 'has_back' => $preset['has_back'],
                    'design_json' => $preset['design'], 'status' => 'active',
                ]);
            }

            foreach ([
                'certificate_completion' => ['Certificate of Completion', 'Certificate of Completion', 'has successfully completed the prescribed course of secondary education.'],
                'certificate_merit' => ['Certificate of Merit', 'Certificate of Merit', 'in recognition of outstanding academic performance and exemplary conduct.'],
            ] as $key => [$name, $title, $body]) {
                $preset = DesignPresets::get($key);
                CertificateTemplate::whereNull('school_id')->firstOrCreate(['name' => $name], [
                    'paper_size' => 'A4', 'orientation' => $preset['orientation'], 'width_mm' => $preset['width'], 'height_mm' => $preset['height'],
                    'design_json' => $preset['design'], 'default_title' => $title, 'default_body' => $body, 'status' => 'active',
                ]);
            }
        });
    }

    /** BWM's own A-Level / O-Level ID cards (systems/benja design) with the school's header photo and watermark. */
    private function bwmTemplates(School $school): void
    {
        // Colour-enhanced (vivid) versions print strong instead of pale; see ImageService::enhanceColors().
        $header = $this->copyAsset('header-vivid.jpg', "templates/{$school->id}/bwm-header-vivid.jpg");
        $watermark = $this->copyAsset('watermark.jpg', "templates/{$school->id}/bwm-watermark.jpg");
        // The school's own finished back design ("If found please return to…"), used as is.
        $back = $this->copyAsset('back-card-vivid.jpg', "templates/{$school->id}/bwm-back-vivid.jpg");

        foreach (['alevel' => 'BWM A-Level Student ID', 'olevel' => 'BWM O-Level Student ID'] as $variant => $name) {
            $design = DesignPresets::photoHeader($variant, $header, $watermark);
            $design['back'] = DesignPresets::imageSide($back);

            IdCardTemplate::firstOrCreate(['name' => $name], [
                'type' => IdCardTemplate::TYPE_STUDENT, 'width_mm' => IdCardTemplate::CR80_WIDTH, 'height_mm' => IdCardTemplate::CR80_HEIGHT,
                'orientation' => 'landscape', 'dpi' => 300, 'has_back' => true, 'status' => 'active', 'is_default' => $variant === 'alevel',
                'design_json' => $design,
            ]);
        }
    }

    private function copyAsset(string $file, string $to): string
    {
        $disk = Storage::disk('public');
        if (! $disk->exists($to)) {
            $disk->put($to, file_get_contents(database_path("seeders/assets/bwm/{$file}")));
        }

        return $to;
    }

    /**
     * Simple round crest with initials for schools without a logo upload.
     *
     * @param  array{int, int, int}  $fill
     * @param  array{int, int, int}  $ring
     */
    private function crest(string $path, string $initials, array $fill, array $ring): string
    {
        $size = 400;
        $im = imagecreatetruecolor($size, $size);
        imagesavealpha($im, true);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
        imagefilledellipse($im, 200, 200, 390, 390, imagecolorallocate($im, ...$ring));
        imagefilledellipse($im, 200, 200, 340, 340, imagecolorallocate($im, ...$fill));
        $font = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');
        $white = imagecolorallocate($im, 255, 255, 255);
        $box = imagettfbbox(90, 0, $font, $initials);
        imagettftext($im, 90, 0, (int) (200 - ($box[2] - $box[0]) / 2), 235, $white, $font, $initials);

        ob_start();
        imagepng($im);
        Storage::disk('public')->put($path, ob_get_clean());

        return $path;
    }
}
