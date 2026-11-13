<?php

// Test-only subprocess. No route invokes this script; the bootstrap verifies restricted DB grants.
require dirname(__DIR__).'/bootstrap.php';

try {
    $app = \Tests\Support\LocalTestSafety::createApplication();
    $input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    $gate = $input['gate'];
    $temporaryRoot = str_replace('\\', '/', sys_get_temp_dir()).'/';
    if (! str_starts_with(str_replace('\\', '/', $gate), $temporaryRoot.'friendie-reset-race-')) {
        throw new RuntimeException('Invalid test barrier.');
    }
    echo "READY\n";
    flush();
    $deadline = microtime(true) + 20;
    while (! is_file($gate)) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Test barrier timed out.');
        }
        usleep(20000);
        clearstatcache(true, $gate);
    }
    $success = $app->make(\App\Services\WebPasswordResetService::class)
        ->reset((string) $input['id'], $input['role'], $input['token'], $input['password']);
    echo $success ? "SUCCESS\n" : "REJECTED\n";
} catch (Throwable) {
    // Never print the exception, input, token or credentials.
    echo "WORKER_FAILED\n";
    exit(1);
}
