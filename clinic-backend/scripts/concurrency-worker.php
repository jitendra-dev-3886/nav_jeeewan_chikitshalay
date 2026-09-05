<?php
// Called by tests/concurrency.py with a disposable, isolated SQLite database.
$path=$argv[1]??'';$phone=$argv[2]??'';
if($path==='mysql-test') {putenv('DB_CONNECTION=mysql');putenv('DB_DATABASE=nav_jeevan_clinic_concurrency_test');}
elseif(str_ends_with(str_replace('\\','/',$path),'/.local/concurrency.sqlite')) {putenv('DB_CONNECTION=sqlite');putenv('DB_DATABASE='.$path);}
else exit(2);
putenv('CACHE_STORE=array');putenv('SESSION_DRIVER=array');
require __DIR__.'/../vendor/autoload.php';$app=require __DIR__.'/../bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if($phone==='setup') {
    Illuminate\Support\Facades\Artisan::call('migrate',['--force'=>true]);
    Illuminate\Support\Facades\Artisan::call('db:seed',['--class'=>'Database\\Seeders\\ClinicSeeder','--force'=>true]);echo 'ready';exit;
}
if($phone==='count') {echo App\Models\Appointment::count();exit;}
Carbon\CarbonImmutable::setTestNow('2026-09-07 01:00:00 UTC');Illuminate\Support\Carbon::setTestNow('2026-09-07 01:00:00 UTC');
try {
    app(App\Services\BookingService::class)->create(['name'=>'Concurrency Test','phone'=>$phone,'age_group'=>'18_35','service_id'=>App\Models\Service::first()->id,'starts_at'=>'2026-09-08T09:00:00+05:30','consent'=>true,'communication'=>'none']);
    echo 'booked';
}catch(Illuminate\Validation\ValidationException $e) {echo 'conflict';}
