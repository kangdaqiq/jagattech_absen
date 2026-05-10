<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\Device;
use App\Models\ApiLog;
use App\Models\ScanHistory;
use App\Models\Jadwal;
use App\Models\Guru;
use App\Models\Siswa;
use App\Models\Attendance;
use App\Models\TeacherCheckoutSession;
use App\Models\MessageQueue;
use App\Models\GateCard;

class RfidController extends Controller
{
    // Config
    const MAX_REQUESTS_PER_MINUTE = 60;
    const SCAN_COOLDOWN_SECONDS = 0;

    protected $wa;

    // Logging context
    private $currentApiKey = null;
    private $currentUid = null;
    private $currentSchoolId = null;
    private $hasLogged = false;

    public function __construct(\App\Services\WhatsAppService $wa)
    {
        $this->wa = $wa;
    }

    private function handleGateScan($uid, $gateCard, $apiKey, $device, $now = null)
    {
        try {
            DB::beginTransaction();
            $now = $now ?? now();

            $gateName = $gateCard->guru_id ? ($gateCard->guru->nama ?? $gateCard->name) : $gateCard->name;

            // Clean expired sessions
            TeacherCheckoutSession::where('expires_at', '<', $now)->delete();


            // Cek apakah gerbang sedang terbuka oleh kartu ini
            $activeSession = TeacherCheckoutSession::where('uid_rfid', $uid)
                ->where('expires_at', '>=', $now)
                ->first();

            if ($activeSession) {
                // Jika sedang terbuka, TUTUP gerbang
                $activeSession->delete();
                DB::commit();

                $this->logRequest($apiKey, 'gate_closed', $uid, true, 'Sesi Kepulangan Ditutup: ' . $gateName);
                return $this->response(true, 'success', "Gerbang Ditutup.", 'ok', [
                    'type' => 'gate_closed',
                    'nama' => $gateName
                ]);
            }

            // Jika belum terbuka, BUKA gerbang
            TeacherCheckoutSession::create([
                'teacher_id' => $gateCard->guru_id, // Link to Guru if exists
                'teacher_name' => $gateName,
                'uid_rfid' => $uid,
                'status' => 'open',
                'expires_at' => $now->copy()->addMinutes(30),
                'created_at' => $now
            ]);

            DB::commit();

            $this->logRequest($apiKey, 'gate_access', $uid, true, 'Sesi Kepulangan Dibuka: ' . $gateName);
            return $this->response(true, 'success', "Gerbang Dibuka (30 Menit).", 'ok', [
                'type' => 'gate_opened',
                'nama' => $gateName
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Gate card scan error: " . $e->getMessage());
            return $this->response(false, 'gagal', 'Gagal memproses kartu gerbang', 'error');
        }
    }

    private function handleTeacherScan($uid, $teacher, $apiKey, $device, $now = null)
    {
        try {
            DB::beginTransaction();
            $now = $now ?? now();
            $today = $now->format('Y-m-d');

            // 1. ABSENSI HARIAN (Daily)
            $absensi = \App\Models\AbsensiGuru::where('guru_id', $teacher->id)
                ->where('tanggal', $today)
                ->where('school_id', $device->school_id) // Scope
                ->whereNull('jadwal_pelajaran_id')
                ->lockForUpdate()
                ->first();

            if (!$absensi) {
                // CASE: CHECK-IN
                \App\Models\AbsensiGuru::create([
                    'guru_id' => $teacher->id,
                    'school_id' => $device->school_id, // Scope
                    'jadwal_pelajaran_id' => null, // Daily
                    'tanggal' => $today,
                    'jam_masuk' => $now->toTimeString(),
                    'waktu_hadir' => $now, // Legacy field
                    'status' => 'Hadir', // Default Hadir
                    'keterangan' => null
                ]);

                DB::commit();

                // Send WA Check-in
                try {
                    $this->wa->sendCheckIn($teacher->nama, $teacher->no_wa, $now->format('H:i'), 'Hadir', $device->school_id, '-', null, '-');
                } catch (\Exception $e) {
                    Log::error("WA Guru Checkin Error: " . $e->getMessage());
                }

                $this->logRequest($apiKey, 'checkin_success', $uid, true, 'Guru Masuk: ' . $teacher->nama);
                return $this->response(true, 'success', "Selamat Pagi, {$teacher->nama}.", 'ok', [
                    'type' => 'absen_masuk_guru',
                    'nama' => $teacher->nama,
                    'jam' => $now->format('H:i')
                ]);

            } else {
                // CASE: ALREADY CHECKED IN (Check for Check-Out logic)
                
                // Check if Teacher Check-Out is enabled
                $checkoutEnabled = \App\Models\Setting::where('school_id', $device->school_id)
                    ->where('setting_key', 'enable_checkout_teacher')
                    ->value('setting_value') ?? 'false';

                if ($checkoutEnabled === 'false') {
                    DB::commit();
                    $this->logRequest($apiKey, 'checkin_success', $uid, true, 'Sudah Absen Masuk: ' . $teacher->nama);
                    return $this->response(true, 'success', "Sudah Absen Masuk.", 'ok', [
                        'type' => 'absen_sudah_masuk_guru',
                        'nama' => $teacher->nama
                    ]);
                }

                // Teacher Check-Out is ENABLED
                // Require Gate Session to check out
                $gateSession = TeacherCheckoutSession::where('expires_at', '>', $now)
                    ->where('status', 'open')
                    // It doesn't strictly need school_id if TeacherCheckoutSession doesn't have it, but gate cards are scoped implicitly
                    // We assume any open gate session is valid. (Ideally add school_id to session, but keeping it simple)
                    ->orderBy('created_at', 'desc')
                    ->first();

                if (!$gateSession) {
                    DB::rollBack();
                    return $this->response(false, 'gagal', 'Belum ada izin gerbang.', 'warning', ['type' => 'no_authorization', 'nama' => $teacher->nama]);
                }

                // Process Pulang
                $masuk = Carbon::parse($absensi->jam_masuk);
                $totalSeconds = $masuk->diffInSeconds($now, false);
                if ($totalSeconds < 0) {
                    $totalSeconds = abs($totalSeconds);
                }

                $absensi->update([
                    'jam_pulang' => $now->toTimeString(),
                    'updated_at' => now(),
                ]);
                DB::commit();

                // Send WA Check-Out
                $hours = floor($totalSeconds / 3600);
                $mins = floor(($totalSeconds % 3600) / 60);
                
                try {
                    $this->wa->sendCheckOut($teacher->nama, $teacher->no_wa, $now->format('H:i'), $hours, $mins, $gateSession->teacher_name, $device->school_id, $masuk->format('H:i'));
                } catch (\Exception $e) {
                    Log::error("WA Guru Checkout Error: " . $e->getMessage());
                }

                $this->logRequest($apiKey, 'checkout_success', $uid, true, 'Guru Pulang: ' . $teacher->nama);
                return $this->response(true, 'success', "Absen pulang berhasil.", 'ok', [
                    'type' => 'absen_pulang_guru',
                    'nama' => $teacher->nama,
                    'authorized_by' => $gateSession->teacher_name
                ]);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Teacher scan error: " . $e->getMessage());
            return $this->response(false, 'gagal', 'Gagal memproses absen guru', 'error');
        }
    }

    public function handle(Request $request)
    {
        $apiKey = trim($request->input('api_key', ''));
        $this->currentApiKey = $apiKey;

        // 1. Auth API Key
        if ($apiKey === '') {
            $this->logFailedAuth('', 'API key kosong', $request);
            return $this->response(false, 'gagal', 'API key tidak boleh kosong', 'error');
        }

        $device = $this->authenticate($apiKey, $request);
        if (!$device) {
            return $this->response(false, 'gagal', 'API key tidak valid', 'error');
        }

        // 2. Input Validation
        $uid = trim($request->input('uid', ''));
        $this->currentUid = $uid;

        if ($uid === '') {
            $this->logRequest($apiKey, 'validation_error', '', false, 'UID kosong');
            return $this->response(false, 'gagal', 'UID kosong', 'error');
        }

        $uid = $this->validateUID($uid);

        // 3. Parse scanned_at (offline sync dari device)
        // Jika device mengirim scanned_at, gunakan sebagai waktu absen
        // Dibatasi maks 7 hari ke belakang untuk keamanan
        $now = now();
        $scannedAt = trim($request->input('scanned_at', ''));
        if ($scannedAt !== '') {
            try {
                $parsed = Carbon::parse($scannedAt);
                $maxBack = now()->subDays(7);
                if ($parsed->lte(now()) && $parsed->gte($maxBack)) {
                    $now = $parsed;
                    Log::info("[OFFLINE SYNC] uid={$uid} scanned_at={$scannedAt}");
                }
            } catch (\Exception $e) {
                // scanned_at tidak valid, pakai now()
            }
        }

        // 4. Cooldown
        if ($res = $this->checkScanCooldown($uid)) {
            return $res;
        }

        // 5. Mode Detection

        // Check Gate Card - SCOPED
        $gateCard = GateCard::with('guru')
            ->whereRaw('UPPER(uid_rfid) = ?', [$uid])
            ->where('school_id', $device->school_id)
            ->first();
        if ($gateCard) {
            return $this->handleGateScan($uid, $gateCard, $apiKey, $device, $now);
        }

        // Check Teacher - SCOPED
        $teacher = $this->checkTeacherCard($uid, $device->school_id);
        if ($teacher) {
            return $this->handleTeacherScan($uid, $teacher, $apiKey, $device, $now);
        }

        // Check Enrollment - SCOPED
        if ($this->hasEnrollmentRequest($device->school_id)) {
            return $this->handleEnroll($uid, $apiKey, $device);
        }

        // Default: Scan Absensi - SCOPED
        return $this->handleScan($uid, $apiKey, $device, $now);
    }

    // ... Helper Methods ...

    private function logFailedAuth(string $apiKey, string $reason, $request = null)
    {
        ApiLog::create([
            'school_id'   => null,
            'api_key'     => $apiKey,
            'action'      => 'auth_failed',
            'uid'         => null,
            'success'     => false,
            'message'     => $reason,
            'ip_address'  => $request ? $request->ip() : request()->ip(),
            'user_agent'  => $request ? $request->userAgent() : request()->userAgent(),
            'created_at'  => now(),
        ]);
    }

    private function authenticate($apiKey, $request = null)
    {
        if (empty($apiKey))
            return null;

        $device = Device::where('api_key', $apiKey)->where('active', true)->first();
        if (!$device) {
            // Log auth failure dengan IP — visible ke SuperAdmin
            $this->logFailedAuth($apiKey, 'API key tidak valid / tidak aktif', $request);
            return null;
        }

        $this->currentSchoolId = $device->school_id;

        // Update last used (manual query to avoid timestamp interfering if model timestamps disabled)
        DB::table('api_keys')->where('id', $device->id)->update(['last_used_at' => now()]);

        // Rate Limit
        $count = ApiLog::where('api_key', $apiKey)
            ->where('created_at', '>', now()->subMinute())
            ->count();

        if ($count > self::MAX_REQUESTS_PER_MINUTE) {
            $this->logRequest($apiKey, 'rate_limit', '', false, 'Rate limit exceeded');
            $this->response(false, 'gagal', 'Terlalu banyak request. Tunggu sebentar.', 'error')->send();
            exit;
        }

        return $device;
    }

    private function validateUID($uid)
    {
        if (!preg_match('/^[A-F0-9]{8,20}$/i', $uid)) {
            $this->response(false, 'gagal', 'Format UID tidak valid', 'error', ['type' => 'invalid_uid'])->send();
            exit;
        }
        return strtoupper($uid);
    }

    private function checkScanCooldown($uid)
    {
        if (self::SCAN_COOLDOWN_SECONDS > 0) {
            $lastScan = ScanHistory::where('uid', $uid)
                ->where('created_at', '>', now()->subSeconds(self::SCAN_COOLDOWN_SECONDS))
                ->first();

            if ($lastScan) {
                return $this->response(false, 'gagal', 'Tunggu sebentar...', 'warning', ['type' => 'scan_cooldown']);
            }
        }

        ScanHistory::create(['uid' => $uid, 'created_at' => now()]);
        return null;
    }

    private function checkTeacherCard($uid, $schoolId)
    {
        return Guru::whereRaw('UPPER(uid_rfid) = ?', [$uid])
            ->where('school_id', $schoolId)
            ->first();
    }

    private function hasEnrollmentRequest($schoolId)
    {
        // Relaxed window to 60 minutes to avoid timezone issues
        $siswa = Siswa::where('enroll_status', 'requested')
            ->where('school_id', $schoolId)
            ->where('updated_at', '>=', now()->subHour())
            ->exists();

        $guru = Guru::where('enroll_status', 'requested')
            ->where('school_id', $schoolId)
            ->where('updated_at', '>=', now()->subHour())
            ->exists();

        $gate = GateCard::where('enroll_status', 'requested')
            ->where('school_id', $schoolId)
            ->where('updated_at', '>=', now()->subHour())
            ->exists();

        return $siswa || $guru || $gate;
    }



    private function handleEnroll($uid, $apiKey, $device)
    {
        DB::beginTransaction();
        try {
            // Check duplicate in both tables
            // Optionally, check duplicate globally?
            // Better to enforce unique RFID globally to prevent confusion,
            // OR if cards are reused, verify they aren't active in THIS school.
            // Let's enforce Global Uniqueness for UID to prevent security issues.
            /*
            if (Siswa::where('uid_rfid', $uid)->exists() || Guru::where('uid_rfid', $uid)->exists()) {
                DB::rollBack();
                return $this->response(false, 'gagal', 'UID sudah ada', 'warning');
            }
            */
            // Actually, for multi-tenant, maybe same card IS allowed in diff schools?
            // But usually cards are unique tokens. Let's check THIS school first.
            if (
                Siswa::where('uid_rfid', $uid)->where('school_id', $device->school_id)->exists() ||
                Guru::where('uid_rfid', $uid)->where('school_id', $device->school_id)->exists() ||
                GateCard::where('uid_rfid', $uid)->where('school_id', $device->school_id)->exists()
            ) {
                DB::rollBack();

                Siswa::where('enroll_status', 'requested')
                    ->where('school_id', $device->school_id)
                    ->update(['enroll_status' => 'error:Kartu sudah terdaftar']);
                Guru::where('enroll_status', 'requested')
                    ->where('school_id', $device->school_id)
                    ->update(['enroll_status' => 'error:Kartu sudah terdaftar']);
                GateCard::where('enroll_status', 'requested')
                    ->where('school_id', $device->school_id)
                    ->update(['enroll_status' => 'error:Kartu sudah terdaftar']);

                return $this->response(false, 'gagal', 'Kartu sudah terdaftar di sekolah ini', 'warning');
            }

            // 1. Check Siswa Request (Scoped to School)
            $siswa = Siswa::where('enroll_status', 'requested')
                ->where('school_id', $device->school_id)
                ->where('updated_at', '>=', now()->subHour())
                ->orderBy('id', 'desc')
                ->lockForUpdate()
                ->first();

            if ($siswa) {
                $siswa->update([
                    'uid_rfid' => $uid,
                    'enroll_status' => 'done'
                ]);
                DB::commit();

                // WA Notification
                try {
                    $this->wa->sendEnrollSuccess($siswa->nama, $siswa->no_wa, $uid, $device->school_id, 'Kartu Siswa', $siswa->wa_ortu);
                } catch (\Exception $e) {
                    Log::error("WA Enroll Error: " . $e->getMessage());
                }

                $this->logRequest($apiKey, 'enroll_success', $uid, true, 'Enroll Siswa berhasil: ' . $siswa->nama);
                return $this->response(true, 'success', 'Enroll Siswa berhasil', 'ok', [
                    'type' => 'enroll_rfid',
                    'nama' => $siswa->nama,
                    'uid' => $uid
                ]);
            }

            // 2. Check Guru Request (Scoped to School)
            $guru = Guru::where('enroll_status', 'requested')
                ->where('school_id', $device->school_id)
                ->where('updated_at', '>=', now()->subHour())
                ->orderBy('id', 'desc')
                ->lockForUpdate()
                ->first();

            if ($guru) {
                $guru->update([
                    'uid_rfid' => $uid,
                    'enroll_status' => 'done'
                ]);
                DB::commit();

                // WA Notification
                try {
                    $this->wa->sendEnrollSuccess($guru->nama, $guru->no_wa, $uid, $device->school_id, 'Kartu Guru');
                } catch (\Exception $e) {
                    Log::error("WA Enroll Error: " . $e->getMessage());
                }

                $this->logRequest($apiKey, 'enroll_success', $uid, true, 'Enroll Guru berhasil: ' . $guru->nama);
                return $this->response(true, 'success', 'Enroll Guru berhasil', 'ok', [
                    'type' => 'enroll_rfid',
                    'nama' => $guru->nama,
                    'uid' => $uid
                ]);
            }

            // 3. Check Gate Card Request (Scoped to School)
            $gate = GateCard::where('enroll_status', 'requested')
                ->where('school_id', $device->school_id)
                ->where('updated_at', '>=', now()->subHour())
                ->orderBy('id', 'desc')
                ->lockForUpdate()
                ->first();

            if ($gate) {
                $gate->update([
                    'uid_rfid' => $uid,
                    'enroll_status' => 'done'
                ]);
                DB::commit();

                $this->logRequest($apiKey, 'enroll_success', $uid, true, 'Enroll Kartu Gerbang berhasil: ' . $gate->name);
                return $this->response(true, 'success', 'Enroll Kartu Gerbang berhasil', 'ok', [
                    'type' => 'enroll_rfid',
                    'nama' => $gate->name,
                    'uid' => $uid
                ]);
            }

            // Neither found (expired race condition?)
            DB::rollBack();
            return $this->response(false, 'gagal', 'Tidak ada permintaan enroll', 'warning', ['type' => 'enroll_request_not_found']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Enroll error: " . $e->getMessage());
            return $this->response(false, 'gagal', 'Terjadi kesalahan sistem', 'error');
        }
    }

    private function handleScan($uid, $apiKey, $device, $now = null)
    {
        try {
            // Find Student SCOPED to Device's School
            $siswa = Siswa::with('kelas')
                ->where('uid_rfid', $uid)
                ->where('school_id', $device->school_id)
                ->first();

            if (!$siswa) {
                $this->logRequest($apiKey, 'unknown_card', $uid, false, 'Kartu tidak terdaftar di sekolah ini');
                return $this->response(false, 'unknown', 'Kartu tdk dikenal', 'error', ['type' => 'unknown_card', 'uid' => $uid]);
            }

            // CHECK: Is Class Attendance Active?
            if ($siswa->kelas && !$siswa->kelas->is_active_attendance) {
                return $this->response(false, 'gagal', 'Absensi dimatikan untuk kelas ini.', 'error', ['type' => 'class_disabled']);
            }

            $now = $now ?? now();
            $today = $now->format('Y-m-d');
            $indexHari = (int) $now->format('N'); // 1 (Mon) - 7 (Sun)

            $jadwal = Jadwal::where('index_hari', $indexHari)
                ->where('is_active', 1)
                ->where('school_id', $device->school_id)
                ->first();

            if (!$jadwal) {
                return $this->response(false, 'gagal', 'Jadwal Libur/Kosong', 'warning', ['type' => 'schedule_empty']);
            }
            $jamMasuk = Carbon::parse($now->format('Y-m-d') . ' ' . $jadwal->jam_masuk);
            $jamPulang = Carbon::parse($now->format('Y-m-d') . ' ' . $jadwal->jam_pulang);
            
            $awalAbsenMasuk = Carbon::parse($now->format('Y-m-d') . ' ' . $jadwal->awal_absen_masuk);
            $akhirAbsenMasuk = Carbon::parse($now->format('Y-m-d') . ' ' . $jadwal->akhir_absen_masuk);
            $akhirAbsenPulang = Carbon::parse($now->format('Y-m-d') . ' ' . $jadwal->akhir_absen_pulang);
            $batasTelat = $jamMasuk; // Toleransi dihapus, telat dihitung langsung setelah jam_masuk

            DB::beginTransaction();

            $att = Attendance::where('student_id', $siswa->id)
                ->where('tanggal', $now->format('Y-m-d'))
                ->lockForUpdate()
                ->first();

            // If record exists but jam_masuk is NULL (Sakit, Izin, or Alpha from system)
            // allow a fresh check-in to override the system record
            if ($att && $att->jam_masuk === null && in_array($att->status, ['S', 'I', 'A', 'B'])) {
                $att->delete();
                $att = null;
            }

            // Case 1: Lengkap
            if ($att && $att->jam_pulang) {
                DB::rollBack();
                return $this->response(true, 'success', 'Absen Lengkap', 'ok', ['type' => 'sudah_lengkap', 'nama' => $siswa->nama]);
            }

            // Case 2: Sudah Masuk, Belum Pulang
            if ($att && !$att->jam_pulang) {
                // Check if checkout is enabled in settings SCOPED
                $checkoutEnabled = \App\Models\Setting::where('school_id', $device->school_id)
                    ->where('setting_key', 'enable_checkout_attendance')
                    ->value('setting_value') ?? 'true';

                // If checkout is disabled, treat as complete attendance
                if ($checkoutEnabled === 'false') {
                    DB::rollBack();
                    return $this->response(true, 'success', 'Absen Lengkap', 'ok', ['type' => 'sudah_lengkap', 'nama' => $siswa->nama]);
                }

                // Check Teacher Session (only open gates) Global? Or should be scoped?
                // Teacher session stores teacher_name.
                // We should check sessions created by teachers OF THIS SCHOOL.
                // Or simply check if there is ANY open session in the system?
                // Ideally scoped. TeacherCheckoutSession needs school_id?
                // Currently it only has teacher_id. We can join with guru table or just rely on teacher_id.
                // For now, let's assume if a teacher opened a gate, it's for their school.

                // Optimized: Check sessions where teacher belongs to this school
                $teacherSession = TeacherCheckoutSession::select('teacher_checkout_sessions.*')
                    ->join('guru', 'teacher_checkout_sessions.teacher_id', '=', 'guru.id')
                    ->where('guru.school_id', $device->school_id)
                    ->where('teacher_checkout_sessions.expires_at', '>', now())
                    ->where('teacher_checkout_sessions.status', 'open')
                    ->orderBy('teacher_checkout_sessions.created_at', 'desc')
                    ->first();

                $isAutoCheckoutTime = $now->between($jamPulang, $akhirAbsenPulang);

                // Jika sudah melewati batas akhir absen pulang dan tidak ada sesi guru, tolak
                if ($now->gt($akhirAbsenPulang) && !$teacherSession) {
                     DB::rollBack();
                     return $this->response(false, 'gagal', 'Pulang Ditutup', 'warning', ['type' => 'checkout_closed', 'nama' => $siswa->nama]);
                }

                // Jika belum masuk waktu pulang otomatis dan tidak ada izin guru, tolak
                if (!$isAutoCheckoutTime && !$teacherSession) {
                    // Beri pesan berbeda jika masih di jam masuk (mencegah spam absen 2x)
                    if ($now->between($awalAbsenMasuk, $akhirAbsenMasuk)) {
                        DB::rollBack();
                        return $this->response(true, 'success', 'Sudah Absen Masuk', 'ok', ['type' => 'sudah_absen_masuk', 'nama' => $siswa->nama]);
                    }

                    DB::rollBack();
                    return $this->response(false, 'gagal', 'Belum waktu pulang', 'warning', ['type' => 'no_authorization', 'nama' => $siswa->nama]);
                }

                // Pulang
                $masuk = Carbon::parse($att->jam_masuk);
                // Calculate duration from check-in to check-out
                // Use diffInSeconds with proper order: from $masuk to $now
                $totalSeconds = $masuk->diffInSeconds($now, false); // false = signed difference

                // Ensure positive value (in case of clock issues)
                if ($totalSeconds < 0) {
                    $totalSeconds = abs($totalSeconds);
                }

                $att->update([
                    'jam_pulang' => $now->toTimeString(),
                    'total_seconds' => $totalSeconds,
                    'updated_at' => now(), // Attendance has timestamp columns? In model I defined them.
                ]);
                DB::commit();

                // WA
                $hours = floor($totalSeconds / 3600);
                $mins = floor(($totalSeconds % 3600) / 60);
                $authorizedBy = $teacherSession ? $teacherSession->teacher_name : 'Sistem Otomatis';
                $this->wa->sendCheckOut($siswa->nama, $siswa->no_wa, $now->format('H:i'), $hours, $mins, $authorizedBy, $device->school_id, $masuk->format('H:i'), $siswa->wa_ortu);

                $this->logRequest($apiKey, 'checkout_success', $uid, true, 'Pulang: ' . $siswa->nama);
                return $this->response(true, 'success', 'Absen pulang berhasil', 'ok', [
                    'type' => 'absen_pulang',
                    'nama' => $siswa->nama,
                    'authorized_by' => $authorizedBy
                ]);
            }

            // Case 3: Absen Masuk
            if (!$att) {
                if ($now->lt($awalAbsenMasuk)) {
                    DB::rollBack();
                    return $this->response(false, 'gagal', 'Absen Tutup (Terlalu Pagi)', 'warning', ['type' => 'too_early']);
                }
                if ($now->gt($akhirAbsenMasuk)) {
                    DB::rollBack();
                    return $this->response(false, 'gagal', 'Absen Masuk Ditutup', 'warning', ['type' => 'checkin_closed']);
                }

                $status = 'H';
                $keterangan = null;

                if ($now->gt($batasTelat)) {
                    $status = 'T'; // Set status to Terlambat
                    // Calculate late duration from jam_masuk
                    $diff = $now->timestamp - $batasTelat->timestamp;
                    $jam = floor($diff / 3600);
                    $menit = floor(($diff % 3600) / 60);

                    // Format the late message
                    if ($jam > 0) {
                        $keterangan = "Telat {$jam} jam {$menit} menit";
                    } else {
                        $keterangan = "Telat {$menit} menit";
                    }
                }

                Attendance::create([
                    'student_id' => $siswa->id,
                    'tanggal' => $now->format('Y-m-d'),
                    'jam_masuk' => $now->toTimeString(),
                    'status' => $status,
                    'keterangan' => $keterangan,
                    'created_at' => now(),
                ]);
                DB::commit();

                $this->wa->sendCheckIn($siswa->nama, $siswa->no_wa, $now->format('H:i'), $status, $device->school_id, $keterangan, $siswa->wa_ortu, $siswa->kelas->nama_kelas ?? '-');


                $this->logRequest($apiKey, 'checkin_success', $uid, true, 'Masuk: ' . $siswa->nama);
                return $this->response(true, 'success', 'Absen masuk berhasil', 'ok', [
                    'type' => 'absen_masuk',
                    'nama' => $siswa->nama,
                    'attendance_status' => $status
                ]);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Scan error: " . $e->getMessage());
            return $this->response(false, 'gagal', 'Terjadi kesalahan sistem', 'error');
        }
    }


    private function response($ok, $status, $message, $sound = 'ok', $extra = [])
    {
        if (!$this->hasLogged) {
            $action = $extra['type'] ?? $status;
            // Use derived action implies we didn't have a specific event like checkin_success
            // So we log it as a generic API response or the status itself
            $this->logRequest($this->currentApiKey, $action, $this->currentUid, $ok, $message);
        }

        $res = [
            'ok' => $ok,
            'status' => $status,
            'message' => $message,
            'sound' => $sound,
            'timestamp' => now()->toDateTimeString()
        ];
        return response()->json(array_merge($res, $extra));
    }

    private function logRequest($apiKey, $action, $uid, $success, $message)
    {
        $this->hasLogged = true;

        ApiLog::create([
            'school_id' => $this->currentSchoolId,
            'api_key' => $apiKey,
            'action' => $action,
            'uid' => $uid,
            'success' => $success,
            'message' => substr($message, 0, 500),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now()
        ]);
    }

    private function sendWhatsApp($phone, $message)
    {
        $phone = $this->formatWhatsApp($phone);
        if ($phone) {
            MessageQueue::create([
                'phone_number' => $phone,
                'message' => $message,
                'status' => 'pending',
                'created_at' => now()
            ]);
        }
    }

    private function formatWhatsApp($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (empty($phone))
            return null;
        if (substr($phone, 0, 1) === '0')
            $phone = '62' . substr($phone, 1);
        elseif (substr($phone, 0, 2) !== '62')
            $phone = '62' . $phone;

        return $phone . '@s.whatsapp.net';
    }
}