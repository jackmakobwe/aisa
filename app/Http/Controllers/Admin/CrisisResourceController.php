<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CrisisResource;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Crisis and referral contacts. A contact is only published after a person
 * marks it verified, and any edit to a verified contact takes it offline
 * until it is verified again.
 */
class CrisisResourceController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        return view('admin.resources.index', [
            'resources' => CrisisResource::with('verifier:id,name')->orderBy('category')->orderBy('sort_order')->get()->groupBy('category'),
        ]);
    }

    public function create(): View
    {
        return view('admin.resources.form', ['resource' => new CrisisResource]);
    }

    public function store(Request $request): RedirectResponse
    {
        $resource = CrisisResource::create($this->validated($request));
        $this->audit->log('resource.created', $resource);

        return redirect()->route('admin.resources.index')->with('status', 'Contact added. It stays hidden from the public until you verify it.');
    }

    public function edit(CrisisResource $resource): View
    {
        return view('admin.resources.form', compact('resource'));
    }

    public function update(Request $request, CrisisResource $resource): RedirectResponse
    {
        $resource->fill($this->validated($request));
        $changed = $resource->isDirty();
        if ($changed) {
            $resource->forceFill(['is_verified' => false, 'verified_by' => null, 'verified_at' => null]);
        }
        $resource->save();
        $this->audit->log('resource.updated', $resource, ['unverified' => $changed]);

        return redirect()->route('admin.resources.index')->with('status', $changed ? 'Contact updated. Verify it again to publish it.' : 'No changes.');
    }

    public function verify(Request $request, CrisisResource $resource): RedirectResponse
    {
        $request->validate(['checked' => ['accepted']], ['checked.accepted' => 'Confirm that you have checked this contact yourself.']);

        $resource->forceFill(['is_verified' => true, 'verified_by' => $request->user()->id, 'verified_at' => now()])->save();
        $this->audit->log('resource.verified', $resource);

        return back()->with('status', "\"{$resource->name}\" is verified and now shown on Get help now.");
    }

    public function destroy(CrisisResource $resource): RedirectResponse
    {
        $this->audit->log('resource.deleted', $resource, ['name' => $resource->name]);
        $resource->delete();

        return redirect()->route('admin.resources.index')->with('status', 'Contact removed.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'category' => ['required', Rule::in(CrisisResource::CATEGORIES)],
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+()\s\-]{3,40}$/', 'required_without_all:url,address'],
            'url' => ['nullable', 'url:https,http', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:80'],
            'region' => ['nullable', Rule::in(config('help.regions'))],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'source_note' => ['nullable', 'string', 'max:500'],
            'description_en' => ['nullable', 'string', 'max:1000'],
            'description_sw' => ['nullable', 'string', 'max:1000'],
            'hours' => ['nullable', 'string', 'max:80'],
            'sort_order' => ['nullable', 'integer', 'between:0,999'],
        ]);
    }
}
