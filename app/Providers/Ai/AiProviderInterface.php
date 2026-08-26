<?php

namespace App\Providers\Ai;

interface AiProviderInterface
{
    /**
     * Understand the intent and extract information from the message.
     */
    public function understand(string $message, array $context = []): array;

    /**
     * Generate a response based on the message, retrieved knowledge, and context.
     */
    public function generateReply(string $message, array $knowledge, array $context = []): string;
}
