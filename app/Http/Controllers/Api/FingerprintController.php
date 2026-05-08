<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\Device;
use App\Models\ApiLog;
use App\Models\Guru;
use App\Models\GuruFingerprint;
use App\Models\Siswa;
use App\Models\SiswaFingerprint;
use App\Models\Attendance;
use App\Models\TeacherCheckoutSession;
use App\Models\GateCard;

class FingerprintController extends Controller
{
private $currentApiKey = null;
private $currentId = null;
private $currentSchoolId = null;
protected $wa;

public function __construct(\App\Services\WhatsAppService $wa)
{
$this->wa = $wa;
}

// ... (handle and checkEnrollRequest methods omitted for brevity as they are unchanged) ...

public function handle(Request $request)
{
$apiKey = trim($request->input('api_key', ''));
$this->currentApiKey = $apiKey;

// Parse scanned_at (offline sync)
$now = now();
$scannedAt = trim($request->input('scanned_at', ''));
if ($scannedAt !== '') {
    try {
        $parsed = Carbon::parse($scannedAt);
        if ($parsed->lte(now()) && $parsed->gte(now()->subDays(7))) {
            $now = $parsed;
        }
    } catch (\Exception $e) {}
}

// 1. Auth: Get Device
if ($apiKey === '') {
    $this->logFailedAuth('', 'API Key Kosong', $request);
    return $this->response(false, 'gagal', 'API Key Kosong');
}

$device = $this->authenticate($apiKey, $request);
if (!$device) {
return $this->response(false, 'gagal', 'API Key Invalid');
}

// 2. Input
$fingerId = $request->input('finger_id');
$this->currentId = $fingerId;

// Check for Ping (Boot Notification)
if ($request->has('ping')) {
ApiLog::create([
'school_id' => $this->currentSchoolId,
'api_key' => $apiKey,
'action' => 'ping',
'uid' => null,
'success' => true,
'message' => 'Boot Ping (IP Record)',
'ip_address' => $request->ip(),
'created_at' => now()
]);
return $this->response(true, 'ok', 'Pong');
}

// Check if this is an Enroll Confirmation
if ($request->has('enroll_success') && $request->input('enroll_success') == true) {
return $this->finalizeEnrollment($fingerId, $device);
}

// 3. Scan Logic
if ($fingerId) {
return $this->handleScan($fingerId, $device, $now);
}

return $this->response(false, 'gagal', 'Finger ID required');
}

public function checkEnrollRequest(Request $request)
{
$apiKey = $request->input('api_key');
// Validate API Key
$device = $this->authenticate($apiKey);
if (!$device) {
return $this->response(false, 'gagal', 'Auth Failed');
}

// Check Guru Enroll Request first SCOPED
$guru = Guru::where('enroll_finger_status', 'requested')
->where('school_id', $device->school_id)
->where('updated_at', '>=', now()->subMinutes(15))
->orderBy('updated_at', 'desc')
->first();

if ($guru) {
return $this->response(true, 'enroll_mode', 'Enroll Mode Active (Guru)', 'ok', [
'enroll_id' => $guru->id,
'nama' => $guru->nama,
'type' => 'guru'
]);
}

// Check Siswa Enroll Request SCOPED
$siswa = Siswa::where('enroll_finger_status', 'requested')
->where('school_id', $device->school_id)
->where('updated_at', '>=', now()->subMinutes(15))
->orderBy('updated_at', 'desc')
->first();

if ($siswa) {
return $this->response(true, 'enroll_mode', 'Enroll Mode Active (Siswa)', 'ok', [
'enroll_id' => $siswa->id,
'nama' => $siswa->nama,
'type' => 'siswa'
]);
}

        // Check Gate Card Enroll Request SCOPED
        $gate = GateCard::where('enroll_status', 'requested')
            ->where('school_id', $device->school_id)
            ->where('updated_at', '>=', now()->subMinutes(15))
            ->orderBy('updated_at', 'desc')
            ->first();

        if ($gate) {
            return $this->response(true, 'enroll_mode', 'Enroll Mode Active (Gerbang)', 'ok', [
                'enroll_id' => $gate->id,
                'nama' => $gate->name,
                'type' => 'gate_card'
            ]);
        }

        return $this->response(false, 'standby', 'No Enrollment');
}

private function finalizeEnrollment($fingerId, $device)
{
DB::beginTransaction();
try {
// Check Guru first SCOPED
$guru = Guru::where('enroll_finger_status', 'requested')
->where('school_id', $device->school_id)
->where('updated_at', '>=', now()->subMinutes(15))
->orderBy('updated_at', 'desc')
->lockForUpdate()
->first();

if ($guru) {
GuruFingerprint::updateOrCreate(
['guru_id' => $guru->id, 'device_id' => $device->id, 'finger_id' => $fingerId],
['created_at' => now()]
);

$guru->update([
'enroll_finger_status' => 'done',
'id_finger' => $fingerId,
]);

DB::commit();
return $this->response(true, 'success', 'Enroll Berhasil (Guru): ' . $guru->nama, 'success');
}

// Check Siswa SCOPED
$siswa = Siswa::where('enroll_finger_status', 'requested')
->where('school_id', $device->school_id)
->where('updated_at', '>=', now()->subMinutes(15))
->orderBy('updated_at', 'desc')
->lockForUpdate()
->first();

if ($siswa) {
SiswaFingerprint::updateOrCreate(
['student_id' => $siswa->id, 'device_id' => $device->id, 'finger_id' => $fingerId],
['created_at' => now()]
);

$siswa->update([
'enroll_finger_status' => 'done',
'id_finger' => $fingerId,
]);

DB::commit();
return $this->response(true, 'success', 'Enroll Berhasil (Siswa): ' . $siswa->nama, 'success');
}

        // Check Gate Card SCOPED
        $gate = GateCard::where('enroll_status', 'requested')
            ->where('school_id', $device->school_id)
            ->where('updated_at', '>=', now()->subMinutes(15))
            ->orderBy('updated_at', 'desc')
            ->lockForUpdate()
            ->first();

        if ($gate) {
            // Note: gate_cards doesn't have id_finger specifically, we reuse uid_rfid field for simplicity or just save it.
            $gate->update([
                'enroll_status' => 'done',
                'uid_rfid' => $fingerId,
            ]);

            DB::commit();
            return $this->response(true, 'success', 'Enroll Berhasil (Gerbang): ' . $gate->name, 'success');
        }

        // Neither found
        DB::rollBack();
return $this->response(false, 'gagal', 'Enroll Timeout / No Request');

} catch (\Exception $e) {
DB::rollBack();
Log::error("Finalize Enroll Error: " . $e->getMessage());
return $this->response(false, 'error', 'Enroll Gagal');
}
}

