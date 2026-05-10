<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Ubah kolom status agar menerima nilai 'T' (Terlambat)
        // Menggunakan raw query untuk mengubah ENUM atau VARCHAR
        DB::statement("ALTER TABLE attendance MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'H'");
    }

    public function down(): void
    {
        // Kembalikan ke ENUM tanpa T (hati-hati: data 'T' yang sudah ada akan hilang jika di-rollback)
        DB::statement("ALTER TABLE attendance MODIFY COLUMN status ENUM('H','I','S','A','B') NOT NULL DEFAULT 'H'");
    }
};
