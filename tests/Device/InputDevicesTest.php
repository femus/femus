<?php

declare(strict_types=1);

use Femus\Adapter\Fake\FakeBoard;
use Femus\Contracts\PinMode;
use Femus\Runtime\StreamSelectLoop;

// Подтяжку выставляем write()-ом ДО создания Button: write() меняет состояние
// без события, иначе искусственный фронт съест debounce-окно перед нажатием.

it('button: нажатие при active-low это LOW на пине', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $board->digitalPin(2, PinMode::InputPullUp)->write(true); // подтяжка: не нажата
    $button = $board->button(2);
    $presses = 0;
    $button->onPress(function () use (&$presses) { $presses++; });
    $board->simulateInput(2, false); // нажали
    expect($presses)->toBe(1)->and($button->isPressed())->toBeTrue();
});

it('button: дребезг в пределах debounce гасится', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $board->digitalPin(2, PinMode::InputPullUp)->write(true);
    $button = $board->button(2, debounceSeconds: 0.05);
    $presses = 0;
    $button->onPress(function () use (&$presses) { $presses++; });
    $board->simulateInput(2, false); // нажатие
    $board->simulateInput(2, true);  // дребезг — мгновенный отскок
    $board->simulateInput(2, false); // дребезг — мгновенный возврат
    expect($presses)->toBe(1);
});

it('button: waitForPress возвращает false по таймауту', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $button = $board->button(2);
    expect($button->waitForPress(timeoutSeconds: 0.05))->toBeFalse();
});

it('button: waitForPress ловит запланированное нажатие', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $board->digitalPin(2, PinMode::InputPullUp)->write(true);
    $button = $board->button(2);
    $board->scheduleInput(0.02, 2, false);
    expect($button->waitForPress(timeoutSeconds: 1.0))->toBeTrue();
});

it('motion sensor: движение и покой', function () {
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

it('button: waitForPress удаляет временный слушатель при таймауте', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $button = $board->button(2);

    // Первый вызов с таймаутом
    $result1 = $button->waitForPress(timeoutSeconds: 0.01);
    expect($result1)->toBeFalse();

    // Второй вызов с таймаутом
    $result2 = $button->waitForPress(timeoutSeconds: 0.01);
    expect($result2)->toBeFalse();

    // Проверяем через рефлексию что pressListeners пустой
    $reflection = new ReflectionClass($button);
    $property = $reflection->getProperty('pressListeners');
    $property->setAccessible(true);
    expect(count($property->getValue($button)))->toBe(0);
});

it('button: waitForPress удаляет временный слушатель при успехе', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $board->digitalPin(2, PinMode::InputPullUp)->write(true);
    $button = $board->button(2);

    $board->scheduleInput(0.01, 2, false);
    $result = $button->waitForPress(timeoutSeconds: 0.5);
    expect($result)->toBeTrue();

    // Проверяем через рефлексию что pressListeners пустой
    $reflection = new ReflectionClass($button);
    $property = $reflection->getProperty('pressListeners');
    $property->setAccessible(true);
    expect(count($property->getValue($button)))->toBe(0);
});

it('motion sensor: waitForMotion удаляет временный слушатель при таймауте', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $pir = $board->motionSensor(4);

    // Первый вызов с таймаутом
    $result1 = $pir->waitForMotion(timeoutSeconds: 0.01);
    expect($result1)->toBeFalse();

    // Второй вызов с таймаутом
    $result2 = $pir->waitForMotion(timeoutSeconds: 0.01);
    expect($result2)->toBeFalse();

    // Проверяем через рефлексию что motionListeners пустой
    $reflection = new ReflectionClass($pir);
    $property = $reflection->getProperty('motionListeners');
    $property->setAccessible(true);
    expect(count($property->getValue($pir)))->toBe(0);
});

it('motion sensor: waitForMotion удаляет временный слушатель при успехе', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $pir = $board->motionSensor(4);

    $board->scheduleInput(0.01, 4, true);
    $result = $pir->waitForMotion(timeoutSeconds: 0.5);
    expect($result)->toBeTrue();

    // Проверяем через рефлексию что motionListeners пустой
    $reflection = new ReflectionClass($pir);
    $property = $reflection->getProperty('motionListeners');
    $property->setAccessible(true);
    expect(count($property->getValue($pir)))->toBe(0);
});
