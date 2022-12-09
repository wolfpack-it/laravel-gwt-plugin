<?php

namespace WolfpackIT\LaravelGWTPlugin;

use BadMethodCallException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * @method TestScenario fake(string $facade)
 * @method TestScenario as(Authenticatable $user, ?string $as = null)
 * @method TestScenario throws(string $exception, ?string $message = '')
 * @method TestScenario given(callable $condition, ?string $as = null)
 * @method TestScenario when(callable $action, ?string $as = null)
 * @method TestScenario then(callable $assertion)
 */
abstract class TestCase extends BaseTestCase
{
    public function __call(string $name, array $arguments): TestScenario
    {
        if (! method_exists(TestScenario::class, $name)) {
            throw new BadMethodCallException(
                "Method {$name} does not exists."
            );
        }

        return (new TestScenario($this))->$name(...$arguments);
    }
}
