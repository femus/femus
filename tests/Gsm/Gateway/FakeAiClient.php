<?php

declare(strict_types=1);

namespace Femus\Tests\Gsm\Gateway;

use Femus\Gsm\Gateway\AiClient;

/** Test double: records questions and returns a canned or echoed answer. */
final class FakeAiClient implements AiClient
{
    /** @var list<string> */
    public array $questions = [];

    public function __construct(private readonly ?string $answer = null)
    {
    }

    public function ask(string $question): string
    {
        $this->questions[] = $question;

        return $this->answer ?? "AI: {$question}";
    }
}
