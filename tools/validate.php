<?php

declare(strict_types=1);

$root=dirname(__DIR__);
$errors=[];
foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file){
    if($file->getExtension()!=='php') continue;
    exec('php -l '.escapeshellarg($file->getPathname()),$out,$code);
    if($code!==0)$errors[]='PHP syntax: '.substr($file->getPathname(),strlen($root)+1);
}
$route=file_get_contents($root.'/Routes/api.php');
preg_match_all('/use Modules\\\\PriyasaCore\\\\Http\\\\Controllers\\\\([^;]+);/', $route, $imports);
foreach($imports[1] as $import){$import=preg_replace('/\s+as\s+.*/','',$import);$path=$root.'/Http/Controllers/'.str_replace('\\','/',$import).'.php';if(!is_file($path))$errors[]='Missing controller: '.$import;}
preg_match_all("/Route::(?:get|post|put|patch|delete)\\('([^']+)'/",$route,$paths);
foreach($paths[1] as $path){if(substr_count($path,'{')!==substr_count($path,'}'))$errors[]='Malformed route URI: '.$path;}
$tables=[];foreach(glob($root.'/Database/Migrations/*.php') as $migration){$body=file_get_contents($migration);if(preg_match_all("/Schema::create\\('([^']+)'/",$body,$matches))foreach($matches[1] as $table){$tables[$table]=($tables[$table]??0)+1;}}
foreach($tables as $table=>$count)if($count>1)$errors[]="Duplicate migration table create: $table";
foreach(['services'=>'Services','models'=>'Models','controllers'=>'Http/Controllers'] as $label=>$path){if(!is_dir($root.'/'.$path))$errors[]="Missing directory: $path";}
if($errors){fwrite(STDERR,"PRIYASA CORE VALIDATION FAILED\n".implode("\n",array_unique($errors))."\n");exit(1);}echo "PRIYASA CORE VALIDATION: PASS\n";
