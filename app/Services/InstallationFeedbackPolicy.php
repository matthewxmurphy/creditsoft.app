<?php

namespace App\Services;

class InstallationFeedbackPolicy
{
    public function __construct(
        protected InstallerState $installerState,
    ) {}

    /**
     * @return array<string, bool>
     */
    public function current(): array
    {
        $state = $this->installerState->read();

        return [
            'portal_sync_enabled' => (bool) data_get($state, 'portal_sync_enabled', true),
            'report_feedback_enabled' => (bool) data_get($state, 'report_feedback_enabled', false),
        ];
    }

    public function portalSyncEnabled(): bool
    {
        return $this->current()['portal_sync_enabled'];
    }

    public function reportFeedbackEnabled(): bool
    {
        return $this->current()['report_feedback_enabled'];
    }
}
