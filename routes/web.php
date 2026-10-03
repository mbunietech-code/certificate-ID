<?php

use App\Http\Controllers\AcademicYearController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\CertificateTemplateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IdCardController;
use App\Http\Controllers\IdCardTemplateController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\PrintCenterController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SchoolContextController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\SchoolProfileController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentImportController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VerificationController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('home');

/*
| Public QR verification (rate limited, no login)
*/
Route::middleware('throttle:verification')->prefix('verify')->name('verify.')->group(function () {
    Route::get('/', [VerificationController::class, 'index'])->name('index');
    Route::post('/', [VerificationController::class, 'lookup'])->name('lookup');
    Route::get('certificate/{code}', [VerificationController::class, 'certificate'])->name('certificate')->where('code', '[A-Za-z0-9\-]{6,40}');
    Route::get('id/{code}', [VerificationController::class, 'idCard'])->name('id')->where('code', '[A-Za-z0-9\-]{6,40}');
});

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'login'])->middleware('throttle:login');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');

    // School context (super admin school selector)
    Route::get('context/select', [SchoolContextController::class, 'select'])->name('context.select');
    Route::post('context/school', [SchoolContextController::class, 'switch'])->name('context.switch');

    // Schools
    Route::resource('schools', SchoolController::class)->except(['destroy']);
    Route::patch('schools/{school}/status', [SchoolController::class, 'toggleStatus'])->name('schools.status');
    Route::middleware('school.selected')->group(function () {
        Route::get('school-profile', [SchoolProfileController::class, 'edit'])->name('school-profile.edit');
        Route::put('school-profile', [SchoolProfileController::class, 'update'])->name('school-profile.update');
    });

    // Academic years
    Route::resource('academic-years', AcademicYearController::class)->except(['show']);
    Route::post('academic-years/{academic_year}/current', [AcademicYearController::class, 'makeCurrent'])->name('academic-years.current');

    // Students (+ import / export / bulk)
    Route::get('students/export', [StudentController::class, 'export'])->name('students.export');
    Route::post('students/bulk', [StudentController::class, 'bulk'])->name('students.bulk');
    Route::get('students/import/template', [StudentImportController::class, 'template'])->name('students.import.template');
    Route::middleware('school.selected')->group(function () {
        Route::get('students/import', [StudentImportController::class, 'create'])->name('students.import');
        Route::post('students/import', [StudentImportController::class, 'store'])->name('students.import.store');
    });
    Route::get('students/import/{import}', [StudentImportController::class, 'show'])->name('students.import.show');
    Route::post('students/import/{import}/confirm', [StudentImportController::class, 'confirm'])->name('students.import.confirm');
    Route::delete('students/import/{import}', [StudentImportController::class, 'destroy'])->name('students.import.cancel');
    Route::post('students/{id}/restore', [StudentController::class, 'restore'])->whereNumber('id')->name('students.restore');
    Route::resource('students', StudentController::class);

    // Staff
    Route::get('staff/export', [StaffController::class, 'export'])->name('staff.export');
    Route::resource('staff', StaffController::class)->parameters(['staff' => 'staff']);

    // Templates (ID cards & certificates share one controller base; explicit bindings in AppServiceProvider)
    Route::prefix('templates')->name('templates.')->group(function () {
        Route::post('assets', [IdCardTemplateController::class, 'uploadAsset'])->name('assets.store');

        foreach (['id-cards' => [IdCardTemplateController::class, 'idTemplate'], 'certificates' => [CertificateTemplateController::class, 'certTemplate']] as $prefix => [$controller, $param]) {
            Route::get("{$prefix}/{{$param}}/design", [$controller, 'design'])->name("{$prefix}.design");
            Route::put("{$prefix}/{{$param}}/design", [$controller, 'saveDesign'])->name("{$prefix}.design.save");
            Route::get("{$prefix}/{{$param}}/preview", [$controller, 'preview'])->name("{$prefix}.preview");
            Route::post("{$prefix}/{{$param}}/duplicate", [$controller, 'duplicate'])->name("{$prefix}.duplicate");
            Route::resource($prefix, $controller)->except(['show'])->parameters([$prefix => $param]);
        }
    });

    // ID cards
    Route::middleware('school.selected')->group(function () {
        Route::get('id-cards/generate', [IdCardController::class, 'create'])->name('id-cards.generate');
        Route::post('id-cards/preview', [IdCardController::class, 'preview'])->name('id-cards.preview');
        Route::post('id-cards/generate', [IdCardController::class, 'store'])->name('id-cards.store');
        Route::post('id-cards/reprint', [IdCardController::class, 'reprint'])->name('id-cards.reprint');
    });
    Route::get('id-cards', [IdCardController::class, 'index'])->name('id-cards.index');
    Route::get('id-cards/{idCard}/front.png', [IdCardController::class, 'frontPng'])->name('id-cards.front-png');
    Route::get('id-cards/{idCard}', [IdCardController::class, 'show'])->name('id-cards.show');
    Route::post('id-cards/{idCard}/revoke', [IdCardController::class, 'revoke'])->name('id-cards.revoke');

    // Certificates
    Route::middleware('school.selected')->group(function () {
        Route::get('certificates/generate', [CertificateController::class, 'create'])->name('certificates.generate');
        Route::post('certificates/preview', [CertificateController::class, 'preview'])->name('certificates.preview');
        Route::post('certificates/generate', [CertificateController::class, 'store'])->name('certificates.store');
        Route::post('certificates/reprint', [CertificateController::class, 'reprint'])->name('certificates.reprint');
    });
    Route::get('certificates', [CertificateController::class, 'index'])->name('certificates.index');
    Route::get('certificates/{certificate}', [CertificateController::class, 'show'])->name('certificates.show');
    Route::post('certificates/{certificate}/revoke', [CertificateController::class, 'revoke'])->name('certificates.revoke');

    // Print center & history
    Route::get('print-center', [PrintCenterController::class, 'index'])->name('print.index');
    Route::get('print-history', [PrintCenterController::class, 'history'])->name('print.history');
    Route::get('print-history/export', [PrintCenterController::class, 'exportHistory'])->name('print.history.export');
    Route::get('print-jobs/{printJob}', [PrintCenterController::class, 'show'])->name('print.show');
    Route::get('print-jobs/{printJob}/status', [PrintCenterController::class, 'status'])->name('print.status');
    Route::get('print-jobs/{printJob}/print', [PrintCenterController::class, 'browserPrint'])->name('print.browser');
    Route::get('print-jobs/{printJob}/pdf', [PrintCenterController::class, 'download'])->name('print.pdf');
    Route::post('print-jobs/{printJob}/retry', [PrintCenterController::class, 'retry'])->name('print.retry');
    Route::post('print-jobs/{printJob}/cancel', [PrintCenterController::class, 'cancel'])->name('print.cancel');

    // Reports
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/{report}', [ReportController::class, 'show'])->name('reports.show');

    // Users & roles
    Route::resource('users', UserController::class)->except(['show']);
    Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
    Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');

    // Settings & audit
    Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit.index');
});
