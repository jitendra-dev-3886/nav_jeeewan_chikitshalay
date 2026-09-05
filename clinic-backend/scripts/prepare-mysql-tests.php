<?php
require __DIR__.'/../vendor/autoload.php';$app=require __DIR__.'/../bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!$app->environment('local'))exit(2);
$pdo=Illuminate\Support\Facades\DB::connection()->getPdo();
foreach(['nav_jeevan_clinic_concurrency_test','nav_jeevan_clinic_feature_test'] as $database) {
    $q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ?');$q->execute([$database]);
    if($q->fetchColumn()>0){echo "$database already contains tables; leave it untouched.\n";exit(2);}
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
}
echo "Created isolated MySQL test databases.\n";
