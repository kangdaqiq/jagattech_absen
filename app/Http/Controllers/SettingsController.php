<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;

class SettingsController extends Controller
{
    public function index()
    {
        if (auth()->user() && !auth()->user()->isSuperAdmin()) {
            $schoolId = auth()->user()->school_id;

            // Only get settings for this specific school
            $settings = Setting::where('school_id', $schoolId)
                ->get()
                ->pluck('setting_value', 'setting_key')
                ->toArray();

            // Merge with Global Settings for display
            $globalSettings = Setting::where('school_id', 0)
                ->get()
                ->pluck('setting_value', 'setting_key')
                ->toArray();

            // Union: School settings take precedence
            $settings = collect($settings + $globalSettings);
        } else {
            // Super admin sees global settings (school_id = 0)
            $settings = Setting::where('school_id', 0)
                ->get()
                ->pluck('setting_value', 'setting_key');
        }

        return view('settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        // Handle logo upload
        // Handle logo upload
        if ($request->hasFile('logo')) {
            $request->validate([
                'logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:10240', // 10MB
            ]);

            // Get school_id (0 for Super Admin)
            $schoolId = auth()->user()->school_id ?? 0;

            // Upload to storage (same as SchoolController)
            $path = $request->file('logo')->store('schools/logos', 'public');

            // Save full path to settings using DB facade to handle composite key correctly
            \Illuminate\Support\Facades\DB::table('settings')->updateOrInsert(
                [
                    'setting_key' => 'logo_filename',
                    'school_id' => $schoolId
                ],
                ['setting_value' => $path]
            );

            if ($schoolId && $schoolId > 0) {
                \App\Models\School::where('id', $schoolId)->update(['logo' => $path]);
            }
        }

        // Handle License Key for self_hosted mode
        if (config('app.mode') === 'self_hosted' && (auth()->user()->isSuperAdmin() || auth()->user()->isAdmin()) && $request->has('license_key')) {
            $key = trim($request->input('license_key'));
            $path = base_path('.env');
            if (file_exists($path)) {
                $env = file_get_contents($path);
                if (str_contains($env, 'LICENSE_KEY=')) {
                    $env = preg_replace('/^LICENSE_KEY=.*$/m', 'LICENSE_KEY=' . $key, $env);
                } else {
                    $env .= "\nLICENSE_KEY=" . $key . "\n";
                }
                file_put_contents($path, $env);
            }
            \Illuminate\Support\Facades\Artisan::call('config:clear');
            app(\App\Services\LicenseService::class)->clearCache();
        }

        // Handle kop surat upload
        if ($request->hasFile('kop_surat')) {
            $request->validate([
                'kop_surat' => 'required|image|mimes:jpeg,png,jpg|max:5120', // 5MB
            ]);

            $schoolId = auth()->user()->school_id ?? 0;
            $path = $request->file('kop_surat')->store('schools/kop_surat', 'public');

            \Illuminate\Support\Facades\DB::table('settings')->updateOrInsert(
                [
                    'setting_key' => 'kop_surat',
                    'school_id' => $schoolId
                ],
                ['setting_value' => $path]
            );
        }

        $data = $request->except('_token', '_method', 'logo', 'license_key', 'kop_surat');

        // Handle checkboxes (they don't send data when unchecked)
        $checkboxSettings = [
            'enable_checkout_attendance',
            'enable_checkout_teacher',
            'absence_notification_enabled'
        ];

        foreach ($checkboxSettings as $checkbox) {
            if (!isset($data[$checkbox])) {
                $data[$checkbox] = 'false';
            }
        }

        // Get and validate school_id
        $schoolId = auth()->user()->school_id ?? 0;

        // Ensure school_id is valid (allow 0 for Super Admin)
        if ((!$schoolId && $schoolId !== 0) && !auth()->user()->isSuperAdmin()) {
            return back()->with('error', 'User tidak memiliki school_id yang valid. Hubungi Super Admin.');
        }

        foreach ($data as $key => $value) {
            \Illuminate\Support\Facades\DB::table('settings')->updateOrInsert(
                [
                    'setting_key' => $key,
                    'school_id' => $schoolId
                ],
                ['setting_value' => $value]
            );
        }

        return back()->with('success', 'Pengaturan berhasil disimpan.');
    }
}
