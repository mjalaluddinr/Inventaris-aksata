<?php

namespace App\Jobs;

use App\Http\Controllers\Admin\ManageBarang;
use App\Models\LogInput;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImportBarangJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 150;

    public function __construct(
        public readonly string $logInputId,
        public readonly string $uploadDirectory,
        public readonly string $filePath,
        public readonly ?string $imagesZipPath,
        public readonly string $operatorId,
    ) {}

    public function handle(ManageBarang $importer): void
    {
        try {
            $importer->processImport(
                $this->logInputId,
                $this->filePath,
                $this->imagesZipPath,
                $this->operatorId,
            );
        } catch (Throwable $exception) {
            report($exception);
            $this->markImportFailed();
        } finally {
            Storage::disk('local')->deleteDirectory($this->uploadDirectory);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->markImportFailed();
        Storage::disk('local')->deleteDirectory($this->uploadDirectory);
    }

    private function markImportFailed(): void
    {
        $log = LogInput::query()->find($this->logInputId);
        if ($log instanceof LogInput) {
            $log->update([
                'status' => 'gagal',
                'detail_error' => [
                    'Import gagal diproses.',
                    'Import gagal diproses. Silakan coba kembali atau hubungi administrator.',
                    [],
                ],
                'selesai_at' => now(),
            ]);
        }
    }
}
