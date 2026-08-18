<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Support\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * /admin/contact-messages — submissions from the public /contact form.
 */
class ContactMessagesController extends Controller
{
    public function index(Request $request): View
    {
        $q = ContactMessage::query()->latest();

        if ($filter = $request->query('filter')) {
            if ($filter === 'unread') {
                $q->where('is_read', false);
            } elseif (in_array($filter, ['sales', 'support', 'partnership', 'other'], true)) {
                $q->where('topic', $filter);
            }
        }
        if ($search = trim((string) $request->query('q', ''))) {
            $esc = addcslashes($search, '%_\\');
            $q->where(function ($w) use ($esc) {
                $w->where('name', 'like', "%{$esc}%")
                  ->orWhere('email', 'like', "%{$esc}%")
                  ->orWhere('company', 'like', "%{$esc}%")
                  ->orWhere('message', 'like', "%{$esc}%");
            });
        }

        return view('admin.contact-messages.index', [
            'messages'    => $q->paginate(20)->withQueryString(),
            'unreadCount' => ContactMessage::where('is_read', false)->count(),
            'totalCount'  => ContactMessage::count(),
            'filter'      => $request->query('filter'),
            'search'      => $search,
        ]);
    }

    public function markRead(int $id): RedirectResponse
    {
        $m = ContactMessage::findOrFail($id);
        $m->update(['is_read' => ! $m->is_read]);
        return back();
    }

    public function markAllRead(): RedirectResponse
    {
        ContactMessage::where('is_read', false)->update(['is_read' => true]);
        return back()->with('success', __('All marked as read.'));
    }

    public function destroy(int $id): RedirectResponse
    {
        $m = ContactMessage::findOrFail($id);
        $m->delete();
        Audit::log('admin.contact_message.deleted', ['resource_label' => $m->email]);
        return back()->with('success', __('Message deleted.'));
    }
}
