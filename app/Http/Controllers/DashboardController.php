<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\IdCard;
use App\Models\PrintJob;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $school = $this->tenant()->school();

        $stats = [
            'students' => Student::where('status', 'active')->count(),
            'staff' => Staff::where('employment_status', 'active')->count(),
            'id_cards' => IdCard::count(),
            'ids_printed' => Student::where('status', 'active')->idPrinted()->count(),
            'certificates' => Certificate::count(),
            'pending_jobs' => PrintJob::whereIn('status', ['pending', 'processing'])->count(),
            'completed_jobs' => PrintJob::where('status', 'completed')->count(),
            'print_jobs' => PrintJob::count(),
        ];

        if (! $school) {
            $stats['schools'] = School::count();
            $stats['active_schools'] = School::active()->count();
        }

        return view('dashboard', [
            'school' => $school,
            'stats' => $stats,
            'activity' => $this->dailyActivity(),
            'byClass' => $school ? Student::where('status', 'active')
                ->select('class_name', DB::raw('count(*) as total'))
                ->groupBy('class_name')->orderBy('class_name')->pluck('total', 'class_name') : collect(),
            'schools' => $school ? collect() : School::withCount([
                'students' => fn ($q) => $q->where('status', 'active'),
                'staff' => fn ($q) => $q->where('employment_status', 'active'),
            ])->orderBy('name')->limit(15)->get(),
            'recentJobs' => PrintJob::with(['user:id,name', 'school:id,school_code,name'])->latest('id')->limit(8)->get(),
        ]);
    }

    /** @return array<string, array{ids: int, certificates: int}> documents generated per day, last 14 days */
    private function dailyActivity(): array
    {
        $from = Carbon::today()->subDays(13);
        $days = [];
        for ($d = $from->copy(); $d->lte(Carbon::today()); $d->addDay()) {
            $days[$d->toDateString()] = ['ids' => 0, 'certificates' => 0];
        }

        $dateExpr = DB::connection()->getDriverName() === 'sqlite' ? 'date(created_at)' : 'DATE(created_at)';
        foreach (['ids' => IdCard::query(), 'certificates' => Certificate::query()] as $key => $query) {
            $rows = $query->where('created_at', '>=', $from)
                ->selectRaw("{$dateExpr} as day, count(*) as total")
                ->groupBy('day')->pluck('total', 'day');
            foreach ($rows as $day => $total) {
                if (isset($days[$day])) {
                    $days[$day][$key] = (int) $total;
                }
            }
        }

        return $days;
    }
}
