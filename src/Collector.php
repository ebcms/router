<?php

namespace Ebcms\Router;

class Collector
{
    protected $parser;
    protected $generator;

    protected $currentGroupPrefix;
    protected $currentMiddlewares;
    protected $currentBinds;

    public function __construct(Parser $parser, Generator $generator)
    {
        $this->parser = $parser;
        $this->generator = $generator;
        $this->currentGroupPrefix = '';
        $this->currentMiddlewares = [];
        $this->currentBinds = [];
    }

    public function addRoute(
        $httpMethod,
        string $route,
        $handler,
        string $name = '',
        array $middlewares = [],
        array $binds = []
    ): self {
        if ($this->currentMiddlewares) {
            array_push($middlewares, ...$this->currentMiddlewares);
        }
        $route = $this->currentGroupPrefix . $route;
        $routeDatas = $this->parser->parse($route);
        foreach ((array) $httpMethod as $method) {
            foreach ($routeDatas as $routeData) {
                $this->generator->addRoute(
                    $method,
                    $routeData,
                    $handler,
                    $name,
                    $middlewares,
                    array_merge($binds, $this->currentBinds)
                );
            }
        }
        return $this;
    }

    public function addGroup(string $prefix, callable $callback): self
    {
        $previousGroupPrefix = $this->currentGroupPrefix;
        $previousMiddlewares = $this->currentMiddlewares;
        $previousBinds = $this->currentBinds;
        $this->currentGroupPrefix = $previousGroupPrefix . $prefix;
        $callback($this);
        $this->currentGroupPrefix = $previousGroupPrefix;
        $this->currentMiddlewares = $previousMiddlewares;
        $this->currentBinds = $previousBinds;
        return $this;
    }

    public function bindMiddlewares(array $middlewares = []): self
    {
        array_push($this->currentMiddlewares, ...$middlewares);
        return $this;
    }

    public function bindParams(array $binds = []): self
    {
        $this->currentBinds = array_merge($this->currentBinds, $binds);
        return $this;
    }

    public function get($route, $handler, string $name = '', array $middlewares = [], array $binds = []): self
    {
        return $this->addRoute('GET', $route, $handler, $name, $middlewares, $binds);
    }

    public function post($route, $handler, string $name = '', array $middlewares = [], array $binds = []): self
    {
        return $this->addRoute('POST', $route, $handler, $name, $middlewares, $binds);
    }

    public function put($route, $handler, string $name = '', array $middlewares = [], array $binds = []): self
    {
        return $this->addRoute('PUT', $route, $handler, $name, $middlewares, $binds);
    }

    public function delete($route, $handler, string $name = '', array $middlewares = [], array $binds = []): self
    {
        return $this->addRoute('DELETE', $route, $handler, $name, $middlewares, $binds);
    }

    public function patch($route, $handler, string $name = '', array $middlewares = [], array $binds = []): self
    {
        return $this->addRoute('PATCH', $route, $handler, $name, $middlewares, $binds);
    }

    public function head($route, $handler, string $name = '', array $middlewares = [], array $binds = []): self
    {
        return $this->addRoute('HEAD', $route, $handler, $name, $middlewares, $binds);
    }

    public function getData(): array
    {
        return $this->generator->getData();
    }
}
