<?php

declare(strict_types=1);

namespace Pastell\Legacy;

use ReflectionClass;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class LegacyRouteLoader extends Loader
{
    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        $collection = new RouteCollection();
        $finder = new Finder();
        $finder->files()->name('*Controler.php')->notName('PastellControler.php');
        /** @var SplFileInfo $legacyScriptFile */
        foreach ($finder->in(__DIR__ . '/../../controler') as $legacyScriptFile) {
            $controllerClassName = \str_replace('.php', '', $legacyScriptFile->getFilename());
            $reflectionClass = new ReflectionClass($controllerClassName);
            $methods = $reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC);
            foreach ($methods as $method) {
                $matches = [];
                if (\preg_match('/(.*)Action$/', $method->name, $matches)) {
                    if ($matches[1] === '_before') {
                        continue;
                    }
                    $controller = (\str_replace('Controler', '', $controllerClassName));
                    $routeName = 'app.legacy.' . \strtolower($controller) . '_' . $matches[1];
                    $route = $controller . '/' . $matches[1];
                    $collection->add(
                        $routeName,
                        new Route($route, [
                            '_controller' => 'Pastell\Controller\LegacyController::loadLegacyScript',
                            'requestPath' => '/' . $route,
                            'legacyScript' => $legacyScriptFile->getPathname(),
                        ])
                    );
                }
            }
        }

        return $collection;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $type === 'legacy';
    }
}
