<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    /**
     * Stream the current SQLite database file as a download.
     *
     * Served through a plain HTTP route rather than a Livewire action so Symfony
     * streams the file in chunks (BinaryFileResponse), instead of Livewire reading
     * the whole file and base64-encoding it into memory — which exhausts PHP's
     * memory limit once the database grows past a few dozen megabytes.
     */
    public function downloadCurrent(): BinaryFileResponse
    {
        $dbPath = config('database.connections.sqlite.database');

        abort_unless(is_string($dbPath) && File::exists($dbPath), 404, 'ملف قاعدة البيانات غير موجود.');

        $filename = 'manual_'.now()->format('Y-m-d_H-i-s').'.sqlite';

        return response()->download($dbPath, $filename);
    }

    /**
     * Stream a previously stored backup file as a download.
     *
     * The requested name is reduced to its basename and the resolved path is
     * verified to sit inside the backups directory, so a crafted value such as
     * "../.env" cannot escape it.
     */
    public function downloadStored(string $filename): BinaryFileResponse
    {
        $backupDir = storage_path('app/backups');
        $path = $backupDir.DIRECTORY_SEPARATOR.basename($filename);

        abort_unless(File::exists($path), 404, 'الملف غير موجود.');

        $realBackupDir = realpath($backupDir);
        $realPath = realpath($path);
        abort_if(
            $realBackupDir === false || $realPath === false || ! str_starts_with($realPath, $realBackupDir.DIRECTORY_SEPARATOR),
            404,
        );

        return response()->download($path);
    }
}