    private function logFailedAuth(string $apiKey, string $reason, $request = null)
    {
        ApiLog::create([
            'school_id'  => null,
            'api_key'    => $apiKey,
            'action'     => 'auth_failed',
            'uid'        => null,
            'success'    => false,
            'message'    => $reason,
            'ip_address' => $request ? $request->ip() : request()->ip(),
            'user_agent' => $request ? $request->userAgent() : request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    private function authenticate($apiKey, $request = null)
    {
        if (empty($apiKey))
            return null;
        $device = Device::where('api_key', $apiKey)->where('active', true)->first();
        if (!$device) {
            $this->logFailedAuth($apiKey, 'API key tidak valid / tidak aktif', $request);
        }
        if ($device) {
            $this->currentSchoolId = $device->school_id;
            DB::table('api_keys')->where('id', $device->id)->update(['last_used_at' => now()]);
        }
        return $device;
    }

    private function handleScan($fingerId, $device, $now = null)
    {
        $now = $now ?? now();
        
        // Check Gate Card first
        $gateCard = GateCard::with('guru')
            ->where('school_id', $device->school_id)
            ->where('uid_rfid', $fingerId)
            ->first();

        if ($gateCard) {
            try {
                DB::beginTransaction();
                
                $gateName = $gateCard->guru_id ? ($gateCard->guru->nama ?? $gateCard->name) : $gateCard->name;
                
                TeacherCheckoutSession::where('expires_at', '<', $now)->delete();

                TeacherCheckoutSession::create([
                    'teacher_id' => $gateCard->guru_id,
                    'teacher_name' => $gateName,
                    'uid_rfid' => $fingerId,
                    'status' => 'open',
                    'expires_at' => $now->copy()->addMinutes(30),
                    'created_at' => $now
                ]);

                DB::commit();
                
                ApiLog::create([
                    'school_id' => $this->currentSchoolId,
                    'api_key' => $this->currentApiKey,
                    'action' => 'gate_access',
                    'uid' => $fingerId,
                    'success' => true,
                    'message' => 'Sesi Kepulangan Dibuka: ' . $gateName,
                    'created_at' => $now
                ]);

                return $this->response(true, 'success', "Gerbang Dibuka (30 Menit).", 'ok', [
                    'type' => 'gate_opened',
                    'nama' => $gateName
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Gate finger scan error: " . $e->getMessage());
                return $this->response(false, 'error', 'System Error');
            }
        }

        // Check Guru
        $guruFingerprint = GuruFingerprint::where('device_id', $device->id)
            ->where('finger_id', $fingerId)
            ->with('guru')
            ->first();

        if ($guruFingerprint && $guruFingerprint->guru) {
            $guru = $guruFingerprint->guru;

            try {
                DB::beginTransaction();
                $today = $now->format('Y-m-d');

                // ABSENSI HARIAN (Daily)
                $absensi = \App\Models\AbsensiGuru::where('guru_id', $guru->id)
                    ->where('tanggal', $today)
                    ->where('school_id', $device->school_id) // Scope
                    ->whereNull('jadwal_pelajaran_id')
                    ->lockForUpdate()
                    ->first();

                if (!$absensi) {
                    // CASE: CHECK-IN
                    \App\Models\AbsensiGuru::create([
                        'guru_id' => $guru->id,
                        'school_id' => $device->school_id, // Scope
                        'jadwal_pelajaran_id' => null, // Daily
                        'tanggal' => $today,
                        'jam_masuk' => $now->toTimeString(),
                        'waktu_hadir' => $now, // Legacy field
                        'status' => 'Hadir', // Default Hadir
                        'keterangan' => null,
                        'created_at' => $now
                    ]);

                    DB::commit();

                    // Send WA Check-in
                    try {
                        $this->wa->sendCheckIn($guru->nama, $guru->no_wa, $now->format('H:i'), 'Hadir', $device->school_id, '-', null, '-');
                    } catch (\Exception $e) {
                        Log::error("WA Guru Checkin Error: " . $e->getMessage());
                    }

                    ApiLog::create([
                        'school_id' => $this->currentSchoolId,
                        'api_key' => $this->currentApiKey,
                        'action' => 'checkin_success',
                        'uid' => $fingerId,
                        'success' => true,
                        'message' => 'Guru Masuk: ' . $guru->nama,
                        'created_at' => $now
                    ]);

                    return $this->response(true, 'success', "Selamat Pagi, {$guru->nama}.", 'ok', [
                        'type' => 'absen_masuk_guru',
                        'nama' => $guru->nama,
                        'jam' => $now->format('H:i')
                    ]);

                } else {
                    // CASE: ALREADY CHECKED IN
                    $checkoutEnabled = \App\Models\Setting::where('school_id', $device->school_id)
                        ->where('setting_key', 'enable_checkout_teacher')
                        ->value('setting_value') ?? 'false';

                    if ($checkoutEnabled === 'false') {
                        DB::commit();
                        ApiLog::create([
                            'school_id' => $this->currentSchoolId,
                            'api_key' => $this->currentApiKey,
                            'action' => 'checkin_success',
                            'uid' => $fingerId,
                            'success' => true,
                            'message' => 'Sudah Absen Masuk: ' . $guru->nama,
                            'created_at' => $now
                        ]);
                        return $this->response(true, 'success', "Sudah Absen Masuk.", 'ok', [
                            'type' => 'absen_sudah_masuk_guru',
                            'nama' => $guru->nama
                        ]);
                    }

                    // Check for active gate session
                    $gateSession = TeacherCheckoutSession::where('expires_at', '>', $now)
                        ->where('status', 'open')
                        ->orderBy('created_at', 'desc')
                        ->first();

                    if (!$gateSession) {
                        DB::rollBack();
                        return $this->response(false, 'gagal', 'Belum ada izin gerbang.', 'warning', ['type' => 'no_authorization', 'nama' => $guru->nama]);
                    }

                    // Process Pulang
                    $masuk = \Carbon\Carbon::parse($absensi->jam_masuk);
                    $totalSeconds = $now->diffInSeconds($masuk);
                    
                    $absensi->update([
                        'jam_pulang' => $now->toTimeString(),
                        'updated_at' => now(),
                    ]);
                    DB::commit();

                    $hours = floor($totalSeconds / 3600);
                    $mins = floor(($totalSeconds % 3600) / 60);

                    try {
                        $this->wa->sendCheckOut($guru->nama, $guru->no_wa, $now->format('H:i'), $hours, $mins, $gateSession->teacher_name, $device->school_id, $masuk->format('H:i'));
                    } catch (\Exception $e) {
                        Log::error("WA Guru Checkout Error: " . $e->getMessage());
                    }

                    ApiLog::create([
                        'school_id' => $this->currentSchoolId,
                        'api_key' => $this->currentApiKey,
                        'action' => 'checkout_success',
                        'uid' => $fingerId,
                        'success' => true,
                        'message' => 'Guru Pulang: ' . $guru->nama,
                        'created_at' => $now
                    ]);

                    return $this->response(true, 'success', "Absen pulang berhasil.", 'ok', [
                        'type' => 'absen_pulang_guru',
                        'nama' => $guru->nama,
                        'authorized_by' => $gateSession->teacher_name
                    ]);
                }

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Teacher finger scan error: " . $e->getMessage());
                return $this->response(false, 'error', 'System Error');
            }
        }

        // Check Siswa
        $siswaFingerprint = SiswaFingerprint::where('device_id', $device->id)
            ->where('finger_id', $fingerId)
            ->with('student.kelas')
            ->first();

        if ($siswaFingerprint && $siswaFingerprint->student) {
            $siswa = $siswaFingerprint->student;

            try {
                return $this->handleStudentAttendance($siswa, $fingerId, $device, $now);
            } catch (\Exception $e) {
                Log::error("Student finger scan error: " . $e->getMessage());
                return $this->response(false, 'error', 'System Error');
            }
        }

        // Neither found
        return $this->response(false, 'gagal', 'ID Sidik Jari Tidak Dikenal di Device Ini');
    }

    private function handleStudentAttendance($siswa, $fingerId, $device, $now = null)
    {
        $now = $now ?? now();
        $today = $now->format('Y-m-d');

        // Get Jadwal (Schedule) SCOPED
        $indexHari = $now->format('N');
        $jadwal = \App\Models\Jadwal::where('index_hari', $indexHari)
            ->where('school_id', $device->school_id)
            ->where('is_active', 1)
            ->first();

        if (!$jadwal) {
            return $this->response(false, 'gagal', 'Jadwal Libur/Kosong', 'warning');
        }

        $jamMasuk = \Carbon\Carbon::parse($now->format('Y-m-d') . ' ' . $jadwal->jam_masuk);
        $jamPulang = \Carbon\Carbon::parse($now->format('Y-m-d') . ' ' . $jadwal->jam_pulang);
        
        $awalAbsenMasuk = \Carbon\Carbon::parse($now->format('Y-m-d') . ' ' . $jadwal->awal_absen_masuk);
        $akhirAbsenMasuk = \Carbon\Carbon::parse($now->format('Y-m-d') . ' ' . $jadwal->akhir_absen_masuk);
        $akhirAbsenPulang = \Carbon\Carbon::parse($now->format('Y-m-d') . ' ' . $jadwal->akhir_absen_pulang);
        $batasTelat = $jamMasuk;

        DB::beginTransaction();

        $att = Attendance::where('student_id', $siswa->id)
            ->where('tanggal', $today)
            ->lockForUpdate()
            ->first();

        // If record exists but jam_masuk is NULL (Sakit, Izin, or Alpha from system)
        // allow a fresh check-in to override the system record
        if ($att && $att->jam_masuk === null && in_array($att->status, ['S', 'I', 'A', 'B'])) {
            $att->delete();
            $att = null;
        }

        // Case 1: Already complete
        if ($att && $att->jam_pulang) {
            DB::rollBack();
            return $this->response(true, 'success', 'Absen Lengkap', 'ok', [
                'type' => 'sudah_lengkap',
                'nama' => $siswa->nama
            ]);
        }

        // Case 2: Check-out
        if ($att && !$att->jam_pulang) {
            // Check if checkout is enabled in settings SCOPED
            $checkoutEnabled = \App\Models\Setting::where('school_id', $device->school_id)
                ->where('setting_key', 'enable_checkout_attendance')
                ->value('setting_value') ?? 'true';

            // If checkout is disabled, treat as complete attendance
            if ($checkoutEnabled === 'false') {
                DB::rollBack();
                return $this->response(true, 'success', 'Absen Lengkap', 'ok', [
                    'type' => 'sudah_lengkap',
                    'nama' => $siswa->nama
                ]);
            }

            // Check teacher authorization SCOPED
            $teacherSession = TeacherCheckoutSession::select('teacher_checkout_sessions.*')
                ->join('guru', 'teacher_checkout_sessions.teacher_id', '=', 'guru.id')
                ->where('guru.school_id', $device->school_id)
                ->where('teacher_checkout_sessions.expires_at', '>', $now)
                ->where('teacher_checkout_sessions.status', 'open')
                ->orderBy('teacher_checkout_sessions.created_at', 'desc')
                ->first();

            $isAutoCheckoutTime = $now->between($jamPulang, $akhirAbsenPulang);

            if ($now->gt($akhirAbsenPulang) && !$teacherSession) {
                 DB::rollBack();
                 return $this->response(false, 'gagal', 'Pulang Ditutup', 'warning', ['type' => 'checkout_closed', 'nama' => $siswa->nama]);
            }

            if (!$isAutoCheckoutTime && !$teacherSession) {
                if ($now->between($awalAbsenMasuk, $akhirAbsenMasuk)) {
                    DB::rollBack();
                    return $this->response(true, 'success', 'Sudah Absen Masuk', 'ok', ['type' => 'sudah_absen_masuk', 'nama' => $siswa->nama]);
                }

                DB::rollBack();
                return $this->response(false, 'gagal', 'Belum waktu pulang', 'warning', ['type' => 'no_authorization', 'nama' => $siswa->nama]);
            }

            // Process check-out
            $masuk = \Carbon\Carbon::parse($att->jam_masuk);
            $totalSeconds = $now->diffInSeconds($masuk, false);
            if ($totalSeconds < 0) $totalSeconds = abs($totalSeconds);

            $att->update([
                'jam_pulang' => $now->toTimeString(),
                'total_seconds' => $totalSeconds,
                'updated_at' => now(),
            ]);
            DB::commit();

            $hours = floor($totalSeconds / 3600);
            $mins = floor(($totalSeconds % 3600) / 60);
            $authorizedBy = $teacherSession ? $teacherSession->teacher_name : 'Sistem Otomatis';
            
            $this->wa->sendCheckOut($siswa->nama, $siswa->no_wa, $now->format('H:i'), $hours, $mins, $authorizedBy, $device->school_id, $masuk->format('H:i'), $siswa->wa_ortu);

            ApiLog::create([
                'school_id' => $this->currentSchoolId,
                'api_key' => $this->currentApiKey,
                'action' => 'checkout_success',
                'uid' => $fingerId,
                'success' => true,
                'message' => 'Pulang: ' . $siswa->nama,
                'created_at' => $now
            ]);

            return $this->response(true, 'success', 'Absen pulang berhasil', 'ok', [
                'type' => 'absen_pulang',
                'nama' => $siswa->nama,
                'authorized_by' => $authorizedBy
            ]);
        }

        // Case 3: Check-in
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
                $diff = $now->timestamp - $batasTelat->timestamp;
                $jam = floor($diff / 3600);
                $menit = floor(($diff % 3600) / 60);
                if ($jam > 0) {
                    $keterangan = "Telat {$jam} jam {$menit} menit";
                } else {
                    $keterangan = "Telat {$menit} menit";
                }
            }

            Attendance::create([
                'student_id' => $siswa->id,
                'tanggal' => $today,
                'jam_masuk' => $now->toTimeString(),
                'status' => $status,
                'keterangan' => $keterangan,
                'created_at' => $now,
            ]);
            DB::commit();

            $this->wa->sendCheckIn($siswa->nama, $siswa->no_wa, $now->format('H:i'), $status, $device->school_id, $keterangan, $siswa->wa_ortu, $siswa->kelas->nama_kelas ?? '-');

            ApiLog::create([
                'school_id' => $this->currentSchoolId,
                'api_key' => $this->currentApiKey,
                'action' => 'checkin_success',
                'uid' => $fingerId,
                'success' => true,
                'message' => 'Masuk: ' . $siswa->nama,
                'created_at' => $now
            ]);

            return $this->response(true, 'success', 'Absen masuk berhasil', 'ok', [
                'type' => 'absen_masuk',
                'nama' => $siswa->nama,
                'attendance_status' => $status
            ]);
        }
    }

private function authenticate($apiKey, $request = null)
{
    if (empty($apiKey))
        return null;
    $device = Device::where('api_key', $apiKey)->where('active', true)->first();
    if (!$device) {
        $this->logFailedAuth($apiKey, 'API key tidak valid / tidak aktif', $request);
    }
    if ($device) {
        $this->currentSchoolId = $device->school_id;
        DB::table('api_keys')->where('id', $device->id)->update(['last_used_at' => now()]);
    }
    return $device;
}

private function response($ok, $status, $message, $sound = 'ok', $extra = [])
{
return response()->json(array_merge([
'ok' => $ok,
'status' => $status,
'message' => $message,
'sound' => $sound
], $extra));
}
}