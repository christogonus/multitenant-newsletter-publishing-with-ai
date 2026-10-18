<?php

namespace App\Enums;

enum GenerationStage: string
{
    case Researching = 'researching';
    case Writing = 'writing';
    case Designing = 'designing';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Researching => 'Finding fresh stories',
            self::Writing => 'Writing your issue',
            self::Designing => 'Laying out the page',
            self::Done => 'Ready',
        };
    }

    public function position(): int
    {
        return array_search($this, self::cases(), true);
    }
}
