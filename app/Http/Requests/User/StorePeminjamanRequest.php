<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class StorePeminjamanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-user-portal') ?? false;
    }

    public function rules(): array
    {
        // ASVS V2.2.2: validate the borrower input at the trusted server boundary.
        return [
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*' => ['required', 'array:barang_id,quantity'],
            'items.*.barang_id' => ['required', 'uuid', 'distinct', 'exists:barang,barang_id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'keperluan' => ['required', 'string', 'max:255'],
            'jenis_peminjaman' => ['required', 'string', 'in:harian,praktik,lainnya', 'max:30'],
            'tanggal_rencana_kembali' => ['required', 'date', 'after_or_equal:today'],
            'preserve_cart' => ['sometimes', 'boolean'],
            // ASVS V2.2.1/2.2.2: only clear the browser cart items explicitly submitted by this form.
            'clear_submitted_items' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Pilih setidaknya satu barang untuk diajukan.',
            'items.min' => 'Pilih setidaknya satu barang untuk diajukan.',
            'items.max' => 'Pengajuan dibatasi maksimal 50 jenis barang.',
            'items.*.barang_id.distinct' => 'Barang yang sama tidak boleh dimasukkan lebih dari sekali.',
            'items.*.quantity.integer' => 'Jumlah barang harus berupa angka bulat.',
            'items.*.quantity.min' => 'Jumlah barang minimal satu.',
            'tanggal_rencana_kembali.after_or_equal' => 'Tanggal rencana kembali tidak boleh sebelum hari ini.',
        ];
    }

    public function attributes(): array
    {
        return [
            'items' => 'barang yang dipinjam',
            'items.*.quantity' => 'jumlah barang',
            'keperluan' => 'keperluan',
            'jenis_peminjaman' => 'jenis peminjaman',
            'tanggal_rencana_kembali' => 'tanggal rencana kembali',
        ];
    }
}
