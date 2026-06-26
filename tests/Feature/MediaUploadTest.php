<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MediaUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_image_is_accepted_and_recorded(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post('/admin/media/upload', [
            'file' => UploadedFile::fake()->image('photo.jpg', 40, 40),
        ]);

        $response->assertOk()->assertJsonStructure(['id', 'url', 'name']);

        $media = Media::first();
        $this->assertNotNull($media);

        // Clean up the file the controller wrote to public/uploads.
        File::delete(public_path('uploads/' . $media->filename));
    }

    public function test_upload_stores_alt_text(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/media/upload', [
            'file' => UploadedFile::fake()->image('photo.jpg', 40, 40),
            'alt'  => 'A defender making a tackle',
        ])->assertOk();

        $media = Media::first();
        $this->assertSame('A defender making a tackle', $media->alt);

        File::delete(public_path('uploads/' . $media->filename));
    }

    public function test_alt_text_can_be_updated_from_the_library(): void
    {
        $admin = User::factory()->admin()->create();
        $media = Media::create([
            'filename' => 'x.jpg', 'original_name' => 'x.jpg', 'mimetype' => 'image/jpeg',
            'url' => '/uploads/x.jpg', 'uploaded_by' => $admin->id,
        ]);

        $this->actingAs($admin)->put(route('admin.media.update', $media), ['alt' => 'Updated alt'])
            ->assertOk()->assertJson(['ok' => true]);

        $this->assertSame('Updated alt', $media->fresh()->alt);
    }

    public function test_non_image_uploads_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/media/upload', [
            'file' => UploadedFile::fake()->create('document.pdf', 200, 'application/pdf'),
        ])->assertSessionHasErrors('file');

        $this->assertSame(0, Media::count());
    }

    public function test_genuine_image_with_php_filename_is_blocked(): void
    {
        $admin = User::factory()->admin()->create();

        // Real PNG bytes, but presented with a .php filename. This exercises
        // Laravel's executable-upload block specifically — the file genuinely
        // IS a valid image, so the plain "is it an image" rule would pass it.
        $img = imagecreatetruecolor(20, 20);
        $tmp = tempnam(sys_get_temp_dir(), 'img');
        imagepng($img, $tmp);
        imagedestroy($img);
        $file = new UploadedFile($tmp, 'shell.php', 'image/png', null, true);

        $this->actingAs($admin)->post('/admin/media/upload', ['file' => $file])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Media::count());

        @unlink($tmp);
    }

    public function test_guests_cannot_upload(): void
    {
        $this->post('/admin/media/upload', [
            'file' => UploadedFile::fake()->image('x.jpg'),
        ])->assertRedirect(route('admin.login'));
    }
}
