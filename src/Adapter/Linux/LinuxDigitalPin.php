<?php

declare(strict_types=1);

namespace Femus\Adapter\Linux;

use Femus\Cli\Process\CommandRunner;
use Femus\Contracts\DigitalPin;
use Femus\Contracts\PinMode;
use Femus\Runtime\Loop;

/**
 * A GPIO line on a Raspberry Pi, driven through the `pinctrl` utility.
 * Register writes persist after the command exits, so a HIGH stays HIGH.
 */
final class LinuxDigitalPin implements DigitalPin
{
    /** @var list<callable> */
    private array $listeners = [];

    private bool $state = false;

    private ?string $pollTimer = null;

    public function __construct(
        private readonly CommandRunner $runner,
        private readonly int $number,
        private readonly PinMode $mode,
        private readonly Loop $loop,
    ) {
        $args = match ($mode) {
            PinMode::Output => ['op'],
            PinMode::Input => ['ip'],
            PinMode::InputPullUp => ['ip', 'pu'],
        };
        $this->runner->run(['pinctrl', 'set', (string) $number, ...$args]);
    }

    public function number(): int
    {
        return $this->number;
    }

    public function write(bool $high): void
    {
        $this->runner->run(['pinctrl', 'set', (string) $this->number, $high ? 'dh' : 'dl']);
        $this->state = $high;
    }

    public function read(): bool
    {
        $output = $this->runner->run(['pinctrl', 'get', (string) $this->number])->output;

        // e.g. "17: ip    -- pu | hi // GPIO17 = input"
        $this->state = preg_match('/\|\s*hi\b/', $output) === 1;

        return $this->state;
    }

    public function onChange(callable $listener): void
    {
        $this->listeners[] = $listener;

        // pinctrl has no interrupts; poll the line for input pins.
        if ($this->pollTimer === null && $this->mode !== PinMode::Output) {
            $this->state = $this->read();
            $this->pollTimer = $this->loop->addPeriodicTimer(0.05, function (): void {
                $previous = $this->state;
                if ($this->read() !== $previous) {
                    foreach ($this->listeners as $listener) {
                        $listener($this->state);
                    }
                }
            });
        }
    }
}
