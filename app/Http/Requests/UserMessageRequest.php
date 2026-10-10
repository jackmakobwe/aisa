<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A message written by a member of the public (first message or follow-up).
 * Content is stored encrypted and always rendered escaped.
 */
class UserMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['message' => $this->normalise($this->input('message'))]);
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:2', 'max:'.config('aisa.limits.message_max_chars')],
        ];
    }

    public function attributes(): array
    {
        return ['message' => __('chat.your_message')];
    }

    private function normalise(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        // Strip control characters (keep newlines and tabs) and trim.
        return trim(preg_replace('/[^\P{C}\n\t]+/u', '', $value) ?? '');
    }
}
