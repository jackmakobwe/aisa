<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeletionRequest;
use App\Services\DeletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeletionController extends Controller
{
    public function index(): View
    {
        return view('admin.deletions.index', [
            'pending' => DeletionRequest::with('ticket:id,reference,safety_flagged')->where('status', DeletionRequest::STATUS_PENDING)->oldest('requested_at')->get(),
            'recent' => DeletionRequest::with('processor:id,name')->where('status', '!=', DeletionRequest::STATUS_PENDING)->latest('completed_at')->limit(30)->get(),
        ]);
    }

    public function complete(Request $request, DeletionRequest $deletion, DeletionService $deletions): RedirectResponse
    {
        abort_unless($deletion->status === DeletionRequest::STATUS_PENDING, 422);
        $deletions->complete($deletion, $request->user());

        return back()->with('status', "Conversation {$deletion->ticket_reference} has been deleted.");
    }

    public function reject(Request $request, DeletionRequest $deletion, DeletionService $deletions): RedirectResponse
    {
        abort_unless($deletion->status === DeletionRequest::STATUS_PENDING, 422);
        $data = $request->validate(['note' => ['required', 'string', 'max:255']]);
        $deletions->reject($deletion, $request->user(), $data['note']);

        return back()->with('status', 'Deletion request declined and recorded.');
    }
}
