<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class JoinWaitlistRequest extends FormRequest
{
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
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('waitlist_entries', 'email')],
            'phone' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'You already joined the waitlist.',
        ];
    }
}
