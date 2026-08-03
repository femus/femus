<?php

declare(strict_types=1);

use Femus\Adapter\Fake\FakeBoard;
use Femus\Contracts\PinMode;
use Femus\Runtime\StreamSelectLoop;

it('chain: button turns relay on, PIR turns buzzer on', function () {
    $board = new FakeBoard(new StreamSelectLoop());
    $board->digitalPin(2, PinMode::InputPullUp)->write(true); // button pull-up
    $button = $board->button(2);
    $relay = $board->relay(7);
    $pir = $board->motionSensor(4);
    $buzzer = $board->buzzer(8);

    $button->onPress(fn () => $relay->on());
    $pir->onMotion(fn () => $buzzer->on());

    $board->scheduleInput(0.01, 2, false);   // t+10ms: button press
    $board->scheduleInput(0.02, 4, true);    // t+20ms: motion detected
    $board->loop()->addTimer(0.05, fn () => $board->stop());

    $board->run();

    expect($relay->isOn())->toBeTrue()
        ->and($buzzer->isOn())->toBeTrue();
});
