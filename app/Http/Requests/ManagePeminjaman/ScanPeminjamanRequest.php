<?php

namespace App\Http\Requests\ManagePeminjaman;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ScanPeminjamanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->canProcessLoans();
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            // ASVS 2.2.1/2.2.2: accept only the code format issued by the trusted loan workflow.
            'code' => ['required', 'string', 'max:30', 'regex:/\A(?:PJM-[0-9]{8}-[0-9]{3,}|[A-Z0-9]{26})\z/'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'QR tidak valid. Pindai QR peminjaman dari halaman riwayat peminjam.',
            'code.max' => 'QR tidak valid. Pindai QR peminjaman dari halaman riwayat peminjam.',
        ];
    }
}
