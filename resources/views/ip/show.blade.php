@extends('layouts.app')
@section('title', $admission->admission_no)
@section('page-title', 'IP Case Sheet')

@php
    $p = $admission->patient;
    $allergies = is_array($p?->allergies) ? $p->allergies : [];
    $wardData = $wards->map(fn ($w) => [
        'id' => $w->id, 'name' => $w->name,
        'beds' => $w->beds->map(fn ($b) => ['id' => $b->id, 'number' => $b->bed_number])->values(),
    ])->values();
    $intakeCats = \App\Modules\Inpatient\Models\IpIntakeOutput::INTAKE_CATEGORIES;
    $outputCats = \App\Modules\Inpatient\Models\IpIntakeOutput::OUTPUT_CATEGORIES;
@endphp

@section('content')
<div x-data="{
        vOpen: false, ioOpen: false, noteOpen: false, tOpen: false, dOpen: false, chargeOpen: false,
        wards: {{ Illuminate\Support\Js::from($wardData) }},
        tWard: '',
        get tBeds() { return (this.wards.find(w => w.id === this.tWard) || {}).beds || []; },
        ioDir: 'intake',
        intakeCats: {{ Illuminate\Support\Js::from($intakeCats) }},
        outputCats: {{ Illuminate\Support\Js::from($outputCats) }},
        get ioCats() { return this.ioDir === 'output' ? this.outputCats : this.intakeCats; }
    }">

    <a href="{{ route('web.ip.admissions') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Back to admissions</a>

    @if(session('success'))<div class="my-4 px-4 py-2.5 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="my-4 px-4 py-2.5 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">{{ session('error') }}</div>@endif

    {{-- Header --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mt-3 mb-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-xl font-bold text-slate-900">{{ $p?->name ?? 'Patient' }}
                    <span class="text-sm font-normal text-slate-400">· {{ $admission->admission_no }}</span>
                </h2>
                <p class="text-sm text-slate-500">
                    {{ $p?->phone }}{{ $p?->gender ? ' · ' . ucfirst($p->gender) : '' }}{{ $p?->age_approximate ? ' · ' . $p->age_approximate . ' yrs' : '' }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                @if($admission->isActive())
                    <span class="px-3 py-1 rounded-full text-sm font-semibold bg-blue-100 text-blue-700">Admitted</span>
                    <button @click="tOpen = true" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 text-slate-600 hover:bg-slate-200">Transfer</button>
                    <button @click="dOpen = true" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-red-50 text-red-700 hover:bg-red-100">Discharge</button>
                @else
                    <span class="px-3 py-1 rounded-full text-sm font-semibold bg-slate-200 text-slate-600">Discharged</span>
                @endif
            </div>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-5 text-sm">
            <div><p class="text-xs text-slate-400">Ward / Bed</p><p class="font-medium text-slate-700">{{ $admission->ward?->name ?? '-' }}{{ $admission->bed?->bed_number ? ' · ' . $admission->bed->bed_number : '' }}</p></div>
            <div><p class="text-xs text-slate-400">Attending Doctor</p><p class="font-medium text-slate-700">{{ $admission->attendingDoctor?->name ?? '-' }}</p></div>
            <div><p class="text-xs text-slate-400">Admitted</p><p class="font-medium text-slate-700">{{ optional($admission->admitted_at)->format('M d, Y g:i A') }}</p></div>
            <div><p class="text-xs text-slate-400">Length of Stay</p><p class="font-medium text-slate-700">{{ $admission->lengthOfDays() }} day(s)</p></div>
        </div>
        <div class="flex flex-wrap gap-2 mt-4">
            @if($admission->provisional_diagnosis)<span class="px-2.5 py-1 rounded-lg text-xs bg-blue-50 text-blue-700 border border-blue-200">Dx: {{ $admission->provisional_diagnosis }}</span>@endif
            @foreach($allergies as $al)<span class="px-2.5 py-1 rounded-lg text-xs bg-red-50 text-red-700 border border-red-200">⚠ {{ $al }}</span>@endforeach
            @if(empty($allergies))<span class="px-2.5 py-1 rounded-lg text-xs bg-slate-50 text-slate-500 border border-slate-200">No known allergies</span>@endif
        </div>
        @if(!$admission->isActive() && $admission->discharge_summary)
            <div class="mt-4 p-3 bg-slate-50 border border-slate-200 rounded-lg text-sm">
                <p class="font-semibold text-slate-700">Discharge — {{ $admission->dischargeTypeLabel() }} · {{ optional($admission->discharged_at)->format('M d, Y') }}</p>
                <p class="text-slate-600 mt-0.5">{{ $admission->discharge_summary }}</p>
            </div>
        @endif
    </div>

    @if($admission->isActive())
    {{-- Discharge workflow --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 mb-6"
         x-data="{ id: '{{ $admission->id }}', c: { billing: {{ $admission->isCleared('billing') ? 'true' : 'false' }}, pharmacy: {{ $admission->isCleared('pharmacy') ? 'true' : 'false' }}, nursing: {{ $admission->isCleared('nursing') ? 'true' : 'false' }} }, busy: false,
            get done() { return Object.values(this.c).filter(Boolean).length; },
            get ready() { return this.done === 3; },
            async toggle(type) {
                if (this.busy) return; this.busy = true;
                try {
                    const r = await fetch('/ip/admissions/' + this.id + '/clearance', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify({ type }) });
                    const d = await r.json();
                    if (d.success) this.c[type] = d.cleared;
                } catch (e) {}
                this.busy = false;
            } }">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-slate-700">Discharge Workflow</h3>
            @if($admission->dischargeInitiated())
                <span class="text-xs font-semibold" :class="ready ? 'text-green-600' : 'text-amber-600'" x-text="ready ? 'Ready to leave' : ('In progress · ' + done + '/3 cleared')"></span>
            @endif
        </div>
        @if(!$admission->dischargeInitiated())
            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                <p class="text-sm text-slate-500 flex-1">Patient is in care. Start the discharge process when the doctor advises discharge.</p>
                <form method="POST" action="{{ route('web.ip.discharge.initiate', $admission->id) }}">
                    @csrf
                    <button type="submit" class="btn-secondary whitespace-nowrap">Initiate Discharge</button>
                </form>
            </div>
        @else
            <p class="text-xs text-slate-500 mb-2">Tap each clearance as it's completed:</p>
            <div class="flex flex-wrap items-center gap-2">
                @foreach(\App\Modules\Inpatient\Models\Admission::CLEARANCES as $ck => $clabel)
                    <button type="button" @click="toggle('{{ $ck }}')" :disabled="busy"
                        :class="c.{{ $ck }} ? 'bg-green-100 text-green-700 border-green-200' : 'bg-white text-slate-500 border-slate-200 hover:bg-slate-50'"
                        class="text-sm px-3 py-1.5 rounded-lg border font-medium">
                        <span x-text="(c.{{ $ck }} ? '✓ ' : '') + '{{ $clabel }}'"></span>
                    </button>
                @endforeach
            </div>
            <div class="mt-4 flex items-center gap-3">
                <button @click="dOpen = true" class="btn-primary px-5" :class="!ready ? 'opacity-60' : ''">Complete Discharge</button>
                <p class="text-xs text-slate-400" x-show="!ready">Finish all clearances, then complete discharge (add type + summary).</p>
            </div>
        @endif
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Vitals --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-slate-700">Vitals</h3>
                @if($admission->isActive())<button @click="vOpen = true" class="text-sm text-blue-600 hover:text-blue-800 font-medium">+ Record</button>@endif
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs text-slate-400 uppercase">
                        <tr><th class="text-left py-1 pr-3">Time</th><th class="text-left px-2">BP</th><th class="px-2">Temp</th><th class="px-2">Pulse</th><th class="px-2">SpO₂</th><th class="px-2">RR</th><th class="px-2">BMI</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @forelse($admission->vitals as $v)
                            @php $flags = $v->abnormalFlags(); @endphp
                            <tr class="{{ $v->isAbnormal() ? 'bg-red-50/40' : '' }}">
                                <td class="py-1.5 pr-3 text-slate-500 whitespace-nowrap">{{ optional($v->recorded_at)->format('M d, g:i A') }}</td>
                                <td class="px-2 text-center {{ in_array('bp',$flags) ? 'text-red-600 font-semibold' : 'text-slate-700' }}">{{ $v->bp_systolic ? $v->bp_systolic.'/'.$v->bp_diastolic : '—' }}</td>
                                <td class="px-2 text-center {{ in_array('temperature',$flags) ? 'text-red-600 font-semibold' : 'text-slate-700' }}">{{ $v->temperature ?? '—' }}</td>
                                <td class="px-2 text-center {{ in_array('pulse',$flags) ? 'text-red-600 font-semibold' : 'text-slate-700' }}">{{ $v->pulse ?? '—' }}</td>
                                <td class="px-2 text-center {{ in_array('spo2',$flags) ? 'text-red-600 font-semibold' : 'text-slate-700' }}">{{ $v->spo2 ?? '—' }}</td>
                                <td class="px-2 text-center {{ in_array('resp_rate',$flags) ? 'text-red-600 font-semibold' : 'text-slate-700' }}">{{ $v->resp_rate ?? '—' }}</td>
                                <td class="px-2 text-center text-slate-700">{{ $v->bmi ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-slate-400 py-6">No vitals recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Intake / Output --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-slate-700">Intake / Output
                    <span class="ml-1 text-xs font-normal {{ $admission->fluidBalance() < 0 ? 'text-amber-600' : 'text-slate-400' }}">(balance {{ $admission->fluidBalance() > 0 ? '+' : '' }}{{ number_format($admission->fluidBalance()) }} ml)</span>
                </h3>
                @if($admission->isActive())<button @click="ioOpen = true" class="text-sm text-blue-600 hover:text-blue-800 font-medium">+ Record</button>@endif
            </div>
            <div class="space-y-2 max-h-72 overflow-y-auto">
                @forelse($admission->intakeOutputs as $io)
                    <div class="flex items-center justify-between text-sm py-1.5 border-b border-slate-50 last:border-0">
                        <div>
                            <span class="px-1.5 py-0.5 rounded text-xs font-medium {{ $io->direction === 'intake' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">{{ ucfirst($io->direction) }}</span>
                            <span class="text-slate-600 ml-1">{{ $io->category }}</span>
                        </div>
                        <div class="text-right">
                            <span class="font-medium text-slate-700">{{ number_format($io->volume_ml) }} ml</span>
                            <span class="block text-[10px] text-slate-400">{{ optional($io->recorded_at)->format('M d, g:i A') }}</span>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-400 text-center py-6">No intake/output recorded.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Notes timeline --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mt-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-slate-700">Doctor &amp; Nurse Notes / Orders</h3>
            @if($admission->isActive())<button @click="noteOpen = true" class="text-sm text-blue-600 hover:text-blue-800 font-medium">+ Add Note</button>@endif
        </div>
        <div class="space-y-3">
            @forelse($admission->notes as $n)
                <div class="border-l-2 pl-3 {{ $n->note_type === 'nurse' ? 'border-pink-300' : ($n->note_type === 'order' ? 'border-amber-300' : 'border-blue-300') }}">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $n->note_type === 'nurse' ? 'bg-pink-100 text-pink-700' : ($n->note_type === 'order' ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700') }}">{{ $n->typeLabel() }}</span>
                        <span class="text-xs text-slate-400">{{ $n->author_name }} · {{ $n->created_at?->format('M d, Y g:i A') }}</span>
                    </div>
                    <p class="text-sm text-slate-700 mt-1 whitespace-pre-line">{{ $n->body }}</p>
                </div>
            @empty
                <p class="text-sm text-slate-400 text-center py-6">No notes yet.</p>
            @endforelse
        </div>
    </div>

    {{-- Inpatient running bill --}}
    @php $cur = \App\Modules\Core\Services\RegionService::currency(); @endphp
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mt-6">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div>
                <h3 class="text-sm font-semibold text-slate-700">Inpatient Bill</h3>
                <p class="text-xs text-slate-400">Room accrues automatically per day. Add procedures, consumables and investigations as they happen.</p>
            </div>
            <div class="flex items-center gap-2">
                @if($admission->isActive())<button @click="chargeOpen = true" class="btn-primary text-sm">+ Add charge</button>@endif
                <form method="POST" action="{{ route('web.ip.bill', $admission->id) }}">@csrf
                    <button type="submit" class="btn-primary text-sm whitespace-nowrap">{{ $ipBill ? 'Open bill' : 'Generate bill' }}</button>
                </form>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-200"><tr>
                    <th class="table-header">Source</th><th class="table-header">Description</th><th class="table-header text-center">Qty</th><th class="table-header text-right">Rate</th><th class="table-header text-right">Amount</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($charges as $ch)
                    <tr>
                        <td class="px-4 py-2.5"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-700">{{ $ch->sourceLabel() }}</span></td>
                        <td class="px-4 py-2.5 text-sm text-slate-800">{{ $ch->description }}</td>
                        <td class="px-4 py-2.5 text-sm text-slate-600 text-center">{{ rtrim(rtrim(number_format($ch->quantity, 2), '0'), '.') }}</td>
                        <td class="px-4 py-2.5 text-sm text-slate-600 text-right">{{ $cur }}{{ number_format($ch->unit_price, 2) }}</td>
                        <td class="px-4 py-2.5 text-sm font-medium text-slate-800 text-right">{{ $cur }}{{ number_format($ch->total, 2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-sm text-slate-400">No charges captured yet.</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="border-t-2 border-slate-200">
                    <tr><td colspan="4" class="px-4 py-3 text-right text-sm font-semibold text-slate-600">Running total</td><td class="px-4 py-3 text-right text-base font-bold text-slate-900">{{ $cur }}{{ number_format($runningTotal, 2) }}</td></tr>
                </tfoot>
            </table>
        </div>
        @if($ipBill)
        <p class="text-xs text-slate-400 mt-3">Bill <a href="{{ route('web.billing.show', $ipBill->id) }}" class="text-blue-600 hover:text-blue-800 font-medium">{{ $ipBill->bill_number }}</a> — {{ $cur }}{{ number_format($ipBill->total_amount, 2) }} · {{ ucfirst(is_object($ipBill->payment_status) ? $ipBill->payment_status->value : $ipBill->payment_status) }}</p>
        @endif
    </div>

    {{-- ================= MODALS ================= --}}

    {{-- Add charge --}}
    <x-modal show="chargeOpen" title="Add Inpatient Charge" max="lg">
        <form method="POST" action="{{ route('web.ip.charge', $admission->id) }}" class="space-y-4"
              x-data="{ svc: '', services: {{ Illuminate\Support\Js::from($services->map(fn($s)=>['id'=>$s->id,'name'=>$s->name,'price'=>(float)$s->price,'category'=>$s->category])->values()) }},
                        source: 'procedure', desc: '', price: '',
                        pick() { const s = this.services.find(x => x.id === this.svc); if (s) { this.desc = s.name; this.price = s.price; const m = {procedure:'procedure',investigation:'lab',consumable:'consumable',nursing:'nursing',room:'other'}; this.source = m[s.category] || 'procedure'; } } }">
            @csrf
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">From rate card <span class="text-slate-400 font-normal">(optional)</span></label>
                <select name="service_charge_id" x-model="svc" @change="pick()" class="input-field">
                    <option value="">— custom —</option>
                    @foreach($services as $s)<option value="{{ $s->id }}">{{ $s->name }} ({{ $cur }}{{ number_format($s->price, 0) }})</option>@endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="block text-sm font-medium text-slate-700 mb-1">Type</label>
                    <select name="source" x-model="source" class="input-field">
                        <option value="procedure">Procedure</option><option value="consumable">Consumable</option><option value="lab">Investigation</option><option value="imaging">Imaging</option><option value="nursing">Nursing</option><option value="other">Other</option>
                    </select>
                </div>
                <div><label class="block text-sm font-medium text-slate-700 mb-1">Quantity</label><input type="number" step="0.01" name="quantity" value="1" min="0.01" required class="input-field"></div>
            </div>
            <div><label class="block text-sm font-medium text-slate-700 mb-1">Description</label><input type="text" name="description" x-model="desc" required maxlength="255" class="input-field"></div>
            <div><label class="block text-sm font-medium text-slate-700 mb-1">Unit price ({{ $cur }})</label><input type="number" step="0.01" name="unit_price" x-model="price" min="0" required class="input-field"></div>
            <div class="flex items-center gap-3 pt-1"><button type="submit" class="btn-primary px-5 py-2.5">Add to bill</button><button type="button" @click="chargeOpen = false" class="text-sm text-slate-500 px-2 py-2">Cancel</button></div>
        </form>
    </x-modal>

    {{-- Vitals --}}
    <x-modal show="vOpen" title="Record Vitals" max="lg">
            <form method="POST" action="{{ route('web.ip.vitals.store', $admission->id) }}" class="grid grid-cols-3 gap-3">
                @csrf
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">BP Sys</label><input type="number" name="bp_systolic" class="input-field" placeholder="120"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">BP Dia</label><input type="number" name="bp_diastolic" class="input-field" placeholder="80"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Temp °F</label><input type="number" step="0.1" name="temperature" class="input-field" placeholder="98.6"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Pulse</label><input type="number" name="pulse" class="input-field" placeholder="72"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">SpO₂ %</label><input type="number" name="spo2" class="input-field" placeholder="98"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Resp Rate</label><input type="number" name="resp_rate" class="input-field" placeholder="16"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Weight kg</label><input type="number" step="0.1" name="weight" class="input-field"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Height cm</label><input type="number" step="0.1" name="height" class="input-field"></div>
                <div class="self-end text-xs text-slate-400 pb-2">BMI auto-calculated</div>
                <div class="col-span-3"><label class="block text-xs font-semibold text-slate-600 mb-1">Notes</label><input type="text" name="notes" class="input-field"></div>
                <div class="col-span-3 flex items-center gap-3 pt-1"><button type="submit" class="btn-primary px-5 py-2.5">Save</button><button type="button" @click="vOpen = false" class="text-sm text-slate-500 px-2 py-2">Cancel</button></div>
            </form>
    </x-modal>

    {{-- Intake/Output --}}
    <x-modal show="ioOpen" title="Record Intake / Output" max="md">
            <form method="POST" action="{{ route('web.ip.io.store', $admission->id) }}" class="grid grid-cols-2 gap-4">
                @csrf
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Type</label>
                    <select name="direction" x-model="ioDir" class="input-field"><option value="intake">Intake</option><option value="output">Output</option></select>
                </div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Category</label>
                    <select name="category" class="input-field"><template x-for="c in ioCats" :key="c"><option :value="c" x-text="c"></option></template></select>
                </div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Volume (ml) *</label><input type="number" name="volume_ml" required min="1" class="input-field"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Notes</label><input type="text" name="notes" class="input-field"></div>
                <div class="col-span-2 flex items-center gap-3 pt-1"><button type="submit" class="btn-primary px-5 py-2.5">Save</button><button type="button" @click="ioOpen = false" class="text-sm text-slate-500 px-2 py-2">Cancel</button></div>
            </form>
    </x-modal>

    {{-- Note --}}
    <x-modal show="noteOpen" title="Add Note" max="lg">
            <form method="POST" action="{{ route('web.ip.notes.store', $admission->id) }}" class="space-y-4">
                @csrf
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Type</label>
                    <select name="note_type" class="input-field"><option value="doctor">Doctor's Note</option><option value="nurse">Nurse's Note</option><option value="order">Doctor's Order</option></select>
                </div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Note *</label><textarea name="body" required rows="4" class="input-field" placeholder="Free-text note…"></textarea></div>
                <div class="flex items-center gap-3 pt-1"><button type="submit" class="btn-primary px-5 py-2.5">Add</button><button type="button" @click="noteOpen = false" class="text-sm text-slate-500 px-2 py-2">Cancel</button></div>
            </form>
    </x-modal>

    {{-- Transfer --}}
    <x-modal show="tOpen" title="Transfer Bed" max="md">
            <form method="POST" action="{{ route('web.ip.transfer', $admission->id) }}" class="grid grid-cols-2 gap-4">
                @csrf
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Ward</label>
                    <select x-model="tWard" class="input-field"><option value="">Select…</option><template x-for="w in wards" :key="w.id"><option :value="w.id" x-text="w.name"></option></template></select>
                </div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Bed *</label>
                    <select name="bed_id" required class="input-field" :disabled="!tWard"><option value="">Select…</option><template x-for="b in tBeds" :key="b.id"><option :value="b.id" x-text="b.number"></option></template></select>
                </div>
                <div class="col-span-2 flex items-center gap-3 pt-1"><button type="submit" class="btn-primary px-5 py-2.5">Transfer</button><button type="button" @click="tOpen = false" class="text-sm text-slate-500 px-2 py-2">Cancel</button></div>
            </form>
    </x-modal>

    {{-- Discharge --}}
    <x-modal show="dOpen" title="Discharge Patient" max="md">
            <form method="POST" action="{{ route('web.ip.discharge', $admission->id) }}" class="space-y-4">
                @csrf
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Discharge type *</label>
                    <select name="discharge_type" required class="input-field">
                        @foreach(\App\Modules\Inpatient\Models\Admission::DISCHARGE_TYPES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                    </select>
                </div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Discharge summary</label><textarea name="discharge_summary" rows="4" class="input-field" placeholder="Condition, treatment given, follow-up advice…"></textarea></div>
                <div class="flex items-center gap-3 pt-1"><button type="submit" class="btn-primary px-5 py-2.5">Confirm Discharge</button><button type="button" @click="dOpen = false" class="text-sm text-slate-500 px-2 py-2">Cancel</button></div>
            </form>
    </x-modal>
</div>
@endsection
