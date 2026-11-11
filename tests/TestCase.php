<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        return Support\LocalTestSafety::createApplication();
    }

    protected function setUpTraits()
    {
        $traits = class_uses_recursive(static::class);

        foreach ([
            \Illuminate\Foundation\Testing\RefreshDatabase::class,
            \Illuminate\Foundation\Testing\DatabaseMigrations::class,
            \Illuminate\Foundation\Testing\DatabaseTruncation::class,
        ] as $destructiveTrait) {
            if (in_array($destructiveTrait, $traits, true)) {
                throw new \RuntimeException(
                    'Legacy schema baseline protected: use DatabaseTransactions; migration/reset traits are disabled.'
                );
            }
        }

        return parent::setUpTraits();
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            chdir(dirname(__DIR__));
        }
    }
}
