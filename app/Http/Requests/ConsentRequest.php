<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConsentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'age_group' => ['required', 'in:adult,minor'],
            'agree' => ['accepted'],
            'display_name' => ['nullable', 'string', 'max:40'],
        ];
    }

    public function attributes(): array
    {
        return [
            'age_group' => __('consent.age_question'),
            'agree' => __('consent.agree_label'),
            'display_name' => __('consent.name_label'),
        ];
    }

    public function messages(): array
    {
        return [
            'agree.accepted' => __('consent.must_agree'),
            'age_group.required' => __('consent.must_choose_age'),
        ];
    }
}
