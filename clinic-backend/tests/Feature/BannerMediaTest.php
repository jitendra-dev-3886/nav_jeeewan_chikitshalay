<?php
namespace Tests\Feature;
use App\Models\Banner;
use App\Models\User;
use Database\Seeders\ClinicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BannerMediaTest extends TestCase
{
    use RefreshDatabase;
    public function test_banner_and_poster_can_be_saved_with_the_image_in_one_request(): void
    {
        $this->actingAs(User::factory()->create(['role'=>'admin','active'=>true]));
        foreach (['banner', 'poster'] as $kind) {
            $payload=['title'=>'Uploaded '.$kind,'kind'=>$kind,'body'=>'','published'=>'0'];
            $response=$this->post('/api/admin/banners', [...$payload,'image'=>UploadedFile::fake()->image('artwork.png',600,400)], ['Accept'=>'application/json'])->assertCreated();
            $id=$response->json('id');
            $url=$response->json('image_url');
            Storage::disk('public')->assertExists(substr($url,9));
            $this->post('/api/admin/banners/'.$id, [...$payload,'_method'=>'PUT','title'=>'Edited '.$kind], ['Accept'=>'application/json'])->assertOk()->assertJsonPath('image_url',$url);
            $replacement=$this->post('/api/admin/banners/'.$id, [...$payload,'_method'=>'PUT','image'=>UploadedFile::fake()->image('replacement.jpg',300,500)], ['Accept'=>'application/json'])->assertOk()->json('image_url');
            $this->assertNotSame($url,$replacement);
            Storage::disk('public')->assertExists(substr($replacement,9));
        }
    }
    public function test_combined_save_validates_before_storing_images(): void
    {
        $this->actingAs(User::factory()->create(['role'=>'admin','active'=>true]));
        $this->post('/api/admin/banners',['kind'=>'banner','image'=>UploadedFile::fake()->image('image.png')],['Accept'=>'application/json'])->assertUnprocessable()->assertJsonValidationErrors('title');
        $this->assertCount(0,Storage::disk('public')->allFiles());
        $this->postJson('/api/admin/banners',['title'=>'Missing image','kind'=>'banner'])->assertUnprocessable()->assertJsonPath('errors.image_url.0','Choose an image for this banner or poster.');
        $this->post('/api/admin/banners',['title'=>'Unsafe image','kind'=>'banner','image'=>UploadedFile::fake()->create('image.svg',1,'image/svg+xml')],['Accept'=>'application/json'])->assertUnprocessable()->assertJsonValidationErrors('image');
        $this->assertDatabaseMissing('banners',['title'=>'Missing image']);
        $this->assertDatabaseMissing('banners',['title'=>'Unsafe image']);
    }
    protected function setUp(): void {parent::setUp();$this->seed(ClinicSeeder::class);Storage::fake('public');}
    public function test_uploaded_poster_persists_and_drafts_are_not_public(): void
    {
        $this->actingAs(User::factory()->create(['role'=>'admin','active'=>true]));
        $url=$this->post('/api/admin/banner-images',['image'=>UploadedFile::fake()->image('poster.png',500,800)],['Accept'=>'application/json'])->assertCreated()->json('image_url');
        $payload=['title'=>'Clinic poster','body'=>'','kind'=>'poster','image_url'=>$url,'image_alt'=>'Clinic information poster','published'=>false];
        $id=$this->postJson('/api/admin/banners',$payload)->assertCreated()->json('id');
        $this->assertSame($url,Banner::findOrFail($id)->image_url);
        $this->getJson('/api/public/clinic')->assertJsonMissing(['title'=>'Clinic poster']);
        $this->putJson('/api/admin/banners/'.$id,[...$payload,'published'=>true])->assertOk();
        $this->getJson('/api/public/clinic')->assertOk()->assertHeader('Cache-Control','no-store, private')->assertJsonFragment(['image_url'=>$url,'kind'=>'poster']);
        $this->getJson('/api/public/clinic')->assertJsonFragment(['title'=>'Clinic poster','image_url'=>$url]);
        $this->get($url)->assertOk()->assertHeader('Content-Type','image/webp');
    }
    public function test_banner_requires_existing_uploaded_image_and_rejects_unsafe_files(): void
    {
        $this->actingAs(User::factory()->create(['role'=>'admin','active'=>true]));
        $payload=['title'=>'Image banner','kind'=>'banner','body'=>'','published'=>true];
        $this->postJson('/api/admin/banners',$payload)->assertUnprocessable();
        $this->postJson('/api/admin/banners',[...$payload,'image_url'=>'https://example.com/image.png'])->assertUnprocessable();
        $this->postJson('/api/admin/banners',[...$payload,'image_url'=>'/storage/gallery/11111111-1111-1111-1111-111111111111.webp'])->assertUnprocessable();
        $this->post('/api/admin/banner-images',['image'=>UploadedFile::fake()->create('poster.svg',1,'image/svg+xml')],['Accept'=>'application/json'])->assertUnprocessable();
    }
    public function test_uploads_are_admin_only_and_legacy_text_banners_still_work(): void
    {
        $this->postJson('/api/admin/banner-images')->assertUnauthorized();
        $this->actingAs(User::factory()->create(['role'=>'receptionist','active'=>true]));
        $this->postJson('/api/admin/banner-images')->assertForbidden();
        $this->actingAs(User::factory()->create(['role'=>'admin','active'=>true]));
        $this->postJson('/api/admin/banners',['title'=>'Notice','body'=>'Reception announcement','published'=>true])->assertCreated()->assertJsonPath('kind','text');
        $this->getJson('/api/public/clinic')->assertJsonFragment(['title'=>'Notice']);
    }
}
