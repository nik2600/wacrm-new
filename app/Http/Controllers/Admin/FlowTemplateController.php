<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Flow;
use App\Models\FlowTemplate;
use Illuminate\Http\Request;

/**
 * /admin/flow-templates — admin-curated Bot-Flow starter templates.
 *
 * The admin builds a standard flow (e.g. "Restaurant welcome + menu"), then
 * registers it here by uploading its exported JSON, pasting the JSON, or
 * cloning an existing flow by id. Active templates appear in every tenant's
 * "Start from a template" gallery on /flows with a one-click Clone.
 */
class FlowTemplateController extends Controller
{
    public function index()
    {
        $templates = FlowTemplate::ordered()->paginate(20);
        $stats = [
            'total'  => FlowTemplate::count(),
            'active' => FlowTemplate::where('is_active', true)->count(),
            'clones' => (int) FlowTemplate::sum('clone_count'),
        ];
        return view('admin.flow-templates.index', compact('templates', 'stats'));
    }

    public function create()
    {
        return view('admin.flow-templates.form', ['template' => null]);
    }

    public function edit(int $id)
    {
        return view('admin.flow-templates.form', ['template' => FlowTemplate::findOrFail($id)]);
    }

    public function store(Request $request)
    {
        return $this->persist($request, null);
    }

    public function update(Request $request, int $id)
    {
        return $this->persist($request, FlowTemplate::findOrFail($id));
    }

    public function toggle(int $id)
    {
        $t = FlowTemplate::findOrFail($id);
        $t->update(['is_active' => !$t->is_active]);
        return back()->with('success', $t->is_active ? __('Template is now visible to tenants.') : __('Template hidden from tenants.'));
    }

    public function destroy(int $id)
    {
        FlowTemplate::findOrFail($id)->delete();
        return redirect()->route('admin.flow-templates.index')->with('success', __('Template deleted.'));
    }

    private function persist(Request $request, ?FlowTemplate $template)
    {
        $data = $request->validate([
            'name'           => ['required', 'string', 'max:160'],
            'description'    => ['nullable', 'string', 'max:1000'],
            'flow_type'      => ['required', 'in:chat,call,instagram'],
            'category'       => ['nullable', 'string', 'max:64'],
            'sort_order'     => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active'      => ['nullable', 'boolean'],
            'flow_file'      => ['nullable', 'file', 'max:4096', 'mimetypes:application/json,text/plain,text/json'],
            'flow_json'      => ['nullable', 'string'],
            'source_flow_id' => ['nullable', 'integer'],
        ]);

        // flow_data: required on create (via file / paste / clone), optional on
        // edit (keep the existing graph when the admin only tweaks metadata).
        $flowData = $this->resolveFlowData($request);
        if ($flowData === null && !$template) {
            return back()->withInput()->withErrors(['flow_json' => 'Provide the flow — upload an exported .json, paste the JSON, or enter an existing flow id to clone.']);
        }
        if ($flowData !== null && !(isset($flowData['flowNodes']) && is_array($flowData['flowNodes']))) {
            return back()->withInput()->withErrors(['flow_json' => 'That doesn\'t look like a valid flow (missing flowNodes).']);
        }

        $payload = [
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'flow_type'   => $data['flow_type'],
            'category'    => $data['category'] ?? null,
            'sort_order'  => (int) ($data['sort_order'] ?? 0),
            'is_active'   => (bool) ($data['is_active'] ?? false),
        ];
        if ($flowData !== null) {
            // Store only the runtime-relevant keys.
            $payload['flow_data'] = [
                'flowNodes' => array_values($flowData['flowNodes']),
                'flowEdges' => array_values($flowData['flowEdges'] ?? []),
                'vars'      => is_array($flowData['vars'] ?? null) ? $flowData['vars'] : [],
            ];
        }

        if ($template) {
            $template->update($payload);
        } else {
            $payload['created_by'] = $request->user()->id;
            $template = FlowTemplate::create($payload);
        }

        return redirect()->route('admin.flow-templates.index')->with('success', __('Template saved.'));
    }

    /** Resolve the graph from: uploaded file → pasted JSON → clone-by-flow-id. */
    private function resolveFlowData(Request $request): ?array
    {
        if ($request->hasFile('flow_file')) {
            $raw = @file_get_contents($request->file('flow_file')->getRealPath());
            return $this->extractGraph(json_decode((string) $raw, true));
        }
        if (trim((string) $request->input('flow_json')) !== '') {
            return $this->extractGraph(json_decode((string) $request->input('flow_json'), true));
        }
        if ($request->filled('source_flow_id')) {
            $flow = Flow::find((int) $request->input('source_flow_id'));
            if ($flow) {
                $d = $flow->decoded_flow_data;
                return is_array($d) ? $d : null;
            }
        }
        return null;
    }

    /** Unwrap the {_wadesk_flow_export…flow_data} wrapper OR accept a bare graph. */
    private function extractGraph($j): ?array
    {
        if (!is_array($j)) return null;
        if (isset($j['flow_data']) && is_array($j['flow_data'])) return $j['flow_data'];
        if (isset($j['flowNodes'])) return $j;
        return null;
    }
}
