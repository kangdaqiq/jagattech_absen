<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Siswa;
use App\Models\Attendance;
use App\Models\ApiLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LiveDashboardController extends Controller
{
    public function index()
    {
        return view('live_dashboard.index');
    }

    public function data()
    {
        $schoolId = auth()->user()->school_id;
        $today = Carbon::today()->format('Y-m-d');

        // 1. Counters
        $totalSiswa = Siswa::where('school_id', $schoolId)->count();
        
        $attendanceToday = Attendance::where('tanggal', $today)
            ->whereHas('student', function($q) use ($schoolId) {
                $q->where('school_id', $schoolId);
            })
            ->get();

        $hadirCount = $attendanceToday->where('status', 'H')->count();
        $alphaCount = $attendanceToday->where('status', 'A')->count();
        $izinCount  = $attendanceToday->where('status', 'I')->count();
        $sakitCount = $attendanceToday->where('status', 'S')->count();
        $bolosCount = $attendanceToday->where('status', 'B')->count();

        $totalAbsen = $alphaCount + $izinCount + $sakitCount + $bolosCount;
        $belumAbsen = $totalSiswa - ($hadirCount + $totalAbsen);

        // 2. Real-time Logs (Latest 15 API Logs for this school)
        // We use ApiLog to show "Live tapping" events
        $logs = ApiLog::where('school_id', $schoolId)
            ->whereIn('action', ['checkin_success', 'checkout_success', 'gate_access', 'unknown_card', 'auth_failed'])
            ->orderBy('created_at', 'desc')
            ->limit(15)
            ->get()
            ->map(function($log) {
                return [
                    'time' => Carbon::parse($log->created_at)->format('H:i:s'),
                    'action' => $log->action,
                    'message' => $log->message,
                    'success' => $log->success,
                    'uid' => $log->uid
                ];
            });

        return response()->json([
            'stats' => [
                'total' => $totalSiswa,
                'absen' => $totalAbsen,
                'belum' => $belumAbsen > 0 ? $belumAbsen : 0,
                'hadir' => $hadirCount,
                'alpha' => $alphaCount,
                'izin' => $izinCount,
                'sakit' => $sakitCount,
                'bolos' => $bolosCount,
            ],
            'logs' => $logs
        ]);
    }
}
