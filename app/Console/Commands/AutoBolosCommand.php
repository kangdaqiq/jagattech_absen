<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Setting;
use App\Models\MessageQueue;
use App\Models\Siswa;
use App\Models\Attendance;
use App\Services\WhatsAppMessageTemplates;
use Carbon\Carbon;

class AutoBolosCommand extends Command
{
    protected $signature = 'absen:process-daily {--force}';
    protected $description = 'Process daily attendance: Mark Bolos (B) if no checkout, and Alpha (A) if no checkin by 13:30';

    public function handle()
    {
        $schools = \App\Models\School::where('is_active', true)->get();

        foreach ($schools as $school) {
            $this->processSchool($school);
        }
    }

    private function processSchool($school)
    {
        $today = now()->format('Y-m-d');
        $schoolId = $school->id;

        $this->info("Processing School: {$school->nama_sekolah} (ID: $schoolId) for $today");

        // 0. Check Schedule Time (Per School)
        $scheduleTime = Setting::where('school_id', $schoolId)
            ->where('setting_key', 'schedule_process_daily')
            ->value('setting_value') ?? '13:30';

        if (now()->format('H:i') < $scheduleTime) {
            // Too early
            return;
        }

        // 1. Debounce check
        $lastRun = Setting::where('school_id', $schoolId)->where('setting_key', 'last_daily_process_date')->value('setting_value');
        if ($lastRun === $today && !$this->option('force')) {
            $this->info("Daily Process already ran today ($today) for school ID $schoolId. Use --force to run anyway.");
            return;
        }

        // 2. Check Weekly Holiday via Schedule (Jadwal)
        // If today has NO active schedule, skip process
        $dayIndex = \Carbon\Carbon::parse($today)->dayOfWeekIso; // 1-7
        $isSchoolDay = \App\Models\Jadwal::where('school_id', $schoolId)
            ->where('index_hari', $dayIndex)
            ->where('is_active', true)
            ->exists();

        if (!$isSchoolDay) {
            $this->info("Today (Day $dayIndex) is NOT an active school day (No Schedule). Process skipped for school ID $schoolId.");
            return;
        }

        // 3. Check for Holiday (Dynamic: if no student attendance exists today, assume holiday)
        $hasAttendance = \App\Models\Attendance::where('tanggal', $today)
            ->whereHas('student', function ($q) use ($schoolId) {
                $q->where('school_id', $schoolId);
            })->exists();

        if (!$hasAttendance) {
            $this->info("Today has no student attendance for school ID $schoolId. Assumed Holiday. Process skipped.");
            return;
        }

        // --- STEP 1: Mark BOLOS (Checked In but No Checkout) ---
        // Only if checkout attendance is enabled
        $checkoutEnabled = Setting::where('school_id', $schoolId)->where('setting_key', 'enable_checkout_attendance')
            ->value('setting_value') ?? 'true';

        $countB = 0;
        if ($checkoutEnabled === 'true') {
            // Only mark as Bolos if checkout is enabled
            // Only for students in Active Attendance Classes AND belonging to this school
            $countB = DB::table('attendance')
                ->join('siswa', 'attendance.student_id', '=', 'siswa.id')
                ->join('kelas', 'siswa.kelas_id', '=', 'kelas.id')
                ->where('siswa.school_id', $schoolId)
                ->where('attendance.tanggal', $today)
                ->whereNotNull('attendance.jam_masuk')
                ->whereNull('attendance.jam_pulang')
                ->whereNotIn('attendance.status', ['I', 'S'])
                ->where('kelas.is_active_attendance', true)
                ->update([
                    'attendance.status' => 'B',
                    'attendance.keterangan' => DB::raw("CONCAT(IFNULL(attendance.keterangan, ''), ' [Auto: Tidak Absen Pulang]')"),
                    'attendance.updated_at' => now()
                ]);

            $this->info("Marked $countB records as Bolos (B) for school ID $schoolId.");
        } else {
            $this->info("Checkout attendance is disabled for school ID $schoolId.");
        }

        // --- STEP 2: Mark ALPHA (No Record at all) ---
        // 2. Get all students who don't have attendance record for today AND belong to this school
        $studentsWithoutAttendance = Siswa::where('school_id', $schoolId)
            ->whereDoesntHave('attendance', function ($query) use ($today) {
                $query->where('tanggal', $today);
            })
            ->whereHas('kelas', function ($q) {
                $q->where('is_active_attendance', true);
            })
            ->get();

        $countA = 0;
        foreach ($studentsWithoutAttendance as $s) {
            \App\Models\Attendance::create([
                'student_id' => $s->id,
                'tanggal' => $today,
                'jam_masuk' => null,
                'jam_pulang' => null,
                'jam_kerja' => null,
                'status' => 'A',
                'keterangan' => 'Alpha (Tidak Hadir)',
                'lokasi_masuk' => 'System',
                'created_at' => now(),
                'updated_at' => now()
            ]);
            $countA++;
        }

        $this->info("Marked $countA students as Alpha (A) for school ID $schoolId.");

        // Update Setting for this school
        Setting::updateOrCreate(
            ['school_id' => $schoolId, 'setting_key' => 'last_daily_process_date'],
            ['setting_value' => $today]
        );

        // Send Absence Report
        $this->sendAbsenceReport($today, $schoolId);
        $this->info("------------------------------------------------");
    }

