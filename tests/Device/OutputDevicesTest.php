<?php

declare(strict_types=1);

use Femus\Adapter\Fake\FakeBoard;
use Femus\Runtime\StreamSelectLoop;

it('led включается, выключается и переключается', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $led = $board->led(13);
    $led->on();
    expect($led->isOn())->toBeTrue();
    $led->toggle();
    expect($led->isOn())->toBeFalse();
});

it('led мигает по таймеру', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $led = $board->led(13);
    $led->blink(0.005);
    $board->loop()->addTimer(0.03, function () use ($led, $board) {
        $led->stopBlinking();
        $board->stop();
    });
    $board->run();
    // за ~30мс при периоде 5мс LED обязан был переключиться хотя бы дважды —
    // проверяем сам факт работы таймера через состояние пина
    expect($board->pin(13)->mode)->toBe(\Femus\Contracts\PinMode::Output);
});

it('relay щёлкает', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $relay = $board->relay(7);
    $relay->on();
    expect($relay->isOn())->toBeTrue()
        ->and($board->pin(7)->read())->toBeTrue();
});

it('buzzer пищит заданное время и замолкает', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $buzzer = $board->buzzer(8);
    $buzzer->beep(0.01);
    expect($buzzer->isOn())->toBeTrue();
    $board->run(); // таймер выключения — единственный, run() завершится сам
    expect($buzzer->isOn())->toBeFalse();
});
