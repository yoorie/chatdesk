<?php

namespace App\Http\Requests;

use App\Services\PlatformSettingsService;
use Illuminate\Foundation\Http\FormRequest;

class PasswordUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $settingsService = app(PlatformSettingsService::class);

        return [
            'current_password' => ['required', 'current_password'],
            'password' => $settingsService->getPasswordRules()];
    }
}
