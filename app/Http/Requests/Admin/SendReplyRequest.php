<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A reply from Aisa. "approved" must be ticked: it is the explicit human
 * review that the project document requires before anything is sent.
 */
class SendReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('respond') ?? false;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:2', 'max:6000'],
            'approved' => ['accepted'],
            'draft_id' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return ['approved.accepted' => 'Tick the box to confirm you have read and approve this reply before it is sent.'];
    }
}
