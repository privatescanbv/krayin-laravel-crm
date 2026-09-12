<?php

test('the graph mail sync runs on the cron expression from config', function () {
    // The point of the config key is that the environment decides the frequency,
    // so assert the schedule follows config rather than a hardcoded expression.
    $expression = config('schedule.emails_sync_graph_cron');

    expect($expression)->toBeString()->not->toBeEmpty();

    $this->artisan('schedule:list', ['--json' => true])
        ->expectsOutputToContain('"expression":"'.$expression.'","command":"php artisan emails:sync-graph"')
        ->assertSuccessful();
});
