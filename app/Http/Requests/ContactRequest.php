<?php

namespace App\Http\Requests;

use App\Models\ContactSubmission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(ContactSubmission::TYPES)],
            'message' => ['required', 'string', 'min:5', 'max:3000'],
            'contact' => ['nullable', 'string', 'max:120'],
            'privacy_ok' => ['accepted'],
            // Honeypot: real people never see or fill this field.
            'website' => ['prohibited'],
        ];
    }

    public function attributes(): array
    {
        return [
            'type' => __('contact.type_label'),
            'message' => __('contact.message_label'),
            'contact' => __('contact.contact_label'),
            'privacy_ok' => __('contact.privacy_label'),
        ];
    }
}
