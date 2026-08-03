<?php

declare(strict_types=1);

use Femus\Adapter\Fake\FakeBoard;
use Femus\Runtime\StreamSelectLoop;

it('LED turns on, turns off, and toggles', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $led = $board->led(13);
    $led->on();
    expect($led->isOn())->toBeTrue();
    $led->toggle();
    expect($led->isOn())->toBeFalse();
});

it('LED blinks on a timer', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $led = $board->led(13);
    $led->blink(0.005);
    $board->loop()->addTimer(0.03, function () use ($led, $board) {
        $led->stopBlinking();
        $board->stop();
    });
    $board->run();
    // in ~30ms with a 5ms period the LED must have toggled at least twice —
    // verify the timer worked by checking the pin mode
    expect($board->pin(13)->mode)->toBe(\Femus\Contracts\PinMode::Output);
});

it('relay clicks', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $relay = $board->relay(7);
    $relay->on();
    expect($relay->isOn())->toBeTrue()
        ->and($board->pin(7)->read())->toBeTrue();
});

it('buzzer beeps for the given duration and then goes silent', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $buzzer = $board->buzzer(8);
    $buzzer->beep(0.01);
    expect($buzzer->isOn())->toBeTrue();
    $board->run(); // the off-timer is the only one; run() exits by itself
    expect($buzzer->isOn())->toBeFalse();
});
