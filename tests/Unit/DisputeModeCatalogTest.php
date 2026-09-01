<?php

namespace Tests\Unit;

use App\Services\DisputeModeCatalog;
use Tests\TestCase;

class DisputeModeCatalogTest extends TestCase
{
    public function test_catalog_contains_the_three_versioned_modes(): void
    {
        $catalog = new DisputeModeCatalog;

        $this->assertSame(['strategy', 'aggressive', 'nuke'], $catalog->keys());
        $this->assertSame(1, $catalog->version());
    }
}
