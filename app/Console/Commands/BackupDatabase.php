<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class BackupDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:backup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backup database to storage/app/backups';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting database backup...');

        $filename = "backup-" . Carbon::now()->format('Y-m-d-H-i-s') . ".sql";
        $path = storage_path("app/backups");

        if (!file_exists($path)) {
            mkdir($path, 0755, true);
        }

        $filePath = "$path/$filename";
        
        // Config
        $host = config('database.connections.mysql.host');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');
        $database = config('database.connections.mysql.database');

        // Path to mysqldump
        if (PHP_OS_FAMILY === 'Windows') {
             $mysqldumpPath = 'c:\xampp\mysql\bin\mysqldump.exe';
             if (!file_exists($mysqldumpPath)) {
                 $mysqldumpPath = 'd:\xampp\mysql\bin\mysqldump.exe'; // Check D: drive XAMPP
                 if (!file_exists($mysqldumpPath)) {
                     $mysqldumpPath = 'mysqldump'; // Try global if not found
                 }
             }
        } else {
             $mysqldumpPath = '/usr/bin/mysqldump'; // Typical Linux path
             if (!file_exists($mysqldumpPath)) {
                 $mysqldumpPath = 'mysqldump'; // Try global path
             }
        }
        
        // Command Construction
        // Note: Password argument -p must be attached immediately to value without space
        $passwordArg = !empty($password) ? "--password=\"$password\"" : "";
        
        // Use 2>&1 to capture errors
        $command = "\"$mysqldumpPath\" --user=\"$username\" $passwordArg --host=\"$host\" \"$database\" > \"$filePath\" 2>&1";

        $this->info("Executing backup...");
        // $this->info("Command: $command"); // Debug only, hides password
        
        $output = [];
        $returnVar = null;
        exec($command, $output, $returnVar);

        if ($returnVar === 0) {
            $this->info("Backup successful: $filename");
            
            // Upload to Cloudflare R2 if configured
            if (env('CLOUDFLARE_R2_ENDPOINT')) {
                $this->info("Uploading backup to Cloudflare R2...");
                try {
                    Storage::disk('r2')->put("backups/$filename", file_get_contents($filePath));
                    $this->info("Backup successfully uploaded to Cloudflare R2.");
                } catch (\Exception $e) {
                    $this->error("Failed to upload to Cloudflare R2: " . $e->getMessage());
                }
            }

            // Clean old backups locally (Keep last 7 days)
            $this->cleanOldBackups($path);
            
        } else {
            $this->error("Backup failed with exit code $returnVar");
        }
    }

    private function cleanOldBackups($path)
    {
        $files = glob("$path/*.sql");
        $now = time();
        $keepDays = 7;
        
        foreach ($files as $file) {
            if (is_file($file)) {
                if ($now - filemtime($file) >= 60 * 60 * 24 * $keepDays) {
                    unlink($file);
                    $this->info("Deleted old backup: " . basename($file));
                }
            }
        }
    }
}
