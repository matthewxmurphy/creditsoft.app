<?php

namespace App\Http\Controllers;

use App\Models\ManagedLetterTemplate;
use App\Models\AutomationDiscovery;
use App\Models\MigrationOperatorCapture;
use App\Services\AuditTrail;
use App\Services\CreditsoftApiAccess;
use App\Services\MigrationOperatorBundle;
use App\Services\MigrationOperatorLetterTemplateImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MigrationOperatorController extends Controller
{
    public function index(Request $request, MigrationOperatorBundle $bundle): Response
    {
        $user = $this->ensureOwner($request);
        $activeKeys = app(CreditsoftApiAccess::class)
            ->activeKeysFor($user)
            ->filter(fn ($key) => in_array('migration_operator', (array) $key->abilities, true))
            ->values();

        return Inertia::render('MigrationOperator', [
            'bundle' => $bundle->summary() + [
                'download_url' => route('migration-operator.download'),
            ],
            'api_base_url' => rtrim(url('/api/v1'), '/'),
            'generated_token' => $request->session()->get('generated_migration_operator_token'),
            'allowed_hosts' => array_values(config('creditsoft.migration_operator.allowed_hosts', [])),
            'capture_types' => array_values(config('creditsoft.migration_operator.capture_types', [])),
            'automation_discoveries' => AutomationDiscovery::query()
                ->latest('last_seen_at')
                ->take(30)
                ->get()
                ->map(fn (AutomationDiscovery $discovery) => [
                    'id' => $discovery->getKey(),
                    'source_system' => $discovery->source_system,
                    'source_product' => $discovery->source_product,
                    'page_kind' => $discovery->page_kind,
                    'source_identifier' => $discovery->source_identifier,
                    'name' => $discovery->name,
                    'status' => $discovery->status,
                    'category' => $discovery->category,
                    'workflow_type' => $discovery->workflow_type,
                    'start_condition' => $discovery->start_condition,
                    'condition_count' => $discovery->condition_count,
                    'action_count' => $discovery->action_count,
                    'step_count' => $discovery->step_count,
                    'seen_count' => $discovery->seen_count,
                    'last_seen_at' => optional($discovery->last_seen_at)?->toIso8601String(),
                    'promoted_at' => optional($discovery->promoted_at)?->toIso8601String(),
                    'page_url' => data_get($discovery->payload, 'page_url'),
                    'steps' => collect(data_get($discovery->payload, 'steps', []))
                        ->take(4)
                        ->map(fn ($step) => [
                            'title' => data_get($step, 'title'),
                            'timing' => data_get($step, 'timing'),
                            'actions' => array_values((array) data_get($step, 'actions', [])),
                        ])
                        ->values()
                        ->all(),
                ])
                ->values(),
            'active_keys' => $activeKeys
                ->map(fn ($key) => [
                    'id' => $key->getKey(),
                    'name' => $key->name,
                    'masked_token' => $key->masked_token,
                    'abilities' => array_values((array) $key->abilities),
                    'created_at' => optional($key->created_at)?->toIso8601String(),
                    'last_used_at' => optional($key->last_used_at)?->toIso8601String(),
                ])
                ->all(),
            'captures' => MigrationOperatorCapture::query()
                ->latest()
                ->take(50)
                ->get()
                ->map(fn (MigrationOperatorCapture $capture) => [
                    'id' => $capture->getKey(),
                    'source_system' => $capture->source_system,
                    'capture_type' => $capture->capture_type,
                    'page_title' => $capture->page_title,
                    'page_url' => $capture->page_url,
                    'operator_note' => $capture->operator_note,
                    'status' => $capture->status,
                    'created_at' => optional($capture->created_at)?->toIso8601String(),
                    'source_host' => data_get($capture->metadata, 'source_host'),
                    'excerpt' => \Illuminate\Support\Str::limit((string) $capture->extracted_text, 260),
                    'importable_as_template' => in_array($capture->capture_type, ['letter_library', 'letter_detail'], true),
                    'imported_template_key' => data_get($capture->metadata, 'imported_letter_template_key'),
                ])
                ->values(),
            'templates' => Schema::hasTable('managed_letter_templates')
                ? ManagedLetterTemplate::query()
                    ->latest('updated_at')
                    ->take(40)
                    ->get()
                    ->map(fn (ManagedLetterTemplate $template) => [
                        'id' => $template->getKey(),
                        'key' => $template->key,
                        'label' => $template->label,
                        'letter_type' => $template->letter_type,
                        'source_system' => $template->source_system === 'imported' ? 'Imported' : $template->source_system,
                        'source_page_url' => null,
                        'updated_at' => optional($template->updated_at)?->toIso8601String(),
                        'content_excerpt' => \Illuminate\Support\Str::limit((string) $template->content_template, 220),
                    ])
                    ->values()
                : collect(),
        ]);
    }

    public function issueKey(
        Request $request,
        CreditsoftApiAccess $apiAccess,
        AuditTrail $auditTrail,
    ): RedirectResponse {
        $user = $this->ensureOwner($request);

        $plainToken = $apiAccess->issueUserToken($user, 'CreditSoft OPS', ['migration_operator']);
        $request->session()->flash('generated_migration_operator_token', $plainToken);

        $auditTrail->record(
            $user,
            'migration_operator.key_created',
            'Generated a CreditSoft OPS migration API key.',
            null,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'CreditSoft OPS API key generated. Copy it now; the full value is only shown once.',
        ]);

        return redirect()->route('migration-operator.index');
    }

    public function download(Request $request, MigrationOperatorBundle $bundle): BinaryFileResponse
    {
        $user = $this->ensureOwner($request);

        app(AuditTrail::class)->record(
            $user,
            'migration_operator.bundle_downloaded',
            'Downloaded the CreditSoft OPS bundle.',
            null,
        );

        return response()->download(
            $bundle->build(),
            $bundle->downloadName(),
            ['Content-Type' => 'application/zip'],
        );
    }

    public function importCapture(
        Request $request,
        MigrationOperatorCapture $capture,
        MigrationOperatorLetterTemplateImporter $importer,
        AuditTrail $auditTrail,
    ): RedirectResponse {
        $user = $this->ensureOwner($request);

        $template = $importer->import([
            'operator_notes' => $request->string('operator_notes')->toString(),
            'label' => $request->string('label')->toString(),
            'letter_type' => $request->string('letter_type')->toString(),
        ], $user, $capture);

        $auditTrail->record(
            $user,
            'migration_operator.capture_imported_from_web',
            "Imported staged capture {$capture->getKey()} into template {$template->key}.",
            $template,
            [
                'capture_id' => $capture->getKey(),
                'template_key' => $template->key,
            ],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Imported {$template->label} into the CreditSoft letter library.",
        ]);

        return redirect()->route('migration-operator.index');
    }

    protected function ensureOwner(Request $request): \App\Models\User
    {
        $user = $request->user();
        abort_unless($user?->hasRole('owner_admin'), 403);

        return $user;
    }
}
