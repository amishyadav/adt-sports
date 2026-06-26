<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ImagePipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_generates_a_webp_sibling(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/media/upload', [
            'file' => UploadedFile::fake()->image('photo.png', 60, 40),
        ])->assertOk();

        $media = Media::first();
        $original = public_path('uploads/' . $media->filename);
        $webp     = preg_replace('/\.(jpe?g|png)$/i', '.webp', $original);

        $this->assertFileExists($webp, 'A .webp sibling should be generated on upload');

        File::delete($original);
        File::delete($webp);
    }

    public function test_responsive_image_emits_picture_with_webp_and_dimensions(): void
    {
        $dir  = public_path('uploads');
        File::ensureDirectoryExists($dir);
        $name = 'pipeline-test-' . uniqid();

        $img = imagecreatetruecolor(80, 50);
        imagepng($img, "$dir/$name.png");
        imagewebp($img, "$dir/$name.webp");
        imagedestroy($img);

        $view = $this->blade(
            '<x-responsive-image :src="$src" alt="Hero" eager />',
            ['src' => "/uploads/$name.png"]
        );

        $view->assertSee('<picture', false);
        $view->assertSee('type="image/webp"', false);
        $view->assertSee("/uploads/$name.webp", false);
        $view->assertSee('width="80"', false);
        $view->assertSee('height="50"', false);
        $view->assertSee('fetchpriority="high"', false);

        File::delete("$dir/$name.png");
        File::delete("$dir/$name.webp");
    }

    public function test_responsive_image_falls_back_for_external_urls(): void
    {
        $view = $this->blade(
            '<x-responsive-image src="https://cdn.example.com/x.jpg" alt="Ext" />'
        );

        $view->assertSee('https://cdn.example.com/x.jpg', false);
        $view->assertSee('loading="lazy"', false);
        $view->assertDontSee('type="image/webp"', false); // no local webp sibling
    }
}
