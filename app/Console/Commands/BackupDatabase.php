<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('db:backup')]
#[Description('Create a backup of the sqlite database and save it to the server')]
class BackupDatabase extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dbPath = config('database.connections.sqlite.database');
        if (! file_exists($dbPath)) {
            $this->error('ملف قاعدة البيانات غير موجود.');

            return;
        }

        $filename = 'scheduled_'.now()->format('Y-m-d_H-i-s').'.sqlite';
        $backupDir = storage_path('app/backups');

        if (! file_exists($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        if (copy($dbPath, $backupDir.'/'.$filename)) {
            $this->info("تم أخذ النسخة الاحتياطية بنجاح: {$filename}");
        } else {
            $this->error('فشل في أخذ النسخة الاحتياطية.');
        }
    }
}
