<?php

declare(strict_types=1);

namespace Femus\Gsm\Gateway\Command;

use Femus\Gsm\Gateway\CurlHttpClient;
use Femus\Gsm\Gateway\HttpClient;
use Femus\Gsm\Gateway\SmsCommand;
use Femus\Gsm\Sms;

/**
 * "/weather Halifax" or "/weather 44.65 -63.57" — the forecast that decides whether
 * you walk on or pitch the tent. An AI answers from memory and cannot know today's
 * sky, so this command fetches the real thing.
 *
 * Open-Meteo needs no API key and no account, which is what makes it right for a box
 * that should keep working years from now without anyone renewing anything.
 */
final class WeatherCommand implements SmsCommand
{
    private const GEOCODE = 'https://geocoding-api.open-meteo.com/v1/search?count=1&format=json&name=';
    private const FORECAST = 'https://api.open-meteo.com/v1/forecast'
        . '?current=temperature_2m,weather_code,wind_speed_10m'
        . '&hourly=temperature_2m,precipitation_probability,weather_code'
        . '&forecast_hours=6&timezone=auto&latitude=%s&longitude=%s';

    /** WMO weather codes, shortened to what fits an SMS. */
    private const CONDITIONS = [
        0 => 'clear', 1 => 'mostly clear', 2 => 'partly cloudy', 3 => 'overcast',
        45 => 'fog', 48 => 'freezing fog',
        51 => 'light drizzle', 53 => 'drizzle', 55 => 'heavy drizzle',
        56 => 'freezing drizzle', 57 => 'freezing drizzle',
        61 => 'light rain', 63 => 'rain', 65 => 'heavy rain',
        66 => 'freezing rain', 67 => 'freezing rain',
        71 => 'light snow', 73 => 'snow', 75 => 'heavy snow', 77 => 'snow grains',
        80 => 'showers', 81 => 'showers', 82 => 'violent showers',
        85 => 'snow showers', 86 => 'heavy snow showers',
        95 => 'THUNDERSTORM', 96 => 'THUNDERSTORM with hail', 99 => 'THUNDERSTORM with hail',
    ];

    public function __construct(private readonly HttpClient $http = new CurlHttpClient())
    {
    }

    public function name(): string
    {
        return 'weather';
    }

    public function description(): string
    {
        return 'forecast: /weather Halifax or /weather 44.65 -63.57';
    }

    public function handle(string $args, Sms $message): string
    {
        $args = trim($args);
        if ($args === '') {
            return 'Where? Send /weather Halifax or /weather 44.65 -63.57';
        }

        try {
            [$lat, $lon, $place] = $this->locate($args);

            return $this->forecast($lat, $lon, $place);
        } catch (\RuntimeException $e) {
            return $e->getMessage();
        }
    }

    /** @return array{0: string, 1: string, 2: string} latitude, longitude, place label */
    private function locate(string $args): array
    {
        // "44.65 -63.57" and "44.65,-63.57" are both coordinates; anything else is a name
        if (preg_match('/^(-?\d{1,2}(?:\.\d+)?)\s*[, ]\s*(-?\d{1,3}(?:\.\d+)?)$/', $args, $m) === 1) {
            return [$m[1], $m[2], "{$m[1]},{$m[2]}"];
        }

        $data = $this->fetch(self::GEOCODE . rawurlencode($args));
        $hit = $data['results'][0] ?? null;
        if (!is_array($hit)) {
            return throw new \RuntimeException("No place called '{$args}'. Try coordinates: /weather 44.65 -63.57");
        }

        return [(string) $hit['latitude'], (string) $hit['longitude'], (string) $hit['name']];
    }

    private function forecast(string $lat, string $lon, string $place): string
    {
        $data = $this->fetch(sprintf(self::FORECAST, rawurlencode($lat), rawurlencode($lon)));

        $now = $data['current'] ?? [];
        $reply = sprintf(
            '%s: %s, %s, wind %s %s.',
            $place,
            $this->temperature($now['temperature_2m'] ?? null, $data['current_units']['temperature_2m'] ?? ''),
            self::CONDITIONS[(int) ($now['weather_code'] ?? -1)] ?? 'unknown',
            (string) ($now['wind_speed_10m'] ?? '?'),
            (string) ($data['current_units']['wind_speed_10m'] ?? ''),
        );

        $ahead = $this->nextHours($data['hourly'] ?? []);

        return $ahead === '' ? $reply : "{$reply} {$ahead}";
    }

    /** The next few hours, compressed to what changes a decision: rain, and how cold it gets. */
    private function nextHours(array $hourly): string
    {
        $times = $hourly['time'] ?? [];
        $temps = $hourly['temperature_2m'] ?? [];
        $rain = $hourly['precipitation_probability'] ?? [];
        $codes = $hourly['weather_code'] ?? [];
        if ($times === [] || $temps === []) {
            return '';
        }

        $last = count($times) - 1;
        $ahead = sprintf('6h: %s.', $this->temperature($temps[$last] ?? null, ''));

        // the worst hour ahead is the one worth a warning, not the average
        $worstRain = 0;
        $worstHour = '';
        foreach ($rain as $i => $chance) {
            if ((int) $chance > $worstRain) {
                $worstRain = (int) $chance;
                $worstHour = substr((string) ($times[$i] ?? ''), 11, 5);
            }
        }
        if ($worstRain >= 40) {
            $ahead .= sprintf(' %s%% precip at %s.', $worstRain, $worstHour);
        }

        foreach ($codes as $i => $code) {
            if ((int) $code >= 95) {
                return $ahead . sprintf(' STORM at %s.', substr((string) ($times[$i] ?? ''), 11, 5));
            }
        }

        return $ahead;
    }

    private function temperature(mixed $value, string $unit): string
    {
        return $value === null ? '?' : round((float) $value) . ($unit !== '' ? $unit : '°');
    }

    /** @return array<mixed> */
    private function fetch(string $url): array
    {
        $response = $this->http->get($url);
        if ($response['status'] !== 200) {
            throw new \RuntimeException("Weather service unavailable ({$response['status']}). Try again later.");
        }

        $data = json_decode($response['body'], true);

        return is_array($data) ? $data : throw new \RuntimeException('Weather service sent nonsense. Try again later.');
    }
}
