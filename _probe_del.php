<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
echo "PRAGMA foreign_keys = "; var_dump(DB::select('PRAGMA foreign_keys'));
// find tables with hospital_id and their FK on_delete action
$tables = DB::select("SELECT name FROM sqlite_master WHERE type='table'");
$noCascade=[];
foreach($tables as $t){
  $fks = DB::select("PRAGMA foreign_key_list(".$t->name.")");
  foreach($fks as $fk){
    if($fk->table==='hospitals'){
      if(strtoupper($fk->on_delete)!=='CASCADE'){
        $noCascade[]=$t->name.' ('.$fk->from.' -> on_delete='.$fk->on_delete.')';
      }
    }
  }
}
echo "\n=== Tables referencing hospitals WITHOUT cascade ===\n";
echo empty($noCascade)? "(none)\n" : implode("\n",$noCascade)."\n";
