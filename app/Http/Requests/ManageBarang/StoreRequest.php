<?php

namespace App\Http\Requests\ManageBarang;

use App\Models\Lemari;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // ASVS 2.2.2/2.2.3: Validate location references and their room-cabinet relationship at the server boundary.
        return [
            'locations' => ['required', 'array', 'min:1', 'max:50'],
            'locations.*' => ['required', 'array:ruangan_id,lemari_id,stok_total,stok_tersedia'],
            'locations.*.ruangan_id' => [
                'required',
                'uuid',
                Rule::exists('ruangan', 'ruangan_id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'locations.*.lemari_id' => [
                'nullable',
                'uuid',
                Rule::exists('lemari', 'lemari_id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'locations.*.stok_total' => ['required', 'integer', 'min:0'],
            'locations.*.stok_tersedia' => ['required', 'integer', 'min:0'],
            'kategori_id' => [
                'required',
                'string',
                Rule::exists('kategori', 'kategori_id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'nama_barang' => ['required', 'string', 'max:150'],
            'satuan' => ['required', 'string', 'max:20'],
            'stok_total' => ['required', 'integer', 'min:0'],
            'stok_tersedia' => ['required', 'integer', 'min:0', 'lte:stok_total'],
            'kondisi' => ['required', Rule::in(['baru', 'lama'])],
            'kelayakan' => ['required', Rule::in(['layak', 'tidak_layak'])],
            'spesifikasi' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $locations = $this->input('locations', []);
                $cabinetIds = collect($locations)
                    ->pluck('lemari_id')
                    ->filter()
                    ->unique()
                    ->values();
                $cabinets = Lemari::query()
                    ->whereIn('lemari_id', $cabinetIds)
                    ->get(['lemari_id', 'ruangan_id'])
                    ->keyBy('lemari_id');
                $locationKeys = [];
                $stockTotal = 0;
                $stockAvailable = 0;

                foreach ($locations as $index => $location) {
                    $roomId = $location['ruangan_id'];
                    $cabinetId = $location['lemari_id'] ?? null;
                    $locationKey = $roomId.'|'.($cabinetId ?? '');

                    if (isset($locationKeys[$locationKey])) {
                        $validator->errors()->add("locations.{$index}.lemari_id", 'Lokasi barang yang sama tidak dapat dipilih lebih dari sekali.');
                    }

                    $locationKeys[$locationKey] = true;

                    if ($cabinetId !== null && $cabinets->get($cabinetId)?->ruangan_id !== $roomId) {
                        $validator->errors()->add("locations.{$index}.lemari_id", 'Lemari harus berada di ruangan yang dipilih.');
                    }

                    $stockTotal += (int) $location['stok_total'];
                    $stockAvailable += (int) $location['stok_tersedia'];

                    if ((int) $location['stok_tersedia'] > (int) $location['stok_total']) {
                        $validator->errors()->add("locations.{$index}.stok_tersedia", 'Stok tersedia tidak boleh melebihi stok di lokasi.');
                    }
                }

                if ($stockTotal !== (int) $this->input('stok_total')) {
                    $validator->errors()->add('locations', 'Jumlah stok per lokasi harus sama dengan jumlah total barang.');
                }

                if ($stockAvailable !== (int) $this->input('stok_tersedia')) {
                    $validator->errors()->add('locations', 'Stok tersedia per lokasi harus sama dengan stok tersedia barang.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'locations.required' => 'Minimal satu lokasi stok harus ditambahkan.',
            'locations.array' => 'Daftar lokasi stok tidak valid.',
            'locations.min' => 'Minimal satu lokasi stok harus ditambahkan.',
            'locations.max' => 'Maksimal 50 lokasi stok dapat ditambahkan.',
            'locations.*.ruangan_id.required' => 'Ruangan wajib dipilih untuk setiap lokasi.',
            'locations.*.ruangan_id.exists' => 'Ruangan tidak ditemukan atau sudah tidak aktif.',
            'locations.*.lemari_id.exists' => 'Lemari tidak ditemukan atau sudah tidak aktif.',
            'locations.*.stok_total.required' => 'Jumlah stok lokasi wajib diisi.',
            'locations.*.stok_total.integer' => 'Jumlah stok lokasi harus berupa bilangan bulat.',
            'locations.*.stok_total.min' => 'Jumlah stok lokasi tidak boleh kurang dari nol.',
            'locations.*.stok_tersedia.required' => 'Stok tersedia lokasi wajib diisi.',
            'locations.*.stok_tersedia.integer' => 'Stok tersedia lokasi harus berupa bilangan bulat.',
            'locations.*.stok_tersedia.min' => 'Stok tersedia lokasi tidak boleh kurang dari nol.',
            'kategori_id.required' => 'Kategori wajib dipilih.',
            'kategori_id.exists' => 'Kategori tidak ditemukan atau sudah tidak aktif.',
            'nama_barang.required' => 'Nama barang wajib diisi.',
            'satuan.required' => 'Satuan wajib diisi.',
            'stok_total.required' => 'Jumlah barang wajib diisi.',
            'stok_tersedia.required' => 'Stok tersedia wajib diisi.',
            'stok_tersedia.lte' => 'Stok tersedia tidak boleh melebihi jumlah barang.',
            'kondisi.in' => 'Kondisi barang tidak valid.',
            'kelayakan.in' => 'Status kelayakan tidak valid.',
        ];
    }
}
