<?php

namespace Illimi\Gradebook\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GradebookEntityChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $entity,
        public readonly string $action,
        public readonly array $payload
    ) {
    }

    public function broadcastOn(): array
    {
        $organizationId = (string) ($this->payload['organization_id'] ?? auth()->user()?->organization_id ?? 'global');

        return [new Channel("gradebook.organization.{$organizationId}")];
    }

    public function broadcastAs(): string
    {
        return 'gradebook.entity.changed';
    }

    public function broadcastWith(): array
    {
        $actor = auth()->user();

        return array_merge($this->payload, [
            'entity' => $this->entity,
            'action' => $this->action,
            'actor_user_id' => $actor?->id,
            'actor_name' => $actor?->name,
            'at' => now()->toIso8601String(),
        ]);
    }
}
