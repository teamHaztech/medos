<?php
/**
 * Throwaway verification harness — seeds realistic dispense + lab activity through
 * the REAL ChargeCapture path inside a transaction, renders both insight pages, asserts
 * the numbers populate, then rolls everything back. Deleted after use.
 */
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\{Auth, View, Session, DB, Config};
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Modules\Core\Models\Order;
use App\Modules\Billing\Services\ChargeCapture;

Session::start();
View::share('errors', new Illuminate\Support\ViewErrorBag());

$user = App\Models\User::where('email','pharmacy@haztech.in')->first();
Auth::setUser($user);
$hid = $user->hospital_id;
Config::set('medos.current_hospital_id', $hid);

$patientId = DB::table('patients')->where('hospital_id',$hid)->value('id');
$meds  = DB::table('medicines')->where('is_active',true)->orderBy('name')->limit(6)->pluck('name')->all();
$tests = DB::table('available_tests')->limit(6)->pluck('name')->all();
echo "patient=$patientId meds=".count($meds)." tests=".count($tests)."\n";

$cc = app(ChargeCapture::class);
DB::beginTransaction();

function mkOrder($hid,$patientId,$type,$status,$items,$when){
    $o = new Order();
    $o->id = Str::uuid()->toString();
    $o->hospital_id = $hid; $o->patient_id = $patientId;
    $o->type=$type; $o->status=$status; $o->priority='routine';
    $o->items=$items; $o->completed_at=$when;
    $o->created_at=$when; $o->updated_at=$when;
    $o->save();
    return $o;
}

// Spread 25 dispensed prescriptions + 25 completed lab orders across this month incl. today.
$now = now();
for ($i=0; $i<25; $i++){
    $when = $now->copy()->startOfMonth()->addDays(rand(0, (int)$now->diffInDays($now->copy()->startOfMonth())))->setTime(rand(9,18), rand(0,59));
    if ($i < 6) $when = $now->copy()->setTime(rand(9, max(9,(int)$now->format('H'))), rand(0,59)); // some today

    // Pharmacy: 1-3 medicine lines, explicit prices so revenue is deterministic
    $pItems = [];
    $pick = (array) array_rand(array_flip($meds), min(rand(1,3), count($meds)));
    foreach ($pick as $mn){ $pItems[] = ['name'=>$mn, 'quantity'=>rand(1,4), 'price'=>rand(20,300)]; }
    $po = mkOrder($hid,$patientId,'pharmacy','dispensed',$pItems,$when);
    $cc->capturePharmacyDispense($po, 'Verify');

    // Lab: 1-2 tests
    $lItems = [];
    $lpick = (array) array_rand(array_flip($tests), min(rand(1,2), count($tests)));
    foreach ($lpick as $tn){ $lItems[] = ['name'=>$tn, 'price'=>rand(150,1200)]; }
    $lo = mkOrder($hid,$patientId,'lab','completed',$lItems,$when);
    $cc->captureOrder($lo, 'Verify');
}

echo "charge_items pharmacy=".DB::table('charge_items')->where('source','pharmacy')->count()
    ." lab=".DB::table('charge_items')->where('source','lab')->count()."\n";

// --- Render both pages for month + today ---
$pc = new App\Http\Controllers\Web\PharmacyController();
$lc = new App\Http\Controllers\Web\LabController();
$ins = app(App\Modules\Analytics\Services\RevenueInsights::class);

foreach (['month','today','week','year'] as $period){
    $req = Request::create('/pharmacy/insights','GET',['period'=>$period]);
    $html = $pc->insights($req, $ins)->render();
    $lreq = Request::create('/lab/insights','GET',['period'=>$period]);
    $lhtml = $lc->insights($lreq, $ins)->render();
    printf("period=%-6s pharmacy_html=%d lab_html=%d\n", $period, strlen($html), strlen($lhtml));
}

// Assert month KPIs populated
$req = Request::create('/pharmacy/insights','GET',['period'=>'month']);
$view = $pc->insights($req, $ins);
$data = $view->getData();
echo "\n--- Pharmacy (month) KPIs ---\n";
echo "revenue=".$data['kpis']['revenue']." rx=".$data['kpis']['rx']." units=".$data['kpis']['units']." avg_rx=".$data['kpis']['avg_rx']."\n";
echo "top by revenue: "; foreach($data['byRevenue']->take(3) as $m){ echo $m->description."(".$m->revenue.") "; } echo "\n";
echo "top by volume:  "; foreach($data['byVolume']->take(3) as $m){ echo $m->description."(".$m->units."u) "; } echo "\n";
echo "inventory cost=".$data['inventory']['cost_value']." retail=".$data['inventory']['retail_value']." low=".$data['inventory']['low']."\n";

$lreq = Request::create('/lab/insights','GET',['period'=>'month']);
$ldata = $lc->insights($lreq, $ins)->getData();
echo "\n--- Lab (month) KPIs ---\n";
echo "revenue=".$ldata['kpis']['revenue']." tests=".$ldata['kpis']['tests']." completed=".$ldata['kpis']['completed']." avg_tat=".$ldata['kpis']['avg_tat']."\n";
echo "most performed: "; foreach($ldata['byVolume']->take(3) as $t){ echo $t->description."(".$t->lines."x) "; } echo "\n";
echo "categories: "; foreach($ldata['categories'] as $ct){ echo $ct->source."=".$ct->revenue." "; } echo "\n";

DB::rollBack();
echo "\nrolled back. charge_items now=".DB::table('charge_items')->count()."\n";
echo "OK\n";
