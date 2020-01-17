<?php

namespace DaveJamesMiller\RouteBrowser;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\MiddlewareNameResolver;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCompiler;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionException;
use ReflectionFunction;

class RoutePresenter
{
    private $route;
    private $router;
    private $request;

    private $action;
    private $matchedParameters = [];
    private $middlewares;

    public function __construct(Route $route, Router $router, Request $request)
    {
        $this->route = $route;
        $this->router = $router;
        $this->request = $request;
    }

    public function matches($method, $uri)
    {
        // Note: The following rules only filter on the parts of the URL that are specified.
        // e.g. If you enter "/path/to/action", it will ignore any scheme or domain filters set on the route.
        // This is why we can't just use $this->route->matches() to filter them.

        // Method
        if ($method && !in_array($method, $this->route->methods(), true)) {
            return false;
        }

        // URI
        if (!$uri) {
            return true;
        }

        $uriParts = parse_url($uri);

        // URI Scheme
        if (!empty($uriParts['scheme'])) {
            if ($uriParts['scheme'] === 'http' && $this->route->httpsOnly()) {
                return false;
            }

            if ($uriParts['scheme'] === 'https' && $this->route->httpOnly()) {
                return false;
            }
        }

        // URI Host
        $compiled = (new RouteCompiler($this->route))->compile();

        if (!empty($uriParts['host']) && ($regex = $compiled->getHostRegex())) {
            if (!preg_match($regex, $uriParts['host'], $matches)) {
                return false;
            }

            // Record the matched parameters so we can display them in the view
            $this->bindParameters($matches);

        }

        // URI Path
        if (!empty($uriParts['path'])) {
            $path = Str::start(rawurldecode($uriParts['path']), '/');

            // If Laravel is installed in a subdirectory, remove that prefix
            // This allows users to just copy-paste the URL without modifying it
            // But it still accepts just the relative path, e.g. "/routes"
            $base = rawurldecode($this->request->getBasePath());
            if ($base && strpos($path, $base) === 0) {
                $path = substr($path, strlen($base));
            }

            if (Str::contains($path, '*')) {

                // Wildcard match
                if (!Str::is($path, Str::start($this->route->uri(), '/'))) {
                    return false;
                }

            } else {

                // Path match
                if (!preg_match($compiled->getRegex(), $path, $matches)) {
                    return false;
                }

                // Record the matched parameters so we can display them in the view
                $this->bindParameters($matches);

            }
        }

        return true;
    }

    private function bindParameters(array $matches)
    {
        foreach ($this->route->parameterNames() as $name) {
            if (array_key_exists($name, $matches)) {
                $this->matchedParameters[$name] = $matches[$name];
            }
        }
    }

    public function method()
    {
        $methods = $this->route->methods();

        // If all methods are allowed, return 'null' instead
        if ($methods === Router::$verbs) {
            return null;
        }

        // Remove HEAD because it's always included with GET and clutters up the display
        return str_replace('GET, HEAD', 'GET', implode(', ', $methods));
    }

    public function allMethods()
    {
        return implode(', ', $this->route->methods());
    }

    public function scheme()
    {
        if ($this->route->httpsOnly()) {
            return 'https://';
        }

        if ($this->route->httpOnly()) {
            return 'http://';
        }

        return null;
    }

    public function domain()
    {
        return $this->route->domain();
    }

    public function path()
    {
        return Str::start($this->route->uri(), '/');
    }

    public function name()
    {
        return $this->route->getName();
    }

    public function action()
    {
        if (!$this->action) {
            $this->action = new CallablePresenter($this->route->getAction('uses'));
        }

        return $this->action;
    }

    public function view()
    {
        if ($this->route->getAction('controller') !== '\Illuminate\Routing\ViewController') {
            return null;
        }

        return $this->route->defaults['view'] ?? null;
    }

    public function link()
    {
        // Only link if there are no parameters and it's a GET request
        if ($this->route->parameterNames() || !in_array('GET', $this->route->methods(), true)) {
            return null;
        }

        if ($this->route->httpsOnly()) {
            $secure = true;
        } elseif ($this->route->httpOnly()) {
            $secure = false;
        } else {
            $secure = null;
        }

        // Can't use route() because it only accepts a name not a route object, and not all routes are named
        // Unfortunately there doesn't seem to be any way to generate a URL from a route object - the methods are protected
        // This works for common cases, but there may be edge cases it fails for...
        return url($this->route->uri(), [], $secure);
    }

