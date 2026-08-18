<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeviceStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $workspaceId,
        public string $phone,
        public string $status,
        public string $rawStatus,
    ) {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("workspace.{$this->workspaceId}.devices")];
    }

    public function broadcastAs(): string
    {
        return 'device.status';
    }

    public function broadcastWith(): array
    {
        return [
            'phone'       => $this->phone,
            'status'      => $this->status,
            'raw_status'  => $this->rawStatus,
            'at'          => now()->toIso8601String(),
        ];
    }
}
