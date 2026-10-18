<?php

namespace Database\Factories;

use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\Newsletter;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Issue>
 */
class IssueFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(5);

        return [
            'newsletter_id' => Newsletter::factory(),
            'title' => $title,
            'subject_line' => Str::limit($title, 55, ''),
            'preheader' => fake()->sentence(8),
            'blocks' => [
                ['id' => (string) Str::ulid(), 'type' => 'paragraph', 'text' => fake()->paragraph()],
                ['id' => (string) Str::ulid(), 'type' => 'heading', 'text' => fake()->sentence(4)],
                ['id' => (string) Str::ulid(), 'type' => 'bullets', 'items' => [fake()->sentence(), fake()->sentence()]],
            ],
            'status' => IssueStatus::Draft,
            'is_ai_generated' => true,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => IssueStatus::Published,
            'public_slug' => Str::slug($attributes['title']),
            'published_at' => now()->subHour(),
        ]);
    }

    public function generating(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => IssueStatus::Generating,
            'blocks' => null,
        ]);
    }
}
