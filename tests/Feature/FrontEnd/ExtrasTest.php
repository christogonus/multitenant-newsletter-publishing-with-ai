<?php

use App\Actions\CreateNewsletter;
use App\Ai\Agents\IssueWriter;
use App\Exceptions\PublicationLimitReachedException;
use App\Livewire\Issues\Editor;
use App\Models\Issue;
use App\Models\Newsletter;
use App\Models\Subscriber;
use App\Models\User;
use App\Support\Blocks;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('one configured provider is used as-is', function () {
    config(['newspub.ai.provider' => 'anthropic', 'newspub.ai.model' => 'claude-sonnet-5-5']);

    expect((new IssueWriter(Newsletter::factory()->make()))->provider())->toBe('anthropic');
});

test('several providers fail over in order and the model applies to the first', function () {
    config(['newspub.ai.provider' => 'anthropic, openai', 'newspub.ai.model' => 'claude-sonnet-5-5']);

    expect((new IssueWriter(Newsletter::factory()->make()))->provider())->toBe([
        'anthropic' => 'claude-sonnet-5-5',
        'openai' => null,
    ]);
});

test('a picture uploaded in the editor is stored and used by the block', function () {
    Storage::fake('public');
    $issue = Issue::factory()->create(['blocks' => Blocks::normalize([['type' => 'image', 'url' => '']])]);
    $blockId = $issue->blocks[0]['id'];
    $this->actingAs($issue->newsletter->user);

    Livewire::test(Editor::class, ['issue' => $issue])
        ->set('imageUploads.'.$blockId, UploadedFile::fake()->image('photo.jpg', 800, 600))
        ->assertHasNoErrors();

    $url = $issue->fresh()->blocks[0]['url'];
    expect($url)->toStartWith('http')->toContain('issue-images/'.$issue->newsletter_id);
    Storage::disk('public')->assertExists('issue-images/'.$issue->newsletter_id.'/'.basename($url));
});

test('non-picture uploads are refused', function () {
    Storage::fake('public');
    $issue = Issue::factory()->create(['blocks' => Blocks::normalize([['type' => 'image', 'url' => '']])]);
    $blockId = $issue->blocks[0]['id'];
    $this->actingAs($issue->newsletter->user);

    Livewire::test(Editor::class, ['issue' => $issue])
        ->set('imageUploads.'.$blockId, UploadedFile::fake()->create('script.php', 10, 'text/x-php'))
        ->assertHasErrors('imageUploads.'.$blockId);

    expect($issue->fresh()->blocks[0]['url'])->toBe('');
});

test('deleting a publication removes its uploaded files', function () {
    Storage::fake('public');
    $newsletter = Newsletter::factory()->create();
    Storage::disk('public')->put('issue-images/'.$newsletter->id.'/a.jpg', 'x');

    $newsletter->delete();

    Storage::disk('public')->assertMissing('issue-images/'.$newsletter->id.'/a.jpg');
});

test('a buyer can delete their account and everything in it', function () {
    $newsletter = Newsletter::factory()->create();
    Subscriber::factory()->for($newsletter)->create();
    $user = $newsletter->user;

    $this->actingAs($user)->delete(route('account.destroy'), ['password' => 'password', 'confirm' => 'nope'])->assertSessionHasErrors('confirm');
    $this->actingAs($user)->delete(route('account.destroy'), ['password' => 'wrong', 'confirm' => 'DELETE'])->assertSessionHasErrors('password');

    $this->actingAs($user)->delete(route('account.destroy'), ['password' => 'password', 'confirm' => 'DELETE'])->assertRedirect(route('login'));

    $this->assertGuest();
    expect(User::find($user->id))->toBeNull()
        ->and(Newsletter::count())->toBe(0)
        ->and(Subscriber::count())->toBe(0);
});

test('publications have an rss feed and a sitemap of published issues only', function () {
    $newsletter = Newsletter::factory()->create(['name' => 'Feed Weekly']);
    $live = Issue::factory()->for($newsletter)->published()->create(['title' => 'Live & well']);
    Issue::factory()->for($newsletter)->create(['title' => 'Hidden draft']);

    $this->get(publicationUrl($newsletter, 'feed'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8')
        ->assertSee('Live &amp; well', false)
        ->assertDontSee('Hidden draft');

    $this->get(publicationUrl($newsletter, 'sitemap.xml'))
        ->assertOk()
        ->assertSee(publicationUrl($newsletter, 'issues/'.$live->public_slug), false);

    expect(simplexml_load_string($this->get(publicationUrl($newsletter, 'feed'))->getContent()))->not->toBeFalse();
});

test('the publication limit is enforced inside the create action itself', function () {
    $user = buyer();
    Newsletter::factory()->for($user)->create();

    expect(fn () => app(CreateNewsletter::class)->handle($user, ['name' => 'Second', 'niche' => 'x', 'frequency' => 'weekly']))
        ->toThrow(PublicationLimitReachedException::class);
});
