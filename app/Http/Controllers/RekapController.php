<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Siswa;
use App\Models\Kelas;
use Illuminate\Http\Request;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Barryvdh\DomPDF\Facade\Pdf;

class RekapController extends Controller
{
    public function index(Request $request)
    {
        // Default range: First to last day of current month
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));
        $kelasId = $request->input('kelas_id');

        // Fetch students
        // If Student, redirect to their own detail
        if (auth()->user()->role === 'student') {
            $siswa = auth()->user()->student;
            if ($siswa) {
                return redirect()->route('rekap.show', $siswa->id);
            }
            return redirect()->route('dashboard')->with('error', 'Akun belum terhubung dengan Data Siswa.');
        }

        $siswaQuery = Siswa::with('kelas')->orderBy('nama');

        // Filter by school_id for non-super admin users
        if (auth()->user() && !auth()->user()->isSuperAdmin()) {
            $siswaQuery->where('school_id', auth()->user()->school_id);
        }

        if ($kelasId) {
            $siswaQuery->where('kelas_id', $kelasId);
        }
        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $siswaQuery->where('nama', 'like', "%{$search}%");
        }

        $allSiswa = $siswaQuery->paginate(50)->withQueryString();

        // Fetch attendance in range
        $attendances = Attendance::whereBetween('tanggal', [$startDate, $endDate])
            ->whereIn('student_id', $allSiswa->pluck('id'))
            ->get();
        // Group by student_id then date? Or just keep raw and map in view logic.
        // Better: Map[student_id] => [H=>count, I=>count...] for summary

        $summary = [];
        foreach ($allSiswa as $s) {
            $summary[$s->id] = [
                'H' => 0,
                'I' => 0,
                'S' => 0,
                'A' => 0,
                'B' => 0,
                'T' => 0
            ];
        }

        foreach ($attendances as $att) {
            if (isset($summary[$att->student_id])) {
                $status = $att->status;
                if (isset($summary[$att->student_id][$status])) {
                    $summary[$att->student_id][$status]++;
                }
            }
        }

        // Calculate Alpha for missing days?
        // Logic: Total Days in range (excluding weekends?) - Present/Excused = Alpha
        // For simplicity, let's stick to counted records. 
        // If we want "Real Alpha", we need to iterate dates. 
        // Implementation Plan implies "Report", usually aggregated.
        // Let's stick to Aggregation of Existing Records + Calculation of "No Record" as Alpha?
        // Complex. Let's provide the raw summary of WHAT IS RECORDED + ability to drill down.
        // Or simpy: Count H, I, S, A, B, T.
        // If system auto-generates Alpha (via AutoBolos or manual crons), database reflects reality.

        $kelasQuery = Kelas::orderBy('nama_kelas');

        // Filter by school_id for non-super admin users
        if (auth()->user() && !auth()->user()->isSuperAdmin()) {
            $kelasQuery->where('school_id', auth()->user()->school_id);
        }

        $allKelas = $kelasQuery->get();

        return view('rekap.index', compact('allSiswa', 'summary', 'startDate', 'endDate', 'allKelas', 'kelasId'));
    }

    public function export(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $kelasId = $request->input('kelas_id');

        // Fetch Data Same as Index
        $siswaQuery = Siswa::with('kelas')->orderBy('nama');

        // Filter by school_id for non-super admin users
        if (auth()->user() && !auth()->user()->isSuperAdmin()) {
            $siswaQuery->where('school_id', auth()->user()->school_id);
        }

        if ($kelasId) {
            $siswaQuery->where('kelas_id', $kelasId);
        }
        $allSiswa = $siswaQuery->get();
        $attendances = Attendance::whereBetween('tanggal', [$startDate, $endDate])->get();

        $summary = [];
        foreach ($allSiswa as $s) {
            $summary[$s->id] = ['H' => 0, 'I' => 0, 'S' => 0, 'A' => 0, 'B' => 0];
        }
        foreach ($attendances as $att) {
            if (isset($summary[$att->student_id])) {
                $status = $att->status;
                // Normalize legacy T to H
                if ($status == 'T')
                    $status = 'H';
                if ($status == 'Hadir')
                    $status = 'H'; // strict check

                if (isset($summary[$att->student_id][$status])) {
                    $summary[$att->student_id][$status]++;
                } elseif ($status == 'H') { // Fallback if T was mapped to H but H key exists
                    $summary[$att->student_id]['H']++;
                }
            }
        }

        // Create Spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header
        $sheet->setCellValue('A1', 'REKAP ABSENSI');
        $sheet->setCellValue('A2', "Periode: $startDate - $endDate");
        $sheet->setCellValue('A4', 'No');
        $sheet->setCellValue('B4', 'NIS');
        $sheet->setCellValue('C4', 'Nama Siswa');
        $sheet->setCellValue('D4', 'Kelas');
        $sheet->setCellValue('E4', 'Hadir (H)');
        $sheet->setCellValue('F4', 'Sakit (S)');
        $sheet->setCellValue('G4', 'Izin (I)');
        $sheet->setCellValue('H4', 'Bolos (B)');
        $sheet->setCellValue('I4', 'Alpha (A)');

        $row = 5;
        $no = 1;
        foreach ($allSiswa as $s) {
            $sum = $summary[$s->id];
            $sheet->setCellValue('A' . $row, $no++);
            $sheet->setCellValue('B' . $row, $s->nis);
            $sheet->setCellValue('C' . $row, $s->nama);
            $sheet->setCellValue('D' . $row, $s->kelas->nama_kelas ?? '-');
            $sheet->setCellValue('E' . $row, $sum['H']);
            $sheet->setCellValue('F' . $row, $sum['S']);
            $sheet->setCellValue('G' . $row, $sum['I']);
            $sheet->setCellValue('H' . $row, $sum['B']);
            $sheet->setCellValue('I' . $row, $sum['A']);
            $row++;
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = "rekap_absensi_{$startDate}_{$endDate}.xlsx";

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . urlencode($fileName) . '"');
        $writer->save('php://output');
        exit;
    }
    public function show($id, Request $request)
    {
        $siswa = Siswa::with('kelas')->findOrFail($id);

        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));

        // Fetch attendance records
        $attendance = Attendance::where('student_id', $id)
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->orderBy('tanggal', 'asc')
            ->get();

        return view('rekap.show', compact('siswa', 'attendance', 'startDate', 'endDate'));
    }

    public function exportDetail($id, Request $request)
    {
        $siswa = Siswa::with('kelas')->findOrFail($id);
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        // Fetch attendance
        $attendance = Attendance::where('student_id', $id)
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->orderBy('tanggal', 'asc')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header Info
        $sheet->setCellValue('A1', 'DETAIL REKAP ABSENSI');
        $sheet->setCellValue('A2', 'Nama: ' . $siswa->nama);
        $sheet->setCellValue('A3', 'Kelas: ' . ($siswa->kelas->nama_kelas ?? '-'));
        $sheet->setCellValue('A4', 'Periode: ' . $startDate . ' - ' . $endDate);

        // Table Header
        $sheet->setCellValue('A6', 'No');
        $sheet->setCellValue('B6', 'Tanggal');
        $sheet->setCellValue('C6', 'Jam Masuk');
        $sheet->setCellValue('D6', 'Jam Pulang');
        $sheet->setCellValue('E6', 'Status');
        $sheet->setCellValue('F6', 'Keterangan');

        $row = 7;
        $no = 1;
        foreach ($attendance as $att) {
            $sheet->setCellValue('A' . $row, $no++);
            $sheet->setCellValue('B' . $row, $att->tanggal);
            $sheet->setCellValue('C' . $row, $att->jam_masuk);
            $sheet->setCellValue('D' . $row, $att->jam_pulang);
            $sheet->setCellValue('E' . $row, $att->status);
            $sheet->setCellValue('F' . $row, $att->keterangan);
            $row++;
        }

        $writer = new Xlsx($spreadsheet);
        $cleanName = preg_replace('/[^A-Za-z0-9]/', '_', $siswa->nama);
        $fileName = "detail_absensi_{$cleanName}_{$startDate}_{$endDate}.xlsx";

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . urlencode($fileName) . '"');
        $writer->save('php://output');
        exit;
    }

    public function printPdf(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $kelasId = $request->input('kelas_id');

        // Fetch Data Same as Index
        $siswaQuery = Siswa::with('kelas')->orderBy('nama');

        // Filter by school_id for non-super admin users
        if (auth()->user() && !auth()->user()->isSuperAdmin()) {
            $siswaQuery->where('school_id', auth()->user()->school_id);
        }

        if ($kelasId) {
            $siswaQuery->where('kelas_id', $kelasId);
        }
        $allSiswa = $siswaQuery->get();
        $attendances = Attendance::whereBetween('tanggal', [$startDate, $endDate])->get();

        $summary = [];
        // Map Students
        foreach ($allSiswa as $s) {
            $summary[$s->id] = [
                'nama' => $s->nama,
                'hadir' => 0,
                'izin' => 0,
                'sakit' => 0,
                'alpha' => 0,
                'bolos' => 0
            ];
        }

        // Aggregate Data
        foreach ($attendances as $att) {
            if (isset($summary[$att->student_id])) {
                $status = $att->status;
                $key = strtolower($status == 'H' ? 'hadir' : ($status == 'I' ? 'izin' : ($status == 'S' ? 'sakit' : ($status == 'A' ? 'alpha' : ($status == 'B' ? 'bolos' : 'telat')))));

                // Fallback for simple mapping codes
                // Assuming status is single char uppercase: H, I, S, A, B, T? 
                // Or full word? Let's check DB. It's varchar(20). 
                // Previous logic used $att->status directly as key.
                // Let's normalize to lowercase keys used in view.

                if ($status == 'H' || $status == 'Hadir' || $status == 'T' || $status == 'Telat')
                    $summary[$att->student_id]['hadir']++;
                elseif ($status == 'I' || $status == 'Izin')
                    $summary[$att->student_id]['izin']++;
                elseif ($status == 'S' || $status == 'Sakit')
                    $summary[$att->student_id]['sakit']++;
                elseif ($status == 'A' || $status == 'Alpha')
                    $summary[$att->student_id]['alpha']++;
                elseif ($status == 'B' || $status == 'Bolos')
                    $summary[$att->student_id]['bolos']++;
            }
        }

        // Fetch Metadata
        // Fetch Metadata
        $schoolId = auth()->user()->isSuperAdmin() ? $allSiswa->first()->school_id ?? null : auth()->user()->school_id;

        if (!$schoolId && $kelasId) {
            $kelas = Kelas::find($kelasId);
            $schoolId = $kelas ? $kelas->school_id : null;
        }

        $schoolName = \App\Models\Setting::where('school_id', $schoolId)->where('setting_key', 'nama_sekolah')->value('setting_value');
        $schoolAddress = \App\Models\Setting::where('school_id', $schoolId)->where('setting_key', 'alamat_sekolah')->value('setting_value');
        $signatureLocation = \App\Models\Setting::where('school_id', $schoolId)->where('setting_key', 'kota_lokasi_ttd')->value('setting_value'); // key is 'kota_lokasi_ttd' based on default settings created
        // Backup check just in case key name differs or was 'alamat_ttd' in old code
        if (!$signatureLocation) {
            $signatureLocation = \App\Models\Setting::where('school_id', $schoolId)->where('setting_key', 'alamat_ttd')->value('setting_value');
        }

        $namaKepsek = \App\Models\Setting::where('school_id', $schoolId)->where('setting_key', 'nama_kepala_sekolah')->value('setting_value');
        $namaWaka = \App\Models\Setting::where('school_id', $schoolId)->where('setting_key', 'nama_waka_kesiswaan')->value('setting_value');
        $kopSurat = \App\Models\Setting::where('school_id', $schoolId)->where('setting_key', 'kop_surat')->value('setting_value');

        // Defaults
        $schoolName = $schoolName ?? 'SMK Negeri Contoh';
        $schoolAddress = $schoolAddress ?? 'Jl. Contoh No. 1';
        $signatureLocation = $signatureLocation ?? 'Jakarta';
        $namaKepsek = $namaKepsek ?? '';
        $namaWaka = $namaWaka ?? '';

        $kelas = Kelas::find($kelasId);
        $rekap = $summary; // Pass array to view

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('rekap.pdf', compact('rekap', 'startDate', 'endDate', 'kelas', 'schoolName', 'schoolAddress', 'signatureLocation', 'namaKepsek', 'namaWaka', 'kopSurat'));
        $pdf->setPaper('a4', 'portrait');

        return $pdf->stream('rekap_absensi.pdf');
    }
}
