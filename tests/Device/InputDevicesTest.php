<?php

declare(strict_types=1);

use Femus\Adapter\Fake\FakeBoard;
use Femus\Contracts\PinMode;
use Femus\Runtime\StreamSelectLoop;

// Set the pull-up with write() BEFORE creating Button: write() changes state
// without firing an event, otherwise the artificial edge would consume the
// debounce window before the press.

it('button: a press with active-low means LOW on the pin', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $board->digitalPin(2, PinMode::InputPullUp)->write(true); // pull-up: not pressed
    $button = $board->button(2);
    $presses = 0;
    $button->onPress(function () use (&$presses) { $presses++; });
    $board->simulateInput(2, false); // pressed
    expect($presses)->toBe(1)->and($button->isPressed())->toBeTrue();
});

it('button: bounce within the debounce window is suppressed', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $board->digitalPin(2, PinMode::InputPullUp)->write(true);
    $button = $board->button(2, debounceSeconds: 0.05);
    $presses = 0;
    $button->onPress(function () use (&$presses) { $presses++; });
    $board->simulateInput(2, false); // press
    $board->simulateInput(2, true);  // bounce — instant release
    $board->simulateInput(2, false); // bounce — instant re-press
    expect($presses)->toBe(1);
});

it('button: waitForPress returns false on timeout', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $button = $board->button(2);
    expect($button->waitForPress(timeoutSeconds: 0.05))->toBeFalse();
});

it('button: waitForPress catches a scheduled press', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $board->digitalPin(2, PinMode::InputPullUp)->write(true);
    $button = $board->button(2);
    $board->scheduleInput(0.02, 2, false);
    expect($button->waitForPress(timeoutSeconds: 1.0))->toBeTrue();
});

it('motion sensor: motion and idle', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $pir = $board->motionSensor(4);
    $events = [];
    $pir->onMotion(function () use (&$events) { $events[] = 'motion'; });
    $pir->onIdle(function () use (&$events) { $events[] = 'idle'; });
    $board->simulateInput(4, true);
    $board->simulateInput(4, false);
    expect($events)->toBe(['motion', 'idle'])
        ->and($pir->isActive())->toBeFalse();
});

it('button: waitForPress removes the temporary listener on timeout', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $button = $board->button(2);

    // First call with timeout
    $result1 = $button->waitForPress(timeoutSeconds: 0.01);
    expect($result1)->toBeFalse();

    // Second call with timeout
    $result2 = $button->waitForPress(timeoutSeconds: 0.01);
    expect($result2)->toBeFalse();

    // Verify via reflection that pressListeners is empty
    $reflection = new ReflectionClass($button);
    $property = $reflection->getProperty('pressListeners');
    $property->setAccessible(true);
    expect(count($property->getValue($button)))->toBe(0);
});

it('button: waitForPress removes the temporary listener on success', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $board->digitalPin(2, PinMode::InputPullUp)->write(true);
    $button = $board->button(2);

    $board->scheduleInput(0.01, 2, false);
    $result = $button->waitForPress(timeoutSeconds: 0.5);
    expect($result)->toBeTrue();

    // Verify via reflection that pressListeners is empty
    $reflection = new ReflectionClass($button);
    $property = $reflection->getProperty('pressListeners');
    $property->setAccessible(true);
    expect(count($property->getValue($button)))->toBe(0);
});

it('motion sensor: waitForMotion removes the temporary listener on timeout', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $pir = $board->motionSensor(4);

    // First call with timeout
    $result1 = $pir->waitForMotion(timeoutSeconds: 0.01);
    expect($result1)->toBeFalse();

    // Second call with timeout
    $result2 = $pir->waitForMotion(timeoutSeconds: 0.01);
    expect($result2)->toBeFalse();

    // Verify via reflection that motionListeners is empty
    $reflection = new ReflectionClass($pir);
    $property = $reflection->getProperty('motionListeners');
    $property->setAccessible(true);
    expect(count($property->getValue($pir)))->toBe(0);
});

it('button: onRelease fires after the debounce window', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $board->digitalPin(2, PinMode::InputPullUp)->write(true); // initial state: not pressed
    $button = $board->button(2, debounceSeconds: 0.0);
    $presses = 0;
    $releases = 0;
    $button->onPress(function () use (&$presses) { $presses++; });
    $button->onRelease(function () use (&$releases) { $releases++; });
    $board->simulateInput(2, false); // pressed (LOW = pressed for active-low)
    $board->simulateInput(2, true);  // released
    expect($presses)->toBe(1)->and($releases)->toBe(1);
});

it('motion sensor: waitForMotion removes the temporary listener on success', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $pir = $board->motionSensor(4);

    $board->scheduleInput(0.01, 4, true);
    $result = $pir->waitForMotion(timeoutSeconds: 0.5);
    expect($result)->toBeTrue();

    // Verify via reflection that motionListeners is empty
    $reflection = new ReflectionClass($pir);
    $property = $reflection->getProperty('motionListeners');
    $property->setAccessible(true);
    expect(count($property->getValue($pir)))->toBe(0);
});
