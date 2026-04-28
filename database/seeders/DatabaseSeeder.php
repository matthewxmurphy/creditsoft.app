<?php

namespace Database\Seeders;

use App\Models\BureauSnapshot;
use App\Models\BrowserCapture;
use App\Models\CaseBrief;
use App\Models\CaseNote;
use App\Models\Client;
use App\Models\MetricSnapshot;
use App\Models\ReportingCycle;
use App\Models\SopRun;
use App\Models\SopTemplate;
use App\Models\Task;
use App\Models\Tradeline;
use App\Models\User;
use App\Services\SmartCreditCaptureParser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roles = collect(config('creditsoft.access.roles', []))->keys();

        foreach ($roles as $role) {
            Role::findOrCreate($role);
        }

        $seededUsers = collect(config('creditsoft.access.login_accounts', []))
            ->mapWithKeys(function (array $account): array {
                $user = User::query()
                    ->where('email', $account['email'])
                    ->orWhere(function ($query) use ($account): void {
                        $query->whereHas('roles', fn ($roleQuery) => $roleQuery->where('name', $account['role']));
                    })
                    ->first();

                if (! $user) {
                    $user = new User();
                }

                $user->forceFill([
                    'name' => $account['name'],
                    'email' => $account['email'],
                    'password' => $account['password'],
                ])->save();

                $user->syncRoles([$account['role']]);

                return [$account['role'] => $user];
            });

        /** @var User $ownerAdmin */
        $ownerAdmin = $seededUsers->get('owner_admin');
        /** @var User $caseManager */
        $caseManager = $seededUsers->get('case_manager');
        /** @var User $staff */
        $staff = $seededUsers->get('staff');

        $client = Client::firstOrCreate([
            'cuid' => 'c_demo001',
        ], [
            'first_name' => 'Avery',
            'last_name' => 'Cole',
            'email' => 'avery@example.com',
            'phone' => '555-0147',
            'current_score' => 612,
            'status' => 'active_review',
            'assigned_to' => ($caseManager ?? $staff)?->getKey(),
            'goals' => 'Improve utilization and clear stale collections.',
        ]);

        $cycle = ReportingCycle::firstOrCreate([
            'client_id' => $client->getKey(),
            'cycle_label' => 'April 2026 review',
        ], [
            'source' => 'manual',
            'started_at' => Carbon::parse('2026-04-01'),
            'public_summary' => 'Initial bureau comparison loaded for dispute prep.',
        ]);

        $experian = BureauSnapshot::firstOrCreate([
            'reporting_cycle_id' => $cycle->getKey(),
            'bureau' => 'experian',
        ], [
            'source' => 'manual',
            'imported_by' => $staff->getKey(),
            'imported_at' => now(),
            'snapshot_hash' => sha1('exp-demo'),
        ]);

        $transunion = BureauSnapshot::firstOrCreate([
            'reporting_cycle_id' => $cycle->getKey(),
            'bureau' => 'transunion',
        ], [
            'source' => 'manual',
            'imported_by' => $staff->getKey(),
            'imported_at' => now(),
            'snapshot_hash' => sha1('tu-demo'),
        ]);

        $equifax = BureauSnapshot::firstOrCreate([
            'reporting_cycle_id' => $cycle->getKey(),
            'bureau' => 'equifax',
        ], [
            'source' => 'manual',
            'imported_by' => $staff->getKey(),
            'imported_at' => now(),
            'snapshot_hash' => sha1('eq-demo'),
        ]);

        $tradelines = [
            [$experian, 'Capital One', 'revolving', 5400, 5000, 'charged off', 'late', 'Status conflict: charged off + open'],
            [$transunion, 'Capital One Bank', 'revolving', 0, 5000, 'closed', 'paid', 'Zero balance but still reporting legacy late status'],
            [$equifax, 'Capital One NA', 'revolving', 5400, 5000, 'closed', 'late', 'Date of last payment missing'],
            [$experian, 'Synchrony Bank', 'revolving', 2200, 3000, 'open', 'current', 'Utilization over 30 percent'],
            [$transunion, 'Synchrony', 'revolving', 2200, 3000, 'open', 'current', 'Utilization over 30 percent'],
            [$equifax, 'Synchrony Bank', 'revolving', 2200, 3000, 'open', 'current', 'Utilization over 30 percent'],
        ];

        foreach ($tradelines as [$snapshot, $creditor, $type, $balance, $limit, $status, $paymentStatus, $remarks]) {
            Tradeline::firstOrCreate([
                'bureau_snapshot_id' => $snapshot->getKey(),
                'creditor_name' => $creditor,
            ], [
                'normalized_key' => Tradeline::buildNormalizedKey([
                    'creditor_name' => $creditor,
                    'account_type' => $type,
                    'bureau_account_reference' => '1234',
                ]),
                'account_type' => $type,
                'balance' => $balance,
                'credit_limit' => $limit,
                'account_status' => $status,
                'payment_status' => $paymentStatus,
                'remarks' => $remarks,
                'is_revolving' => true,
                'is_open' => $status === 'open',
                'positive_classification' => false,
                'provenance' => 'seed',
            ]);
        }

        CaseNote::firstOrCreate([
            'client_id' => $client->getKey(),
            'visibility' => 'private_note',
            'note' => 'Client was frustrated during call; avoid syncing this note.',
        ], [
            'user_id' => $staff->getKey(),
        ]);

        CaseBrief::firstOrCreate([
            'client_id' => $client->getKey(),
            'title' => 'April weekly brief',
        ], [
            'user_id' => $staff->getKey(),
            'period' => 'weekly',
            'content' => 'Capital One mismatch and high utilization are the main focus areas this week.',
            'approved_at' => now(),
            'approved_by' => $ownerAdmin?->getKey(),
        ]);

        $template = SopTemplate::firstOrCreate([
            'slug' => 'monthly-review',
        ], [
            'name' => 'Monthly review',
            'description' => 'Review all three bureaus, confirm mismatches, and prepare next action.',
            'steps' => [
                ['title' => 'Import current bureau snapshots', 'done' => true],
                ['title' => 'Confirm high-severity mismatches', 'done' => false],
                ['title' => 'Approve shareable case brief', 'done' => false],
            ],
        ]);

        SopRun::firstOrCreate([
            'client_id' => $client->getKey(),
            'sop_template_id' => $template->getKey(),
        ], [
            'reporting_cycle_id' => $cycle->getKey(),
            'assigned_to' => $staff->getKey(),
            'steps' => $template->steps,
        ]);

        Task::firstOrCreate([
            'title' => 'Draft Capital One dispute letter',
        ], [
            'client_id' => $client->getKey(),
            'assigned_to' => $staff->getKey(),
            'priority' => 'high',
            'status' => 'open',
            'due_at' => now()->addDay(),
        ]);

        $scoreTrackerHtml = <<<'HTML'
            <html>
                <head><title>Score Tracker | SmartCredit</title></head>
                <body>
                    <div class="content-heading">
                        <span class="heading-sublink"><strong>Score as of:</strong> Apr 8, 2026</span>
                    </div>
                    <div id="credit-score-info" class="score-details-container"><h4>624</h4><p>Grade: D</p></div>
                    <div id="auto-score-info" class="score-details-container"><h4>614</h4><p>Grade: D</p></div>
                    <div id="insurance-score-info" class="score-details-container"><h4>614</h4><p>Grade: D</p></div>
                    <div class="score-tracker-history">
                        <table class="score-tracker-table">
                            <tbody>
                                <tr>
                                    <th>Date</th>
                                    <th>Credit Score</th>
                                    <th>Auto Score</th>
                                    <th>Insurance Score</th>
                                </tr>
                                <tr>
                                    <td>Apr 08, 2026</td>
                                    <td>624</td>
                                    <td>614</td>
                                    <td>614</td>
                                </tr>
                                <tr>
                                    <td>Jan 13, 2026</td>
                                    <td>610</td>
                                    <td>600</td>
                                    <td>600</td>
                                </tr>
                                <tr>
                                    <td>Dec 11, 2025</td>
                                    <td>610</td>
                                    <td>600</td>
                                    <td>600</td>
                                </tr>
                                <tr>
                                    <td>Aug 26, 2023</td>
                                    <td>540</td>
                                    <td>505</td>
                                    <td>518</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </body>
            </html>
        HTML;

        /** @var SmartCreditCaptureParser $smartCreditCaptureParser */
        $smartCreditCaptureParser = app(SmartCreditCaptureParser::class);
        $scoreTrackerMetadata = $smartCreditCaptureParser->parse(
            $scoreTrackerHtml,
            'Score Tracker | SmartCredit',
            'https://www.smartcredit.com/member/scores/score-tracker.htm',
        );

        BrowserCapture::updateOrCreate([
            'client_id' => $client->getKey(),
            'reporting_cycle_id' => $cycle->getKey(),
            'page_title' => 'Score Tracker | SmartCredit',
        ], [
            'user_id' => $staff->getKey(),
            'source_type' => 'safari_webarchive',
            'browser_name' => 'Safari',
            'page_url' => 'https://www.smartcredit.com/member/scores/score-tracker.htm',
            'archive_format' => 'webarchive-binary',
            'content_html' => $scoreTrackerHtml,
            'extracted_text' => 'SmartCredit score tracker archive with dated credit, auto, and insurance score history.',
            'metadata' => array_filter([
                'snapshot_pipeline' => 'browser_evidence',
                'ingestion_mode' => 'safari_webarchive',
                'import_profile' => $scoreTrackerMetadata['profile'] ?? null,
                'smartcredit' => $scoreTrackerMetadata,
                'parse_status' => 'parsed',
            ]),
            'imported_at' => Carbon::parse('2026-04-08 09:15:00'),
        ]);

        $client->forceFill([
            'current_score' => 624,
        ])->save();

        MetricSnapshot::query()->upsert(
            collect([
                ['2026-01-01', 9800],
                ['2026-02-01', 10300],
                ['2026-03-01', 11150],
                ['2026-04-01', 11850],
            ])->map(fn (array $snapshot) => [
                'key' => 'mrr',
                'bucket_date' => $snapshot[0],
                'label' => 'Monthly recurring revenue',
                'value' => $snapshot[1],
                'meta' => json_encode(['source' => 'manual_seed'], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ])->all(),
            ['key', 'bucket_date'],
            ['label', 'value', 'meta', 'updated_at'],
        );
    }
}
