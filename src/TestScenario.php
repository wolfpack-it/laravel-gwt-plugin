<?php

namespace WolfpackIT\LaravelGWTPlugin;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Arr;
use ReflectionFunction;
use ReflectionParameter;

class TestScenario
{
    public const PASSPORT_AUTH_PROVIDER = 'Laravel\Passport\Passport';
    public const SANCTUM_AUTH_PROVIDER = 'Laravel\Sanctum\Sanctum';

    protected static string $authProvider = self::SANCTUM_AUTH_PROVIDER;
    protected static ?string $guard = null;

    protected array $conditions = [];
    protected array $actions = [];
    protected array $params = [];

    public function __construct(
        protected \PHPUnit\Framework\TestCase $testCase
    ) {
    }

    public static function setAuthProvider(string $authProvider): void
    {
        if (! method_exists($authProvider, 'actingAs')) {
            throw new \BadMethodCallException(
                "Class {$authProvider} does not contain the method actingAs."
            );
        }

        static::$authProvider = $authProvider;
    }

    public static function setAuthGuard(string $guard): void
    {
        if (!config("auth.guards.{$guard}")) {
            throw new \BadMethodCallException(
                "There is no guard for {$guard} defined."
            );
        }

        static::$guard = $guard;
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
        ?string $injectAs = null
    ): TestScenario {
        if (! method_exists(static::$authProvider, 'actingAs')) {
            throw new \BadMethodCallException(sprintf(
                "Class %s does not contain the method actingAs.", static::$authProvider
            ));
        }

        // Pass on a closure as a given condition which execute the actingAs method on the auth provider
        return $this->given(
            fn (): Authenticatable => call_user_func([static::$authProvider, 'actingAs'], $user, [], static::$guard),
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

            // Get the argument type(s)
            $type = $argument->getType();

            // Prepare as array to support union types
            $types = method_exists($type, 'getTypes') ? $type->getTypes() : [$type];

            // If the type matches any of the argument type (first wins), use param value as auto-injection
            foreach ($types as $type) {
                if (get_debug_type($param) === $type->getName()) {
                    $toInject = $param;
                    break;
                }
            }
        }

        return $toInject;
    }
}
