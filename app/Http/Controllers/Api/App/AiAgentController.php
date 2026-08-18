<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Http\Controllers\TeamInboxController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile-app AI Agents — the SAME AI agents the web Team Inbox manages.
 *
 * We delegate to TeamInboxController so list / create / update / delete keep
 * byte-identical logic, validation, plan gating (access_ai_agents feature +
 * ai_agents_limit) and response shapes as the web — no drift. Everything is
 * workspace-scoped via current_workspace_id, which the app.workspace middleware
 * sets from the X-Workspace-Id header, so the app manages the AI agents of
 * whichever workspace it selected.
 *
 * Shapes: list → array of AiAgent::toCard(); create/update → { ok, agent }.
 */
class AiAgentController extends Controller
{
    /** GET /ai-agents — list this workspace's AI agents. */
    public function index(Request $request): JsonResponse
    {
        return app(TeamInboxController::class)->aiAgentsIndex($request);
    }

    /** POST /ai-agents — create an AI agent (name, provider, model, prompt, …). */
    public function store(Request $request): JsonResponse
    {
        return app(TeamInboxController::class)->aiAgentsStore($request);
    }

    /** PATCH /ai-agents/{id} — update an AI agent. */
    public function update(Request $request, int $id): JsonResponse
    {
        return app(TeamInboxController::class)->aiAgentsUpdate($request, $id);
    }

    /** DELETE /ai-agents/{id} — delete an AI agent. */
    public function destroy(Request $request, int $id): JsonResponse
    {
        return app(TeamInboxController::class)->aiAgentsDestroy($request, $id);
    }
}
