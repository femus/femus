# Event Loop

Everything in femus is event-driven: device callbacks, timers and stream watching run
in one `stream_select()`-based loop. No extensions, no external dependencies.

```php
$loop = $board->loop();

$loop->addTimer(5.0, fn () => print("five seconds passed\n"));
$loop->addPeriodicTimer(1.0, fn () => print("tick\n"));

$board->run();     // blocks and dispatches events
$board->stop();    // call from any callback to exit run()
```

## Watching streams

The loop can watch any PHP stream — this is how the chat examples read the keyboard
while radio messages keep arriving:

```php
stream_set_blocking(STDIN, false);

$board->loop()->addReadStream(STDIN, function () use ($radio) {
    $line = trim((string) fgets(STDIN));
    if ($line !== '') {
        $radio->send(2, $line);
    }
});

$board->run();
```

## Blocking helpers

Sometimes a script is simpler without callbacks. Input devices offer `waitFor*`
helpers that spin the loop internally until an event or a timeout:

```php
if ($board->button(2)->waitForPress(timeoutSeconds: 10)) {
    echo "pressed!\n";
}

$board->motionSensor(4)->waitForMotion();
$board->analogSensor(0)->waitForValueAbove(0.8, timeoutSeconds: 30);
```

## One loop, many boards

Pass a shared loop into each board — see [Board & Ports](/guide/board#multiple-boards-one-process).
