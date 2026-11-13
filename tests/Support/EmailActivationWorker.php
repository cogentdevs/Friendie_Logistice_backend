<?php

require dirname(__DIR__).'/bootstrap.php';
try {
    $app = \Tests\Support\LocalTestSafety::createApplication();
    config(['app.url' => 'https://activation.example.test']);
    $input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    $gate = $input['gate'];
    if (! str_starts_with(str_replace('\\', '/', $gate), str_replace('\\', '/', sys_get_temp_dir()).'/friendie-activation-race-')) {
        throw new RuntimeException('Invalid test barrier.');
    }
    echo "READY\n";
    flush();
    $deadline = microtime(true) + 20;
    while (! is_file($gate)) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Test barrier timeout.');
        }
        usleep(20000);
        clearstatcache(true, $gate);
    }
    $state = $app->make(\App\Services\EmailActivationService::class)
        ->verify(\Illuminate\Http\Request::create($input['url'], 'POST'), (string) $input['id'], $input['slug']);
    echo $state."\n";
} catch (Throwable) {
    echo "WORKER_FAILED\n"; // Never print signed URLs, credentials or exceptions.
    exit(1);
}
