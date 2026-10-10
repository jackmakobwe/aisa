<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Models\ContactSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function show(): View
    {
        return view('public.contact', ['types' => ContactSubmission::TYPES]);
    }

    public function store(ContactRequest $request): RedirectResponse
    {
        ContactSubmission::create([
            'type' => $request->validated('type'),
            'message' => $request->validated('message'),
            'contact' => $request->validated('contact') ?: null,
            'locale' => app()->getLocale(),
            'status' => 'new',
        ]);

        return redirect()->route('contact.show')->with('status', __('contact.thanks'));
    }
}
