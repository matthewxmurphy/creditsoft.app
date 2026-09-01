<?php

return [
    'version' => 1,

    // These are planning estimates, not postage quotes. Offices can change them
    // without changing a playbook or a client's signed consent.
    'mailing_rates_cents' => [
        'certified' => (int) env('DISPUTE_CERTIFIED_LETTER_ESTIMATE_CENTS', 435),
        'regular' => (int) env('DISPUTE_REGULAR_LETTER_ESTIMATE_CENTS', 100),
    ],

    'modes' => [
        'strategy' => [
            'name' => 'Strategy Mode',
            'summary' => 'Measured bureau rounds with an evidence review before each escalation.',
            'steps' => [
                ['key' => 'round_1_bureau_disputes', 'round' => 1, 'day' => 0, 'title' => 'Prepare Round 1 bureau disputes', 'action_type' => 'bureau_dispute', 'letter_count' => 6],
                ['key' => 'round_1_day_9_compliance', 'round' => 1, 'day' => 9, 'title' => 'Run Day 9 dispute-remark compliance check', 'action_type' => 'compliance_check', 'depends_on' => 'round_1_bureau_disputes'],
                ['key' => 'round_1_bureau_clock', 'round' => 1, 'day' => 30, 'title' => 'Audit bureau response clocks', 'action_type' => 'bureau_clock_check', 'depends_on' => 'round_1_bureau_disputes'],
                ['key' => 'round_1_report_refresh', 'round' => 1, 'day' => 45, 'title' => 'Reimport report and log Round 1 outcomes', 'action_type' => 'report_reimport', 'depends_on' => 'round_1_bureau_disputes'],
                ['key' => 'round_2_unresolved', 'round' => 2, 'day' => 46, 'title' => 'Prepare Round 2 for unresolved reporting', 'action_type' => 'bureau_dispute', 'letter_count' => 6, 'depends_on' => 'round_1_report_refresh'],
            ],
        ],
        'aggressive' => [
            'name' => 'Aggressive Mode',
            'summary' => 'Coordinated collector, furnisher, secondary-bureau, and bureau waves with tight timing.',
            'steps' => [
                ['key' => 'secondary_bureau_wave', 'round' => 1, 'day' => 0, 'title' => 'Queue secondary-bureau disputes', 'action_type' => 'secondary_bureau_dispute', 'letter_count' => 4],
                ['key' => 'debt_validation_wave', 'round' => 1, 'day' => 0, 'title' => 'Queue debt-validation letters', 'action_type' => 'debt_validation', 'letter_count' => 4],
                ['key' => 'cease_desist_wave', 'round' => 1, 'day' => 0, 'title' => 'Queue collector cease-and-desist letters', 'action_type' => 'cease_desist', 'letter_count' => 4],
                ['key' => 'round_1_bureau_disputes', 'round' => 1, 'day' => 7, 'title' => 'Queue bureau disputes seven days after collector wave', 'action_type' => 'bureau_dispute', 'letter_count' => 6, 'depends_on' => 'debt_validation_wave'],
                ['key' => 'round_1_day_9_compliance', 'round' => 1, 'day' => 16, 'title' => 'Run Day 9 dispute-remark compliance check', 'action_type' => 'compliance_check', 'depends_on' => 'round_1_bureau_disputes'],
                ['key' => 'round_1_bureau_clock', 'round' => 1, 'day' => 37, 'title' => 'Audit bureau response clocks', 'action_type' => 'bureau_clock_check', 'depends_on' => 'round_1_bureau_disputes'],
                ['key' => 'round_1_report_refresh', 'round' => 1, 'day' => 52, 'title' => 'Reimport report and log Round 1 outcomes', 'action_type' => 'report_reimport', 'depends_on' => 'round_1_bureau_disputes'],
                ['key' => 'round_2_unresolved', 'round' => 2, 'day' => 53, 'title' => 'Launch the next unresolved-item wave', 'action_type' => 'bureau_dispute', 'letter_count' => 6, 'depends_on' => 'round_1_report_refresh'],
            ],
        ],
        'nuke' => [
            'name' => 'Nuke Mode',
            'summary' => 'Identity cleanup first, followed by a broad evidence-based dispute round.',
            'steps' => [
                ['key' => 'identity_scrub', 'round' => 0, 'day' => 0, 'title' => 'Scrub obsolete names, addresses, and employers', 'action_type' => 'identity_scrub'],
                ['key' => 'round_1_bureau_disputes', 'round' => 1, 'day' => 1, 'title' => 'Prepare Nuke Round 1 dispute package', 'action_type' => 'bureau_dispute', 'letter_count' => 14, 'depends_on' => 'identity_scrub'],
                ['key' => 'round_1_day_9_compliance', 'round' => 1, 'day' => 10, 'title' => 'Run Day 9 dispute-remark compliance check', 'action_type' => 'compliance_check', 'depends_on' => 'round_1_bureau_disputes'],
                ['key' => 'round_1_bureau_clock', 'round' => 1, 'day' => 31, 'title' => 'Audit bureau response clocks', 'action_type' => 'bureau_clock_check', 'depends_on' => 'round_1_bureau_disputes'],
                ['key' => 'round_1_report_refresh', 'round' => 1, 'day' => 46, 'title' => 'Reimport report and log Nuke Round 1 outcomes', 'action_type' => 'report_reimport', 'depends_on' => 'round_1_bureau_disputes'],
                ['key' => 'round_2_unresolved', 'round' => 2, 'day' => 47, 'title' => 'Prepare the next unresolved-item round', 'action_type' => 'bureau_dispute', 'letter_count' => 10, 'depends_on' => 'round_1_report_refresh'],
            ],
        ],
    ],
];
