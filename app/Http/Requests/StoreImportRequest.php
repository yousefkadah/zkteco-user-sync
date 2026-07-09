<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreImportRequest extends FormRequest
{
    private const ALLOWED_EXTENSIONS = ['xlsx', 'xls', 'csv', 'txt'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:10240'],
        ];
    }

    /**
     * xlsx/csv MIME sniffing is unreliable (xlsx is a zip), so validate the
     * user-provided extension instead of the guessed MIME type.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $file = $this->file('file');

            if ($file === null) {
                return;
            }

            $extension = strtolower($file->getClientOriginalExtension());

            if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
                $validator->errors()->add('file', 'The file must be a .xlsx, .xls or .csv file.');
            }
        });
    }
}
