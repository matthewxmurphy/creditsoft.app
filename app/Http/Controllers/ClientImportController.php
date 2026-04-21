<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Services\AuditTrail;
use App\Services\ClientAssignmentService;
use App\Services\DisputeFoxClientImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use RuntimeException;

class ClientImportController extends Controller
{
    public function index(ClientAssignmentService $assignments): Response
    {
        return Inertia::render('clients/Import', [
            'staff' => $assignments->staffOptions(),
            'assignmentModes' => $assignments->importModes(),
            'unassignedCount' => Client::query()->whereNull('assigned_to')->count(),
        ]);
    }

    public function importDisputeFox(
        Request $request,
        DisputeFoxClientImporter $importer,
        AuditTrail $auditTrail,
        ClientAssignmentService $assignments,
    ): RedirectResponse {
        $validated = $request->validate([
            'import_file' => ['required', 'file', 'max:10240', 'extensions:xlsx'],
            'assignment_mode' => ['required', 'string', 'in:source_match,single_user,split_evenly'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'assignment_user_ids' => ['nullable', 'array'],
            'assignment_user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        try {
            $assignment = $this->validatedAssignmentConfig($validated, $assignments);
        } catch (InvalidArgumentException $exception) {
            $field = $validated['assignment_mode'] === ClientAssignmentService::MODE_SPLIT_EVENLY
                ? 'assignment_user_ids'
                : 'assigned_to';

            throw ValidationException::withMessages([
                $field => $exception->getMessage(),
            ]);
        }

        try {
            $summary = $importer->import($validated['import_file'], $assignment);
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'import_file' => $exception->getMessage(),
            ]);
        }

        $auditTrail->record(
            $request->user(),
            'clients.imported.disputefox',
            "Imported {$summary['rows']} DisputeFox client row(s).",
            null,
            [
                'rows' => $summary['rows'],
                'created' => $summary['created'],
                'updated' => $summary['updated'],
                'skipped' => $summary['skipped'],
                'client_ids' => $summary['clients'],
                'headers' => $summary['headers'],
                'assignment_mode' => $assignment['mode'],
                'assigned_to' => $assignment['assigned_to'],
                'assignment_user_ids' => $assignment['assignment_user_ids'],
                'source_file' => $validated['import_file']->getClientOriginalName(),
            ],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => sprintf(
                'Imported %d DisputeFox row(s): %d new, %d updated.',
                $summary['rows'],
                $summary['created'],
                $summary['updated'],
            ),
        ]);

        return redirect()->route('clients.import.index');
    }

    public function assignUnassigned(
        Request $request,
        ClientAssignmentService $assignments,
        AuditTrail $auditTrail,
    ): RedirectResponse {
        $validated = $request->validate([
            'assignment_mode' => ['required', 'string', 'in:source_match,single_user,split_evenly'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'assignment_user_ids' => ['nullable', 'array'],
            'assignment_user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        try {
            $assignment = $this->validatedAssignmentConfig($validated, $assignments);
        } catch (InvalidArgumentException $exception) {
            $field = $validated['assignment_mode'] === ClientAssignmentService::MODE_SPLIT_EVENLY
                ? 'assignment_user_ids'
                : 'assigned_to';

            throw ValidationException::withMessages([
                $field => $exception->getMessage(),
            ]);
        }

        $clients = Client::query()
            ->whereNull('assigned_to')
            ->orderBy('id')
            ->get();

        if ($clients->isEmpty()) {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => 'There are no unassigned clients to fix right now.',
            ]);

            return redirect()->route('clients.import.index');
        }

        foreach ($clients->values() as $index => $client) {
            $client->forceFill([
                'assigned_to' => $assignments->resolveForBatchRow(
                    $assignment['mode'],
                    $assignment['assigned_to'],
                    $assignment['assignment_user_ids'],
                    $index,
                    [
                        (string) data_get($client->metadata, 'imports.disputefox.agent', ''),
                        (string) data_get($client->metadata, 'imports.disputefox.sales_rep', ''),
                    ],
                ),
            ])->save();
        }

        $auditTrail->record(
            $request->user(),
            'clients.assigned.unassigned',
            "Assigned {$clients->count()} previously unassigned client dossiers.",
            null,
            [
                'count' => $clients->count(),
                'client_ids' => $clients->pluck('id')->values()->all(),
                'assignment_mode' => $assignment['mode'],
                'assigned_to' => $assignment['assigned_to'],
                'assignment_user_ids' => $assignment['assignment_user_ids'],
            ],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => sprintf(
                'Assigned %d previously unassigned client %s.',
                $clients->count(),
                $clients->count() === 1 ? 'dossier' : 'dossiers',
            ),
        ]);

        return redirect()->route('clients.import.index');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{mode:string,assigned_to:?int,assignment_user_ids:list<int>}
     */
    protected function validatedAssignmentConfig(array $validated, ClientAssignmentService $assignments): array
    {
        $config = [
            'mode' => (string) $validated['assignment_mode'],
            'assigned_to' => isset($validated['assigned_to']) ? (int) $validated['assigned_to'] : null,
            'assignment_user_ids' => $assignments->normalizedTeamUserIds($validated['assignment_user_ids'] ?? []),
        ];

        $assignments->resolveForBatchRow(
            $config['mode'],
            $config['assigned_to'],
            $config['assignment_user_ids'],
            0,
            ['validation preview'],
        );

        return $config;
    }
}
