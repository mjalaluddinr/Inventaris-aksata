<?php

namespace App\Jobs;

use App\Http\Controllers\Admin\ManageUser;
use App\Models\LogInput;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImportAkunJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 150;

    public function __construct(
        public readonly string $logInputId,
        public readonly string $uploadDirectory,
        public readonly string $filePath,
    ) {}

    public function handle(ManageUser $importer): void
    {
        try {
            $importer->processImport($this->logInputId, $this->filePath);
        } catch (Throwable $exception) {
            report($exception);
            $this->markImportFailed();
        } finally {
            Storage::disk('local')->deleteDirectory($this->uploadDirectory);
        }
    }

    public function failed(?Throwable $exception): void
    {
        if ($exception !== null) {
            report($exception);
        }

        $this->markImportFailed();
        Storage::disk('local')->deleteDirectory($this->uploadDirectory);
    }

    private function markImportFailed(): void
    {
        $log = LogInput::query()
            ->where('jenis_input', 'akun')
            ->find($this->logInputId);

        if ($log instanceof LogInput) {
            $log->update([
                'status' => 'gagal',
                'detail_error' => [
                    $log->nama_file,
                    'processing_failed',
                    [],
                ],
                'selesai_at' => now(),
            ]);
        }
    }
}
