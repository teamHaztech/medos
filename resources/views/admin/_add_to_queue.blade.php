@php
    $qDoctors = $doctors ?? collect();
    $qTests = $tests ?? collect();
    $btnClass = $addBtnClass ?? 'btn-primary';
@endphp
<div x-data="addToQueueForm({{ \Illuminate\Support\Js::from($qTests->map(fn ($t) => ['name' => $t->name, 'price' => (float) ($t->price ?? 0)])->values()) }})" class="inline-block">
    <button type="button" @click="open = true" class="{{ $btnClass }}">+ Add to Queue</button>

    <x-modal show="open" title="Add to Queue" max="lg">
            <form method="POST" action="{{ route('web.admin.queue.add') }}" class="space-y-5">
                @csrf
                <input type="hidden" name="target" :value="target">

                {{-- Destination --}}
                <div class="flex gap-2">
                    <button type="button" @click="target='doctor'" :class="target==='doctor' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600'" class="flex-1 px-4 py-2 rounded-lg text-sm font-semibold">🩺 Doctor</button>
                    <button type="button" @click="target='lab'" :class="target==='lab' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600'" class="flex-1 px-4 py-2 rounded-lg text-sm font-semibold">🧪 Lab</button>
                </div>

                {{-- Patient --}}
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-sm font-medium text-slate-700">Patient</label>
                        <div class="flex gap-1 text-xs">
                            <button type="button" @click="mode='existing'" :class="mode==='existing'?'bg-blue-600 text-white':'bg-slate-100 text-slate-600'" class="px-2 py-0.5 rounded font-semibold">Existing</button>
                            <button type="button" @click="mode='new'" :class="mode==='new'?'bg-blue-600 text-white':'bg-slate-100 text-slate-600'" class="px-2 py-0.5 rounded font-semibold">New</button>
                        </div>
                    </div>
                    <div x-show="mode==='existing'" class="relative">
                        <template x-if="selectedPatient">
                            <div class="flex items-center justify-between px-3 py-2 bg-blue-50 border border-blue-200 rounded-lg">
                                <span class="text-sm font-medium text-slate-800"><span x-text="selectedPatient.name"></span> <span class="text-slate-400" x-text="selectedPatient.phone ? '· '+selectedPatient.phone : ''"></span></span>
                                <button type="button" @click="selectedPatient=null" class="text-slate-400 hover:text-slate-600 text-lg leading-none">&times;</button>
                            </div>
                        </template>
                        <template x-if="!selectedPatient">
                            <div>
                                <input type="text" x-model="patientSearch" @input.debounce.300ms="searchPatients()" class="input-field" placeholder="Search name or phone…">
                                <div x-show="patientResults.length" class="absolute z-20 left-0 right-0 mt-1 bg-white border border-slate-200 rounded-lg shadow-xl overflow-y-auto" style="max-height:200px">
                                    <template x-for="p in patientResults" :key="p.id">
                                        <button type="button" @click="pickPatient(p)" class="w-full flex items-center justify-between p-2.5 hover:bg-blue-50 text-left border-b border-slate-100 last:border-0">
                                            <span class="text-sm font-medium text-slate-800" x-text="p.name"></span>
                                            <span class="text-xs text-slate-400" x-text="p.phone"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </template>
                        <input type="hidden" name="patient_id" :value="selectedPatient ? selectedPatient.id : ''">
                    </div>
                    <div x-show="mode==='new'" style="display:none" class="grid grid-cols-2 gap-2">
                        <input type="text" name="new_name" x-model="newName" class="input-field" placeholder="Full name">
                        <input type="text" name="new_phone" x-model="newPhone" class="input-field" placeholder="Phone">
                    </div>
                </div>

                {{-- Doctor branch --}}
                <div x-show="target==='doctor'" class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Doctor</label>
                        <select name="doctor_id" x-model="doctorId" class="input-field">
                            <option value="">Select doctor…</option>
                            @foreach($qDoctors as $d)<option value="{{ $d->id }}">{{ $d->name }}{{ $d->department ? ' · '.$d->department : '' }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Reason (optional)</label>
                        <input type="text" name="reason" x-model="reason" class="input-field" placeholder="Chief complaint">
                    </div>
                    <p class="text-xs text-slate-400">Adds the patient as checked-in — they appear on the doctor's live queue &amp; display board immediately.</p>
                </div>

                {{-- Lab branch --}}
                <div x-show="target==='lab'" style="display:none" class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Tests</label>
                        <input type="text" x-model="testQuery" class="input-field" placeholder="Search tests…">
                        <div x-show="testQuery.length" class="mt-1 border border-slate-200 rounded-lg overflow-y-auto" style="max-height:160px">
                            <template x-for="t in filteredTests" :key="t.name">
                                <button type="button" @click="addTest(t)" class="w-full flex items-center justify-between p-2 hover:bg-blue-50 text-left border-b border-slate-100 last:border-0">
                                    <span class="text-sm text-slate-800" x-text="t.name"></span>
                                    <span class="text-xs text-slate-400" x-text="t.price ? '{{ \App\Modules\Core\Services\RegionService::currency() }}'+t.price : ''"></span>
                                </button>
                            </template>
                            <p x-show="!filteredTests.length" class="p-2 text-xs text-slate-400">No matching tests.</p>
                        </div>
                    </div>
                    <div x-show="selectedTests.length" class="flex flex-wrap gap-1.5">
                        <template x-for="(t, i) in selectedTests" :key="i">
                            <span class="inline-flex items-center gap-1 text-xs px-2 py-1 rounded-full bg-indigo-100 text-indigo-700">
                                <span x-text="t.name"></span>
                                <button type="button" @click="removeTest(i)" class="text-indigo-400 hover:text-indigo-600">&times;</button>
                                <input type="hidden" :name="'tests['+i+'][name]'" :value="t.name">
                                <input type="hidden" :name="'tests['+i+'][price]'" :value="t.price">
                            </span>
                        </template>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Priority</label>
                        <select name="priority" x-model="priority" class="input-field">
                            <option value="routine">Routine</option>
                            <option value="urgent">Urgent</option>
                            <option value="stat">STAT</option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button type="submit" class="btn-primary px-5" :disabled="!canSubmit" :class="!canSubmit ? 'opacity-40 cursor-not-allowed' : ''">Add to Queue</button>
                    <button type="button" @click="open = false" class="btn-secondary">Cancel</button>
                </div>
            </form>
    </x-modal>
</div>

@once
@push('scripts')
<script>
function addToQueueForm(allTests) {
    return {
        open: false,
        target: 'doctor',
        mode: 'existing',
        patientSearch: '', patientResults: [], selectedPatient: null,
        newName: '', newPhone: '',
        doctorId: '', reason: '',
        testQuery: '', selectedTests: [], priority: 'routine',
        allTests: allTests || [],

        get filteredTests() {
            const q = this.testQuery.toLowerCase().trim();
            if (!q) return [];
            const chosen = new Set(this.selectedTests.map(t => t.name));
            return this.allTests.filter(t => t.name.toLowerCase().includes(q) && !chosen.has(t.name)).slice(0, 20);
        },
        get canSubmit() {
            const hasPatient = this.mode === 'existing' ? !!this.selectedPatient : (this.newName.trim() && this.newPhone.trim());
            const hasTarget = this.target === 'doctor' ? !!this.doctorId : this.selectedTests.length > 0;
            return hasPatient && hasTarget;
        },
        async searchPatients() {
            if (this.patientSearch.length < 2) { this.patientResults = []; return; }
            try { const r = await fetch('/ajax/patients?q='+encodeURIComponent(this.patientSearch), {headers:{'Accept':'application/json'}}); if (r.ok) this.patientResults = await r.json(); } catch(e){}
        },
        pickPatient(p) { this.selectedPatient = p; this.patientResults = []; this.patientSearch = ''; },
        addTest(t) { this.selectedTests.push({ name: t.name, price: t.price || 0 }); this.testQuery = ''; },
        removeTest(i) { this.selectedTests.splice(i, 1); },
    };
}
</script>
@endpush
@endonce
