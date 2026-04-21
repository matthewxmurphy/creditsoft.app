<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Task;
use App\Models\ViolationCandidate;
use App\Services\AuditTrail;
use App\Services\OperationalReminderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function inbox(OperationalReminderService $operationalReminders): Response
    {
        return Inertia::render('inbox/Index', [
            'tasks' => $operationalReminders->prependToTaskFeed(
                Task::query()->with('client', 'assignedUser')->whereIn('status', ['open', 'in_progress'])->orderBy('due_at')->get(),
            ),
            'violationsNeedingReview' => ViolationCandidate::query()->with('client')->where('status', 'open')->where('severity', 'high')->latest()->take(8)->get(),
        ]);
    }

    public function index(OperationalReminderService $operationalReminders): Response
    {
        return Inertia::render('tasks/Index', [
            'tasks' => $operationalReminders->prependToTaskFeed(
                Task::query()->with('client', 'assignedUser')->latest('due_at')->get(),
            ),
            'clients' => Client::query()->orderBy('last_name')->get(['id', 'first_name', 'last_name']),
        ]);
    }

    public function store(Request $request, AuditTrail $auditTrail): RedirectResponse
    {
        $validated = $request->validate([
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'title' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string'],
            'priority' => ['required', 'in:low,normal,high'],
            'due_at' => ['nullable', 'date'],
        ]);

        $task = Task::create([
            ...$validated,
            'assigned_to' => $request->user()?->getKey(),
            'status' => 'open',
            'source' => 'manual',
        ]);

        $auditTrail->record(
            $request->user(),
            'task.created',
            "Created task {$task->title}.",
            $task,
        );

        return redirect()->route('tasks.index');
    }

    public function update(Request $request, Task $task, AuditTrail $auditTrail): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:open,in_progress,done'],
        ]);

        $task->update($validated);

        $auditTrail->record(
            $request->user(),
            'task.updated',
            "Updated {$task->title} to {$task->status}.",
            $task,
        );

        return back();
    }
}
