<?php
// This script only initializes a local development login; it never changes an existing user.
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!$app->environment('local')) { fwrite(STDERR,"Local setup is disabled outside APP_ENV=local.\n"); exit(1); }
$email='admin@navjeevan.local';
if(App\Models\User::where('email',$email)->exists()) { echo "Local administrator already exists; password unchanged.\n"; exit; }
$password=bin2hex(random_bytes(12));
App\Models\User::create(['name'=>'Clinic Administrator','email'=>$email,'password'=>$password,'role'=>'admin','active'=>true]);
$dir=dirname(__DIR__,2).'/.local';if(!is_dir($dir))mkdir($dir,0700,true);
file_put_contents($dir.'/access.txt',"LOCAL DEVELOPMENT ACCESS\nWebsite: http://127.0.0.1:5173\nStaff sign in: http://127.0.0.1:5173/login\nEmail: $email\nPassword: $password\n\nChange this password in Users & roles. Do not publish this file.\n");
echo "Local administrator created. Credentials saved in .local/access.txt at the workspace root.\n";
