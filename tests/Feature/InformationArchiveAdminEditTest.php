<?php

use App\Models\Information;
use App\Models\InformationFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('an administrator can edit an imported article and manage its attachments', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
    $sourceFingerprint = hash('sha256', 'original-import');
    $information = Information::query()->create([
        'title' => '旧サイト移行記事',
        'body' => '<p>移行本文</p>',
        'body_format' => 'html',
        'is_public' => true,
        'category' => 'NEWS',
        'published_at' => '2025-04-01 00:00:00',
        'starts_at' => '2025-04-01 00:00:00',
        'audience' => 'public',
        'source_type' => 'legacy_information',
        'source_key' => 'legacy-information-admin-edit-test',
        'source_fingerprint' => $sourceFingerprint,
    ]);
    $oldFile = InformationFile::query()->create([
        'information_id' => $information->id,
        'type' => 'pdf',
        'title' => '旧資料.pdf',
        'file_path' => 'documents/jpba/content-archive/assets/old.pdf',
        'visibility' => 'public',
        'sort_order' => 0,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.informations.edit', $information))
        ->assertOk()
        ->assertSee('旧サイトから移行した記事です')
        ->assertSee('記事の公開日')
        ->assertSee('現在の画像・添付資料')
        ->assertSee('旧資料.pdf');

    $response = $this->actingAs($admin)->put(route('admin.informations.update', $information), [
        'title' => '編集後の移行記事',
        'category' => '大会',
        'audience' => 'public',
        'is_public' => '1',
        'published_at' => '2025-05-02T12:30',
        'starts_at' => '2025-05-02T12:30',
        'body' => '<p onclick="alert(1)">編集後本文</p><script>alert(2)</script>',
        'remove_attachment_ids' => [$oldFile->id],
        'attachments' => [UploadedFile::fake()->create('追加資料.pdf', 100, 'application/pdf')],
    ]);

    $response->assertRedirect();

    $information->refresh();
    expect($information->title)->toBe('編集後の移行記事')
        ->and($information->category)->toBe('大会')
        ->and($information->published_at?->format('Y-m-d H:i'))->toBe('2025-05-02 12:30')
        ->and($information->body)->toContain('編集後本文')
        ->and($information->body)->not->toContain('onclick')
        ->and($information->body)->not->toContain('<script')
        ->and($information->source_fingerprint)->not->toBe($sourceFingerprint);

    $this->assertDatabaseMissing('information_files', ['id' => $oldFile->id]);
    $newFile = $information->files()->sole();
    expect($newFile->title)->toBe('追加資料.pdf')
        ->and($newFile->type)->toBe('pdf')
        ->and($newFile->visibility)->toBe('public');
    Storage::disk('public')->assertExists($newFile->file_path);
});
