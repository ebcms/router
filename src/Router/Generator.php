<?php

namespace Ebcms\Router;

use LogicException;

class Generator
{
    protected $staticRoutes = [];
    protected $methodToRegexToRoutesMap = [];

    public function addRoute(
        string $httpMethod,
        array $routeData,
        $handler,
        string $name = '',
        array $middlewares = [],
        array $binds = []
    ) {
        if ($this->isStaticRoute($routeData)) {
            $this->addStaticRoute($httpMethod, $routeData, $handler, $name, $middlewares, $binds);
        } else {
            $this->addVariableRoute($httpMethod, $routeData, $handler, $name, $middlewares, $binds);
        }
    }

    public function getData(): array
    {
        if (empty($this->methodToRegexToRoutesMap)) {
            return [$this->staticRoutes, []];
        }

        return [$this->staticRoutes, $this->generateVariableRouteData()];
    }

    protected function getApproxChunkSize(): int
    {
        return 10;
    }

    protected function processChunk(array $regexToRoutesMap): array
    {
        $routeMap = [];
        $regexes = [];
        $numGroups = 0;
        foreach ($regexToRoutesMap as $regex => $route) {
            $numVariables = count($route['variables']);
            $numGroups = max($numGroups, $numVariables);

            $regexes[] = $regex . str_repeat('()', $numGroups - $numVariables);
            $routeMap[$numGroups + 1] = $route;

            ++$numGroups;
        }

        $regex = '~^(?|' . implode('|', $regexes) . ')$~';
        return [
            'regex' => $regex,
            'routeMap' => $routeMap
        ];
    }

    private function generateVariableRouteData(): array
    {
        $data = [];
        foreach ($this->methodToRegexToRoutesMap as $method => $regexToRoutesMap) {
            $chunkSize = $this->computeChunkSize(count($regexToRoutesMap));
            $chunks = array_chunk($regexToRoutesMap, $chunkSize, true);
            $data[$method] = array_map([$this, 'processChunk'], $chunks);
        }
        return $data;
    }

    private function computeChunkSize(int $count): int
    {
        $numParts = max(1, round($count / $this->getApproxChunkSize()));
        return (int) ceil($count / $numParts);
    }

    private function isStaticRoute(array $routeData): bool
    {
        return count($routeData) === 1 && is_string($routeData[0]);
    }

    private function addStaticRoute(
        string $httpMethod,
        array $routeData,
        $handler,
        string $name = '',
        array $middlewares = [],
        array $binds = []
    ) {
        $routeStr = $routeData[0];

        if (isset($this->methodToRegexToRoutesMap[$httpMethod])) {
            foreach ($this->methodToRegexToRoutesMap[$httpMethod] as $route) {
                if (preg_match('~^' . $route['regex'] . '$~', $routeStr)) {
                    throw new LogicException(sprintf(
                        'Static route "%s" is shadowed by previously defined variable route "%s" for method "%s"',
                        $routeStr,
                        $route['regex'],
                        $httpMethod
                    ));
                }
            }
        }

        $this->staticRoutes[$httpMethod][$routeStr] = [
            'routeStr' => $routeStr,
            'routeData' => $routeData,
            'handler' => $handler,
            'name' => $name,
            'middlewares' => $middlewares,
            'binds' => $binds,
        ];
    }

    private function addVariableRoute(
        string $httpMethod,
        array $routeData,
        $handler,
        string $name = '',
        array $middlewares = [],
        array $binds = []
    ) {
        list($regex, $variables) = $this->buildRegexForRoute($routeData);

        $this->methodToRegexToRoutesMap[$httpMethod][$regex] = [
            'handler' => $handler,
            'routeData' => $routeData,
            'regex' => $regex,
            'variables' => $variables,
            'name' => $name,
            'middlewares' => $middlewares,
            'binds' => $binds,
        ];
    }

    private function buildRegexForRoute(array $routeData): array
    {
        $regex = '';
        $variables = [];
        foreach ($routeData as $part) {
            if (is_string($part)) {
                $regex .= preg_quote($part, '~');
                continue;
            }

            list($varName, $regexPart) = $part;

            if (isset($variables[$varName])) {
                throw new LogicException(sprintf(
                    'Cannot use the same placeholder "%s" twice',
                    $varName
                ));
            }

            if ($this->regexHasCapturingGroups($regexPart)) {
                throw new LogicException(sprintf(
                    'Regex "%s" for parameter "%s" contains a capturing group',
                    $regexPart,
                    $varName
                ));
            }

            $variables[$varName] = $varName;
            $regex .= '(' . $regexPart . ')';
        }

        return [$regex, $variables];
    }

    private function regexHasCapturingGroups(string $regex): bool
    {
        if (false === strpos($regex, '(')) {
            // Needs to have at least a ( to contain a capturing group
            return false;
        }

        // Semi-accurate detection for capturing groups
        return (bool) preg_match(
            '~
                (?:
                    \(\?\(
                  | \[ [^\]\\\\]* (?: \\\\ . [^\]\\\\]* )* \]
                  | \\\\ .
                ) (*SKIP)(*FAIL) |
                \(
                (?!
                    \? (?! <(?![!=]) | P< | \' )
                  | \*
                )
            ~x',
            $regex
        );
    }
}
