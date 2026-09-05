<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContentImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_and_article_images_can_be_uploaded_retained_replaced_and_removed(): void
    {
        Storage::fake('public');
        $this->seed(\Database\Seeders\ClinicSeeder::class);
        $this->actingAs(User::factory()->create(['role'=>'admin','active'=>true]));
        $cases=[
            'services'=>['name'=>'Test service','slug'=>'test-service','summary'=>'Summary','duration'=>20,'published'=>'1'],
            'content'=>['title'=>'Test article','slug'=>'test-article','type'=>'article','language'=>'en','body'=>'Article text','published'=>'1'],
        ];
        foreach ($cases as $resource=>$payload) {
            $response=$this->post('/api/admin/'.$resource,[...$payload,'image'=>UploadedFile::fake()->image('cover.jpg',600,400),'image_alt'=>'Clinic photograph'],['Accept'=>'application/json'])->assertCreated();
            $id=$response->json('id');
            $url=$response->json('image_url');
            Storage::disk('public')->assertExists(substr($url,9));
            $this->getJson('/api/public/'.$resource)->assertJsonFragment(['image_url'=>$url,'image_alt'=>'Clinic photograph']);
            $this->post('/api/admin/'.$resource.'/'.$id,[...$payload,'_method'=>'PUT'],['Accept'=>'application/json'])->assertOk()->assertJsonPath('image_url',$url);
            $replacement=$this->post('/api/admin/'.$resource.'/'.$id,[...$payload,'_method'=>'PUT','image'=>UploadedFile::fake()->image('new.png',400,500)],['Accept'=>'application/json'])->assertOk()->json('image_url');
            $this->assertNotSame($url,$replacement);
            $this->post('/api/admin/'.$resource.'/'.$id,[...$payload,'_method'=>'PUT','remove_image'=>'1'],['Accept'=>'application/json'])->assertOk()->assertJsonPath('image_url',null);
            $this->post('/api/admin/'.$resource,[...$payload,'slug'=>'invalid-'.$resource,'image'=>UploadedFile::fake()->create('unsafe.svg',1,'image/svg+xml')],['Accept'=>'application/json'])->assertUnprocessable()->assertJsonValidationErrors('image');
        }
    }
}
