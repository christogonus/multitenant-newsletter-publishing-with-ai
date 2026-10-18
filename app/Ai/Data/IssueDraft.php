<?php

namespace App\Ai\Data;

/**
 * A written issue as returned by the writer: metadata plus normalised content blocks.
 */
final readonly class IssueDraft
{
    /**
     * @param  list<array<string, mixed>>  $blocks
     */
    public function __construct(
        public string $title,
        public string $subjectLine,
        public string $preheader,
        public array $blocks,
    ) {}
}
