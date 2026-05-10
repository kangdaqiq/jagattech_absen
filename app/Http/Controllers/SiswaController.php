<?php

namespace App\Http\Controllers;

use App\Models\Siswa;
use App\Models\Kelas;
use App\Models\Device;
use App\Models\ApiLog;
use App\Models\SiswaFingerprint;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class SiswaController extends Controller
{
    public function index(Request $request)
    {
        $query = Siswa::with(['kelas', 'user'])->orderBy('nama');

        // Filter by school_id for non-super admin users
        if (auth()->user() && !auth()->user()->isSuperAdmin()) {
            $query->where('school_id', auth()->user()->school_id);
        }

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('nis', 'like', "%{$search}%")
                  ->orWhere('no_wa', 'like', "%{$search}%");
            });
        }

        // Filter by kelas
        if ($request->has('kelas_id') && !empty($request->kelas_id)) {
            $query->where('kelas_id', $request->kelas_id);
        }

        $siswa = $query->paginate(20)->withQueryString();

        // Also filter kelas by school
        $kelasQuery = Kelas::orderBy('nama_kelas');
        if (auth()->user() && !auth()->user()->isSuperAdmin()) {
            $kelasQuery->where('school_id', auth()->user()->school_id);
        }
        $kelas = $kelasQuery->get();

        $devices = Device::where('active', true)
            ->whereIn('type', ['fingerprint', 'rfid_fingerprint'])
            ->orderBy('name')
            ->get();

        return view('siswa.index', compact('siswa', 'kelas', 'devices'));
    }

    public function store(Request $request)
    {
        $schoolId = auth()->user()->isSuperAdmin() ? null : auth()->user()->school_id;

        $request->validate([
            'nama' => 'required|string|max:100',
            'nis' => [
                'required',
                'string',
                'max:20',
                Rule::unique('siswa')->where(fn($q) => $q->where('school_id', $schoolId))
            ],
            'tgl_lahir' => 'nullable|date',
            'kelas_id' => 'required|exists:kelas,id',
            'no_wa' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^(08|628)[0-9]{8,13}$/',
                Rule::unique('siswa')->where(fn($q) => $q->where('school_id', $schoolId))
            ],
            'wa_ortu' => ['nullable', 'string', 'max:20', 'regex:/^(08|628)[0-9]{8,13}$/'],
            'user_id' => 'nullable|exists:users,id',
        ]);

        $input = $request->all();
        // Force null if empty string to avoid unique constraint issues on empty strings
        if (empty($input['no_wa']))
            $input['no_wa'] = null;
        if (empty($input['wa_ortu']))
            $input['wa_ortu'] = null;

        if (auth()->user() && !auth()->user()->isSuperAdmin()) {
            $input['school_id'] = auth()->user()->school_id;
        }

        // Check Quota Limit
        $school = auth()->user()->isSuperAdmin() ? null : auth()->user()->school;
        // If super admin adds, we assume they know to check limit? No, let's just bypass for super admin or check target school?
        // Wait, if super admin creates student, school_id is passed?
        // SiswaController index filters by school_id for non-super admin.
        // For Super Admin, 'school_id' is NOT in the form input? $input['school_id'] is set from auth user only if NOT super admin.
        // If Super Admin creates a student, they currently CANNOT select a school?!
        // Let's check store method again.
        // " $schoolId = auth()->user()->isSuperAdmin() ? null : auth()->user()->school_id;"
        // "Rule::unique('siswa')->where(fn($q) => $q->where('school_id', $schoolId))"
        // This implies Super Admin creates global students (school_id=null)?
        // If so, limit applies to school. If global (system admin?), maybe unlimited.

        if (!auth()->user()->isSuperAdmin()) {
            if (!$school->hasStudentQuota()) {
                return back()->with('error', 'Gagal: Kuota siswa untuk sekolah ini sudah penuh (' . $school->student_limit . ' siswa). Hubungi Super Admin untuk upgrade quota.')->withInput();
            }
        }

        $siswa = Siswa::create($input);



        return redirect()->route('siswa.index')->with('success', 'Siswa berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $siswa = Siswa::findOrFail($id);
        $schoolId = auth()->user()->isSuperAdmin() ? null : auth()->user()->school_id;

        $request->validate([
            'nama' => 'required|string|max:100',
            'nis' => [
                'required',
                'string',
                'max:20',
                Rule::unique('siswa')->ignore($siswa->id)->where(fn($q) => $q->where('school_id', $schoolId))
            ],
            'tgl_lahir' => 'nullable|date',
            'kelas_id' => 'required|exists:kelas,id',
            'no_wa' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^(08|628)[0-9]{8,13}$/',
                Rule::unique('siswa')->ignore($siswa->id)->where(fn($q) => $q->where('school_id', $schoolId))
            ],
            'wa_ortu' => ['nullable', 'string', 'max:20', 'regex:/^(08|628)[0-9]{8,13}$/'],
            'uid_rfid' => 'nullable|string|max:50',
            'user_id' => 'nullable|exists:users,id',
        ]);

        $input = $request->all();
        if (empty($input['no_wa']))
            $input['no_wa'] = null;
        if (empty($input['wa_ortu']))
            $input['wa_ortu'] = null;

        $siswa->update($input);

        return redirect()->route('siswa.index')->with('success', 'Data siswa berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $siswa = Siswa::findOrFail($id);

        // Optional: Delete linked user? For now keep it or manual delete.
        // If we want to clean up:
        // if ($siswa->user_id) { \App\Models\User::destroy($siswa->user_id); }

        $siswa->delete();

        return redirect()->route('siswa.index')->with('success', 'Siswa berhasil dihapus.');
    }

    public function import(Request $request)
    {
        $request->validate([
            'fileExcel' => 'required|mimes:xlsx,xls,csv'
        ]);

        try {
            $file = $request->file('fileExcel');
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            $countSuccess = 0;
            $countSkip = 0;
            $firstRow = true;
            $schoolId = auth()->user()->isSuperAdmin() ? null : auth()->user()->school_id;

            // Fetch school once for quota checking
            $school = $schoolId ? \App\Models\School::find($schoolId) : null;

            // Scope Kelas Map by School
            $kelasMapQuery = Kelas::query();
            if ($schoolId) {
                $kelasMapQuery->where('school_id', $schoolId);
            }
            $kelasMap = $kelasMapQuery->pluck('id', 'nama_kelas')->mapWithKeys(function($id, $nama) {
                return [strtolower(trim($nama)) => $id];
            })->toArray();

            // Scope Existing NIS by School
            $existingNisQuery = Siswa::query();
            if ($schoolId) {
                $existingNisQuery->where('school_id', $schoolId);
            }
            $existingNis = $existingNisQuery->pluck('nis')->toArray();

            foreach ($rows as $row) {
                if ($firstRow) {
                    $firstRow = false;
                    continue;
                }

                try {
                    $nama = trim($row[0] ?? '');
                    $nis = trim($row[1] ?? '');

                    // Column C (Index 2): Tgl Lahir
                    $tglLahirRaw = trim($row[2] ?? '');
                    $tglLahir = null;
                    if ($tglLahirRaw) {
                        try {
                            // Support dd/mm/yyyy (primary), yyyy/mm/dd, yyyy-mm-dd, and other Carbon-parseable formats
                            if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $tglLahirRaw, $m)) {
                                // dd/mm/yyyy or dd-mm-yyyy
                                $tglLahir = \Carbon\Carbon::createFromFormat('d/m/Y', sprintf('%02d/%02d/%04d', $m[1], $m[2], $m[3]))->format('Y-m-d');
                            } elseif (preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/', $tglLahirRaw, $m)) {
                                // yyyy/mm/dd or yyyy-mm-dd
                                $tglLahir = \Carbon\Carbon::createFromFormat('Y/m/d', sprintf('%04d/%02d/%02d', $m[1], $m[2], $m[3]))->format('Y-m-d');
                            } elseif (is_numeric($tglLahirRaw)) {
                                // Excel serial date number
                                $tglLahir = \Carbon\Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$tglLahirRaw))->format('Y-m-d');
                            } else {
                                $tglLahir = \Carbon\Carbon::parse($tglLahirRaw)->format('Y-m-d');
                            }
                        } catch (\Exception $e) {
                            $tglLahir = null;
                        }
                    }

                    // Column D (Index 3): Kelas
                    $namaKelas = trim($row[3] ?? '');

                    // Column E (Index 4): WA Siswa
                    $wa = isset($row[4]) ? trim($row[4]) : null;

                    // Column F (Index 5): WA Ortu
                    $waOrtu = isset($row[5]) ? trim($row[5]) : null;

                    if ($nama === '' || $nis === '') {
                        $countSkip++;
                        continue;
                    }

                    if (in_array($nis, $existingNis)) {
                        $countSkip++;
                        continue;
                    }

                    // Resolve Kelas
                    $kelasId = null;
                    if ($namaKelas !== '') {
                        $namaKelasLower = strtolower($namaKelas);
                        if (isset($kelasMap[$namaKelasLower])) {
                            $kelasId = $kelasMap[$namaKelasLower];
                        } else {
                            // Jika kelas tidak cocok dengan yang ada di database, biarkan kosong (null)
                            $kelasId = null;
                        }
                    }

                    // Check Quota Limit before creating
                    if ($school && !$school->hasStudentQuota()) {
                        if ($request->wantsJson()) {
                            return response()->json(['success' => false, 'message' => "Import dihentikan: Kuota siswa penuh ({$school->student_limit} siswa). Berhasil diimpor: {$countSuccess} siswa."]);
                        }
                        return redirect()->route('siswa.index')
                            ->with('error', "Import dihentikan: Kuota siswa penuh ({$school->student_limit} siswa). Berhasil diimpor: {$countSuccess} siswa.");
                    }

                    $siswa = Siswa::create([
                        'nama' => $nama,
                        'nis' => $nis,
                        'tgl_lahir' => $tglLahir ?: null, // Ensure null if empty string
                        'kelas_id' => $kelasId,
                        'no_wa' => $this->normalizeWa($wa) ?: null,
                        'wa_ortu' => $this->normalizeWa($waOrtu) ?: null,
                        'school_id' => $schoolId,
                        'created_at' => now()
                    ]);


                    $existingNis[] = $nis;
                    $countSuccess++;
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error("Import Row Error (NIS: " . ($row[1] ?? 'unknown') . "): " . $e->getMessage());
                    $countSkip++;
                }
            }

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'message' => "Import selesai. Berhasil: $countSuccess. Dilewati/Gagal: $countSkip."]);
            }
            return redirect()->route('siswa.index')->with('success', "Import selesai. Berhasil: $countSuccess. Dilewati/Gagal: $countSkip.");

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Import Siswa Error: ' . $e->getMessage());
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Gagal import: ' . $e->getMessage()]);
            }
            return redirect()->route('siswa.index')->with('error', 'Gagal import: ' . $e->getMessage());
        }
    }

    public function downloadTemplate()
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header
        $sheet->setCellValue('A1', 'Nama Lengkap');
        $sheet->setCellValue('B1', 'NIS');
        $sheet->setCellValue('C1', 'Tanggal Lahir (DD/MM/YYYY)');
        $sheet->setCellValue('D1', 'Nama Kelas');
        $sheet->setCellValue('E1', 'No WhatsApp Siswa');
        $sheet->setCellValue('F1', 'No WhatsApp Ortu');

        // Example
        $sheet->setCellValue('A2', 'Ahmad Dani');
        $sheet->setCellValue('B2', '12345');
        $sheet->setCellValue('C2', '01/01/2005');
        $sheet->setCellValue('D2', 'X TKJ 1');
        $sheet->setCellValue('E2', '081234567890');
        $sheet->setCellValue('F2', '081234567891');

        // Format kolom C sebagai teks agar Excel tidak mengubah format tanggal
        $sheet->getStyle('C2:C1000')->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
        // Set lebar kolom
        $sheet->getColumnDimension('A')->setWidth(25);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(22);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(20);
        $sheet->getColumnDimension('F')->setWidth(20);
        // Style header
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);
        $sheet->getStyle('A1:F1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FF3C50E0');
        $sheet->getStyle('A1:F1')->getFont()->getColor()->setARGB('FFFFFFFF');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        $response = new \Symfony\Component\HttpFoundation\StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment;filename="Template_Import_Siswa.xlsx"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }



    private function normalizeWa($wa)
    {
        if (!$wa)
            return null;
        $wa = preg_replace('/[^0-9]/', '', $wa);
        if (strpos($wa, '08') === 0) {
            $wa = '62' . substr($wa, 1);
        }
        return $wa;
    }

    // Enrollment Methods
    public function enrollRequest($id)
    {
        $siswa = Siswa::findOrFail($id);

        // Reset ALL other pending requests first (Single Active Request Policy) - SCOPED
        $schoolId = $siswa->school_id;

        Siswa::where('enroll_status', 'requested')
            ->where('school_id', $schoolId)
            ->update(['enroll_status' => 'none']);

        \App\Models\Guru::where('enroll_status', 'requested')
            ->where('school_id', $schoolId)
            ->update(['enroll_status' => 'none']);

        $siswa->update(['enroll_status' => 'requested']);
        return response()->json(['ok' => true]);
    }

    public function cancelEnroll($id)
    {
        $siswa = Siswa::findOrFail($id);
        if ($siswa->enroll_status === 'requested') {
            $siswa->update(['enroll_status' => 'none']);
        }
        return response()->json(['ok' => true]);
    }

    public function enrollCheck($id)
    {
        $siswa = Siswa::findOrFail($id);
        if ($siswa->enroll_status === 'done' && $siswa->uid_rfid) {
            return response()->json(['ok' => true, 'uid' => $siswa->uid_rfid]);
        } elseif (str_starts_with($siswa->enroll_status, 'error:')) {
            $errorMsg = substr($siswa->enroll_status, 6);
            $siswa->update(['enroll_status' => 'none']);
            return response()->json(['ok' => true, 'uid' => null, 'error' => $errorMsg]);
        }
        return response()->json(['ok' => true, 'uid' => null]);
    }

    public function deleteUid($id)
    {
        $siswa = Siswa::findOrFail($id);
        $siswa->update(['uid_rfid' => null, 'enroll_status' => 'none']);
        return response()->json(['ok' => true]);
    }

    // Fingerprint Enrollment Methods
    public function enrollFingerRequest(Request $request, $id)
    {
        $request->validate([
            'device_id' => 'required|exists:api_keys,id'
        ]);

        $siswa = Siswa::findOrFail($id);

        // Reset ALL other pending requests - SCOPED
        $schoolId = $siswa->school_id; // Get school from the student being enrolled

        Siswa::where('enroll_finger_status', 'requested')
            ->where('school_id', $schoolId)
            ->update(['enroll_finger_status' => 'none']);

        \App\Models\Guru::where('enroll_finger_status', 'requested')
            ->where('school_id', $schoolId)
            ->update(['enroll_finger_status' => 'none']);

        $siswa->update(['enroll_finger_status' => 'requested']);

        // Get device IP and send push notification
        $device = Device::find($request->device_id);
        $latestLog = ApiLog::where('api_key', $device->api_key)
            ->whereNotNull('ip_address')
            ->orderBy('created_at', 'desc')
            ->first();

        if ($latestLog && $latestLog->ip_address) {
            try {
                $url = "http://{$latestLog->ip_address}/enroll-finger?id={$siswa->id}";
                Http::timeout(3)->get($url);
                \Log::info("Fingerprint enrollment push sent to {$latestLog->ip_address} for siswa {$siswa->id}");
            } catch (\Exception $e) {
                \Log::error("Failed to send enrollment push: " . $e->getMessage());
            }
        }

        return response()->json(['ok' => true]);
    }

    public function cancelFingerEnroll($id)
    {
        $siswa = Siswa::findOrFail($id);
        if ($siswa->enroll_finger_status === 'requested') {
            $siswa->update(['enroll_finger_status' => 'none']);
        }
        return response()->json(['ok' => true]);
    }

    public function enrollFingerCheck($id)
    {
        $siswa = Siswa::findOrFail($id);

        if ($siswa->enroll_finger_status === 'done' && $siswa->id_finger) {
            return response()->json(['ok' => true, 'id_finger' => $siswa->id_finger, 'status' => 'done']);
        }

        return response()->json(['ok' => true, 'id_finger' => null, 'status' => 'requested']);
    }

    public function deleteFingerId($id)
    {
        $siswa = Siswa::findOrFail($id);

        // Get all fingerprints for this student
        $fingerprints = SiswaFingerprint::where('student_id', $siswa->id)->get();

        foreach ($fingerprints as $fingerprint) {
            $device = Device::find($fingerprint->device_id);
            if ($device) {
                // Get device IP
                $latestLog = ApiLog::where('api_key', $device->api_key)
                    ->whereNotNull('ip_address')
                    ->orderBy('created_at', 'desc')
                    ->first();

                if ($latestLog && $latestLog->ip_address) {
                    try {
                        $url = "http://{$latestLog->ip_address}/delete-finger?id={$fingerprint->finger_id}";
                        Http::timeout(3)->get($url);
                        \Log::info("Delete fingerprint push sent to {$latestLog->ip_address} for finger_id {$fingerprint->finger_id}");
                    } catch (\Exception $e) {
                        \Log::error("Failed to send delete push: " . $e->getMessage());
                    }
                }
            }
        }

        // Delete from database
        SiswaFingerprint::where('student_id', $siswa->id)->delete();
        $siswa->update(['id_finger' => null, 'enroll_finger_status' => 'none']);

        return response()->json(['ok' => true]);
    }
    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:siswa,id'
        ]);

        $query = Siswa::whereIn('id', $request->ids);
        if (auth()->user() && !auth()->user()->isSuperAdmin()) {
            $query->where('school_id', auth()->user()->school_id);
        }
        
        $count = $query->delete();

        return response()->json([
            'success' => true,
            'message' => "$count data siswa berhasil dihapus."
        ]);
    }

    public function bulkUpdateKelas(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:siswa,id',
            'kelas_id' => 'required|integer|exists:kelas,id'
        ]);

        $query = Siswa::whereIn('id', $request->ids);
        if (auth()->user() && !auth()->user()->isSuperAdmin()) {
            $query->where('school_id', auth()->user()->school_id);
            // Verify that the requested kelas_id belongs to the same school
            $kelas = \App\Models\Kelas::where('id', $request->kelas_id)
                ->where('school_id', auth()->user()->school_id)
                ->first();
            if (!$kelas) {
                return response()->json(['success' => false, 'message' => 'Kelas tidak valid.'], 403);
            }
        }

        $count = $query->update(['kelas_id' => $request->kelas_id]);

        return response()->json([
            'success' => true,
            'message' => "$count siswa berhasil dipindah kelas."
        ]);
    }
}
