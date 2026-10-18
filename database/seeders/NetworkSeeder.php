<?php

namespace Database\Seeders;

use App\Enums\Frequency;
use App\Enums\IssueStatus;
use App\Models\Newsletter;
use App\Models\User;
use App\Support\Blocks;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeds the cross-promo network (OTO 10) with house publications so the directory is never empty.
 * Replace these with the vendor's real publications before launch.
 */
class NetworkSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::firstOrCreate(
            ['email' => 'network@newspub.local'],
            ['name' => 'NewsPub Network', 'password' => Hash::make(Str::random(40)), 'terms_accepted_at' => now()],
        );

        $publications = [
            ['The Weekly Ledger', 'money', 'personal finance', 'Clear money tips for everyday people.'],
            ['AI at Work', 'tech', 'AI tools', 'The AI tools worth your time, tested each week.'],
            ['Founder Notes', 'business', 'small business', 'Practical lessons for people running small businesses.'],
            ['Move Daily', 'health', 'home fitness', 'Short workouts and healthy habits for busy weeks.'],
            ['Small Space Garden', 'lifestyle', 'gardening', 'Grow food and flowers on a balcony or windowsill.'],
            ['Tiny Humans', 'family', 'parenting', 'Calm, practical ideas for parents of young children.'],
            ['Weekend Maker', 'creative', 'DIY and crafts', 'One simple project to make every weekend.'],
            ['Around Town Digest', 'local', 'local community', 'A template for local news and events done well.'],
        ];

        foreach ($publications as [$name, $category, $niche, $pitch]) {
            $newsletter = Newsletter::firstOrCreate(
                ['subdomain' => Str::slug($name)],
                ['user_id' => $owner->id, 'name' => $name, 'niche' => $niche, 'tagline' => $pitch, 'frequency' => Frequency::Weekly, 'template' => 'modern'],
            );

            if (! $newsletter->issues()->exists()) {
                $newsletter->issues()->create([
                    'title' => 'Welcome to '.$name,
                    'subject_line' => 'Welcome to '.$name,
                    'blocks' => Blocks::normalize([
                        ['type' => 'paragraph', 'text' => $pitch],
                        ['type' => 'paragraph', 'text' => 'Subscribe to get the next issue in your inbox.'],
                    ]),
                    'status' => IssueStatus::Published,
                    'public_slug' => 'welcome',
                    'published_at' => now(),
                ]);
            }

            $newsletter->networkListing()->updateOrCreate([], [
                'category' => $category,
                'pitch' => $pitch,
                'audience_size' => 0,
                'is_seeded' => true,
                'is_listed' => true,
            ]);
        }
    }
}
