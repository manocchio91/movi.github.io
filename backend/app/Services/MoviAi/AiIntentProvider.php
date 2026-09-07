<?php

namespace App\Services\MoviAi;

interface AiIntentProvider
{
    /** @throws \Throwable */
    public function parse(string $message, array $schema): array;
}
