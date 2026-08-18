<?php

namespace App\Http\Controllers;

use App\Models\AiChatAssistant;
use App\Models\AiTrainingSource;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * AI Training surface — chat assistants (persona + LLM settings) and
 * their knowledge sources (URLs, files, raw text, Q&A pairs). The
 * resulting context is concat'd into the system prompt at chat time
 * by AiChatService.
 */
class AiTrainingController extends Controller
{
    public function index(): View
    {
        $wsId = (int) (Auth::user()?->current_workspace_id ?? 0);

        $assistants = AiChatAssistant::query()
            ->where('workspace_id', $wsId)
            ->withCount('trainingSources')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        // Self-heal: purge knowledge sources orphaned by an agent that was
        // deleted BEFORE the cascade-on-delete existed. They otherwise keep
        // inflating the "indexed" count and — because contextFor() also reads
        // workspace-wide rows — can keep the bot answering from knowledge the
        // user believes they removed. A source is orphaned when its
        // assistant_id points to an agent that no longer exists in this
        // workspace. NULL / workspace-wide sources are intentional; left alone.
        try {
            $liveIds = AiChatAssistant::where('workspace_id', $wsId)->pluck('id');
            $orphans = AiTrainingSource::where('workspace_id', $wsId)
                ->whereNotNull('assistant_id')
                ->whereNotIn('assistant_id', $liveIds)
                ->get();
            foreach ($orphans as $o) {
                if ($o->file_path && Storage::disk('local')->exists($o->file_path)) {
                    Storage::disk('local')->delete($o->file_path);
                }
                $o->delete();
            }
        } catch (\Throwable $e) {
            \Log::warning('[AI-TRAINING] orphan source sweep failed: ' . $e->getMessage());
        }

        $stats = [
            'all'        => AiChatAssistant::where('workspace_id', $wsId)->count(),
            'active'     => AiChatAssistant::where('workspace_id', $wsId)->where('status', 'active')->count(),
            'sources'    => AiTrainingSource::where('workspace_id', $wsId)->count(),
            'ready'      => AiTrainingSource::where('workspace_id', $wsId)->where('status', 'ready')->count(),
        ];

        $workspace = \App\Models\Workspace::find($wsId);

        // Meta Business Agent coexistence modes — defined here (not inline in the
        // blade) so the variable is always bound even when a host serves a
        // stale-compiled view from OPcache (shared hosting can't flush it).
        $modes = [
            'wadesk_only'             => [__('Our AI answers'), __(':brand AI + keyword auto-replies handle chats (default).', ['brand' => brand_name()])],
            'meta_agent_only'         => [__('Meta agent answers'), __('Meta’s Business Agent replies. We only log the thread — our AI stays silent.')],
            'meta_agent_then_handoff' => [__('Meta agent, then handoff'), __('Meta fronts tier-1; when it escalates, the chat lands in your Team Inbox for a human. Our auto-AI stays silent.')],
        ];

        // Current responder mode + Meta-agent toggle — passed from here (not
        // inline in the blade) for the same stale-compiled-view resilience.
        $_mode   = optional($workspace)->ai_responder_mode ?? 'wadesk_only';
        $_metaOn = (bool) (optional($workspace)->meta_agent_enabled ?? false);

        return view('user.ai-training.index', compact('assistants', 'stats', 'workspace', 'modes', '_mode', '_metaOn'));
    }

    /**
     * Meta Business Agent coexistence — choose who answers this workspace's
     * WhatsApp so the customer never gets two replies. When Meta's agent is on
     * (meta_agent_only / meta_agent_then_handoff), our AI + keyword auto-reply
     * stand down (enforced in AiAgentService + KeywordReplyDispatcher).
     */
    public function saveResponderMode(Request $request): \Illuminate\Http\RedirectResponse
    {
        $wsId = (int) (Auth::user()?->current_workspace_id ?? 0);
        $data = $request->validate([
            'ai_responder_mode'  => 'required|in:' . implode(',', \App\Models\Workspace::RESPONDER_MODES),
            'meta_agent_enabled' => 'nullable|boolean',
        ]);
        $ws = \App\Models\Workspace::find($wsId);
        if ($ws) {
            $ws->forceFill([
                'meta_agent_enabled' => $request->boolean('meta_agent_enabled'),
                'ai_responder_mode'  => $data['ai_responder_mode'],
            ])->save();
        }
        return back()->with('status', __('AI responder mode saved.'));
    }

