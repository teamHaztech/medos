<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
echo "=== HOSPITALS ===\n";
foreach (DB::table('hospitals')->get() as $h) {
  printf("%s | %s | active=%s | slug=%s\n", substr($h->id,0,8), $h->name, $h->is_active, $h->slug);
}
echo "\n=== SUPER ADMINS (their hospital_id) ===\n";
foreach (DB::table('users')->where('role','super_admin')->get() as $u) {
  printf("%s | hospital_id=%s\n", $u->email, substr((string)$u->hospital_id,0,8));
}
echo "\n=== Row counts per hospital (users/staff) ===\n";
foreach (DB::table('hospitals')->pluck('name','id') as $id=>$name){
  $u=DB::table('users')->where('hospital_id',$id)->count();
  $s=DB::table('staff')->where('hospital_id',$id)->count();
  printf("%-22s users=%d staff=%d\n",$name,$u,$s);
}
