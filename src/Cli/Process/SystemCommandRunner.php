<?php

declare(strict_types=1);

namespace Femus\Cli\Process;

final class SystemCommandRunner implements CommandRunner
{
    /** @param bool $echo stream child output to STDOUT as it arrives (off for protocol modes like MCP) */
    public function __construct(private readonly bool $echo = true)
    {
    }

    public function run(array $argv): CommandResult
    {
        $command = implode(' ', array_map('escapeshellarg', $argv));
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($command, $descriptors, $pipes);
        if (!is_resource($process)) {
            return new CommandResult(127);
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $output = '';
        while (!feof($pipes[1]) || !feof($pipes[2])) {
            $read = [$pipes[1], $pipes[2]];
            $write = $except = [];
            if (stream_select($read, $write, $except, 1) === false) {
                break;
            }
            foreach ($read as $pipe) {
                $chunk = fread($pipe, 4096);
                if ($chunk !== false && $chunk !== '') {
                    if ($this->echo) {
                        fwrite(STDOUT, $chunk);
                    }
                    $output .= $chunk;
                }
            }
        }

        fclose($pipes[1]);
        fclose($pipes[2]);

        return new CommandResult(proc_close($process), $output);
    }
}
