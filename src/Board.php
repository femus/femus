<?php

declare(strict_types=1);

namespace Femus;

use Femus\Adapter\Fake\FakeBoard;
use Femus\Adapter\Firmata\FirmataBoard;
use Femus\Runtime\StreamSelectLoop;

final class Board
{
    public static function fake(): FakeBoard
    {
        return new FakeBoard(new StreamSelectLoop());
    }

    /** Arduino с прошивкой StandardFirmata, подключённая по USB. */
    public static function firmata(string $device, int $baudRate = 57600): FirmataBoard
    {
        return FirmataBoard::open($device, $baudRate);
    }

    private function __construct()
    {
    }
}
