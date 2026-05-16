<?php

declare(strict_types=1);

namespace App\Services;

use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionNamedType;
use Exception;

class Container implements ContainerInterface
{
    private array $bindings = [];
    private array $singletons = [];

    /**
     * Bind an abstract to a concrete implementation.
     */
    public function bind(string $abstract, callable $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    /**
     * Bind a singleton.
     */
    public function singleton(string $abstract, callable $concrete): void
    {
        $this->singletons[$abstract] = $concrete;
    }

    /**
     * Get an instance of the abstract.
     */
    public function get(string $abstract)
    {
        if (isset($this->singletons[$abstract])) {
            if (is_callable($this->singletons[$abstract])) {
                $this->singletons[$abstract] = $this->singletons[$abstract]($this);
            }
            return $this->singletons[$abstract];
        }

        if (isset($this->bindings[$abstract])) {
            return $this->bindings[$abstract]($this);
        }

        // Auto-resolve
        return $this->resolve($abstract);
    }

    /**
     * Check if the container has a binding or can resolve.
     */
    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->singletons[$abstract]) || class_exists($abstract);
    }

    /**
     * Resolve a class automatically using reflection.
     */
    private function resolve(string $abstract)
    {
        if (!class_exists($abstract)) {
            throw new Exception("Class {$abstract} does not exist.");
        }

        $reflection = new ReflectionClass($abstract);
        $constructor = $reflection->getConstructor();

        if (!$constructor) {
            return new $abstract();
        }

        $params = $constructor->getParameters();
        $dependencies = [];

        foreach ($params as $param) {
            $type = $param->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $dependencies[] = $this->get($type->getName());
            } elseif ($param->isDefaultValueAvailable()) {
                $dependencies[] = $param->getDefaultValue();
            } else {
                throw new Exception("Cannot resolve dependency for parameter {$param->getName()} in {$abstract}");
            }
        }

        return $reflection->newInstanceArgs($dependencies);
    }
}