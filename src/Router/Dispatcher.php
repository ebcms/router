<?php

namespace Ebcms\Router;

class Dispatcher
{
    const NOT_FOUND = 0;
    const FOUND = 1;
    const METHOD_NOT_ALLOWED = 2;

    protected $generator;

    public function __construct(Generator $generator)
    {
        $this->generator = $generator;
    }

    public function dispatch(string $httpMethod, string $uri): array
    {
        list($staticRouteMap, $variableRouteData) = $this->generator->getData();

        if (isset($staticRouteMap[$httpMethod][$uri])) {
            $staticRouteData = $staticRouteMap[$httpMethod][$uri];
            return [self::FOUND, $staticRouteData['handler'], [], $staticRouteData['middlewares'], $staticRouteData['binds']];
        }

        $varRouteData = $variableRouteData;
        if (isset($varRouteData[$httpMethod])) {
            $result = $this->dispatchVariableRoute($varRouteData[$httpMethod], $uri);
            if ($result[0] === self::FOUND) {
                return $result;
            }
        }

        if ($httpMethod === 'HEAD') {
            if (isset($staticRouteMap['GET'][$uri])) {
                $staticRouteData = $staticRouteMap['GET'][$uri];
                return [self::FOUND, $staticRouteData['handler'], [], $staticRouteData['middlewares'], $staticRouteData['binds']];
            }
            if (isset($varRouteData['GET'])) {
                $result = $this->dispatchVariableRoute($varRouteData['GET'], $uri);
                if ($result[0] === self::FOUND) {
                    return $result;
                }
            }
        }

        if (isset($staticRouteMap['*'][$uri])) {
            $staticRouteData = $staticRouteMap['*'][$uri];
            return [self::FOUND, $staticRouteData['handler'], [], $staticRouteData['middlewares'], $staticRouteData['binds']];
        }
        if (isset($varRouteData['*'])) {
            $result = $this->dispatchVariableRoute($varRouteData['*'], $uri);
            if ($result[0] === self::FOUND) {
                return $result;
            }
        }

        $allowedMethods = [];

        foreach ($staticRouteMap as $method => $uriMap) {
            if ($method !== $httpMethod && isset($uriMap[$uri])) {
                $allowedMethods[] = $method;
            }
        }

        foreach ($varRouteData as $method => $routeData) {
            if ($method === $httpMethod) {
                continue;
            }

            $result = $this->dispatchVariableRoute($routeData, $uri);
            if ($result[0] === self::FOUND) {
                $allowedMethods[] = $method;
            }
        }

        if ($allowedMethods) {
            return [self::METHOD_NOT_ALLOWED, $allowedMethods];
        }

        return [self::NOT_FOUND];
    }

    protected function dispatchVariableRoute(array $routeData, string $uri): array
    {
        foreach ($routeData as $data) {
            if (!preg_match($data['regex'], $uri, $matches)) {
                continue;
            }
            $route = $data['routeMap'][count($matches)];
            $vars = [];
            $i = 0;
            foreach ($route['variables'] as $varName) {
                $vars[$varName] = $matches[++$i];
            }
            return [self::FOUND, $route['handler'], $vars, $route['middlewares'], $route['binds']];
        }

        return [self::NOT_FOUND];
    }
}
