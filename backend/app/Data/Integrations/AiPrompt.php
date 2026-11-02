<?php

declare(strict_types=1);

namespace App\Data\Integrations;

final readonly class AiPrompt
{
    /**
     * @param  list<string>  $knowledgeExcerpts
     */
    public function __construct(
        public string $systemInstructions,
        public string $question,
        public array $knowledgeExcerpts,
        public int $maxOutputTokens,
    ) {}

    public function userContent(): string
    {
        if ($this->knowledgeExcerpts === []) {
            return $this->question;
        }

        return implode("\n\n", [...$this->knowledgeExcerpts, $this->question]);
    }
}
