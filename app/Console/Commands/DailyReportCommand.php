<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Siswa;
use App\Models\Attendance;
use App\Models\MessageQueue;
use App\Models\Setting;

use App\Models\Kelas;
use App\Services\WhatsAppMessageTemplates;
use Carbon\Carbon;

class DailyReportCommand extends Command
{
    protected $signature = 'absen:daily-report {targetJid?} {--force}';
    protected $description = 'Generate daily attendance report and send to WhatsApp Group';

    public function handle()
    {
        $targetJid = $this->argument('targetJid');
        $today = now()->format('Y-m-d');

        // Global Sunday check REMOVED to support per-school schedules
        // if (now()->isSunday()) { ... }

        // Iterate through ALL Active Schools
        $schools = \App\Models\School::where('is_active', true)->get();

        foreach ($schools as $school) {
            $this->processSchoolReport($school, $today, $targetJid);
        }
    }

    private function processSchoolReport($school, $today, $targetJidOverride = null)
    {
        $schoolId = $school->id;
        $this->info("------------------------------------------------");
        $this->info("Processing Daily Report for School: {$school->nama_sekolah} (ID: $schoolId)");

        // 1. Check for Holiday (Dynamic: if no student attendance exists today, assume holiday)
        $hasAttendance = \App\Models\Attendance::where('tanggal', $today)
            ->whereHas('student', function ($q) use ($schoolId) {
                $q->where('school_id', $schoolId);
            })->exists();

        if (!$hasAttendance && !$this->option('force')) {
            $this->info("Today has no student attendance for school ID $schoolId. Assumed Holiday. Skipped.");
            return;
        }

        // 2. Check Weekly Holiday via Schedule (Jadwal)
        $dayIndex = now()->dayOfWeekIso; // 1-7
        $isSchoolDay = \App\Models\Jadwal::where('school_id', $schoolId)
            ->where('index_hari', $dayIndex)
            ->where('is_active', true) // Only active days
            ->exists();

        if (!$isSchoolDay) {
            $this->info("Today (Day $dayIndex) is NOT an active school day (No Schedule). Skipped.");
            return;
        }


        // 2. Check Schedule Time (Per School)
        $scheduleTime = Setting::where('school_id', $schoolId)
            ->where('setting_key', 'schedule_daily_report')
            ->value('setting_value') ?? '08:15';

        if (now()->format('H:i') < $scheduleTime) {
            // Too early
            return;
        }

        // 3. Look up setting (Per School)
        $targetJid = $targetJidOverride;
        if (!$targetJid) {
            $targetJid = Setting::where('school_id', $schoolId)->where('setting_key', 'report_target_jid')->value('setting_value');
        }

        // 4. Debounce check (Per School)
        $lastRun = Setting::where('school_id', $schoolId)->where('setting_key', 'last_daily_report_date')->value('setting_value');
        if ($lastRun === $today && !$this->option('force')) {
            $this->info("DailyReport already ran today for this school.");
            return;
        }

        // 4. Get classes with WhatsApp Group ID (Per School)
        $kelasWithGroupId = Kelas::where('school_id', $schoolId)
            ->whereNotNull('wa_group_id')
            ->where('wa_group_id', '!=', '')
            ->where('is_active_attendance', true)
            ->where('is_active_report', true)
            ->get();

        // 5. Check if we have anything to report (Groups, Admin, or Wali Kelas)
        $hasWaliKelas = Kelas::where('school_id', $schoolId)
            ->whereNotNull('wali_kelas_id')
            ->where('is_active_attendance', true)
            ->exists();

        if ($kelasWithGroupId->isEmpty() && !$targetJid && !$hasWaliKelas) {
            $this->warn("No report targets (Groups, Admin, or Wali Kelas) found for this school. Skipped.");
            return;
        }

        $this->info("Generating report...");

        // --- AUTO-EXTEND SAKIT ---
        $maxSakitDays = (int) (Setting::where('school_id', $schoolId)->where('setting_key', 'sakit_max_days')->value('setting_value') ?? 2);
        $autoExtendCount = 0;

        if ($maxSakitDays > 1) {
            $yesterday = now()->subDay()->format('Y-m-d');

            // Find students who were SAKIT yesterday SCOPED
            $yesterdaySakit = Attendance::where('tanggal', $yesterday)
                ->whereHas('student', function ($q) use ($schoolId) {
                    $q->where('school_id', $schoolId);
                })
                ->where('status', 'S')
                ->get();

            foreach ($yesterdaySakit as $att) {
                // Count consecutive "Sakit" days backwards starting from yesterday
                $consecutiveDays = 1;
                $checkDate = \Carbon\Carbon::parse($yesterday)->subDay();
                
                // Maximum safety loop
                for ($i = 0; $i < 30; $i++) {
                    $prevRecord = Attendance::where('student_id', $att->student_id)
                        ->where('tanggal', $checkDate->format('Y-m-d'))
                        ->where('status', 'S')
                        ->exists();
                    
                    if ($prevRecord) {
                        $consecutiveDays++;
                        $checkDate->subDay();
                        if ($consecutiveDays >= $maxSakitDays) break;
                    } else {
                        break;
                    }
                }

                // If consecutive days (up to yesterday) is less than max allowed, extend to today
                if ($consecutiveDays < $maxSakitDays) {
                    // Check if student already has attendance record for today
                    $existsToday = Attendance::where('student_id', $att->student_id)
                        ->where('tanggal', $today)
                        ->exists();

                    // Only create if no record exists
                    if (!$existsToday) {
                        Attendance::create([
                            'student_id' => $att->student_id,
                            'tanggal' => $today,
                            'jam_masuk' => null,
                            'jam_pulang' => null,
                            'jam_kerja' => null,
                            'status' => 'S',
                            'keterangan' => '[Auto-Lanjut] Sakit (Hari ke-' . ($consecutiveDays + 1) . ')',
                            'is_auto_extended' => true,
                            'lokasi_masuk' => 'System',
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                        $autoExtendCount++;
                    }
                }
            }
        }
        $this->info("Auto-extended $autoExtendCount Sakit records.");

        // Get All Students SCOPED
        $siswaAll = Siswa::where('school_id', $schoolId)
            ->whereHas('kelas', function ($q) {
                $q->where('is_active_attendance', true);
            })->with('kelas')->orderBy('nama')->get();

        // Get Attendance SCOPED (Implicit by student_id, but good to optimize)
        // We can just fetch all attendance for today and filter by student IDs in memory or query
        $studentIds = $siswaAll->pluck('id');
        $attendance = Attendance::where('tanggal', $today)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy('student_id');

        $totalMasuk = 0;
        $absentByStatus = [
            'A' => [],
            'I' => [],
            'S' => [],
            'B' => []
        ];

        foreach ($siswaAll as $s) {
            if ($attendance->has($s->id)) {
                $att = $attendance[$s->id];
                if ($att->status === 'H') {
                    $totalMasuk++;
                } else {
                    $status = $att->status;
                    if (isset($absentByStatus[$status])) {
                        $kelas = $s->kelas->nama_kelas ?? '-';
                        $absentByStatus[$status][] = "{$s->nama} ({$kelas})";
                    }
                }
            } else {
                // No record = Alpha
                $kelas = $s->kelas->nama_kelas ?? '-';
                $absentByStatus['A'][] = "{$s->nama} ({$kelas})";
            }
        }

        // Calculate total absent
        $totalTidakMasuk = 0;
        foreach ($absentByStatus as $students) {
            $totalTidakMasuk += count($students);
        }

        // --- SEND CLASS-SPECIFIC REPORTS TO CLASS GROUPS ---
        $this->info("Processing class-specific group reports...");
        $studentsByClass = $siswaAll->groupBy('kelas_id');

        foreach ($kelasWithGroupId as $kelas) {
            $siswaKelas = $studentsByClass[$kelas->id] ?? collect();

            if ($siswaKelas->isEmpty()) {
                continue;
            }

            $masuk = 0;
            $tidakMasuk = 0;
            $absentByStatusClass = [
                'A' => [],
                'I' => [],
                'S' => [],
                'B' => []
            ];

            foreach ($siswaKelas as $s) {
                if ($attendance->has($s->id)) {
                    $att = $attendance[$s->id];
                    if ($att->status === 'H') {
                        $masuk++;
                    } else {
                        $tidakMasuk++;
                        $status = $att->status;
                        if (isset($absentByStatusClass[$status])) {
                            $absentByStatusClass[$status][] = $s->nama;
                        }
                    }
                } else {
                    $tidakMasuk++;
                    $absentByStatusClass['A'][] = $s->nama;
                }
            }

            $msgClass = WhatsAppMessageTemplates::dailyReportClass(
                namaKelas: $kelas->nama_kelas,
                masuk: $masuk,
                tidakMasuk: $tidakMasuk,
                absentByStatus: $absentByStatusClass
            );

            MessageQueue::create([
                'school_id' => $schoolId,
                'phone_number' => $kelas->wa_group_id,
                'message' => $msgClass,
                'status' => 'pending',
                'created_at' => now()
            ]);
            $this->info("Queued class report: {$kelas->nama_kelas}");
        }

        // --- SEND GLOBAL REPORT TO LEGACY TARGET ---
        if ($targetJid) {
            $msg = WhatsAppMessageTemplates::dailyReportGlobal(
                totalMasuk: $totalMasuk,
                totalTidakMasuk: $totalTidakMasuk,
                absentByStatus: $absentByStatus
            );

            MessageQueue::create([
                'school_id' => $schoolId,
                'phone_number' => $targetJid,
                'message' => $msg,
                'status' => 'pending',
                'created_at' => now()
            ]);
            $this->info("Queued global report to admin ($targetJid)");
        }

        // --- LAPORAN PER WALI KELAS ---
        $this->info("Processing Wali Kelas reports...");
        $kelasWithWali = \App\Models\Kelas::where('school_id', $schoolId)
            ->whereNotNull('wali_kelas_id')
            ->with('waliKelas')
            ->get();

        foreach ($kelasWithWali as $kelas) {
            $wali = $kelas->waliKelas;
            if (!$wali || !$wali->no_wa || !isset($studentsByClass[$kelas->id])) {
                continue;
            }

            $siswaKelas = $studentsByClass[$kelas->id];
            $masuk = 0;
            $tidakMasuk = 0;
            $listAbsen = [];

            foreach ($siswaKelas as $s) {
                if ($attendance->has($s->id)) {
                    $att = $attendance[$s->id];
                    if ($att->status === 'H') {
                        $masuk++;
                    } else {
                        $tidakMasuk++;
                        $statusKet = match ($att->status) {
                            'I' => 'Izin',
                            'S' => 'Sakit',
                            'A' => 'Alpha',
                            'B' => 'Bolos',
                            default => $att->status
                        };
                        $listAbsen[] = "{$s->nama} ({$statusKet})";
                    }
                } else {
                    $tidakMasuk++;
                    $listAbsen[] = "{$s->nama} (Alpha)";
                }
            }

            $msgWali = WhatsAppMessageTemplates::dailyReportWaliKelas(
                namaKelas: $kelas->nama_kelas,
                namaWali: $wali->nama,
                masuk: $masuk,
                tidakMasuk: $tidakMasuk,
                listAbsen: $listAbsen
            );

            MessageQueue::create([
                'school_id' => $schoolId,
                'phone_number' => $wali->no_wa,
                'message' => $msgWali,
                'status' => 'pending',
                'created_at' => now()
            ]);
        }

        // --- ALPHA NOTIFICATIONS ---
        $this->info("Processing alpha notifications...");
        // Filter alphaStudentIds for THIS SCHOOL
        $alphaStudentIds = [];
        foreach ($siswaAll as $s) {
            if (!$attendance->has($s->id)) {
                $alphaStudentIds[] = $s->id;
            }
        }

        if (!empty($alphaStudentIds)) {
            $alphaStudents = Siswa::with('kelas')
                ->whereIn('id', $alphaStudentIds)
                ->get(); // Already scoped by $siswaAll

            foreach ($alphaStudents as $student) {
                $this->sendAlphaNotification($student, $schoolId);
            }
        }

        // Mark done for this school
        Setting::updateOrCreate(
            ['school_id' => $schoolId, 'setting_key' => 'last_daily_report_date'],
            ['setting_value' => $today]
        );
    }

    private function sendAlphaNotification($student, $schoolId)
    {
        $studentName = $student->nama;
        $studentPhone = $student->no_wa;
        $parentPhone = $student->wa_ortu;
        $kelasName = $student->kelas->nama_kelas ?? '-';

        if ($studentPhone) {
            $msgStudent = "❌ *Pemberitahuan Ketidakhadiran*\n\n" .
                "Halo, *{$studentName}*,\n\n" .
                "📅 Tanggal: " . now()->format('d/m/Y') . "\n" .
                "📊 Status: Alpha (Tidak Hadir)\n\n" .
                "Anda tercatat tidak hadir hari ini tanpa keterangan.\n" .
                "Mohon segera konfirmasi ke wali kelas atau bagian kesiswaan.\n\n" .
                "_Notifikasi otomatis dari sistem absensi sekolah._";

            MessageQueue::create([
                'school_id' => $schoolId,
                'phone_number' => $studentPhone,
                'message' => $msgStudent,
                'status' => 'pending',
                'created_at' => now()
            ]);
        }

        if ($parentPhone) {
            $msgParent = "❌ *Pemberitahuan Ketidakhadiran Anak*\n\n" .
                "Halo, Orang Tua/Wali dari *{$studentName}*,\n\n" .
                "📅 Tanggal: " . now()->format('d/m/Y') . "\n" .
                "📊 Status: Alpha (Tidak Hadir)\n" .
                "⚠️ Kelas: {$kelasName}\n\n" .
                "Anak Anda tercatat tidak hadir hari ini tanpa keterangan.\n" .
                "Mohon konfirmasi kepada wali kelas atau bagian kesiswaan.\n\n" .
                "_Notifikasi otomatis dari sistem absensi sekolah._";

            MessageQueue::create([
                'school_id' => $schoolId,
                'phone_number' => $parentPhone,
                'message' => $msgParent,
                'status' => 'pending',
                'created_at' => now()
            ]);
        }
    }
}
