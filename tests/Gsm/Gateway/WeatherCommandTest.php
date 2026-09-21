<?php

declare(strict_types=1);

use Femus\Gsm\Gateway\Command\WeatherCommand;
use Femus\Gsm\Sms;

function jsonOk(array $data): array
{
    return ['status' => 200, 'body' => (string) json_encode($data)];
}

/** Shape of a real Open-Meteo forecast reply, trimmed to the fields the command reads. */
function forecastBody(int $code = 3, array $rain = [0, 10, 20, 30, 20, 10], array $hourCodes = [3, 3, 3, 3, 3, 3]): array
{
    return jsonOk([
        'current' => ['temperature_2m' => 12.4, 'weather_code' => $code, 'wind_speed_10m' => 15.3],
        'current_units' => ['temperature_2m' => '°C', 'wind_speed_10m' => 'km/h'],
        'hourly' => [
            'time' => ['2026-09-21T01:00', '2026-09-21T02:00', '2026-09-21T03:00',
                '2026-09-21T04:00', '2026-09-21T05:00', '2026-09-21T06:00'],
            'temperature_2m' => [12, 11, 10, 9, 8, 7.6],
            'precipitation_probability' => $rain,
            'weather_code' => $hourCodes,
        ],
    ]);
}

$sms = new Sms('+15551234567', '/weather');

it('takes coordinates without asking the geocoder', function () use ($sms) {
    $http = new FakeHttpClient(['status' => 200, 'body' => '{}']);
    $http->getQueue = [forecastBody()];

    $reply = (new WeatherCommand($http))->handle('44.65 -63.57', $sms);

    expect($reply)->toBe('44.65,-63.57: 12°C, overcast, wind 15.3 km/h. 6h: 8°.')
        ->and($http->getUrls)->toHaveCount(1)
        ->and($http->getUrls[0])->toContain('latitude=44.65')
        ->and($http->getUrls[0])->toContain('longitude=-63.57');
});

it('looks up a place name first', function () use ($sms) {
    $http = new FakeHttpClient(['status' => 200, 'body' => '{}']);
    $http->getQueue = [
        jsonOk(['results' => [['name' => 'Halifax', 'latitude' => 44.65, 'longitude' => -63.57]]]),
        forecastBody(),
    ];

    expect((new WeatherCommand($http))->handle('Halifax', $sms))->toStartWith('Halifax: 12°C, overcast');
    expect($http->getUrls[0])->toContain('name=Halifax');
});

it('warns about rain worth turning back for', function () use ($sms) {
    $http = new FakeHttpClient(['status' => 200, 'body' => '{}']);
    $http->getQueue = [forecastBody(rain: [10, 20, 80, 30, 10, 0])];

    expect((new WeatherCommand($http))->handle('44.65 -63.57', $sms))->toContain('80% precip at 03:00');
});

it('shouts about a thunderstorm', function () use ($sms) {
    $http = new FakeHttpClient(['status' => 200, 'body' => '{}']);
    $http->getQueue = [forecastBody(hourCodes: [3, 3, 95, 3, 3, 3])];

    expect((new WeatherCommand($http))->handle('44.65 -63.57', $sms))->toContain('STORM at 03:00');
});

it('explains an unknown place instead of failing', function () use ($sms) {
    $http = new FakeHttpClient(['status' => 200, 'body' => '{}']);
    $http->getQueue = [jsonOk(['generationtime_ms' => 0.2])];   // no "results" key at all

    expect((new WeatherCommand($http))->handle('Nowhereville', $sms))->toContain("No place called 'Nowhereville'");
});

it('survives the service being down', function () use ($sms) {
    $http = new FakeHttpClient(['status' => 503, 'body' => 'upstream down']);

    expect((new WeatherCommand($http))->handle('44.65 -63.57', $sms))->toContain('unavailable (503)');
});

it('asks where when given nothing', function () use ($sms) {
    $http = new FakeHttpClient(['status' => 200, 'body' => '{}']);

    expect((new WeatherCommand($http))->handle('  ', $sms))->toStartWith('Where?')
        ->and($http->getUrls)->toBeEmpty();
});
