<?php

namespace WolfpackIT\LaravelGWTPlugin;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Arr;
use ReflectionFunction;
use ReflectionParameter;

class TestScenario
{
    public const DEFAULT_AUTH_PROVIDER = 'Laravel\Sanctum';

    protected array $conditions = [];

    protected array $actions = [];

    protected array $params = [];

    public function __construct(
        protected \PHPUnit\Framework\TestCase $testCase
    ) {
    }

    public function fake(string $facade): TestScenario
    {
        if (! method_exists($facade, 'fake')) {
            throw new \BadMethodCallException(
                "Class {$facade} does not contain the method fake."
            );
        }

        call_user_func([$facade, 'fake']);

        return $this;
    }

    public function as(
        Authenticatable $user,
        ?string $injectAs = 'user',
        string $authProvider = self::DEFAULT_AUTH_PROVIDER
    ): TestScenario {
        if (! method_exists($authProvider, 'actingAs')) {
            throw new \BadMethodCallException(
                "Class {$authProvider} does not contain the method actingAs."
            );
        }

        // Pass on a closure as a given condition which execute the actingAs method on the auth provider
        return $this->given(
            fn (): Authenticatable => call_user_func([$authProvider, 'actingAs'], $user),
            $injectAs
        );
    }

    public function given(mixed $condition, ?string $as = null): self
    {
        if (is_callable($condition)) {
            $this->append($this->conditions, $condition, $as);

            return $this;
        }

        $this->params[$as ?: get_debug_type($condition)] = $condition;

        return $this;
    }

    public function when(callable $action, ?string $as = 'response'): self
    {
        $this->append($this->actions, $action, $as);

        return $this;
    }

    public function then(callable $assertion): self
    {
        $this->params = $this->evaluate($this->conditions, $this->params);
        $this->params = $this->evaluate($this->actions, $this->params);

        $this->evaluate($assertion, $this->params);

        return $this;
    }

    public function throws(string $exception, ?string $message = ''): self
    {
        $this->testCase->expectException($exception);

        if ($message) {
            $this->testCase->expectExceptionMessage($message);
        }

        return $this;
    }

    protected function append(array &$callbacks, callable $callback, ?string $as): void
    {
        if ($as) {
            $callbacks[$as] = $callback;

            return;
        }

        $callbacks[] = $callback;
    }

    protected function evaluate(array|callable &$callbacks, array $params = []): array
    {
        $callbacks = Arr::wrap($callbacks);

        if (empty($callbacks)) {
            return $params;
        }

        // Evaluate each callback
        foreach (Arr::wrap($callbacks) as $key => $callback) {
            // Run callback with possible auto-injected params, this can potentially
            // throw a BadMethodException when missing params
            $result = call_user_func($callback, ...$this->evaluateParamsToInject($callback, $params));

            if ($result !== null) {
                // If key is empty, use the debug type as key name
                $key ??= get_debug_type($result);

                $params[$key] = $result;
            }
        }

        // Clear the callback passed by reference, so they only get executed once.
        $callbacks = [];

        return $params;
    }

    protected function evaluateParamsToInject(callable $callback, array $params): array
    {
        try {
            $reflection = new ReflectionFunction($callback);
            $arguments = $reflection->getParameters();

            $injections = [];

            krsort($params);

            // Foreach argument of the callable, try to find matching params
            foreach ($arguments as $argument) {
                if ($toInject = $this->findMatchingParam($argument, $params)) {
                    $injections[] = $toInject;
                }
            }

            return $injections;
        } catch (\ReflectionException $e) {
            return [];
        }
    }

    protected function findMatchingParam(ReflectionParameter $argument, array $params): mixed
    {
        $toInject = null;

        foreach ($params as $key => $param) {
            // If the key matches the argument name, use param value as auto-injection
            if ($key === $argument->getName()) {
                return $param;
            }

            // If the type matches the argument type, use param value as auto-injection
            if (get_debug_type($param) === $argument->getType()->getName()) {
                $toInject = $param;
            }
        }

        return $toInject;
    }
}
