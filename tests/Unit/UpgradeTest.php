<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Upgrade;
use PHPUnit\Framework\TestCase;

class UpgradeTest extends TestCase
{
    public function testNeedsVersionUpdateReturnsFalseWhenVersionsMatch(): void
    {
        $this->assertFalse(Upgrade::needsVersionUpdate('0.1.0', '0.1.0'));
    }

    public function testNeedsVersionUpdateReturnsTrueWhenVersionsDiffer(): void
    {
        $this->assertTrue(Upgrade::needsVersionUpdate('0.2.0', '0.1.0'));
    }
}
