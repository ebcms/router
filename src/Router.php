<?php

namespace Ebcms\Router;

class Router
{

    /**
     * @var Builder $builder
     * @var Dispatcher $dispatcher
     * @var Collector $collector
     */
    protected $builder;
    protected $collector;
    protected $dispatcher;

    public function __construct(
        Dispatcher $dispatcher,
        Collector $collector,
        Builder $builder
    ) {
        $this->builder = $builder;
        $this->collector = $collector;
        $this->dispatcher = $dispatcher;
    }

    public function getCollector(): Collector
    {
        return $this->collector;
    }

    public function getDispatcher(): Dispatcher
    {
        return $this->dispatcher;
    }

    public function getBuilder(): Builder
    {
        return $this->builder;
    }

    public function buildUrl(
        string $name,
        array $param = [],
        string $method = 'GET'
    ): string {
        return $this->builder->build(
            $name,
            $param,
            $method
        );
    }
}
