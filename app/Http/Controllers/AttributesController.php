<?php

namespace App\Http\Controllers;

use App\Models\Attribute;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * /attributes — workspace-scoped template variables.
 *
 * Meta WhatsApp Business templates only accept positional placeholders
 * ({{1}}, {{2}}, ...), not named ones ({{name}}). The slash-popover
 * inserts the next available positional index; this table holds the
 * metadata (key + display name + default + description) the operator
 * sees in the picker.
 *
 * Scoping: per workspace, NOT per user — different workspace owners
 * shouldn't see each other's `order_id` definitions.
 */
class AttributesController extends Controller
{
    private function workspaceId(): ?int
    {
        return Auth::user()?->current_workspace_id;
    }

    public function index(Request $request): View|JsonResponse
    {
        $wsId   = $this->workspaceId();
        $q      = $request->string('q')->toString();
        $status = $request->string('status')->toString() ?: 'all';

        $rows = Attribute::query()
            ->forWorkspace($wsId)
            ->orderByDesc('id')
            ->get()
            ->filter(function ($a) use ($q, $status) {
                if ($status === 'active'   && !$a->status) return false;
                if ($status === 'inactive' &&  $a->status) return false;
                if ($q !== '' && !str_contains(mb_strtolower($a->attribute_name . ' ' . $a->attribute_key . ' ' . $a->description), mb_strtolower($q))) return false;
                return true;
            })
            ->values();
        $rows = $this->paginateCollection($rows, $request, 12);

        $all    = Attribute::query()->forWorkspace($wsId)->get();
        $counts = [
            'all'      => $all->count(),
            'active'   => $all->where('status', true)->count(),
            'inactive' => $all->where('status', false)->count(),
        ];

        if ($request->boolean('partial')) {
            return response()->json([
                'ok'     => true,
                'rows'   => view('user.attributes._rows', ['rows' => $rows])->render(),
                'counts' => $counts,
                'pagination' => view('user.partials.pagination', ['paginator' => $rows, 'dataAttr' => 'data-attr-page', 'label' => 'attributes'])->render(),
                'shown'  => $rows->count(),
                'total'  => $rows->total(),
                'page'   => $rows->currentPage(),
            ]);
        }

        return view('user.attributes.index', [
            'rows'           => $rows,
            'counts'         => $counts,
            'currentStatus'  => $status,
            'currentSearch'  => $q,
        ]);
    }

    public function apiList(): JsonResponse
    {
        $rows = Attribute::query()
            ->forWorkspace($this->workspaceId())
            ->where('status', true)
            ->get();

        return response()->json([
            'ok'     => true,
            'system' => [],
            'custom' => $rows->map(fn ($a) => [
                'name'        => $a->attribute_name,
                'key'         => $a->attribute_key,
                'description' => $a->description,
                'value'       => $a->attribute_value,
            ])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $wsId = $this->workspaceId();

        $data = $request->validate([
            'attribute_name'  => 'required|string|max:120',
            'attribute_key'   => [
                'required', 'string', 'max:64', 'alpha_dash',
                Rule::unique('attributes', 'attribute_key')->where(fn ($q) => $q->where('workspace_id', $wsId)),
            ],
            'attribute_value' => 'nullable|string|max:255',
            'description'     => 'nullable|string|max:500',
            'status'          => 'nullable|boolean',
        ]);

        Attribute::create([
            'user_id'         => Auth::id(),
            'workspace_id'    => $wsId,
            'attribute_name'  => $data['attribute_name'],
            'attribute_key'   => $data['attribute_key'],
            'attribute_value' => $data['attribute_value'] ?? null,
            'description'     => $data['description']     ?? null,
            'type'            => 'custom',
            'status'          => (bool) ($data['status'] ?? true),
        ]);

        return redirect()->route('user.attributes')->with('status', 'Attribute saved.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $a = Attribute::query()->forWorkspace($this->workspaceId())->findOrFail($id);

        $data = $request->validate([
            'attribute_name'  => 'required|string|max:120',
            'attribute_value' => 'nullable|string|max:255',
            'description'     => 'nullable|string|max:500',
            'status'          => 'nullable|boolean',
        ]);

        $a->fill([
            'attribute_name'  => $data['attribute_name'],
            'attribute_value' => $data['attribute_value'] ?? null,
            'description'     => $data['description']     ?? null,
            'status'          => (bool) ($data['status'] ?? true),
        ])->save();

        return back()->with('status', 'Attribute updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        Attribute::query()->forWorkspace($this->workspaceId())->findOrFail($id)->delete();
        return response()->json(['ok' => true]);
    }

    /**
     * Server-side validation helper used by Templates/Broadcasts/Campaigns
     * controllers when persisting a body that contains positional
     * placeholders. Rejects gaps and out-of-range slots so a body never
     * lands in the DB that Meta would later reject at template approval.
     *
     * Returns null when valid, or an error string when invalid.
     */
    public static function validatePlaceholders(?string $body): ?string
    {
        if ($body === null || $body === '') return null;
        preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $body, $m);
        if (empty($m[1])) return null;
        $nums   = array_map('intval', $m[1]);
        $unique = array_values(array_unique($nums));
        sort($unique);
        $expected = range(1, count($unique));
        if ($unique !== $expected) {
            return 'Variable placeholders must be 1-based and contiguous (use {{1}}, {{2}}, {{3}}... no gaps).';
        }
        return null;
    }
}
