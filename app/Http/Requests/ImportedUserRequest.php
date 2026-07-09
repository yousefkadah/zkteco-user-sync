<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportedUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Permissive at the form level — the device-specific rules (digits only,
     * length caps, duplicates) are applied by ImportedUserValidator so a row can
     * be saved and then shown as "invalid" for the user to fix.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:100'],
            'password' => ['nullable', 'string', 'max:20'],
            'card_number' => ['nullable', 'string', 'max:20'],
            'privilege' => ['nullable', 'string', 'in:user,admin'],
        ];
    }
}
