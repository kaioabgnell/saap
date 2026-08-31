<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
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
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'council_id' => ['nullable', 'string', 'max:40'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],

            'clinic_name' => ['nullable', 'string', 'max:160'],
            'clinic_phone' => ['nullable', 'string', 'max:20'],
            'clinic_email' => ['nullable', 'email', 'max:160'],
            'clinic_address' => ['nullable', 'string', 'max:255'],
            'clinic_city' => ['nullable', 'string', 'max:120'],
            'clinic_state' => ['nullable', 'string', 'size:2'],
            'clinic_zip' => ['nullable', 'string', 'max:9'],
        ];
    }
}
