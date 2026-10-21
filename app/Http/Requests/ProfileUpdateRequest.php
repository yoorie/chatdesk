<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'country_code' => ['nullable', 'string', 'regex:/^\+[1-9][0-9]{0,4}$/'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'locale' => ['nullable', 'string', 'in:en-IN,en-US,hi-IN,ta-IN,te-IN,mr-IN,bn-IN'],
            'timezone' => ['nullable', 'timezone']];
    }
}
