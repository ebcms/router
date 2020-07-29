<?php

namespace Ebcms\Router;

class Builder
{
    protected $generator;

    protected $staticRouteMap = [];
    protected $variableRouteData = [];

    public function __construct(Generator $generator)
    {
        $this->generator = $generator;
    }

    public function build(string $name, array $param = [], string $method = 'GET'): ?string
    {
        $this->init();

        if (isset($this->staticRouteMap[$name])) {
            foreach ($this->staticRouteMap[$name] as $route) {
                if ($route['method'] == '*' || $route['method'] == $method) {
                    if (!$this->checkParams($param, $route['binds'])) {
                        continue;
                    }
                    if ($tmp = array_diff_key($param, $route['binds'])) {
                        return $route['routeStr'] . '?' . http_build_query($tmp);
                    }
                    return $route['routeStr'];
                }
            }
        }

        $build = function (array $routeData, $param) {
            $uri = '';
            foreach ($routeData as $part) {
                if (is_array($part)) {
                    if (isset($param[$part[0]]) && preg_match('~^' . $part[1] . '$~', (string) $param[$part[0]])) {
                        $uri .= $param[$part[0]];
                        unset($param[$part[0]]);
                        continue;
                    } else {
                        return false;
                    }
                } else {
                    $uri .= $part;
                }
            }
            if ($param) {
                return $uri . '?' . http_build_query($param);
            }
            return $uri;
        };

        if (isset($this->variableRouteData[$name])) {
            foreach ($this->variableRouteData[$name] as $route) {
                if ($route['method'] == '*' || $route['method'] == $method) {
                    if (!$this->checkParams($param, $route['binds'])) {
                        continue;
                    }
                    if (false !== $uri = $build($route['routeData'], array_diff_key($param, $route['binds']))) {
                        return $uri;
                    }
                }
            }
        }

        return $this->getWebRoot() . $name . ($param ? '?' . http_build_query($param) : '');
    }

    private function init()
    {
        static $init;
        if (!$init) {
            $init = 1;

            list($staticRouteMap, $variableRouteData) = $this->generator->getData();
            foreach ($staticRouteMap as $method => $routes) {
                foreach ($routes as $route) {
                    if ($route['name']) {
                        $route['method'] = $method;
                        $this->staticRouteMap[$route['name']][] = $route;
                    }
                }
            }

            foreach ($variableRouteData as $method => $chunks) {
                foreach ($chunks as $chunk) {
                    foreach ($chunk['routeMap'] as $route) {
                        if ($route['name']) {
                            $route['method'] = $method;
                            $this->variableRouteData[$route['name']][] = $route;
                        }
                    }
                }
            }
        }
    }

    private function getWebRoot()
    {
        static $web_root;
        if (is_null($web_root)) {
            $web_root = (function (): string {
                $script_name = '/' . implode('/', array_filter(explode('/', $_SERVER['SCRIPT_NAME'])));
                $request_uri = parse_url('/' . implode('/', array_filter(explode('/', $_SERVER['REQUEST_URI']))), PHP_URL_PATH);
                if (strpos($request_uri, $script_name) === 0) {
                    return $script_name;
                } else {
                    return strlen(dirname($script_name)) > 1 ? dirname($script_name) : '';
                }
            })();
        }
        return $web_root;
    }

    private function checkParams($param, $binds)
    {
        foreach ($param as $key => $value) {
            if (isset($binds[$key]) && $binds[$key] != $value) {
                return false;
            }
        }
        return true;
    }
}