    public function type()
    {
        if (!empty($this->route->isFallback)) {
            return 'Fallback';
        }

        return 'Standard';
    }

    public function parameterPatterns()
    {
        $patterns = [];

        foreach ($this->route->parameterNames() as $name) {
            $patterns[$name] = $this->route->wheres[$name] ?? null;
        }

        return $patterns;
    }

    public function matchedParameters()
    {
        return $this->matchedParameters;
    }

    public function defaultParameters()
    {
        return $this->route->defaults;
    }

    public function middleware(): Collection
    {
        if ($this->middlewares !== null) {
            return $this->middlewares;
        }

        // This is somewhat complex, and may not work in all cases, but it's the simplest way I can find to display
        // where the middleware was added and the original group / short name that was used, without re-implementing the
        // whole (rather complex) middleware gathering process.

        // Get the raw middleware lists and record whether each are added in the Route, Controller or both
        $added_ins = [];

        foreach ((array)$this->route->middleware() as $middleware) {
            $added_ins[$this->middlewareKey($middleware)][] = 'Route';
        }

        try {
            foreach ($this->route->controllerMiddleware() as $middleware) {
                $added_ins[$this->middlewareKey($middleware)][] = 'Controller';
            }
        } catch (ReflectionException $e) {
            // If the controller doesn't exist, this fails - but there's nothing we can do except catch it
        }

        // Resolve groups (e.g. 'web') and short names (e.g. 'auth') into classes and record where they came from
        $originals = [];

        try {
            $all = $this->route->gatherMiddleware();
        } catch (ReflectionException $e) {
            $all = [];
            // If the controller doesn't exist, this fails - but there's nothing we can do except catch it
        }

        foreach ($all as $original) {
            foreach ((array)MiddlewareNameResolver::resolve($original, $this->router->getMiddleware(), $this->router->getMiddlewareGroups()) as $resolved) {
                if ($resolved !== $original) {
                    $originals[$resolved][] = [
                        'original' => $original,
                        'added_ins' => $added_ins[$original],
                    ];
                }
            }
        }

        // Get the de-duplicated, sorted middlewares used for this route to display
        $middlewares = [];

        foreach ($this->router->gatherRouteMiddleware($this->route) as $middleware) {

            $key = $this->middlewareKey($middleware);

            // Closure
            if ($middleware instanceof Closure) {

                $middlewares[] = (object)[
                    'handler' => new CallablePresenter($middleware),
                    'parameters' => [],
                    'addedIn' => implode(', ', $added_ins[$key]) ?? null,
                    'original' => null,
                    'terminates' => false,
                ];

                continue;
            }

            // Class
            [$name, $parameters] = array_pad(explode(':', $middleware, 2), 2, []);

            if (is_string($parameters)) {
                $parameters = explode(',', $parameters);
            }

            $original = collect($originals[$middleware] ?? []);

            $added_in = implode(', ', array_unique(array_merge(
                $added_ins[$key] ?? [],
                ...$original->pluck('added_ins')->all()
            )));

            $middlewares[] = (object)[
                'handler' => new CallablePresenter($name, 'handle'),
                'parameters' => implode(', ', array_map(function ($param) {
                    return var_export($param, true);
                }, $parameters)),
                'addedIn' => $added_in,
                'original' => $original->pluck('original')->implode('; '),
                'terminates' => method_exists($name, 'terminate'),
                'terminator' => method_exists($name, 'terminate') ? new CallablePresenter($name, 'terminate') : null,
            ];
        }

        return $this->middlewares = collect($middlewares);
    }

    private function closureToString(Closure $closure)
    {
        $reflection = new ReflectionFunction($closure);

        return 'Closure in ' . $this->relativePath($reflection->getFileName()) . ':' . $reflection->getStartLine();
    }

    private function middlewareKey($middleware)
    {
        if ($middleware instanceof Closure) {
            return spl_object_hash($middleware);
        }

        return $middleware;
    }

    private function relativePath($path)
    {
        $root = base_path() . DIRECTORY_SEPARATOR;

        if (Str::startsWith($path, $root)) {
            return substr($path, strlen($root));
        }

        return $path;
    }
}
