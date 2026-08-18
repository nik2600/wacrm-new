<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class NotificationsController extends Controller
{
    public function index(Request $request)
    {
        $userId   = Auth::id();
        $category = $request->string('category')->toString() ?: 'all';
        $q        = $request->string('q')->toString();
        $perPage  = 12;

        $query = Notification::query()->forCurrentWorkspace();
        if ($category === 'unread') {
            $query->where('status', true);
        } else {
            $query->category($category);
        }

        $items = $query->orderByDesc('created_at')
            ->get()
            ->filter(function ($n) use ($q) {
                if ($q === '') return true;
                $hay = mb_strtolower(($n->notification_title ?? '') . ' ' . ($n->notification_msg ?? ''));
                return str_contains($hay, mb_strtolower($q));
            })
            ->values();

        $page = max(1, $request->integer('page', 1));
        $lastPage = max(1, (int) ceil($items->count() / $perPage));
        $page = min($page, $lastPage);

        $notifications = new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            [
                'path'  => route('user.notifications.index'),
                'query' => $request->except('partial'),
            ]
        );

        $stats        = $this->stats($userId);
        $catCounts    = $this->categoryCounts($userId);
        $grouped      = $this->groupByDay($notifications->getCollection());

        if ($request->boolean('partial')) {
            return response()->json([
                'ok'             => true,
                'feed'           => view('user.notifications._feed', compact('grouped'))->render(),
                'pagination'     => view('user.notifications._pagination', compact('notifications'))->render(),
                'stats'          => $stats,
                'categoryCounts' => $catCounts,
                'shown'          => $notifications->count(),
                'total'          => $notifications->total(),
                'page'           => $notifications->currentPage(),
            ]);
        }

        return view('user.notifications.index', [
            'grouped'         => $grouped,
            'notifications'   => $notifications,
            'stats'           => $stats,
            'categoryCounts'  => $catCounts,
            'currentCategory' => $category,
            'currentQuery'    => $q,
            'currentPage'     => $notifications->currentPage(),
            'totalShown'      => $notifications->count(),
            'totalFiltered'   => $notifications->total(),
        ]);
    }

    /**
     * Tiny payload for the header bell dropdown — 8 most recent rows
     * + the unread badge count. No pagination, no filters. The full
     * page at /notifications handles those.
     */
    public function recent(): JsonResponse
    {
        $userId = Auth::id();
        $rows = Notification::query()
            ->forCurrentWorkspace()
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();
        $unread = Notification::query()->forCurrentWorkspace()->where('status', true)->count();

        return response()->json([
            'unread' => $unread,
            'items'  => $rows->map(fn ($n) => [
                'id'        => $n->id,
                'title'     => (string) ($n->notification_title ?? ''),
                'message'   => (string) ($n->notification_msg ?? ''),
                'category'  => (string) ($n->category ?? 'other'),
                'severity'  => (string) ($n->severity ?? 'info'),
                'icon'      => (string) ($n->icon ?? ''),
                'unread'    => (bool) $n->status,
                'is_urgent' => (bool) $n->is_urgent,
                'time_ago'  => $n->created_at?->diffForHumans() ?: '',
                'action_url'=> (string) ($n->action_url ?? ''),
            ])->values(),
        ]);
    }

    public function markRead(int $id): JsonResponse
    {
        $n = Notification::query()->forCurrentWorkspace()->findOrFail($id);
        $n->update(['status' => false, 'read_at' => now()]);
        return response()->json(['ok' => true]);
    }

    public function markAllRead(): JsonResponse
    {
        Notification::query()->forCurrentWorkspace()->where('status', true)->update([
            'status'  => false,
            'read_at' => now(),
        ]);
        return response()->json(['ok' => true]);
    }

    public function destroy(int $id): JsonResponse
    {
        Notification::query()->forCurrentWorkspace()->findOrFail($id)->delete();
        return response()->json(['ok' => true]);
    }

    public function destroyAll(): JsonResponse
    {
        Notification::query()->forCurrentWorkspace()->delete();
        return response()->json(['ok' => true]);
    }

    private function stats(?int $userId): array
    {
        $base   = Notification::query()->forCurrentWorkspace();
        $unread = (clone $base)->where('status', true)->count();
        $urgent = (clone $base)->where('is_urgent', true)->where('status', true)->count();
        $today  = (clone $base)->whereDate('created_at', now()->toDateString())->count();
        $week   = (clone $base)->where('created_at', '>=', now()->startOfWeek())->count();
        $avg    = max(1, (int) round((clone $base)->where('created_at', '>=', now()->subDays(30))->count() / 30));
        $delta  = $avg ? (int) round((($today - $avg) / $avg) * 100) : 0;
        $rules  = (clone $base)
            ->select('category')
            ->where('created_at', '>=', now()->subWeek())
            ->groupBy('category')
            ->pluck('category')
            ->count();

        return [
            'unread'      => $unread,
            'urgent'      => $urgent,
            'today'       => $today,
            'week'        => $week,
            'todayDelta'  => $delta,
            'activeRules' => max($rules, 0),
        ];
    }

    private function categoryCounts(?int $userId): array
    {
        $rows = Notification::query()
            ->forCurrentWorkspace()
            ->select('category', DB::raw('COUNT(*) as c'))
            ->groupBy('category')
            ->pluck('c', 'category')
            ->all();

        $unread = Notification::query()->forCurrentWorkspace()->where('status', true)->count();
        $total  = Notification::query()->forCurrentWorkspace()->count();

        return [
            'all'       => $total,
            'unread'    => $unread,
            'mention'   => (int) ($rows['mention']   ?? 0),
            'campaign'  => (int) ($rows['campaign']  ?? 0),
            'system'    => (int) ($rows['system']    ?? 0),
            'billing'   => (int) ($rows['billing']   ?? 0),
            'chat'      => (int) ($rows['chat']      ?? 0),
            'webhook'   => (int) ($rows['webhook']   ?? 0),
            'broadcast' => (int) ($rows['broadcast'] ?? 0),
            'template'  => (int) ($rows['template']  ?? 0),
            'device'    => (int) ($rows['device']    ?? 0),
            'contact'   => (int) ($rows['contact']   ?? 0),
        ];
    }

    /**
     * Group notifications into Today / Yesterday / Earlier-this-week / Older
     * for the feed sections in the blade.
     */
    private function groupByDay($items): array
    {
        $today = now()->startOfDay();
        $yest  = now()->subDay()->startOfDay();
        $week  = now()->startOfWeek();

        $groups = [
            'Today'                => collect(),
            'Yesterday'            => collect(),
            'Earlier this week'    => collect(),
            'Older'                => collect(),
        ];
        foreach ($items as $n) {
            $created = $n->created_at;
            if ($created >= $today)      $groups['Today']->push($n);
            elseif ($created >= $yest)   $groups['Yesterday']->push($n);
            elseif ($created >= $week)   $groups['Earlier this week']->push($n);
            else                         $groups['Older']->push($n);
        }
        return array_filter($groups, fn ($g) => $g->isNotEmpty());
    }
}