    public function create(): View
    {
        return view('user.ai-training.builder', ['assistant' => null, 'mode' => 'create']);
    }

    public function edit(int $id): View
    {
        $wsId = (int) (Auth::user()?->current_workspace_id ?? 0);
        $assistant = AiChatAssistant::where('workspace_id', $wsId)
            ->withCount('trainingSources')
            ->findOrFail($id);
        return view('user.ai-training.builder', ['assistant' => $assistant, 'mode' => 'edit']);
    }

    /**
     * Duplicate an assistant + its training sources. Useful for
     * branching personas (e.g. cloning "Pricing concierge" into
     * "Pricing concierge — Spanish").
     */
    public function duplicate(int $id): \Illuminate\Http\RedirectResponse
    {
        $wsId = (int) (Auth::user()?->current_workspace_id ?? 0);
        $src  = AiChatAssistant::where('workspace_id', $wsId)->findOrFail($id);

        $copy = $src->replicate();
        $copy->name = $src->name . ' (copy)';
        $copy->slug = $src->slug . '-copy-' . \Illuminate\Support\Str::random(4);
        $copy->status = 'paused';
        $copy->save();

        foreach ($src->trainingSources()->get() as $tr) {
            $clone = $tr->replicate();
            $clone->assistant_id = $copy->id;
            $clone->save();
        }
        return redirect()->route('user.ai-training.edit', $copy->id);
    }

    /* ----------------------------- Assistants ----------------------------- */

    public function apiSaveAssistant(Request $request): JsonResponse
    {
        $user = Auth::user();
        $wsId = (int) ($user?->current_workspace_id ?? 0);
        if (!$wsId) return response()->json(['ok' => false, 'error' => 'no_workspace'], 400);

        $data = $request->validate([
            'id'               => 'nullable|integer',
            'name'             => 'required|string|max:120',
            'greeting'         => 'nullable|string|max:1000',
            'system_prompt'    => 'nullable|string|max:8000',
            'tone'             => 'nullable|string|max:32',
            'language'         => 'nullable|string|max:16',
            'ai_provider'      => 'nullable|in:openai,anthropic,gemini',
            'ai_model'         => 'nullable|string|max:80',
            'reply_max_tokens' => 'nullable|integer|min:50|max:4000',
            'temperature'      => 'nullable|numeric|min:0|max:2',
            'fallback_message' => 'nullable|string|max:1000',
            'handoff_enabled'  => 'nullable|boolean',
            'handoff_keyword'  => 'nullable|string|max:60',
            'handoff_message'  => 'nullable|string|max:1000',
            'status'           => 'nullable|in:active,paused',
        ]);

        $assistant = !empty($data['id'])
            ? AiChatAssistant::where('workspace_id', $wsId)->find($data['id'])
            : null;
        if (!$assistant) {
            $assistant = new AiChatAssistant();
            $assistant->workspace_id = $wsId;
            $assistant->user_id      = $user->id;
        }

        // withTrashed() is load-bearing: the table's unique(workspace_id, slug)
        // index still counts soft-deleted rows, but this model's default scope
        // hides them. Without it, re-creating an agent whose name matches one
        // the operator deleted earlier found "no clash", then blew up on the
        // INSERT with a duplicate-key SQLSTATE 23000 — a 500 the wizard could
        // only report as "Save failed", with no way to ever get past it.
        $base = Str::slug($data['name']) ?: ('assistant-' . Str::random(6));
        $slug = $assistant->slug ?: $base;
        $i = 1;
        while (AiChatAssistant::withTrashed()
            ->where('workspace_id', $wsId)
            ->where('slug', $slug)
            ->where('id', '!=', $assistant->id ?? 0)
            ->exists()) {
            $slug = $base . '-' . (++$i);
        }
        $assistant->slug = $slug;

        $assistant->fill($data);
        $assistant->save();
        return response()->json(['ok' => true, 'id' => $assistant->id, 'slug' => $assistant->slug]);
    }

