@extends('layouts.app')
@section('title', 'Clinical Nutrition')
@section('page-title', 'Dietary / Clinical Nutrition')

@php use App\Modules\Dietary\Models\TherapeuticDiet as TD; use App\Modules\Dietary\Models\DietOrder as DOrder; use App\Modules\Dietary\Models\NutritionAssessment as NA; @endphp

@section('content')
<div x-data="nutrition()">
    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">{{ session('error') }}</div>@endif

    <div class="grid grid-cols-4 gap-3 mb-6">
        <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Active diet orders</p><p class="text-2xl font-bold text-slate-800">{{ $counts['active'] }}</p></div>
        <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">NPO (nil by mouth)</p><p class="text-2xl font-bold text-red-600">{{ $counts['npo'] }}</p></div>
        <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Tube-fed (NG/PEG)</p><p class="text-2xl font-bold text-amber-600">{{ $counts['tube'] }}</p></div>
        <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">High nutrition risk</p><p class="text-2xl font-bold text-orange-600">{{ $counts['high_risk'] }}</p></div>
    </div>

    <div class="flex flex-wrap items-center gap-2 mb-4">
        <button type="button" @click="tab='orders'" :class="tab==='orders'?'bg-blue-600 text-white':'bg-white border border-slate-200 text-slate-600'" class="px-3 py-1.5 rounded-lg text-sm font-semibold">Diet Orders</button>
        <button type="button" @click="tab='census'" :class="tab==='census'?'bg-blue-600 text-white':'bg-white border border-slate-200 text-slate-600'" class="px-3 py-1.5 rounded-lg text-sm font-semibold">Kitchen Census</button>
        <button type="button" @click="tab='assess'" :class="tab==='assess'?'bg-blue-600 text-white':'bg-white border border-slate-200 text-slate-600'" class="px-3 py-1.5 rounded-lg text-sm font-semibold">Nutrition Assessments</button>
        <button type="button" @click="tab='catalog'" :class="tab==='catalog'?'bg-blue-600 text-white':'bg-white border border-slate-200 text-slate-600'" class="px-3 py-1.5 rounded-lg text-sm font-semibold">Diet Catalogue</button>
        <div class="ml-auto flex gap-2">
            <button type="button" @click="openAssess()" class="btn-secondary text-sm">+ Assessment</button>
            <button type="button" @click="openOrder()" class="btn-primary">+ Order diet</button>
        </div>
    </div>

    {{-- DIET ORDERS --}}
    <div x-show="tab==='orders'" class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-200"><tr>
                    <th class="table-header">Patient</th><th class="table-header">Ward</th><th class="table-header">Diet</th><th class="table-header">Texture / Route</th><th class="table-header text-center">Targets</th><th class="table-header">Ordered by</th><th class="table-header text-right">Status</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($orders as $o)
                    <tr class="{{ $o->status==='discontinued' ? 'opacity-50' : '' }}">
                        <td class="px-4 py-2.5 text-sm text-slate-800">{{ $o->patient?->name ?? '—' }}<span class="block text-xs text-slate-400">{{ $o->patient?->phone }}</span></td>
                        <td class="px-4 py-2.5 text-sm text-slate-600">{{ $o->ward ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-sm text-slate-800">{{ $o->diet?->name ?? '—' }}<span class="text-xs text-slate-400"> {{ $o->diet?->code }}</span></td>
                        <td class="px-4 py-2.5 text-xs text-slate-600">
                            {{ TD::TEXTURES[$o->texture] ?? $o->texture }}
                            @php $rc = ['npo'=>'text-red-600 font-semibold','ng_tube'=>'text-amber-600','peg'=>'text-amber-600'][$o->route] ?? 'text-slate-500'; @endphp
                            <span class="block {{ $rc }}">{{ DOrder::ROUTES[$o->route] ?? $o->route }}{{ $o->fluid_restriction_ml ? ' · fluid '.$o->fluid_restriction_ml.'ml' : '' }}</span>
                        </td>
                        <td class="px-4 py-2.5 text-xs text-slate-600 text-center">{{ $o->kcal_target ? $o->kcal_target.' kcal' : '—' }}<span class="block">{{ $o->protein_target_g ? $o->protein_target_g.'g protein' : '' }}</span></td>
                        <td class="px-4 py-2.5 text-xs text-slate-500">{{ $o->ordered_by_name ?? '—' }}<span class="block">{{ optional($o->start_date)->format('M d') }}</span></td>
                        <td class="px-4 py-2.5 text-right">
                            @if($o->status==='active')
                                <form method="POST" action="{{ route('web.dietary.orders.discontinue', $o->id) }}" onsubmit="return confirm('Discontinue this diet order?')">@csrf<button type="submit" class="text-xs font-medium text-red-500 hover:text-red-700">Discontinue</button></form>
                            @else<span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-500">Discontinued</span>@endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-sm text-slate-400">No diet orders.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- KITCHEN CENSUS --}}
    <div x-show="tab==='census'" style="display:none" class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-200 flex items-center justify-between">
            <h4 class="text-sm font-semibold text-slate-500 uppercase tracking-wider">Active Diet Census — kitchen production</h4>
            <span class="text-sm text-slate-500">Total trays: {{ $census->sum('qty') }}</span>
        </div>
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200"><tr>
                <th class="table-header">Diet</th><th class="table-header">Texture</th><th class="table-header text-right">Trays to prepare</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($census as $c)
                <tr>
                    <td class="px-4 py-2.5 text-sm text-slate-800">{{ $c->diet }}<span class="text-xs text-slate-400"> {{ $c->code }}</span></td>
                    <td class="px-4 py-2.5 text-xs text-slate-600">{{ TD::TEXTURES[$c->texture] ?? $c->texture }}</td>
                    <td class="px-4 py-2.5 text-right"><span class="text-lg font-bold text-slate-800">{{ $c->qty }}</span></td>
                </tr>
                @empty
                <tr><td colspan="3" class="px-4 py-8 text-center text-sm text-slate-400">No active diet orders to prepare.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- NUTRITION ASSESSMENTS --}}
    <div x-show="tab==='assess'" style="display:none" class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200"><tr>
                <th class="table-header">Patient</th><th class="table-header">Tool / Score</th><th class="table-header">Risk</th><th class="table-header text-center">BMI</th><th class="table-header">Plan</th><th class="table-header">Follow-up</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($assessments as $a)
                @php $rk = ['low'=>'bg-green-100 text-green-700','medium'=>'bg-amber-100 text-amber-700','high'=>'bg-red-100 text-red-700'][$a->risk] ?? 'bg-slate-100'; @endphp
                <tr>
                    <td class="px-4 py-2.5 text-sm text-slate-800">{{ $a->patient?->name ?? '—' }}</td>
                    <td class="px-4 py-2.5 text-xs text-slate-600">{{ $a->tool }}{{ $a->score !== null ? ' · '.$a->score : '' }}</td>
                    <td class="px-4 py-2.5"><span class="text-xs px-2 py-0.5 rounded-full {{ $rk }}">{{ NA::RISKS[$a->risk] ?? $a->risk }}</span></td>
                    <td class="px-4 py-2.5 text-center text-sm text-slate-700">{{ $a->bmi ?? '—' }}</td>
                    <td class="px-4 py-2.5 text-xs text-slate-500 max-w-xs truncate">{{ $a->plan ?? $a->diagnosis ?? '—' }}</td>
                    <td class="px-4 py-2.5 text-xs text-slate-500">{{ optional($a->follow_up_date)->format('M d, Y') ?? '—' }}</td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-slate-400">No assessments yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- CATALOGUE --}}
    <div x-show="tab==='catalog'" style="display:none">
        <div class="flex justify-end mb-3"><button type="button" @click="openDiet()" class="btn-secondary text-sm">+ Add diet</button></div>
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-200"><tr>
                    <th class="table-header">Diet</th><th class="table-header">Category</th><th class="table-header">Default texture</th><th class="table-header text-center">kcal / protein</th><th class="table-header">Restrictions</th><th class="table-header text-right"></th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($diets as $d)
                    <tr class="{{ $d->is_active ? '' : 'opacity-50' }}">
                        <td class="px-4 py-2.5 text-sm text-slate-800">{{ $d->name }}<span class="text-xs text-slate-400"> {{ $d->code }}</span><span class="block text-xs text-slate-400">{{ $d->indications }}</span></td>
                        <td class="px-4 py-2.5 text-sm text-slate-600">{{ TD::CATEGORIES[$d->category] ?? $d->category }}</td>
                        <td class="px-4 py-2.5 text-xs text-slate-600">{{ TD::TEXTURES[$d->default_texture] ?? $d->default_texture }}</td>
                        <td class="px-4 py-2.5 text-xs text-slate-600 text-center">{{ $d->default_kcal ?? '—' }} / {{ $d->default_protein_g ?? '—' }}g</td>
                        <td class="px-4 py-2.5 text-xs text-slate-500 max-w-xs truncate">{{ $d->restrictions ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-right"><button type="button" @click="openDiet({ id: @js($d->id), code: @js($d->code), name: @js($d->name), category: @js($d->category), default_texture: @js($d->default_texture), indications: @js($d->indications ?? ''), restrictions: @js($d->restrictions ?? ''), default_kcal: {{ $d->default_kcal ?: 'null' }}, default_protein_g: {{ $d->default_protein_g ?: 'null' }}, is_active: {{ $d->is_active ? 'true' : 'false' }} })" class="text-xs font-medium text-blue-600 hover:text-blue-800">Edit</button></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- ORDER DIET modal --}}
    <x-modal show="orderModal" title="Order Therapeutic Diet" max="2xl">
        <form method="POST" action="{{ route('web.dietary.order') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="patient_id" :value="selectedPatient?.id || ''">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="relative">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Patient</label>
                    <div x-show="selectedPatient" class="flex items-center justify-between px-3 py-2 bg-blue-50 border border-blue-200 rounded-lg">
                        <span class="text-sm font-medium text-slate-800"><span x-text="selectedPatient?.name"></span> <span class="text-slate-400" x-text="selectedPatient?.phone"></span></span>
                        <button type="button" @click="selectedPatient=null" class="text-slate-400 text-lg leading-none">&times;</button>
                    </div>
                    <div x-show="!selectedPatient">
                        <input type="text" x-model="patientSearch" @input.debounce.300ms="searchPatients()" class="input-field" placeholder="Search patient…" autocomplete="off">
                        <div x-show="patientResults.length" class="absolute z-30 left-0 right-0 mt-1 bg-white border border-slate-200 rounded-lg shadow-xl" style="display:none">
                            <template x-for="p in patientResults" :key="p.id"><button type="button" @click="pickPatient(p)" class="w-full flex justify-between p-2.5 hover:bg-blue-50 text-left border-b border-slate-100 last:border-0"><span class="text-sm text-slate-800" x-text="p.name"></span><span class="text-xs text-slate-400" x-text="p.phone"></span></button></template>
                        </div>
                    </div>
                </div>
                <div><label class="block text-sm font-medium text-slate-700 mb-1">Ward / Bed</label><input type="text" name="ward" x-model="ord.ward" class="input-field" placeholder="e.g. Ward 3 / Bed 12"></div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Diet</label>
                <select name="diet_id" x-model="ord.diet_id" @change="applyDiet()" required class="input-field">
                    <option value="">Select therapeutic diet…</option>
                    @foreach($diets->where('is_active', true) as $d)<option value="{{ $d->id }}">{{ $d->name }} ({{ $d->code }})</option>@endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Texture (IDDSI)</label>
                    <select name="texture" x-model="ord.texture" class="input-field">@foreach(TD::TEXTURES as $k=>$l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Route</label>
                    <select name="route" x-model="ord.route" class="input-field">@foreach(DOrder::ROUTES as $k=>$l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                </div>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div><label class="block text-sm font-medium text-slate-700 mb-1">kcal target</label><input type="number" name="kcal_target" x-model="ord.kcal_target" min="0" class="input-field"></div>
                <div><label class="block text-sm font-medium text-slate-700 mb-1">Protein (g)</label><input type="number" name="protein_target_g" x-model="ord.protein_target_g" min="0" class="input-field"></div>
                <div><label class="block text-sm font-medium text-slate-700 mb-1">Fluid restriction (ml)</label><input type="number" name="fluid_restriction_ml" min="0" class="input-field" placeholder="none"></div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Restrictions / allergies</label>
                <input type="text" name="restrictions" maxlength="500" class="input-field" placeholder="e.g. nut allergy, no pork, lactose-free">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Special instructions <span class="text-slate-400 font-normal">(optional)</span></label>
                <input type="text" name="special_instructions" maxlength="500" class="input-field" placeholder="e.g. small frequent feeds, thicken fluids">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="block text-sm font-medium text-slate-700 mb-1">Start date</label><input type="date" name="start_date" x-model="ord.start_date" required class="input-field"></div>
                <div><label class="block text-sm font-medium text-slate-700 mb-1">End date <span class="text-slate-400 font-normal">(optional)</span></label><input type="date" name="end_date" class="input-field"></div>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="orderModal=false" class="btn-secondary text-sm">Cancel</button>
                <button type="submit" class="btn-primary" :disabled="!selectedPatient || !ord.diet_id" :class="(!selectedPatient || !ord.diet_id) ? 'opacity-40' : ''">Place order</button>
            </div>
        </form>
    </x-modal>

    {{-- ASSESSMENT modal --}}
    <x-modal show="assessModal" title="Nutrition Assessment" max="2xl">
        <form method="POST" action="{{ route('web.dietary.assessment') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="patient_id" :value="asPatient?.id || ''">
            <div class="relative">
                <label class="block text-sm font-medium text-slate-700 mb-1">Patient</label>
                <div x-show="asPatient" class="flex items-center justify-between px-3 py-2 bg-blue-50 border border-blue-200 rounded-lg">
                    <span class="text-sm text-slate-800" x-text="asPatient?.name"></span><button type="button" @click="asPatient=null" class="text-slate-400 text-lg leading-none">&times;</button>
                </div>
                <div x-show="!asPatient">
                    <input type="text" x-model="asSearch" @input.debounce.300ms="searchAssess()" class="input-field" placeholder="Search patient…" autocomplete="off">
                    <div x-show="asResults.length" class="absolute z-30 left-0 right-0 mt-1 bg-white border border-slate-200 rounded-lg shadow-xl" style="display:none">
                        <template x-for="p in asResults" :key="p.id"><button type="button" @click="asPatient=p; asResults=[]; asSearch=''" class="w-full flex justify-between p-2.5 hover:bg-blue-50 text-left border-b border-slate-100 last:border-0"><span class="text-sm text-slate-800" x-text="p.name"></span><span class="text-xs text-slate-400" x-text="p.phone"></span></button></template>
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div><label class="block text-sm font-medium text-slate-700 mb-1">Screening tool</label><select name="tool" class="input-field">@foreach(NA::TOOLS as $k=>$l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                <div><label class="block text-sm font-medium text-slate-700 mb-1">Score</label><input type="number" name="score" min="0" class="input-field"></div>
                <div><label class="block text-sm font-medium text-slate-700 mb-1">Risk</label><select name="risk" class="input-field">@foreach(NA::RISKS as $k=>$l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="block text-sm font-medium text-slate-700 mb-1">Weight (kg)</label><input type="number" step="0.1" name="weight_kg" class="input-field"></div>
                <div><label class="block text-sm font-medium text-slate-700 mb-1">Height (cm)</label><input type="number" step="0.1" name="height_cm" class="input-field"></div>
            </div>
            <div><label class="block text-sm font-medium text-slate-700 mb-1">Diagnosis / findings</label><input type="text" name="diagnosis" maxlength="500" class="input-field"></div>
            <div><label class="block text-sm font-medium text-slate-700 mb-1">Nutrition plan</label><textarea name="plan" rows="2" class="input-field" placeholder="Recommended diet, supplements, targets, monitoring"></textarea></div>
            <div><label class="block text-sm font-medium text-slate-700 mb-1">Follow-up date</label><input type="date" name="follow_up_date" class="input-field"></div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="assessModal=false" class="btn-secondary text-sm">Cancel</button>
                <button type="submit" class="btn-primary" :disabled="!asPatient" :class="!asPatient ? 'opacity-40' : ''">Save assessment</button>
            </div>
        </form>
    </x-modal>

    {{-- CATALOGUE modal --}}
    <x-modal show="dietModal" title-expr="diet.id ? 'Edit Diet' : 'Add Diet'" max="2xl">
        <form method="POST" :action="diet.id ? '/dietary/diets/' + diet.id : '{{ route('web.dietary.diets.store') }}'" class="space-y-4">
            @csrf
            <template x-if="diet.id"><input type="hidden" name="_method" value="PUT"></template>
            <div class="grid grid-cols-3 gap-3">
                <div><label class="block text-sm font-medium text-slate-700 mb-1">Code</label><input type="text" name="code" x-model="diet.code" required class="input-field"></div>
                <div class="col-span-2"><label class="block text-sm font-medium text-slate-700 mb-1">Name</label><input type="text" name="name" x-model="diet.name" required class="input-field"></div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="block text-sm font-medium text-slate-700 mb-1">Category</label><select name="category" x-model="diet.category" class="input-field">@foreach(TD::CATEGORIES as $k=>$l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                <div><label class="block text-sm font-medium text-slate-700 mb-1">Default texture</label><select name="default_texture" x-model="diet.default_texture" class="input-field">@foreach(TD::TEXTURES as $k=>$l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="block text-sm font-medium text-slate-700 mb-1">Default kcal</label><input type="number" name="default_kcal" x-model="diet.default_kcal" min="0" class="input-field"></div>
                <div><label class="block text-sm font-medium text-slate-700 mb-1">Default protein (g)</label><input type="number" name="default_protein_g" x-model="diet.default_protein_g" min="0" class="input-field"></div>
            </div>
            <div><label class="block text-sm font-medium text-slate-700 mb-1">Indications</label><input type="text" name="indications" x-model="diet.indications" class="input-field"></div>
            <div><label class="block text-sm font-medium text-slate-700 mb-1">Restrictions</label><input type="text" name="restrictions" x-model="diet.restrictions" class="input-field"></div>
            <template x-if="diet.id"><label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" x-model="diet.is_active" class="rounded border-slate-300"><span class="text-slate-600">Active</span></label></template>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="dietModal=false" class="btn-secondary text-sm">Cancel</button>
                <button type="submit" class="btn-primary">Save</button>
            </div>
        </form>
    </x-modal>
</div>

@push('scripts')
<script>
function nutrition() {
    return {
        tab: 'orders', orderModal: false, assessModal: false, dietModal: false,
        diets: @js($diets->where('is_active', true)->map(fn($d)=>['id'=>$d->id,'texture'=>$d->default_texture,'kcal'=>$d->default_kcal,'protein'=>$d->default_protein_g,'category'=>$d->category])->values()),
        patientSearch: '', patientResults: [], selectedPatient: null,
        ord: { ward: '', diet_id: '', texture: 'regular', route: 'oral', kcal_target: '', protein_target_g: '', start_date: '{{ now()->toDateString() }}' },
        asSearch: '', asResults: [], asPatient: null,
        diet: { id: '', code: '', name: '', category: 'therapeutic', default_texture: 'regular', indications: '', restrictions: '', default_kcal: null, default_protein_g: null, is_active: true },
        openOrder() { this.selectedPatient = null; this.patientSearch = ''; this.patientResults = []; this.ord = { ward: '', diet_id: '', texture: 'regular', route: 'oral', kcal_target: '', protein_target_g: '', start_date: '{{ now()->toDateString() }}' }; this.orderModal = true; },
        openAssess() { this.asPatient = null; this.asSearch = ''; this.asResults = []; this.assessModal = true; },
        openDiet(d) { this.diet = d ? { ...d } : { id: '', code: '', name: '', category: 'therapeutic', default_texture: 'regular', indications: '', restrictions: '', default_kcal: null, default_protein_g: null, is_active: true }; this.dietModal = true; },
        applyDiet() {
            const d = this.diets.find(x => x.id === this.ord.diet_id);
            if (!d) return;
            this.ord.texture = d.texture; this.ord.kcal_target = d.kcal ?? ''; this.ord.protein_target_g = d.protein ?? '';
            if (d.category === 'npo') this.ord.route = 'npo';
        },
        async _find(q) { try { const r = await fetch('/ajax/patients?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' }); return r.ok ? await r.json() : []; } catch (e) { return []; } },
        async searchPatients() { this.patientResults = this.patientSearch.trim().length < 2 ? [] : await this._find(this.patientSearch.trim()); },
        async searchAssess() { this.asResults = this.asSearch.trim().length < 2 ? [] : await this._find(this.asSearch.trim()); },
        pickPatient(p) { this.selectedPatient = p; this.patientResults = []; this.patientSearch = ''; },
    };
}
</script>
@endpush
@endsection
