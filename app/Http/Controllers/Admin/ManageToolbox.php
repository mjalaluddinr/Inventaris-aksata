<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ManageToolbox\StoreRequest;
use App\Models\Toolbox;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ManageToolbox extends Controller
{
    public function store(StoreRequest $request): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request): void {
                Toolbox::query()->create([
                    ...$request->validated(),
                    'kode_toolbox' => $this->nextToolboxCode(),
                ]);
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }

            return back()->withInput()->with('error', 'Kode wadah otomatis gagal dibuat. Silakan coba lagi.');
        }

        return redirect()->route('admin.barang')
            ->with('success', 'Wadah berhasil ditambahkan.');
    }

    private function nextToolboxCode(): string
    {
        $usedCodes = array_fill_keys(
            array_map('strtoupper', Toolbox::withTrashed()->lockForUpdate()->pluck('kode_toolbox')->all()),
            true,
        );
        $nextCodeNumber = 0;

        foreach (array_keys($usedCodes) as $code) {
            if (preg_match('/^WDH-(\d+)$/', $code, $matches) === 1) {
                $nextCodeNumber = max($nextCodeNumber, (int) $matches[1]);
            }
        }

        do {
            $code = sprintf('WDH-%03d', ++$nextCodeNumber);
        } while (isset($usedCodes[$code]));

        return $code;
    }
}
