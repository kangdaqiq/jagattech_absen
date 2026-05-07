<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ApiLog;

class ApiLogController extends Controller
{
    public function index(Request $request)
    {
        $query = ApiLog::with('school')->orderBy('created_at', 'desc');

        $isSuperAdmin = auth()->user()->isSuperAdmin();

        if (!$isSuperAdmin) {
            $schoolId = auth()->user()->school_id;
            if ($schoolId) {
                $query->where('school_id', $schoolId);
            } else {
                $query->whereRaw('0 = 1');
            }
        }

        // Filter: tab (auth_failed / semua)
        $tab = $request->input('tab', 'all');
        if ($tab === 'auth_failed') {
            $query->where('action', 'auth_failed');
        } elseif ($tab === 'failed') {
            $query->where('success', false)->where('action', '!=', 'auth_failed');
        }

        // Filter: IP Address
        if ($request->filled('ip')) {
            $query->where('ip_address', $request->ip);
        }

        // Filter: tanggal
        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        $logs           = $query->paginate(25)->withQueryString();
        $authFailCount  = (clone ApiLog::query())
            ->when(!$isSuperAdmin, fn($q) => $q->where('school_id', auth()->user()->school_id))
            ->where('action', 'auth_failed')
            ->whereDate('created_at', today())
            ->count();

        return view('api_logs.index', compact('logs', 'tab', 'authFailCount', 'isSuperAdmin'));
    }
}
