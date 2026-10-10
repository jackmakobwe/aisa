<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChooseHelpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mode' => ['required', 'in:ai,human'],
            // Explicit agreement that the conversation and AI summary are shared with Aisa.
            'share_ok' => ['exclude_unless:mode,human', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return ['share_ok.accepted' => __('help.must_agree_share')];
    }
}
