<?php

declare(strict_types=1);

namespace Femus\Adapter\Firmata;

use Femus\Contracts\ScaleInput;
use Femus\Transport\Transport;

/**
 * HX711 stream over the femus sysex extension.
 * v1 supports one HX711 per board: the protocol carries no channel id,
 * so every reading frame is delivered to every FirmataScaleInput.
 */
final class FirmataScaleInput implements ScaleInput
{
    private ?int $lastRaw = null;

    /** @var list<callable> */
    private array $listeners = [];

    public function __construct(
        Transport $transport,
        FirmataParser $parser,
        int $doutPin,
        int $sckPin,
    ) {
        $parser->onSysex(function (string $payload): void {
            $reading = Hx711Reading::fromSysexPayload($payload);
            if ($reading === null) {
                return;
            }
            $this->lastRaw = $reading->value;
            foreach ($this->listeners as $listener) {
                $listener($reading->value);
            }
        });
        $transport->write(FirmataEncoder::hx711Attach($doutPin, $sckPin));
    }

    public function onRawReading(callable $listener): void
    {
        $this->listeners[] = $listener;
    }

    public function lastRaw(): ?int
    {
        return $this->lastRaw;
    }
}
