<?php

namespace DaveJamesMiller\RouteBrowser;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

class RouteListController
{
    public function __invoke(Request $request, Router $router)
    {
        $collection = $router->getRoutes();
        $routes = collect($collection->getRoutes());

        // Filter out excluded routes
        $exclude = config('route-browser.exclude');
        $excludeSelf = config('route-browser.exclude-self');

        $routes = $routes->reject(static function (Route $route) use ($exclude, $excludeSelf) {
            if ($excludeSelf) {
                try {
                    // Checking the controller not the paths to prevent false-positives
                    $controller = $route->getController();

                    if (is_object($controller) && strpos(get_class($controller), 'DaveJamesMiller\RouteBrowser\\') !== false) {
                        return true;
                    }
                } catch (Throwable $_) {
                    // Ignore errors in getController() due to invalid classes
                }
            }

            return Str::is($exclude, Str::start($route->uri, '/'));
        });

        // Based on RouteCollection::matchAgainstRoutes() - sort fallback routes to the end
        [$fallbacks, $routes] = $routes->partition('isFallback');

        /** @var Collection $routes */
        $routes = $routes
            ->merge($fallbacks)
            ->map(static function (Route $route) use ($router, $request) {
                return new RoutePresenter($route, $router, $request);
            })
            ->filter(static function (RoutePresenter $route) use ($request) {
                return $route->matches($request['method'], $request['uri']);
            });

        return view('route-browser::list', compact('routes'));
    }
}
