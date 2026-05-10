<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Guru extends Model
{
    protected $table = 'guru';
    public $timestamps = true;
    // timestamps enabled by default
    // const UPDATED_AT = null;  // Column does not exist in DB
    protected $fillable = ['nama', 'nip', 'no_wa', 'bot_access', 'is_global_report', 'id_finger', 'uid_rfid', 'enroll_status', 'enroll_finger_status', 'created_at', 'updated_at', 'school_id'];

    protected $casts = [
        'bot_access' => 'boolean',
        'is_global_report' => 'boolean',
    ];

    public function fingerprints()
    {
        return $this->hasMany(GuruFingerprint::class, 'guru_id');
    }

    public function jadwalPelajaran()
    {
        return $this->hasMany(JadwalPelajaran::class, 'guru_id');
    }

    public function absensi()
    {
        return $this->hasMany(AbsensiGuru::class, 'guru_id');
    }

    public function kelas()
    {
        return $this->hasOne(Kelas::class, 'wali_kelas_id');
    }
}
