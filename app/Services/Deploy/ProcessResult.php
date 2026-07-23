<?php

namespace App\Services\Deploy;

class ProcessResult
{
    public function __construct(
        public readonly bool $successful,
        public readonly string $output,
        public readonly string $errorOutput,
        public readonly int $exitCode,
    ) {}

    public function combinedOutput(): string
    {
        return trim($this->output.($this->errorOutput !== '' ? "\n".$this->errorOutput : ''));
    }
}
