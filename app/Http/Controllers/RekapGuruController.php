<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AbsensiGuru;
use App\Models\Guru;
use App\Exports\RekapGuruExport;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class RekapGuruController extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->input('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', now()->format('Y-m-d'));
        $guruId = $request->input('guru_id');

        // Query Absensi Harian (jadwal_pelajaran_id IS NULL)
        $query = AbsensiGuru::with(['guru'])
            ->whereNull('jadwal_pelajaran_id')
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->orderBy('tanggal', 'desc')
            ->orderBy('jam_masuk', 'desc');

        if ($guruId) {
            $query->where('guru_id', $guruId);
        }

        // Filter by school_id for non-super admin users
        if (auth()->user() && !auth()->user()->isSuperAdmin()) {
            $query->where('school_id', auth()->user()->school_id);
        }

        // Statistics based on the full query
        $stats = [
            'total' => clone $query,
            'hadir' => clone $query,
            'tidak_hadir' => clone $query,
        ];

        $stats = [
            'total' => $stats['total']->count(),
            'hadir' => $stats['hadir']->where('status', 'Hadir')->count(),
            'tidak_hadir' => $stats['tidak_hadir']->where('status', '!=', 'Hadir')->count(),
        ];

        // Search functionality for guru name
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->whereHas('guru', function($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%");
            });
        }

        $absensi = $query->paginate(50)->withQueryString();

        $gurusQuery = Guru::orderBy('nama');
        if (auth()->user() && !auth()->user()->isSuperAdmin()) {
            $gurusQuery->where('school_id', auth()->user()->school_id);
        }
        $gurus = $gurusQuery->get();

        return view('rekap-guru.index', compact('absensi', 'gurus', 'startDate', 'endDate', 'guruId', 'stats'));
    }

    public function export(Request $request)
    {
        $startDate = $request->input('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', now()->format('Y-m-d'));
        $guruId = $request->input('guru_id');

        $fileName = 'rekap-absensi-guru-' . $startDate . '-to-' . $endDate . '.xlsx';

        return Excel::download(new RekapGuruExport($startDate, $endDate, $guruId), $fileName);
    }

    public function printPdf(Request $request)
    {
        $startDate = $request->input('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', now()->format('Y-m-d'));
        $guruId = $request->input('guru_id');

        $query = AbsensiGuru::with(['guru', 'jadwal.mapel', 'jadwal.kelas'])
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->orderBy('tanggal', 'asc')
            ->orderBy('waktu_hadir', 'asc');

        if ($guruId) {
            $query->where('guru_id', $guruId);
        }

        $absensi = $query->get();

        $stats = [
            'total' => $absensi->count(),
            'hadir' => $absensi->where('status', 'Hadir')->count(),
            'tidak_hadir' => $absensi->where('status', 'Tidak Hadir')->count(),
        ];

        // Fetch Metadata for Header
        $schoolId = auth()->user()->isSuperAdmin() ? ($guruId ? Guru::find($guruId)->school_id : null) : auth()->user()->school_id;
        // If super admin and no guru specific selected, maybe pick first from result?
        if (!$schoolId && $absensi->count() > 0) {
            $schoolId = $absensi->first()->school_id;
        }

        $schoolName = \App\Models\Setting::where('school_id', $schoolId)->where('setting_key', 'nama_sekolah')->value('setting_value');
        $schoolAddress = \App\Models\Setting::where('school_id', $schoolId)->where('setting_key', 'alamat_sekolah')->value('setting_value');
        $kopSurat = \App\Models\Setting::where('school_id', $schoolId)->where('setting_key', 'kop_surat')->value('setting_value');

        $pdf = Pdf::loadView('rekap-guru.pdf', compact('absensi', 'startDate', 'endDate', 'stats', 'schoolName', 'schoolAddress', 'kopSurat'));
        $pdf->setPaper('a4', 'landscape');

        return $pdf->download('rekap-absensi-guru-' . $startDate . '-to-' . $endDate . '.pdf');
    }
}
