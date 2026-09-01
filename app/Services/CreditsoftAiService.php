<?php

namespace App\Services;

use App\Models\BrowserCapture;
use App\Models\Client;
use App\Models\ReportingCycle;
use App\Models\ViolationCandidate;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

use function Laravel\Ai\agent;

class CreditsoftAiService
{
    public function __construct(
        protected CreditsoftAiRegistry $registry,
        protected CreditReportComparisonService $comparisonService,
    ) {}

    /**
     * @return array{title:string,content:string,meta:array<string,mixed>}
     */
    public function generateLetterDraft(
        Client $client,
        ?ReportingCycle $cycle,
        string $letterType,
        string $legalBasis,
        ?array $template = null,
        ?string $operatorFocus = null,
    ): array {
        $context = $this->buildClientContext($client, $cycle);
        $focus = trim((string) $operatorFocus);
        $resolvedFocus = $focus !== ''
            ? $focus
            : 'Use the strongest confirmed inconsistencies, missing information, stale collections, and status conflicts.';
        $templateBlock = $template
            ? trim(<<<TEMPLATE
            Template key: {$template['key']}
            Template version: {$template['version']}
            Template label: {$template['label']}
            Template operator notes: {$template['operator_notes']}
            Template draft focus: {$template['ai_focus']}
            TEMPLATE)
            : 'No specific template was selected. Use the default CreditSoft operator letter style.';
        $result = $this->generateStructured(
            task: 'drafting',
            instructions: 'You draft compliant, concise consumer dispute letters written in the client\'s first-person voice. Avoid fake legal claims, never invent facts, and use only the supplied case context. Do not frame the letter like an internal operator memo, case packet, or processing summary. The letter should read like it is being sent by the client directly to a credit bureau or reporting agency. Return a clear title and a sendable letter body.',
            prompt: trim(<<<PROMPT
            Draft a {$letterType} credit repair letter.

            Write in first person as the client.
            Do not add internal labels like Client:, Cycle:, Status:, Template:, Prepared:, or operator notes.
            For dispute letters, focus on the inaccurate information appearing on the client's credit report, identify the actual disputed accounts and inconsistencies, and ask for deletion or correction if the information cannot be verified as complete and accurate.
            Do not use internal jargon like round, packet, workflow, reporting cycle, or three-bureau cross-reference in the finished letter.
            End with a normal signature block for the client. If you need a name placeholder, use [Client Name].

            Legal basis:
            {$legalBasis}

            Template:
            {$templateBlock}

            Operator focus:
            {$resolvedFocus}

            Case context:
            {$context}
            PROMPT),
            schema: fn (JsonSchema $schema) => [
                'title' => $schema->string()->required(),
                'content' => $schema->string()->required(),
            ],
        );

        $title = trim((string) Arr::get($result, 'structured.title', 'AI draft letter'));
        $content = trim((string) Arr::get($result, 'structured.content', ''));

        if ($letterType === 'dispute') {
            $title = 'Credit Report Dispute Letter';
            $content = $this->normalizeGeneratedDisputeDraft($content);
        }

        return [
            'title' => $title,
            'content' => $content,
            'meta' => [
                ...Arr::get($result, 'meta', []),
                'template_key' => $template['key'] ?? null,
                'template_version' => $template['version'] ?? null,
            ],
        ];
    }

    /**
     * @return array{title:string,content:string,meta:array<string,mixed>}
     */
    public function generateCaseBrief(
        Client $client,
        ?ReportingCycle $cycle,
        string $period,
        ?string $operatorFocus = null,
    ): array {
        $context = $this->buildClientContext($client, $cycle);
        $focus = trim((string) $operatorFocus);
        $resolvedFocus = $focus !== ''
            ? $focus
            : 'Summarize progress, priority disputes, utilization targets, and the next clean action plan.';
        $result = $this->generateStructured(
            task: 'summaries',
            instructions: 'You draft clean weekly or monthly case briefs for credit repair operators. Do not include private insults, raw identifiers, or unapproved promises. Keep the tone professional and client-facing.',
            prompt: trim(<<<PROMPT
            Draft a {$period} shareable case brief.

            Operator focus:
            {$resolvedFocus}

            Case context:
            {$context}
            PROMPT),
            schema: fn (JsonSchema $schema) => [
                'title' => $schema->string()->required(),
                'content' => $schema->string()->required(),
            ],
        );

        return [
            'title' => trim((string) Arr::get($result, 'structured.title', 'AI case brief')),
            'content' => trim((string) Arr::get($result, 'structured.content', '')),
            'meta' => Arr::get($result, 'meta', []),
        ];
    }

