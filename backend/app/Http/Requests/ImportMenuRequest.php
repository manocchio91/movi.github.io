<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class ImportMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['file' => ['required', File::types(['xlsx', 'docx'])->max(config('menu.max_upload_kb'))], 'name' => ['nullable', 'string', 'max:120']];
    }
}
