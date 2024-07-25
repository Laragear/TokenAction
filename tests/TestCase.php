<?php

namespace Tests;

use Laragear\TokenAction\Facades\Token;
use Laragear\TokenAction\TokenActionServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [TokenActionServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return [Token::class];
    }
}
