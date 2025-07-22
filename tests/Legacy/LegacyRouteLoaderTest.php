<?php

declare(strict_types=1);

namespace Pastell\Tests\Legacy;

use Pastell\Legacy\LegacyRouteLoader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;

final class LegacyRouteLoaderTest extends TestCase
{
    /**
     * @throws \Exception
     */
    public function testLoad(): void
    {
        $legacyRouteLoader = new LegacyRouteLoader();
        $routes = $legacyRouteLoader->load('');

        $this->assertCount(232, $routes);
        $this->assertContainsOnly(Route::class, $routes);

        foreach ($routes as $route) {
            $this->assertSame(
                'Pastell\Controller\LegacyController::loadLegacyScript',
                $route->getDefault('_controller')
            );
        }
    }
}