    public function apiDeleteAssistant(int $id): JsonResponse
    {
        $wsId = (int) (Auth::user()?->current_workspace_id ?? 0);
        $assistant = AiChatAssistant::where('workspace_id', $wsId)->findOrFail($id);

        // Delete the agent's OWN knowledge with it. Without this the agent's
        // training sources were orphaned: they kept inflating the workspace
        // "indexed" count and (since retrieval also pulls workspace-wide rows)
        // the bot could keep answering from knowledge the user thought they'd
        // removed. Only this agent's sources are touched — workspace-wide
        // (assistant_id NULL) knowledge is shared and left alone.
        $sources = AiTrainingSource::where('workspace_id', $wsId)
            ->where('assistant_id', $assistant->id)->get();
        foreach ($sources as $s) {
            if ($s->file_path && Storage::disk('local')->exists($s->file_path)) {
                Storage::disk('local')->delete($s->file_path);
            }
            $s->delete();
        }

        $assistant->delete();
        return response()->json(['ok' => true]);
    }

    /* ----------------------------- Sources ----------------------------- */

    /**
     * POST /ai-training/api/source — accepts `kind` ∈ {url, text, qa}
     * and optional `assistant_id` (null = workspace-wide). For URLs
     * we synchronously fetch + strip tags so the operator immediately
     * sees the row in `ready` state. File uploads use apiUploadFile().
     */
    public function apiAddSource(Request $request): JsonResponse
    {
        $user = Auth::user();
        $wsId = (int) ($user?->current_workspace_id ?? 0);
        if (!$wsId) return response()->json(['ok' => false, 'error' => 'no_workspace'], 400);

        $data = $request->validate([
            'assistant_id' => 'nullable|integer',
            'kind'         => 'required|in:url,text,qa',
            'label'        => 'required|string|max:200',
            'url'          => 'nullable|string|max:1024',
            'content'      => 'nullable|string|max:200000',
            'question'     => 'nullable|string|max:5000',
            'answer'       => 'nullable|string|max:20000',
        ]);

        if (!empty($data['assistant_id'])) {
            $ok = AiChatAssistant::where('workspace_id', $wsId)
                ->where('id', $data['assistant_id'])->exists();
            if (!$ok) return response()->json(['ok' => false, 'error' => 'assistant_not_in_workspace'], 422);
        } else {
            $data['assistant_id'] = null;
        }

        $src = new AiTrainingSource();
        $src->workspace_id = $wsId;
        $src->user_id      = $user->id;
        $src->fill($data);

        if ($data['kind'] === 'url') {
            if (empty($data['url'])) {
                return response()->json(['ok' => false, 'error' => 'url_required'], 422);
            }
            [$ok, $text, $err] = $this->fetchUrlAsText($data['url']);
            if ($ok) {
                $src->content = $text;
                $src->status  = 'ready';
                $src->tokens_estimate = (int) ceil(mb_strlen($text) / 4);
            } else {
                $src->content = null;
                $src->status  = 'failed';
                $src->error   = $err;
            }
        } elseif ($data['kind'] === 'text') {
            if (empty(trim((string) ($data['content'] ?? '')))) {
                return response()->json(['ok' => false, 'error' => 'content_required'], 422);
            }
            $src->status = 'ready';
            $src->tokens_estimate = (int) ceil(mb_strlen($data['content']) / 4);
        } elseif ($data['kind'] === 'qa') {
            if (empty(trim((string) ($data['question'] ?? ''))) || empty(trim((string) ($data['answer'] ?? '')))) {
                return response()->json(['ok' => false, 'error' => 'qa_requires_both'], 422);
            }
            $src->status = 'ready';
            $src->tokens_estimate = (int) ceil((mb_strlen($data['question']) + mb_strlen($data['answer'])) / 4);
        }

        $src->save();
        return response()->json(['ok' => true, 'id' => $src->id, 'status' => $src->status, 'error' => $src->error]);
    }

