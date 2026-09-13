<?php

declare(strict_types=1);

/**
 * Static certification for the complete cumulative PriyasaCore module.
 * Runtime certification is `php artisan priyasa:api-certify` in the host app.
 */
$root = dirname(__DIR__);
$fail = [];
$warn = [];
$pass = [];

function check(bool $ok, string $message, array &$pass, array &$fail): void
{
    if ($ok) $pass[] = $message; else $fail[] = $message;
}

function readSafe(string $file): string { return is_file($file) ? (string) file_get_contents($file) : ''; }

// 1. Syntax.
$phpFiles = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) if ($f instanceof SplFileInfo && $f->getExtension() === 'php') $phpFiles[] = $f;
$bad = [];
foreach ($phpFiles as $f) {
    $out=[]; $code=0; exec('php -l '.escapeshellarg($f->getPathname()).' 2>&1',$out,$code);
    if ($code !== 0) $bad[] = $f->getPathname();
}
check(!$bad, count($phpFiles).' PHP files pass syntax validation', $pass, $fail);
if ($bad) $fail[]='Syntax failures: '.implode(', ',$bad);

// 2. PSR-4 duplicate classes.
$classes=[];
foreach ($phpFiles as $f) {
    $s=readSafe($f->getPathname());
    if (!preg_match('/namespace\s+([^;]+);/',$s,$nm)) continue;
    if (preg_match_all('/\bclass\s+(\w+)/',$s,$cm)) foreach($cm[1] as $cl) $classes[$nm[1].'\\'.$cl][]=$f->getPathname();
}
$dupeClasses=array_filter($classes,fn($v)=>count($v)>1);
check(!$dupeClasses,'No duplicate PHP class definitions', $pass,$fail);
foreach($dupeClasses as $c=>$fs) $fail[]='Duplicate class '.$c.': '.implode(', ',$fs);

// 3. Migration integrity: duplicate exact content is a hard failure; same timestamps with distinct
// migrations are retained for upgrade compatibility and are only informational.
$migrations=glob($root.'/Database/Migrations/*.php')?:[];
$hashes=[]; $timestamps=[];
foreach($migrations as $f){
    $hash=hash_file('sha256',$f); $hashes[$hash][]=basename($f);
    if(preg_match('/^(\d{4}_\d{2}_\d{2}_\d{6})_/',basename($f),$m)) $timestamps[$m[1]][]=basename($f);
}
$dupContent=array_filter($hashes,fn($v)=>count($v)>1);
check(!$dupContent,count($migrations).' migration files have no duplicate content', $pass,$fail);
foreach($dupContent as $names) $fail[]='Duplicate migration content: '.implode(', ',$names);
foreach($timestamps as $ts=>$names) if(count($names)>1) $warn[]='Legacy shared migration timestamp '.$ts.' (distinct migrations): '.implode(', ',$names);

// 4. Canonical route loader.
$api=readSafe($root.'/Routes/api.php'); $features=readSafe($root.'/Routes/api_features.php');
check(is_file($root.'/Providers/PriyasaCoreServiceProvider.php'),'PriyasaCoreServiceProvider.php is present',$pass,$fail);
check(str_contains(readSafe($root.'/Providers/PriyasaCoreServiceProvider.php'),'api_features.php'),'Provider loads the consolidated feature route file',$pass,$fail);
check(!preg_match("~prefix\(\s*['\"]api/v1~",$features),'Consolidated feature routes do not own /api/v1 prefix',$pass,$fail);
check(!preg_match("~['\"]/api/v1/~",$features),'Consolidated feature routes do not contain absolute /api/v1 paths',$pass,$fail);

// 5. Core schema contract references.
$order=readSafe($root.'/Services/OrderService.php');
$analytics=readSafe($root.'/Services/AnalyticsService.php');
$finance=readSafe($root.'/Services/FinancialReconciliationService.php');
check(str_contains($order,'grand_total'),'OrderService uses canonical grand_total',$pass,$fail);
check(!str_contains($analytics,"total_amount"),'AnalyticsService does not depend on legacy total_amount',$pass,$fail);
check(!str_contains($finance,"total_amount"),'FinancialReconciliationService does not depend on legacy total_amount',$pass,$fail);

// 6. Required production assets.
foreach([
    'Services/StorefrontApiContract.php','Http/Middleware/ApiContractHeaders.php','Http/Middleware/ApiContractResponse.php',
    'Http/Middleware/AdminPermissionV2.php','Http/Middleware/IdempotencyV2.php','Services/CheckoutTransactionService.php',
    'Services/InventoryReservationService.php','Services/AdminActionService.php','Console/Commands/BuildApiContract.php',
    'Console/Commands/CertifyProductionApi.php','docs/openapi.yaml','docs/swagger.html',
] as $file) check(is_file($root.'/'.$file),'Required asset present: '.$file,$pass,$fail);

$tests=count(glob($root.'/Tests/Feature/*.php')?:[])+count(glob($root.'/tests/Feature/*.php')?:[]);
check($tests>=10,'Feature-contract/E2E test inventory present: '.$tests,$pass,$fail);

$result=['status'=>$fail?'BLOCKED':'STATIC_CERTIFIED','php_files'=>count($phpFiles),'migration_files'=>count($migrations),'passes'=>$pass,'failures'=>$fail,'warnings'=>$warn,'note'=>'Static certification is not a substitute for running the host Laravel application against a disposable production-like database and external-provider sandboxes.'];
file_put_contents($root.'/docs/p52-certification-report.json',json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);
echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($fail?1:0);
