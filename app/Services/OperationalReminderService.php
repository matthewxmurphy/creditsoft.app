<?php

namespace App\Services;

use App\Models\Task;
use Illuminate\Support\Collection;

class OperationalReminderService
{
    public function __construct(
        protected TailscaleCredentialService $tailscaleCredentials,
    ) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function activeTaskItems(): Collection
    {
        return collect([
            $this->tailscaleCredentials->activeReminder(),
        ])->filter()->values();
    }

    public function activeCount(): int
    {
        return $this->activeTaskItems()->count();
    }

    /**
     * @param  iterable<int, Task>  $tasks
     * @return array<int, array<string, mixed>>
     */
    public function prependToTaskFeed(iterable $tasks, ?int $limit = null): array
    {
        $items = $this->activeTaskItems()
            ->merge(collect($tasks)->map(fn (Task $task) => $this->serializeTask($task)));

        if ($limit !== null) {
            $items = $items->take($limit);
        }

        return $items->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeTask(Task $task): array
    {
        return [
            'id' => $task->getKey(),
            'title' => $task->title,
            'details' => $task->details,
            'status' => $task->status,
            'priority' => $task->priority,
            'due_at' => $task->due_at?->toIso8601String(),
            'client' => $task->client ? [
                'id' => $task->client->getKey(),
                'display_name' => $task->client->display_name,
                'first_name' => $task->client->first_name,
                'last_name' => $task->client->last_name,
            ] : null,
            'system_item' => false,
            'source' => $task->source,
            'action_href' => null,
            'action_label' => null,
        ];
    }
}
