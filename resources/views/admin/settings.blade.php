@extends('layouts.app')

@section('title', 'Settings')
@section('page-title', 'Hospital Settings')

@section('content')
<div x-data="settingsPage()" class="max-w-4xl">

    @php
        $region = \App\Modules\Core\Services\RegionService::get($hospital?->country ?? 'IN');
    @endphp

    {{-- Hospital Info + Region --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6" x-data="{ saving: false, saved: false }">
        <h3 class="text-base font-semibold text-slate-800 mb-4">Hospital Information</h3>
        <form method="POST" action="{{ route('web.admin.settings.save') }}" class="space-y-5" @submit="saving = true">
            @csrf

            {{-- Region (read-only — only Super Admin can change) --}}
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Region</label>
                <div class="flex items-center gap-3 p-4 rounded-xl border border-slate-200 bg-slate-50">
                    <span class="text-3xl">{{ ($hospital->country ?? 'IN') === 'AE' ? '🇦🇪' : '🇮🇳' }}</span>
                    <div>
                        <p class="font-bold text-slate-900">{{ ($hospital->country ?? 'IN') === 'AE' ? 'UAE' : 'India' }}</p>
                        <p class="text-xs text-slate-500">{{ $region['currency'] }} · {{ $region['health_id']['system'] }} · {{ implode(', ', array_keys($region['languages'])) }}</p>
                    </div>
                    <span class="ml-auto text-xs text-slate-400">Contact Super Admin to change</span>
                </div>
                <input type="hidden" name="country" value="{{ $hospital->country ?? 'IN' }}">
            </div>

            {{-- Hospital Name --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Hospital Name *</label>
                    <input type="text" name="name" value="{{ $hospital->name ?? '' }}" required class="input-field" placeholder="City Care Hospital">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">URL Slug</label>
                    <input type="text" name="slug" value="{{ $hospital->slug ?? '' }}" class="input-field" placeholder="city-care">
                </div>
            </div>

            {{-- Location --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">City *</label>
                    <input type="text" name="city" value="{{ $hospital->city ?? '' }}" required class="input-field">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">State / Emirate</label>
                    <input type="text" name="state" value="{{ $hospital->state ?? '' }}" class="input-field">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Phone</label>
                    <input type="tel" name="phone" value="{{ $hospital->phone ?? '' }}" class="input-field">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Address</label>
                <textarea name="address" rows="2" class="input-field">{{ $hospital->address ?? '' }}</textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                <input type="email" name="email" value="{{ $hospital->email ?? '' }}" class="input-field">
            </div>

            {{-- Current region info --}}
            <div class="p-4 rounded-lg bg-slate-50 border border-slate-200">
                <p class="text-xs font-semibold text-slate-500 uppercase mb-2">Current Region Settings</p>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                    <div>
                        <p class="text-slate-400 text-xs">Currency</p>
                        <p class="font-medium">{{ $region['currency'] }} {{ $region['currency_code'] }}</p>
                    </div>
                    <div>
                        <p class="text-slate-400 text-xs">Health ID</p>
                        <p class="font-medium">{{ $region['health_id']['system'] }}</p>
                    </div>
                    <div>
                        <p class="text-slate-400 text-xs">Insurance</p>
                        <p class="font-medium">{{ $region['insurance']['system'] }}</p>
                    </div>
                    <div>
                        <p class="text-slate-400 text-xs">Languages</p>
                        <p class="font-medium">{{ count($region['languages']) }} supported</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="btn-primary" :disabled="saving">
                    <span x-show="!saving">Save Changes</span>
                    <span x-show="saving">Saving...</span>
                </button>
                @if(session('success'))
                    <span class="text-sm text-green-600 font-medium">{{ session('success') }}</span>
                @endif
            </div>
        </form>
    </div>

    {{-- Operating Hours --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6">
        <h3 class="text-base font-semibold text-slate-800 mb-4">Operating Hours</h3>
        <div class="space-y-3">
            <template x-for="day in days" :key="day.name">
                <div class="flex items-center gap-4 py-2">
                    <span class="w-24 text-sm font-medium text-slate-700" x-text="day.label"></span>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" x-model="day.open" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        <span class="text-sm text-slate-500">Open</span>
                    </label>
                    <template x-if="day.open">
                        <div class="flex items-center gap-2">
                            <input type="time" x-model="day.start" class="input-field w-32">
                            <span class="text-sm text-slate-400">to</span>
                            <input type="time" x-model="day.end" class="input-field w-32">
                        </div>
                    </template>
                    <span x-show="!day.open" class="text-sm text-slate-400">Closed</span>
                </div>
            </template>
        </div>
        <div class="flex justify-end mt-4">
            <button type="button" @click="saveHours()" class="btn-primary">Save Hours</button>
        </div>
    </div>

    {{-- Department Management --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-semibold text-slate-800">Departments</h3>
            <button type="button" @click="openDept(-1)" class="text-sm text-blue-600 hover:text-blue-800 font-medium">+ Add Department</button>
        </div>
        <div class="space-y-2">
            <template x-for="(dept, index) in departments" :key="index">
                <div class="flex items-center gap-3 py-2 border-b border-slate-100 last:border-0">
                    <span class="flex-1 text-sm text-slate-700" x-text="dept.name"></span>
                    <span class="text-xs px-2 py-0.5 rounded-full" :class="dept.active ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500'" x-text="dept.active ? 'Active' : 'Inactive'"></span>
                    <button type="button" @click="openDept(index)" class="text-xs text-blue-600 hover:text-blue-800">Edit</button>
                    <button type="button" @click="departments.splice(index, 1)" class="text-xs text-red-500 hover:text-red-700">Remove</button>
                </div>
            </template>
            <p x-show="!departments.length" class="text-sm text-slate-400 py-2">No departments yet — add one.</p>
        </div>
    </div>

    {{-- Department Add/Edit modal --}}
    <div x-show="deptModal" x-transition.opacity style="display:none" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @keydown.escape.window="deptModal = false">
        <div @click.away="deptModal = false" class="bg-white rounded-2xl shadow-xl w-full max-w-md">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
                <h3 class="text-lg font-bold text-slate-900" x-text="deptIndex === -1 ? 'Add Department' : 'Edit Department'"></h3>
                <button type="button" @click="deptModal = false" class="text-slate-400 hover:text-slate-600 text-2xl leading-none">&times;</button>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Department name *</label>
                    <input type="text" x-model="deptDraft.name" maxlength="100" @keydown.enter="saveDept()" class="input-field" placeholder="e.g. Cardiology">
                    <p x-show="deptError" x-text="deptError" class="text-red-500 text-xs mt-1"></p>
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" x-model="deptDraft.active" class="rounded border-slate-300 text-blue-600">
                    Active
                </label>
                <div class="flex items-center gap-3 pt-1">
                    <button type="button" @click="saveDept()" class="btn-primary px-5 py-2.5">Save</button>
                    <button type="button" @click="deptModal = false" class="text-sm text-slate-500 hover:text-slate-700 px-2 py-2">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Module Toggles --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6">
        <h3 class="text-base font-semibold text-slate-800 mb-4">Module Configuration</h3>
        <div class="space-y-3">
            <template x-for="mod in modules" :key="mod.key">
                <div class="flex items-center justify-between py-3 border-b border-slate-100 last:border-0">
                    <div>
                        <p class="text-sm font-medium text-slate-800" x-text="mod.name"></p>
                        <p class="text-xs text-slate-500" x-text="mod.description"></p>
                    </div>
                    <button
                        @click="mod.enabled = !mod.enabled"
                        :class="mod.enabled ? 'bg-blue-600' : 'bg-slate-300'"
                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                    >
                        <span
                            :class="mod.enabled ? 'translate-x-6' : 'translate-x-1'"
                            class="inline-block h-4 w-4 rounded-full bg-white transition-transform"
                        ></span>
                    </button>
                </div>
            </template>
        </div>
    </div>

    {{-- AI Configuration --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6">
        <h3 class="text-base font-semibold text-slate-800 mb-4">AI Configuration</h3>
        <div class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">AI Provider</label>
                    <select x-model="ai.provider" class="input-field">
                        <option value="openai">OpenAI</option>
                        <option value="anthropic">Anthropic</option>
                        <option value="google">Google AI</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Model</label>
                    <input type="text" x-model="ai.model" class="input-field" placeholder="e.g. gpt-4o">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">API Key</label>
                <input type="password" x-model="ai.apiKey" class="input-field" placeholder="sk-...">
            </div>
            <div class="flex justify-end">
                <button type="button" class="btn-primary">Save AI Settings</button>
            </div>
        </div>
    </div>

    {{-- WhatsApp Configuration --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h3 class="text-base font-semibold text-slate-800 mb-4">WhatsApp Configuration</h3>
        <div class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">WhatsApp Business Number</label>
                    <input type="tel" x-model="whatsapp.number" class="input-field" placeholder="+91XXXXXXXXXX">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Provider</label>
                    <select x-model="whatsapp.provider" class="input-field">
                        <option value="twilio">Twilio</option>
                        <option value="meta">Meta Cloud API</option>
                        <option value="gupshup">Gupshup</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">API Token</label>
                <input type="password" x-model="whatsapp.token" class="input-field" placeholder="Enter API token">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Webhook URL</label>
                <div class="flex items-center gap-2">
                    <input type="text" readonly :value="webhookUrl" class="input-field flex-1 bg-slate-50">
                    <button @click="navigator.clipboard.writeText(webhookUrl)" class="btn-secondary text-sm">Copy</button>
                </div>
            </div>
            <div class="flex justify-end">
                <button type="button" class="btn-primary">Save WhatsApp Settings</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function settingsPage() {
    return {
        // TODO: fetch from API / pass from controller
        hospital: {
            name: @json($hospital->name ?? 'MedOS Hospital'),
            phone: @json($hospital->phone ?? ''),
            address: @json($hospital->address ?? ''),
        },
        days: (() => {
            const saved = @json(($hospital->config ?? [])['operating_hours'] ?? []);
            const defaults = {
                monday: { open: true, start: '08:00', end: '18:00' },
                tuesday: { open: true, start: '08:00', end: '18:00' },
                wednesday: { open: true, start: '08:00', end: '18:00' },
                thursday: { open: true, start: '08:00', end: '18:00' },
                friday: { open: true, start: '08:00', end: '18:00' },
                saturday: { open: true, start: '09:00', end: '14:00' },
                sunday: { open: false, start: '09:00', end: '14:00' },
            };
            const labels = { monday: 'Monday', tuesday: 'Tuesday', wednesday: 'Wednesday', thursday: 'Thursday', friday: 'Friday', saturday: 'Saturday', sunday: 'Sunday' };
            return Object.keys(defaults).map(name => ({
                name,
                label: labels[name],
                open: saved[name]?.open ?? defaults[name].open,
                start: saved[name]?.start ?? defaults[name].start,
                end: saved[name]?.end ?? defaults[name].end,
            }));
        })(),
        departments: [
            { name: 'General Medicine', active: true },
            { name: 'Cardiology', active: true },
            { name: 'Pediatrics', active: true },
            { name: 'Orthopedics', active: true },
        ],
        deptModal: false,
        deptIndex: -1,
        deptDraft: { name: '', active: true },
        deptError: '',
        openDept(index) {
            this.deptIndex = index;
            this.deptError = '';
            this.deptDraft = index === -1
                ? { name: '', active: true }
                : Object.assign({}, this.departments[index]);
            this.deptModal = true;
        },
        saveDept() {
            const name = (this.deptDraft.name || '').replace(/[^a-zA-Z\s\-&(),]/g, '').trim().substring(0, 100);
            if (name.length < 2) { this.deptError = 'Enter a valid department name (min 2 letters).'; return; }
            const dupe = this.departments.some((d, i) => i !== this.deptIndex && d.name.toLowerCase() === name.toLowerCase());
            if (dupe) { this.deptError = 'That department already exists.'; return; }
            const dept = { name, active: !!this.deptDraft.active };
            if (this.deptIndex === -1) { this.departments.push(dept); }
            else { this.departments[this.deptIndex] = dept; }
            this.deptModal = false;
        },
        modules: [
            { key: 'ai_receptionist', name: 'AI Receptionist', description: 'Automated patient intake via WhatsApp', enabled: true },
            { key: 'triage', name: 'AI Triage', description: 'Automatic urgency classification', enabled: true },
            { key: 'doctor_assist', name: 'Doctor Assist', description: 'AI-generated patient briefings and SOAP notes', enabled: true },
            { key: 'insurance', name: 'Insurance Module', description: 'Insurance verification and claims', enabled: true },
            { key: 'billing', name: 'Billing', description: 'Automated bill generation', enabled: true },
            { key: 'whatsapp', name: 'WhatsApp Integration', description: 'Patient communication via WhatsApp', enabled: false },
            { key: 'engagement', name: 'Patient Engagement', description: 'Follow-ups, reminders, and feedback', enabled: true },
        ],
        ai: {
            provider: 'openai',
            model: 'gpt-4o',
            apiKey: '',
        },
        whatsapp: {
            number: '',
            provider: 'twilio',
            token: '',
        },
        webhookUrl: window.location.origin + '/api/whatsapp/webhook',

        saveHospitalInfo() {
            // TODO: POST to API
            alert('Hospital info saved (placeholder)');
        },
        async saveHours() {
            const hours = {};
            this.days.forEach(d => {
                hours[d.name] = { open: d.open, start: d.start, end: d.end };
            });
            try {
                const res = await fetch('{{ route("web.admin.settings.hours") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ hours }),
                });
                const data = await res.json();
                alert(data.message || 'Saved!');
            } catch(e) { alert('Failed to save hours.'); }
        },
    };
}
</script>
@endpush