    /**
     * @param  array<string, mixed>  $signal
     * @param  array<string, mixed>|null  $previous
     * @return array{content:string,meta:array<string,mixed>}
     */
    public function generateClientHealthNote(Client $client, array $signal, ?array $previous = null): array
    {
        $context = [
            'client_alias' => 'Client '.$client->getKey(),
            'previous' => $previous ? [
                'state' => $previous['state'] ?? null,
                'label' => $previous['label'] ?? null,
                'score' => $previous['score'] ?? null,
            ] : null,
            'current' => [
                'state' => $signal['state'] ?? null,
                'label' => $signal['label'] ?? null,
                'score' => $signal['score'] ?? null,
                'score_label' => $signal['score_label'] ?? null,
                'tone' => $signal['tone'] ?? null,
                'detail' => $signal['detail'] ?? null,
                'reason' => $signal['reason'] ?? null,
                'amount' => $signal['amount_label'] ?? null,
                'last_paid' => $signal['last_paid_label'] ?? null,
                'next_due' => $signal['next_due_label'] ?? null,
                'paid_payment_count' => $signal['paid_payment_count'] ?? null,
                'late_payment_count' => $signal['late_payment_count'] ?? null,
            ],
        ];
        $contextJson = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';

        $result = $this->generateStructured(
            task: 'summaries',
            instructions: 'You write short internal billing-health notes for CreditSoft admins. Explain why the client health color or internal score changed based only on payment history. Do not include personal identifiers, payment transaction ids, passwords, raw credentials, legal advice, or promises. One or two plain sentences only.',
            prompt: trim(<<<PROMPT
            Write an internal note explaining this client health signal change.

            Context:
            {$contextJson}
            PROMPT),
            schema: fn (JsonSchema $schema) => [
                'title' => $schema->string()->required(),
                'content' => $schema->string()->required(),
            ],
        );

        return [
            'content' => trim((string) Arr::get($result, 'structured.content', '')),
            'meta' => Arr::get($result, 'meta', []),
        ];
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @return array{title:string,summary:string,strengths:array<int,string>,risks:array<int,string>,coaching_notes:string,next_week_focus:array<int,string>,meta:array<string,mixed>}
     */
    public function generateHrWeeklyReport(string $employeeName, string $periodLabel, array $metrics): array
    {
        $metricsJson = json_encode($metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
        $result = $this->generateStructured(
            task: 'summaries',
            instructions: 'You write internal weekly HR performance reports for a credit repair intranet. Use only the supplied performance metrics. Be fair, specific, and practical. Do not infer protected class, health, disability, family status, religion, age, race, gender, or other sensitive traits. Do not mention surveillance. Do not recommend discipline unless the metrics clearly show a work issue. Keep it useful for a manager coaching an employee.',
            prompt: trim(<<<PROMPT
            Draft a weekly HR performance report.

            Employee:
            {$employeeName}

            Period:
            {$periodLabel}

            Metrics:
            {$metricsJson}

            Return concise content that a manager can paste into an internal employee performance file.
            PROMPT),
            schema: fn (JsonSchema $schema) => [
                'title' => $schema->string()->required(),
                'summary' => $schema->string()->required(),
                'strengths' => $schema->string()->required(),
                'risks' => $schema->string()->required(),
                'coaching_notes' => $schema->string()->required(),
                'next_week_focus' => $schema->string()->required(),
            ],
            requiredKeys: ['title', 'summary', 'strengths', 'risks', 'coaching_notes', 'next_week_focus'],
        );

        return [
            'title' => trim((string) Arr::get($result, 'structured.title', 'Weekly HR report')),
            'summary' => trim((string) Arr::get($result, 'structured.summary', '')),
            'strengths' => $this->parseAiList(Arr::get($result, 'structured.strengths', '')),
            'risks' => $this->parseAiList(Arr::get($result, 'structured.risks', '')),
            'coaching_notes' => trim((string) Arr::get($result, 'structured.coaching_notes', '')),
            'next_week_focus' => $this->parseAiList(Arr::get($result, 'structured.next_week_focus', '')),
            'meta' => Arr::get($result, 'meta', []),
        ];
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @return array{title:string,summary:string,bottleneck:string,recommendations:array<int,string>,meta:array<string,mixed>}
     */
    public function generateCtoPerformanceRecommendations(array $metrics): array
    {
        $provider = 'openrouter_creditsoft';

        if (! $this->registry->providerIsConfigured($provider)) {
            throw new RuntimeException('OpenRouter is not configured yet.');
        }

        $definition = $this->registry->providerDefinition($provider);
        $models = collect([
            Arr::get($definition, 'models.text.cto'),
            'nvidia/nemotron-3-super-120b-a12b:free',
            'nvidia/nemotron-3-nano-30b-a3b:free',
            'nvidia/nemotron-nano-9b-v2:free',
            'nvidia/nemotron-3-super-120b-a12b',
            'nvidia/nemotron-3-nano-30b-a3b',
            'nvidia/nemotron-nano-9b-v2',
        ])->filter(fn (mixed $model): bool => is_string($model) && trim($model) !== '')
            ->map(fn (string $model): string => trim($model))
            ->unique()
            ->values();
        $metricsJson = json_encode($metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
        $instructions = 'You are a local-first infrastructure performance advisor for CreditSoft. Use only the supplied diagnostics. Decide whether the best upgrades are CPU, memory, storage, network/internet, software/runtime tuning, or cluster layout. Be specific, practical, and conservative. For macOS and Apple Silicon nodes, do not recommend adding RAM from high used memory alone; use memory_pressure, available bytes, swap used, swapins, and swapouts as the evidence. Treat a healthy Apple Silicon office node as a valid secondary server node, not failed hardware, even if a larger Linux peer has more raw capacity. Do not ask for secrets, do not mention raw API keys, and do not invent benchmark numbers.';
        $prompt = trim(<<<PROMPT
        Review this CreditSoft CTO diagnostics snapshot and return the top 3 performance recommendations.

        Requirements:
        - Rank exactly three recommendations.
        - Each recommendation should say what to change, why it matters, and what evidence points there.
        - Keep each recommendation one short sentence.
        - If the evidence is weak, say "measure first" instead of pretending.
        - Favor the cheapest/highest-impact action first.
        - If a macOS/Apple Silicon node has healthy memory pressure and zero swap, do not list "add RAM" as a top recommendation.

        Diagnostics:
        {$metricsJson}
        PROMPT);
        $lastError = null;

        foreach ($models as $model) {
            try {
                if (($definition['driver'] ?? null) === 'openai-compatible') {
                    $result = $this->promptOpenAiCompatible(
                        $definition,
                        $model,
                        $instructions,
                        $prompt,
                        ['title', 'summary', 'bottleneck', 'recommendations'],
                    );
                } elseif ($provider === 'openrouter_creditsoft') {
                    $result = $this->promptOpenRouterJson(
                        $definition,
                        $model,
                        $instructions,
                        $prompt,
                        ['title', 'summary', 'bottleneck', 'recommendations'],
                    );
                } else {
                    $result = $this->promptLaravelAi(
                        $provider,
                        $model,
                        $instructions,
                        $prompt,
                        fn (JsonSchema $schema) => [
                            'title' => $schema->string()->required(),
                            'summary' => $schema->string()->required(),
                            'bottleneck' => $schema->string()->required(),
                            'recommendations' => $schema->string()->required(),
                        ],
                    );
                }

                if (
                    blank(Arr::get($result, 'structured.title'))
                    || blank(Arr::get($result, 'structured.summary'))
                    || blank(Arr::get($result, 'structured.bottleneck'))
                    || $this->parseAiList(Arr::get($result, 'structured.recommendations', '')) === []
                ) {
                    throw new RuntimeException('OpenRouter Nemotron returned an incomplete recommendation payload.');
                }

                break;
            } catch (Throwable $throwable) {
                $lastError = $throwable;
            }
        }

        if (! isset($result)) {
            throw new RuntimeException(
                'OpenRouter Nemotron could not return recommendations right now: '
                .Str::limit($lastError?->getMessage() ?: 'no model endpoint responded.', 180),
                previous: $lastError,
            );
        }

        return [
            'title' => trim((string) Arr::get($result, 'structured.title', 'Performance recommendations')),
            'summary' => trim((string) Arr::get($result, 'structured.summary', '')),
            'bottleneck' => trim((string) Arr::get($result, 'structured.bottleneck', 'mixed')),
            'recommendations' => collect($this->parseAiList(Arr::get($result, 'structured.recommendations', '')))
                ->take(3)
                ->values()
                ->all(),
            'meta' => [
                ...Arr::get($result, 'meta', []),
                'provider' => Arr::get($result, 'meta.provider', $provider),
                'model' => Arr::get($result, 'meta.model', $model),
            ],
        ];
    }

    /**
     * @param  array<int, array{role?:string, content?:string}>  $history
     * @return array{reply:string,meta:array<string,mixed>}
     */
    public function chatAssistant(
        string $provider,
        string $message,
        ?Client $client = null,
        array $history = [],
    ): array {
        $resolvedMessage = trim($message);

        if ($resolvedMessage === '') {
            throw new RuntimeException('Ask a question before sending the assistant a prompt.');
        }

        if (! $this->registry->providerIsConfigured($provider)) {
            throw new RuntimeException('The selected AI lane is not configured yet.');
        }

        $definition = $this->registry->providerDefinition($provider);
        $model = $this->resolveChatModel($definition);
        $historyText = collect($history)
            ->filter(fn (array $entry) => in_array($entry['role'] ?? null, ['user', 'assistant'], true) && filled($entry['content'] ?? null))
            ->take(-6)
            ->map(fn (array $entry) => strtoupper((string) $entry['role']).': '.Str::limit(trim((string) $entry['content']), 1_000))
            ->implode("\n\n");
        $resolvedHistory = $historyText !== '' ? $historyText : 'No prior conversation.';

        $clientContext = $client ? $this->buildClientContext($client, null) : null;
        $workspace = $client
            ? "Client dossier for internal case review only.\n\n{$clientContext}"
            : 'General CreditSoft operations workspace without client-specific context.';

        $prompt = trim(<<<PROMPT
        Workspace context:
        {$workspace}

        Recent conversation:
        {$resolvedHistory}

        Operator message:
        {$resolvedMessage}
        PROMPT);

        $instructions = 'You are CreditSoft Copilot inside a privacy-first credit repair intranet. Give practical operator guidance for Metro 2 review, dispute planning, utilization coaching, SOP execution, case briefs, letters, and workflow decisions. Be concise, high-signal, and operational. Answer in plain text only. Do not use Markdown headings, bold markers, fenced code blocks, or checklist syntax. Prefer short paragraphs or flat bullets. Do not invent facts, do not make legal guarantees, and do not suggest sending raw PII off the local system. If the operator asks for an action you cannot verify from the supplied context, say what is missing.';

        $result = ($definition['driver'] ?? null) === 'openai-compatible'
            ? $this->promptOpenAiCompatibleReply($definition, $model, $instructions, $prompt)
            : $this->promptLaravelAiTextReply($provider, $model, $instructions, $prompt);

        return [
            'reply' => $this->normalizeAssistantReply((string) Arr::get($result, 'text', Arr::get($result, 'structured.reply', ''))),
            'meta' => Arr::get($result, 'meta', []),
        ];
    }

    /**
     * @param  array<string, mixed>  $event
     * @param  array<string, mixed>  $signals
     * @param  array<string, mixed>  $fallback
     * @return array{title:string,summary:string,next_action:string,priority:string,channel:string,draft_message:string,crm_note:string,confidence:string,meta:array<string,mixed>}
     */
    public function generateCrmAutomationPlan(?Client $client, array $event, array $signals, array $fallback): array
    {
        $eventJson = json_encode($event, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
        $signalsJson = json_encode($signals, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
        $fallbackJson = json_encode($fallback, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
        $clientContext = $client ? $this->buildClientContext($client, $client->reportingCycles()->first()) : 'No matching CreditSoft client was found.';

        $result = $this->generateStructured(
            task: 'oversight',
            instructions: 'You are CreditSoft campaign intelligence running outside the CRM. Decide the next practical follow-up from a Twenty CRM webhook using CreditSoft client context. Do not invent facts, do not promise credit outcomes, do not ask clients to text passwords or SSNs, and do not send anything automatically. Prefer SMS draft only when the action is short, consent-safe, and client-facing. Return concise JSON.',
            prompt: trim(<<<PROMPT
            A CRM webhook arrived. Decide the next CreditSoft campaign action.

            CRM event:
            {$eventJson}

            CreditSoft signals:
            {$signalsJson}

            Rule fallback:
            {$fallbackJson}

            Client context:
            {$clientContext}

            Requirements:
            - Keep the title under 90 characters.
            - priority must be one of: low, normal, high.
            - channel must be one of: task, email_draft, sms_draft, note.
            - next_action should be one concrete operator action.
            - draft_message can be blank unless email_draft or sms_draft is appropriate.
            - crm_note should be safe to write back into CRM as an internal timeline note.
            PROMPT),
            schema: fn (JsonSchema $schema) => [
                'title' => $schema->string()->required(),
                'summary' => $schema->string()->required(),
                'next_action' => $schema->string()->required(),
                'priority' => $schema->string()->required(),
                'channel' => $schema->string()->required(),
                'draft_message' => $schema->string()->required(),
                'crm_note' => $schema->string()->required(),
                'confidence' => $schema->string()->required(),
            ],
            requiredKeys: ['title', 'summary', 'next_action', 'priority', 'channel', 'draft_message', 'crm_note', 'confidence'],
        );

        $structured = Arr::get($result, 'structured', []);
        $priority = in_array($structured['priority'] ?? null, ['low', 'normal', 'high'], true)
            ? (string) $structured['priority']
            : (string) ($fallback['priority'] ?? 'normal');
        $channel = in_array($structured['channel'] ?? null, ['task', 'email_draft', 'sms_draft', 'note'], true)
            ? (string) $structured['channel']
            : (string) ($fallback['channel'] ?? 'task');

        return [
            'title' => Str::limit(trim((string) ($structured['title'] ?? $fallback['title'] ?? 'CRM automation review')), 90, ''),
            'summary' => trim((string) ($structured['summary'] ?? $fallback['summary'] ?? 'Review the CRM event and decide the next action.')),
            'next_action' => trim((string) ($structured['next_action'] ?? $fallback['next_action'] ?? 'Review the CRM event.')),
            'priority' => $priority,
            'channel' => $channel,
            'draft_message' => trim((string) ($structured['draft_message'] ?? $fallback['draft_message'] ?? '')),
            'crm_note' => trim((string) ($structured['crm_note'] ?? $fallback['crm_note'] ?? 'CreditSoft received a CRM automation event.')),
            'confidence' => trim((string) ($structured['confidence'] ?? 'medium')),
            'meta' => Arr::get($result, 'meta', []),
        ];
    }

    /**
     * @param  callable(JsonSchema):array<string,mixed>  $schema
     * @return array{structured:array<string,mixed>,meta:array<string,mixed>}
     */
    protected function generateStructured(string $task, string $instructions, string $prompt, callable $schema, array $requiredKeys = ['title', 'content']): array
    {
        $lastError = null;

        foreach ($this->registry->providerChain($task) as $candidate) {
            if (! $this->registry->providerIsConfigured($candidate['provider'])) {
                continue;
            }

            try {
                return $candidate['definition']['driver'] === 'openai-compatible'
                    ? $this->promptOpenAiCompatible($candidate['definition'], $candidate['model'], $instructions, $prompt, $requiredKeys)
                    : $this->promptLaravelAi($candidate['provider'], $candidate['model'], $instructions, $prompt, $schema);
            } catch (Throwable $throwable) {
                $lastError = $throwable;
            }
        }

        throw new RuntimeException(
            $lastError?->getMessage() ?: 'No configured Creditsoft AI providers were available for this task.',
            previous: $lastError,
        );
    }

    /**
     * @param  callable(JsonSchema):array<string,mixed>  $schema
     * @return array{structured:array<string,mixed>,meta:array<string,mixed>}
     */
    protected function promptLaravelAi(string $provider, string $model, string $instructions, string $prompt, callable $schema): array
    {
        $response = agent($instructions, [], [], $schema)->prompt($prompt, provider: $provider, model: $model);

        return [
            'structured' => method_exists($response, 'toArray') ? $response->toArray() : [],
            'meta' => [
                'provider' => $response->meta->provider,
                'model' => $response->meta->model,
            ],
        ];
    }

    /**
     * @return array{structured:array<string,mixed>,meta:array<string,mixed>}
     */
    protected function promptLaravelAiTextReply(string $provider, string $model, string $instructions, string $prompt): array
    {
        $response = agent($instructions)->prompt($prompt, provider: $provider, model: $model);

        return [
            'text' => trim((string) $response->text),
            'meta' => [
                'provider' => $response->meta->provider,
                'model' => $response->meta->model,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $provider
     * @return array{structured:array<string,mixed>,meta:array<string,mixed>}
     */
    protected function promptOpenAiCompatible(array $provider, string $model, string $instructions, string $prompt, array $requiredKeys = ['title', 'content']): array
    {
        $jsonKeys = collect($requiredKeys)
            ->map(fn (string $key): string => '"'.$key.'"')
            ->implode(', ');

        $response = Http::timeout(90)
            ->acceptJson()
            ->withToken((string) $provider['key'])
            ->post((string) $provider['url'], [
                'model' => $model,
                'temperature' => 0.2,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $instructions.' Return valid JSON only with these keys: '.$jsonKeys.'. For list-like fields, use newline-separated strings.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(($provider['name'] ?? 'OpenAI-compatible provider').' request failed with status '.$response->status().'.');
        }

        $content = (string) data_get($response->json(), 'choices.0.message.content', '');
        $structured = $this->extractJson($content);

        if (! is_array($structured)) {
            throw new RuntimeException('OpenAI-compatible provider did not return a valid JSON payload.');
        }

        $missing = collect($requiredKeys)
            ->filter(fn (string $key): bool => blank($structured[$key] ?? null))
            ->values();

        if ($missing->isNotEmpty()) {
            throw new RuntimeException('OpenAI-compatible provider did not return the expected JSON keys: '.$missing->implode(', ').'.');
        }

        return [
            'structured' => $structured,
            'meta' => [
                'provider' => $provider['name'] ?? 'opencode_zen',
                'model' => $model,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $provider
     * @return array{structured:array<string,mixed>,meta:array<string,mixed>}
     */
    protected function promptOpenRouterJson(array $provider, string $model, string $instructions, string $prompt, array $requiredKeys = ['title', 'content']): array
    {
        return $this->promptOpenAiCompatible([
            ...$provider,
            'name' => $provider['name'] ?? 'openrouter_creditsoft',
            'url' => $provider['url'] ?? 'https://openrouter.ai/api/v1/chat/completions',
        ], $model, $instructions, $prompt, $requiredKeys);
    }

    /**
     * @param  array<string, mixed>  $provider
     * @return array{structured:array<string,mixed>,meta:array<string,mixed>}
     */
    protected function promptOpenAiCompatibleReply(array $provider, string $model, string $instructions, string $prompt): array
    {
        $response = Http::timeout(90)
            ->acceptJson()
            ->withToken((string) $provider['key'])
            ->post((string) $provider['url'], [
                'model' => $model,
                'temperature' => 0.2,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $instructions.' Return valid JSON with the key "reply" only.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('OpenCode Zen request failed with status '.$response->status().'.');
        }

        $content = (string) data_get($response->json(), 'choices.0.message.content', '');
        $structured = $this->extractJson($content);

        if (! is_array($structured) || blank($structured['reply'] ?? null)) {
            throw new RuntimeException('OpenCode Zen did not return the expected assistant payload.');
        }

        return [
            'structured' => $structured,
            'meta' => [
                'provider' => $provider['name'] ?? 'opencode_zen',
                'model' => $model,
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function extractJson(string $content): ?array
    {
        $decoded = json_decode($content, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $content, $matches) === 1) {
            $decoded = json_decode($matches[0], true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    protected function resolveChatModel(array $definition): string
    {
        $model = Arr::get($definition, 'models.text.smartest')
            ?? Arr::get($definition, 'models.text.default');

        if (! is_string($model) || $model === '') {
            throw new RuntimeException('The selected AI lane does not define a chat-capable model.');
        }

        return $model;
    }

    protected function buildClientContext(Client $client, ?ReportingCycle $cycle): string
    {
        $client->loadMissing([
            'notes' => fn ($query) => $query->latest()->limit(4),
            'violations' => fn ($query) => $query->latest()->limit(8),
            'browserCaptures' => fn ($query) => $query->latest('imported_at')->limit(3),
        ]);

        $cycle?->loadMissing('bureauSnapshots.tradelines', 'violationCandidates');

        $summary = $cycle ? $this->comparisonService->reviewSummary($cycle) : null;
        $comparisonRows = $cycle
            ? collect($this->comparisonService->comparisonRows($cycle))
                ->filter(fn (array $row) => filled($row['mismatches'] ?? []))
                ->take(8)
                ->map(fn (array $row) => [
                    'account' => $row['label'],
                    'severity' => $row['severity'] ?? null,
                    'issues' => collect($row['mismatches'] ?? [])
                        ->map(fn (string $mismatch) => Str::of($mismatch)->replace('_', ' ')->lower()->value())
                        ->values()
                        ->all(),
                    'bureau_snapshot' => collect($row['bureaus'] ?? [])
                        ->filter(fn ($bureau) => is_array($bureau))
                        ->mapWithKeys(fn (array $bureau, string $key) => [$key => array_filter([
                            'status' => $bureau['account_status'] ?? null,
                            'payment_status' => $bureau['payment_status'] ?? null,
                            'balance' => $bureau['balance'] ?? null,
                            'last_payment' => $bureau['date_last_payment'] ?? null,
                        ], fn ($value) => ! blank($value))])
                        ->all(),
                ])
                ->values()
                ->all()
            : [];

        $violations = ($cycle?->violationCandidates ?? $client->violations)
            ->take(8)
            ->map(fn (ViolationCandidate $violation) => [
                'title' => $violation->title,
                'severity' => $violation->severity,
                'status' => $violation->status,
                'next_action' => $violation->next_action,
            ])->values()->all();

        $captures = $client->browserCaptures
            ->take(3)
            ->map(fn (BrowserCapture $capture) => [
                'title' => $capture->page_title,
                'url' => $capture->page_url,
                'excerpt' => Str::limit((string) $capture->extracted_text, 900),
                'source_type' => $capture->source_type,
            ])->values()->all();

        $notes = $client->notes
            ->map(fn ($note) => [
                'visibility' => $note->visibility,
                'note' => Str::limit((string) $note->note, 220),
            ])->values()->all();

        $context = [
            'client_alias' => 'Client '.$client->getKey(),
            'client_status' => $client->status,
            'current_score' => $client->current_score,
            'goals' => $client->goals,
            'reporting_cycle' => $cycle?->cycle_label,
            'summary' => $summary,
            'comparison_findings' => $comparisonRows,
            'violations' => $violations,
            'recent_notes' => $notes,
            'browser_captures' => $captures,
        ];

        return json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    protected function normalizeAssistantReply(string $reply): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", trim($reply));
        $normalized = preg_replace('/^\s{0,3}#{1,6}\s*/m', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\*\*(.*?)\*\*/s', '$1', $normalized) ?? $normalized;
        $normalized = preg_replace('/__(.*?)__/s', '$1', $normalized) ?? $normalized;
        $normalized = preg_replace('/^\s*[-*]\s+\[(?: |x|X)\]\s*/m', '- ', $normalized) ?? $normalized;
        $normalized = preg_replace('/^\s*[•●▪]\s*/m', '- ', $normalized) ?? $normalized;
        $normalized = preg_replace("/\n{3,}/", "\n\n", $normalized) ?? $normalized;

        return trim($normalized);
    }

    /**
     * @return array<int, string>
     */
    protected function parseAiList(mixed $content): array
    {
        if (is_array($content)) {
            return collect($content)
                ->map(fn (mixed $line): string => $this->normalizeAiListLine((string) $line))
                ->filter()
                ->values()
                ->all();
        }

        return collect(preg_split('/\r\n|\r|\n|;/', trim((string) $content)) ?: [])
            ->map(fn (string $line): string => $this->normalizeAiListLine($line))
            ->filter()
            ->values()
            ->all();
    }

    protected function normalizeAiListLine(string $line): string
    {
        return trim(preg_replace('/^\s*(?:[-*\/]+|\d+[.)])\s*/', '', $line) ?? $line);
    }

    protected function normalizeGeneratedDisputeDraft(string $content): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", trim($content));
        $lines = preg_split('/\n/u', $normalized) ?: [];
        $cleaned = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                if (($cleaned[count($cleaned) - 1] ?? null) !== '') {
                    $cleaned[] = '';
                }

                continue;
            }

            if (preg_match('/^(Client|Cycle|Status|Template|Prepared|File Reference|Dispute Type|Total Accounts Under Dispute):/i', $trimmed)) {
                continue;
            }

            if (preg_match('/^DISPUTE IDENTIFICATION:/i', $trimmed)) {
                continue;
            }

            if (preg_match('/^\[Client Services on behalf of Client \d+\]$/i', $trimmed)) {
                continue;
            }

            $cleaned[] = $trimmed;
        }

        return trim(implode("\n", $cleaned));
    }
}
