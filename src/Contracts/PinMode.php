<?php

declare(strict_types=1);

namespace Femus\Contracts;

enum PinMode
{
    case Input;
    case InputPullUp;
    case Output;
}