    private function sendAbsenceReport($today, $schoolId)
    {
        // Get classes with WhatsApp Group ID for this school
        $kelasWithGroupId = \App\Models\Kelas::where('school_id', $schoolId)
            ->whereNotNull('wa_group_id')
            ->where('wa_group_id', '!=', '')
            ->where('is_active_attendance', true)
            ->where('is_active_report', true)
            ->get();

        $legacyTarget = Setting::where('school_id', $schoolId)->where('setting_key', 'report_target_jid')->value('setting_value');

        if ($kelasWithGroupId->isEmpty() && !$legacyTarget) {
            return;
        }

        // Get all absent students (A, B, I, S) for this school
        $absentStudents = Attendance::where('tanggal', $today)
            ->whereIn('status', ['A', 'B', 'I', 'S'])
            ->whereHas('student', function ($q) use ($schoolId) {
                $q->where('school_id', $schoolId);
            })
            ->whereHas('student.kelas', function ($q) {
                $q->where('is_active_attendance', true);
            })
            ->with(['student.kelas'])
            ->orderBy('status')
            ->get();

        // Get all present students (H, T) for this school to count
        $presentStudents = Attendance::where('tanggal', $today)
            ->whereIn('status', ['H', 'T'])
            ->whereHas('student', function ($q) use ($schoolId) {
                $q->where('school_id', $schoolId);
            })
            ->whereHas('student.kelas', function ($q) {
                $q->where('is_active_attendance', true);
            })
            ->get();

        // Jika tidak ada siswa absen SAMA SEKALI di sekolah, bisa langsung return
        if ($absentStudents->isEmpty()) {
            return;
        }

        // Kirim laporan per kelas ke Grup WA Kelas dan Wali Kelas
        $allKelas = \App\Models\Kelas::where('school_id', $schoolId)
            ->where('is_active_attendance', true)
            ->where('is_active_report', true)
            ->with('waliKelas')
            ->get();

        foreach ($allKelas as $kelas) {
            // Ambil siswa tidak hadir khusus kelas ini
            $absenKelas = $absentStudents->filter(function ($att) use ($kelas) {
                return $att->student->kelas_id == $kelas->id;
            });

            if ($absenKelas->isEmpty()) {
                continue; // Tidak ada yang absen di kelas ini, skip
            }

            // Hitung siswa hadir di kelas ini
            $totalPresentKelas = $presentStudents->filter(function ($att) use ($kelas) {
                return $att->student->kelas_id == $kelas->id;
            })->count();

            $wali = $kelas->waliKelas;
            $namaWali = $wali ? $wali->nama : '-';

            $groupedKelas = $absenKelas->groupBy('status');
            $msgKelas = WhatsAppMessageTemplates::finalAbsenceReport(
                totalPresent: $totalPresentKelas,
                totalAbsent: $absenKelas->count(),
                absentStudentsGrouped: $groupedKelas
            );

            $msgKelas = "📋 *Laporan Kelas {$kelas->nama_kelas}*\n" .
                       "👤 Wali Kelas: {$namaWali}\n\n" . $msgKelas;

            // 1. Kirim ke Grup WA Kelas (jika diatur)
            if (!empty($kelas->wa_group_id)) {
                MessageQueue::create([
                    'school_id'    => $schoolId,
                    'phone_number' => $kelas->wa_group_id,
                    'message'      => $msgKelas,
                    'status'       => 'pending',
                    'created_at'   => now()
                ]);
            }

            // 2. Kirim ke Nomor WA Pribadi Wali Kelas (jika ada)
            if ($wali && !empty($wali->no_wa)) {
                $noWa = $wali->no_wa;
                if (!str_contains($noWa, '@')) {
                    $noWa = preg_replace('/^0/', '62', $noWa);
                    $noWa = $noWa . '@s.whatsapp.net';
                }

                MessageQueue::create([
                    'school_id'    => $schoolId,
                    'phone_number' => $noWa,
                    'message'      => $msgKelas,
                    'status'       => 'pending',
                    'created_at'   => now()
                ]);
            }
        }

        // Persiapkan laporan global jika diperlukan (untuk legacy target atau Guru Global)
        $guruGlobal = \App\Models\Guru::where('school_id', $schoolId)
            ->where('is_global_report', true)
            ->whereNotNull('no_wa')
            ->where('no_wa', '!=', '')
            ->get();

        if ($legacyTarget || $guruGlobal->isNotEmpty()) {
            $groupedGlobal = $absentStudents->groupBy('status');
            $totalPresentGlobal = $presentStudents->count();

            $messageGlobal = WhatsAppMessageTemplates::finalAbsenceReport(
                totalPresent: $totalPresentGlobal,
                totalAbsent: $absentStudents->count(),
                absentStudentsGrouped: $groupedGlobal
            );

            // Legacy target
            if ($legacyTarget) {
                MessageQueue::create([
                    'school_id'    => $schoolId,
                    'phone_number' => $legacyTarget,
                    'message'      => $messageGlobal,
                    'status'       => 'pending',
                    'created_at'   => now()
                ]);
            }

            // Guru dengan akses report global
            foreach ($guruGlobal as $guru) {
                $noWa = $guru->no_wa;
                if (!str_contains($noWa, '@')) {
                    $noWa = preg_replace('/^0/', '62', $noWa);
                    $noWa = $noWa . '@s.whatsapp.net';
                }

                MessageQueue::create([
                    'school_id'    => $schoolId,
                    'phone_number' => $noWa,
                    'message'      => $messageGlobal,
                    'status'       => 'pending',
                    'created_at'   => now()
                ]);
            }
        }

        $this->info("✓ Absence report queued for school ID $schoolId.");
    }
}