    /**
     * Upload a knowledge file and extract its text. Accepts TXT / MD /
     * CSV / HTML (read directly), PDF (smalot/pdfparser) and DOCX
     * (dependency-free ZipArchive). Up to 10 MB; extracted text is
     * capped at 200k chars. Unsupported types — incl. legacy binary
     * .doc — are rejected with a clear message.
     */
    public function apiUploadFile(Request $request): JsonResponse
    {
        $user = Auth::user();
        $wsId = (int) ($user?->current_workspace_id ?? 0);
        if (!$wsId) return response()->json(['ok' => false, 'error' => 'no_workspace'], 400);

        $request->validate([
            'file'         => 'required|file|max:10240',  // 10 MB — PDFs/DOCX run larger than plain text
            'label'        => 'required|string|max:200',
            'assistant_id' => 'nullable|integer',
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        $allowed = ['txt', 'md', 'markdown', 'text', 'csv', 'log', 'html', 'htm', 'pdf', 'docx'];
        if (!in_array($ext, $allowed, true)) {
            return response()->json([
                'ok' => false,
                'error' => $ext === 'doc'
                    ? 'Legacy .doc files aren\'t supported — re-save as .docx (or export to .pdf) and upload again.'
                    : 'Accepted file types: TXT, Markdown, CSV, HTML, PDF, DOCX. For anything else, paste the text into a Text source.',
            ], 422);
        }

        $assistantId = $request->input('assistant_id');
        if (!empty($assistantId)) {
            $ok = AiChatAssistant::where('workspace_id', $wsId)
                ->where('id', $assistantId)->exists();
            if (!$ok) return response()->json(['ok' => false, 'error' => 'assistant_not_in_workspace'], 422);
        } else {
            $assistantId = null;
        }

        // Extract plain text per file type. PDFs use smalot/pdfparser;
        // DOCX is unzipped + tag-stripped with zero dependencies; HTML
        // is tag-stripped; the rest are read verbatim.
        [$content, $extractErr] = $this->extractFileText($file->getRealPath(), $ext);
        if ($extractErr !== null) {
            return response()->json(['ok' => false, 'error' => $extractErr], 422);
        }
        $content = trim((string) $content);
        if ($content === '') {
            return response()->json([
                'ok' => false,
                'error' => 'No readable text found. If this is a scanned/image-only PDF it has no extractable text — paste the text into a Text source instead.',
            ], 422);
        }
        // Cap at ~200k chars to keep training tables sane.
        if (mb_strlen($content) > 200000) {
            $content = mb_substr($content, 0, 200000);
        }
        $path = $file->storeAs(
            "training/{$wsId}",
            time() . '-' . Str::random(8) . '.' . $ext,
            'local'
        );

        $src = AiTrainingSource::create([
            'workspace_id'    => $wsId,
            'assistant_id'    => $assistantId,
            'user_id'         => $user->id,
            'kind'            => 'file',
            'label'           => $request->input('label'),
            'file_path'       => $path,
            'content'         => $content,
            'status'          => 'ready',
            'tokens_estimate' => (int) ceil(mb_strlen($content) / 4),
        ]);
        return response()->json(['ok' => true, 'id' => $src->id]);
    }

    public function apiDeleteSource(int $id): JsonResponse
    {
        $wsId = (int) (Auth::user()?->current_workspace_id ?? 0);
        $src = AiTrainingSource::where('workspace_id', $wsId)->findOrFail($id);
        if ($src->file_path && Storage::disk('local')->exists($src->file_path)) {
            Storage::disk('local')->delete($src->file_path);
        }
        $src->delete();
        return response()->json(['ok' => true]);
    }

    /**
     * GET /ai-training/api/sources?assistant_id=NN — returns the
     * sources scoped to one assistant plus any workspace-wide ones.
     */
    public function apiListSources(Request $request): JsonResponse
    {
        $wsId = (int) (Auth::user()?->current_workspace_id ?? 0);
        $assistantId = $request->integer('assistant_id') ?: null;

        $q = AiTrainingSource::query()->where('workspace_id', $wsId);
        if ($assistantId) {
            $q->where(function ($qq) use ($assistantId) {
                $qq->where('assistant_id', $assistantId)->orWhereNull('assistant_id');
            });
        }
        $rows = $q->orderByDesc('id')->get([
            'id', 'assistant_id', 'kind', 'label', 'url', 'status', 'tokens_estimate', 'error', 'created_at',
        ]);
        return response()->json(['ok' => true, 'sources' => $rows]);
    }

    /**
     * Convenience for the chat-widget builder picker.
     */
    public function apiListAssistants(): JsonResponse
    {
        $wsId = (int) (Auth::user()?->current_workspace_id ?? 0);
        $rows = AiChatAssistant::where('workspace_id', $wsId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'ai_provider', 'ai_model']);
        return response()->json(['ok' => true, 'assistants' => $rows]);
    }

    /* --------------------------- file extraction -------------------------- */

    /**
     * Reduce an uploaded knowledge file to plain text. Returns
     * [text|'', error|null]. Never throws — extraction failures come
     * back as a friendly error string so the upload endpoint can 422.
     */
    private function extractFileText(string $path, string $ext): array
    {
        try {
            switch ($ext) {
                case 'pdf':
                    if (!class_exists(\Smalot\PdfParser\Parser::class)) {
                        return ['', 'PDF support is not installed on this server. Paste the text into a Text source instead.'];
                    }
                    $parser = new \Smalot\PdfParser\Parser();
                    $pdf    = $parser->parseFile($path);
                    return [(string) $pdf->getText(), null];

                case 'docx':
                    return [$this->docxToText($path), null];

                case 'html':
                case 'htm':
                    $raw = (string) file_get_contents($path);
                    // Drop script/style blocks before stripping tags so we
                    // don't ingest JS/CSS as "knowledge".
                    $raw = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $raw) ?? $raw;
                    return [trim(html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8')), null];

                default: // txt, md, markdown, text, csv, log
                    return [(string) file_get_contents($path), null];
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[AI-TRAINING] file extract failed (' . $ext . '): ' . $e->getMessage());
            return ['', 'Could not read that file — it may be corrupt or password-protected. Try another file or paste the text.'];
        }
    }

    /**
     * Pull readable text out of a .docx without any library. A .docx is
     * a ZIP whose body lives in word/document.xml; paragraph (<w:p>) and
     * tab (<w:tab/>) tags become newlines/spaces, then all tags are
     * stripped and XML entities decoded.
     */
    private function docxToText(string $path): string
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('zip extension unavailable');
        }
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('not a valid docx (zip open failed)');
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if ($xml === false || $xml === '') return '';

        // Preserve structure: paragraph breaks + tabs + line breaks.
        $xml = preg_replace('#</w:p>#', "\n", $xml) ?? $xml;
        $xml = preg_replace('#<w:tab[^>]*/?>#', "\t", $xml) ?? $xml;
        $xml = preg_replace('#<w:br[^>]*/?>#', "\n", $xml) ?? $xml;
        $text = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
        // Collapse the runs of blank lines docx XML tends to leave behind.
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        return trim($text);
    }

    /* ----------------------------- URL fetch ----------------------------- */

    /**
     * Synchronously fetch a URL and reduce it to plain text. v1 uses
     * Laravel's Http client + a strip-tags pass; good enough for blog
     * posts, FAQ pages, and short marketing copy. Returns
     * [ok, text|null, error|null].
     */
    private function fetchUrlAsText(string $url): array
    {
        // SSRF guard. Without this an operator with workspace access
        // could point the training URL at http://localhost:6379 /
        // 169.254.169.254 (AWS IMDS) / 192.168.x.x and read internal
        // services through our server's network. We only allow public
        // HTTP/HTTPS URLs whose resolved IPs are NOT private/loopback/
        // link-local/CGNAT.
        try {
            // Follow redirects MANUALLY so the SSRF guard runs on EVERY hop.
            // Laravel/Guzzle auto-follows 3xx by default and never re-checks
            // the redirect target, so a public host returning 302 ->
            // http://169.254.169.254/... would bypass a one-shot guard. We
            // disable auto-redirects and re-validate each Location ourselves.
            $current      = $url;
            $maxRedirects = 5;
            $res          = null;
            for ($hop = 0; $hop <= $maxRedirects; $hop++) {
                $ssrfErr = $this->guardSsrf($current);
                if ($ssrfErr) return [false, null, $ssrfErr];

                $res = Http::timeout(20)
                    ->withOptions(['allow_redirects' => false])
                    ->withHeaders(['User-Agent' => 'WaDeskAITrainingBot/1.0'])
                    ->get($current);

                if ($res->redirect()) {
                    $loc = (string) $res->header('Location');
                    if ($loc === '') break; // 3xx without Location — treat as final
                    if ($hop === $maxRedirects) {
                        return [false, null, 'too many redirects'];
                    }
                    $current = $this->resolveRedirectUrl($current, $loc);
                    continue;
                }
                break;
            }
            if (!$res || !$res->ok()) {
                return [false, null, 'fetch failed: HTTP ' . ($res ? $res->status() : 0)];
            }
            $html = (string) $res->body();
            // Strip script/style blocks first, then all tags.
            $html = preg_replace('#<script\b[^>]*>(.*?)</script>#is', ' ', $html) ?? $html;
            $html = preg_replace('#<style\b[^>]*>(.*?)</style>#is',  ' ', $html) ?? $html;
            $text = html_entity_decode(strip_tags($html));
            // Collapse whitespace.
            $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
            $text = trim($text);
            if ($text === '') return [false, null, 'fetched page contained no text'];
            // Cap so a giant page doesn't blow the budget.
            if (mb_strlen($text) > 80000) $text = mb_substr($text, 0, 80000);
            return [true, $text, null];
        } catch (\Throwable $e) {
            return [false, null, 'fetch exception: ' . $e->getMessage()];
        }
    }

    /**
     * Resolve a redirect Location (which may be absolute or relative)
     * against the URL it was returned from, so the SSRF guard can be
     * re-applied to the concrete next hop. Relative paths inherit the
     * base scheme/host/port.
     */
    private function resolveRedirectUrl(string $base, string $location): string
    {
        $location = trim($location);
        // Absolute URL (has a scheme) — use as-is; the guard vets the scheme.
        if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.\-]*://#', $location)) {
            return $location;
        }
        $b = parse_url($base);
        if (!$b || empty($b['scheme']) || empty($b['host'])) {
            return $location;
        }
        $origin = $b['scheme'] . '://' . $b['host'] . (isset($b['port']) ? ':' . $b['port'] : '');
        if ($location === '') return $origin;
        if ($location[0] === '/') {
            // Protocol-relative //host/path
            if (isset($location[1]) && $location[1] === '/') {
                return $b['scheme'] . ':' . $location;
            }
            return $origin . $location; // absolute path
        }
        // Relative path — resolve against the base directory.
        $path = $b['path'] ?? '/';
        $dir  = substr($path, 0, strrpos($path, '/') + 1) ?: '/';
        return $origin . $dir . $location;
    }

    /**
     * SSRF guard for training-source URL fetch.
     *
     * Returns NULL when the URL is safe to fetch, or a human-readable
     * error string when it should be refused. Refuses:
     *   - Non-http(s) schemes (file://, gopher://, dict://, …)
     *   - Hostnames that resolve to private/loopback/link-local/
     *     reserved IP ranges (RFC1918, 127.0.0.0/8, 169.254.0.0/16,
     *     ::1, fc00::/7, etc.)
     *   - Cloud metadata endpoints (169.254.169.254 covered by the
     *     link-local check; also explicit *.metadata.* domains).
     *
     * Combined with the 20s timeout + 80KB cap this makes the URL
     * source kind safe for operator-supplied input.
     */
    private function guardSsrf(string $url): ?string
    {
        $p = parse_url($url);
        if (!$p || empty($p['scheme']) || empty($p['host'])) {
            return 'invalid URL';
        }
        $scheme = strtolower($p['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return "scheme {$scheme} not allowed (use http or https)";
        }
        $host = strtolower($p['host']);
        // Block explicit metadata-service domains some clouds expose.
        if (str_contains($host, 'metadata.') || str_ends_with($host, '.internal')) {
            return 'metadata host not allowed';
        }
        // Resolve to IPs and refuse if ANY resolved IP is private/
        // reserved. gethostbynamel returns null on resolution failure.
        $ips = @gethostbynamel($host) ?: [];
        // Also resolve literal IPv6 / numeric IPv4 directly.
        if (filter_var($host, FILTER_VALIDATE_IP)) $ips = [$host];
        if (empty($ips)) {
            // Try IPv6 + numeric directly via @dns_get_record AAAA.
            $aaaa = @dns_get_record($host, DNS_AAAA);
            foreach ((array) $aaaa as $rec) {
                if (!empty($rec['ipv6'])) $ips[] = $rec['ipv6'];
            }
        }
        if (empty($ips)) {
            return 'hostname did not resolve to a public IP';
        }
        foreach ($ips as $ip) {
            // FILTER_FLAG_NO_PRIV_RANGE  → block RFC1918 (10/8, 172.16/12, 192.168/16)
            // FILTER_FLAG_NO_RES_RANGE   → block loopback, link-local, multicast, reserved
            $public = filter_var($ip, FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
            if ($public === false) {
                return "host resolves to private/reserved IP ({$ip}) — refusing to fetch";
            }
        }
        return null;
    }
}
