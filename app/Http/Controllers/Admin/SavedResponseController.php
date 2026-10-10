<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SavedResponse;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SavedResponseController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        return view('admin.saved-responses.index', [
            'responses' => SavedResponse::orderBy('language')->orderBy('title')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.saved-responses.form', ['response' => new SavedResponse(['language' => 'en'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $response = SavedResponse::create([...$this->validated($request), 'created_by' => $request->user()->id]);
        $this->audit->log('saved_response.created', $response);

        return redirect()->route('admin.saved-responses.index')->with('status', 'Saved response added.');
    }

    public function edit(SavedResponse $savedResponse): View
    {
        return view('admin.saved-responses.form', ['response' => $savedResponse]);
    }

    public function update(Request $request, SavedResponse $savedResponse): RedirectResponse
    {
        $savedResponse->update($this->validated($request));
        $this->audit->log('saved_response.updated', $savedResponse);

        return redirect()->route('admin.saved-responses.index')->with('status', 'Saved response updated.');
    }

    public function destroy(SavedResponse $savedResponse): RedirectResponse
    {
        $this->audit->log('saved_response.deleted', $savedResponse);
        $savedResponse->delete();

        return redirect()->route('admin.saved-responses.index')->with('status', 'Saved response deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:6000'],
            'language' => ['required', Rule::in(['en', 'sw'])],
            'category' => ['nullable', Rule::in(config('aisa.categories'))],
        ]);
    }
}
