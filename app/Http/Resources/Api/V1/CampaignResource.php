<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public shape of a campaign in the customer API. Scramble reads this to
 * document the response. Reads from the existing App\CampaignController::
 * transformCampaign() output array (status counts, delivery/read/click/
 * response metrics, schedule + timestamps).
 */
class CampaignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $c = $this->resource;

        $stats   = $c['stats']   ?? [];
        $metrics = $c['metrics'] ?? [];

        return [
            'id'            => $c['id']            ?? null,
            'name'          => $c['campaign_name'] ?? null,
            'type'          => $c['campaign_type'] ?? null,
            'status'        => $c['status']        ?? null,
            'device_id'     => $c['device_id']     ?? null,
            'template_id'   => $c['template_id']   ?? null,
            'flow_id'       => $c['flow_id']       ?? null,
            'ab_testing'    => (bool) ($c['ab_testing'] ?? false),
            'ab_split'      => $c['ab_split']      ?? null,
            'counts'        => [
                'total_recipients' => (int) ($stats['total_recipients'] ?? 0),
                'sent'             => (int) ($stats['sent']      ?? 0),
                'delivered'        => (int) ($stats['delivered'] ?? 0),
                'read'             => (int) ($stats['read']      ?? 0),
                'failed'           => (int) ($stats['failed']    ?? 0),
                'clicked'          => (int) ($stats['clicked']   ?? 0),
                'responded'        => (int) ($stats['responded'] ?? 0),
            ],
            'metrics'       => [
                'delivery_rate' => $metrics['delivery_rate'] ?? 0,
                'read_rate'     => $metrics['read_rate']     ?? 0,
                'click_rate'    => $metrics['click_rate']    ?? 0,
                'response_rate' => $metrics['response_rate'] ?? 0,
            ],
            'schedule_type' => $c['schedule_type'] ?? null,
            'scheduled_for' => $c['scheduled_for'] ?? null,
            'timezone'      => $c['timezone']      ?? null,
            'created_at'    => $c['created_at']    ?? null,
            'updated_at'    => $c['updated_at']    ?? null,
            'completed_at'  => $c['completed_at']  ?? null,
        ];
    }
}
