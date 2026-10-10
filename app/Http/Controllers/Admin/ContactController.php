<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactSubmission;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $status = $request->query('status') === 'handled' ? 'handled' : 'new';
        $submissions = ContactSubmission::where('status', $status)->latest('id')->paginate(20)->withQueryString();
        $this->audit->log('contacts.viewed', null, ['status' => $status, 'count' => $submissions->count()]);

        return view('admin.contacts.index', compact('submissions', 'status'));
    }

    public function handled(Request $request, ContactSubmission $contact): RedirectResponse
    {
        $contact->forceFill(['status' => 'handled', 'handled_by' => $request->user()->id, 'handled_at' => now()])->save();
        $this->audit->log('contact.handled', $contact);

        return back()->with('status', 'Marked as handled.');
    }
}
