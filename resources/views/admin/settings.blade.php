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
            <div class="flex items-center gap-2">
                <button type="button" @click="openDept(-1)" class="btn-primary text-sm">+ Add Department</button>
                <button type="button" @click="saveDepts()" class="btn-primary text-sm">Save</button>
            </div>
        </div>
        <div class="space-y-2">
            <template x-for="(dept, index) in departments" :key="index">
                <div class="flex items-center gap-3 py-2 border-b border-slate-100 last:border-0">
                    <span class="flex-1 text-sm text-slate-700" x-text="dept.name || 'Untitled department'"></span>
                    <span class="text-xs px-2 py-0.5 rounded-full" :class="dept.active ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500'" x-text="dept.active ? 'Active' : 'Inactive'"></span>
                    <button type="button" @click="openDept(index)" class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium text-blue-700 bg-blue-50 hover:bg-blue-100">Edit</button>
                    <button type="button" @click="departments.splice(index, 1)" class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium text-red-700 bg-red-50 hover:bg-red-100">Remove</button>
                </div>
            </template>
            <p x-show="!departments.length" class="text-sm text-slate-400 py-2">No departments yet — add one.</p>
        </div>
    </div>

    {{-- Patient Areas / Rooms --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6"
         x-data="{ extra: {{ \Illuminate\Support\Js::from($areas['extra'] ?? []) }} }">
        <div class="mb-4">
            <h3 class="text-base font-semibold text-slate-800">Patient Areas</h3>
            <p class="text-xs text-slate-400 mt-0.5">Your hospital's waiting and sample-collection areas — shown to patients on their token/check-in slip. Set your own floor/room names.</p>
        </div>
        <form method="POST" action="{{ route('web.admin.settings.areas') }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">OPD waiting area</label>
                    <input type="text" name="waiting" value="{{ $areas['waiting'] ?? '' }}" maxlength="120" class="input-field" placeholder="e.g. Floor 2, Waiting Area">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Lab / sample collection area</label>
                    <input type="text" name="lab" value="{{ $areas['lab'] ?? '' }}" maxlength="120" class="input-field" placeholder="e.g. Ground Floor, Sample Collection Counter">
                </div>
            </div>

            {{-- Additional custom areas --}}
            <template x-for="(area, i) in extra" :key="i">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-end">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Area name</label>
                        <input type="text" :name="`extra[${i}][label]`" x-model="area.label" maxlength="60" class="input-field" placeholder="e.g. Radiology / X-Ray">
                    </div>
                    <div class="flex items-end gap-2">
                        <div class="flex-1">
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Location / room</label>
                            <input type="text" :name="`extra[${i}][value]`" x-model="area.value" maxlength="120" class="input-field" placeholder="e.g. Ground Floor, Room 5">
                        </div>
                        <button type="button" @click="extra.splice(i, 1)" class="text-xs font-medium text-red-500 hover:text-red-700 px-2 py-2 rounded hover:bg-red-50 shrink-0">Remove</button>
                    </div>
                </div>
            </template>

            <div class="flex items-center gap-2 pt-1">
                <button type="button" @click="extra.push({ label: '', value: '' })" class="btn-primary text-sm">+ Add area</button>
                <button type="submit" class="btn-primary text-sm">Save areas</button>
            </div>
        </form>
    </div>

    {{-- GST / Tax identity --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6">
        <div class="mb-4">
            <h3 class="text-base font-semibold text-slate-800">GST / Tax Details</h3>
            <p class="text-xs text-slate-400 mt-0.5">Your GSTIN and state print on tax invoices. GST is charged per service (set each service's rate in Billing → Services).</p>
        </div>
        <form method="POST" action="{{ route('web.admin.settings.gst') }}" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">GSTIN</label>
                <input type="text" name="gstin" value="{{ $gst['gstin'] ?? '' }}" maxlength="20" class="input-field" placeholder="e.g. 29ABCDE1234F1Z5" style="text-transform:uppercase">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">State (place of supply)</label>
                <input type="text" name="state" value="{{ $gst['state'] ?? '' }}" maxlength="60" class="input-field" placeholder="e.g. Karnataka">
            </div>
            <div class="sm:col-span-2"><button type="submit" class="btn-primary text-sm">Save GST details</button></div>
        </form>
    </div>

    {{-- Department Add/Edit modal --}}
    <x-modal show="deptModal" title-expr="deptIndex === -1 ? 'Add Department' : 'Edit Department'" max="md">
        <div class="space-y-4">
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
                <button type="button" @click="deptModal = false" class="btn-secondary">Cancel</button>
            </div>
        </div>
    </x-modal>

    {{-- Module Toggles --}}
    <form method="POST" action="{{ route('web.admin.settings.modules') }}" class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6">
        @csrf
        <div class="flex items-center justify-between mb-1">
            <h3 class="text-base font-semibold text-slate-800">Module Configuration</h3>
            <button type="submit" class="btn-primary">Save Modules</button>
        </div>
        <p class="text-xs text-slate-500 mb-3">Disabled modules are fully blocked — their pages 404 and their links disappear.</p>
        <div class="space-y-5">
            <template x-for="cat in moduleCategories" :key="cat.name">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-1" x-text="cat.name"></p>
                    <template x-for="mod in cat.modules" :key="mod.key">
                        <div class="flex items-center justify-between py-2.5 border-b border-slate-100 last:border-0">
                            <div>
                                <p class="text-sm font-medium text-slate-800" x-text="mod.name"></p>
                                <p class="text-xs text-slate-500" x-text="mod.description"></p>
                            </div>
                            <label :class="mod.enabled ? 'bg-blue-600' : 'bg-slate-300'"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors shrink-0 cursor-pointer">
                                <input type="checkbox" name="modules[]" :value="mod.key" x-model="mod.enabled" style="position:absolute;inset:0;width:100%;height:100%;opacity:0;margin:0;cursor:pointer;">
                                <span :class="mod.enabled ? 'translate-x-6' : 'translate-x-1'" class="inline-block h-4 w-4 rounded-full bg-white transition-transform"></span>
                            </label>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </form>

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
                <input type="password" x-model="ai.apiKey" class="input-field" :placeholder="ai.hasKey ? '•••••••• saved — leave blank to keep' : 'sk-...'">
            </div>
            <div class="flex justify-end">
                <button type="button" @click="saveAi()" class="btn-primary">Save AI Settings</button>
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
                <input type="password" x-model="whatsapp.token" class="input-field" :placeholder="whatsapp.hasToken ? '•••••••• saved — leave blank to keep' : 'Enter API token'">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Webhook URL</label>
                <div class="flex items-center gap-2">
                    <input type="text" readonly :value="webhookUrl" class="input-field flex-1 bg-slate-50">
                    <button @click="navigator.clipboard.writeText(webhookUrl)" class="btn-secondary text-sm">Copy</button>
                </div>
            </div>
            <div class="flex justify-end">
                <button type="button" @click="saveWhatsapp()" class="btn-primary">Save WhatsApp Settings</button>
            </div>
        </div>
    </div>

    {{-- Billing Integrations --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <div class="flex items-center justify-between mb-1">
            <h3 class="text-base font-semibold text-slate-800">Billing Integrations</h3>
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" x-model="billing.enabled" class="rounded border-slate-300">
                <span class="text-slate-600">Enabled</span>
            </label>
        </div>
        <p class="text-xs text-slate-500 mb-4">Send every bill to your hospital's own billing / accounting software as it is created, paid, refunded or cancelled.</p>
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Connector</label>
                <select x-model="billing.connector" class="input-field">
                    <template x-for="c in billing.connectors" :key="c.code">
                        <option :value="c.code" x-text="c.label"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Endpoint URL</label>
                <input type="url" x-model="billing.endpoint" class="input-field" placeholder="https://your-system.example.com/webhooks/medos-bills">
                <p class="text-xs text-slate-400 mt-1">MedOS sends an HTTP POST with the bill as JSON to this URL.</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">API key / Bearer token</label>
                    <input type="password" x-model="billing.secret" class="input-field" :placeholder="billing.hasSecret ? '•••••••• saved — leave blank to keep' : 'Optional'">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Signing secret (HMAC)</label>
                    <input type="password" x-model="billing.signing_secret" class="input-field" :placeholder="billing.hasSigning ? '•••••••• saved — leave blank to keep' : 'Optional'">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Send these events</label>
                <div class="flex flex-wrap gap-4">
                    @foreach($biEvents as $key => $label)
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" value="{{ $key }}" x-model="billing.events" class="rounded border-slate-300">
                        <span class="text-slate-600">{{ $label }}</span>
                    </label>
                    @endforeach
                </div>
            </div>
            <div class="rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-800">
                Sync runs in the background queue. On shared hosting, add a scheduled task running <code>php artisan queue:work --stop-when-empty</code> each minute so pushes are delivered.
            </div>
            <p class="text-xs text-slate-500">Prefer your software to <strong>pull</strong> data instead of receiving pushes? <a href="{{ route('web.admin.api-keys') }}" class="text-blue-600 hover:text-blue-800 font-medium">Generate an API key →</a></p>
            <div class="flex items-center justify-between gap-3">
                <span class="text-sm" :class="billing.testOk === true ? 'text-green-600' : (billing.testOk === false ? 'text-red-600' : 'text-slate-400')" x-text="billing.testMsg"></span>
                <div class="flex gap-2">
                    <button type="button" @click="testBilling()" class="btn-secondary text-sm">Send test</button>
                    <button type="button" @click="saveBilling()" class="btn-primary">Save</button>
                </div>
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
        departments: {{ \Illuminate\Support\Js::from($departments ?? []) }},
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
        modules: {{ \Illuminate\Support\Js::from($moduleList ?? []) }},
        get moduleCategories() {
            const order = [], map = {};
            this.modules.forEach(m => {
                const c = m.category || 'Other';
                if (!map[c]) { map[c] = []; order.push(c); }
                map[c].push(m);
            });
            return order.map(name => ({ name, modules: map[name] }));
        },
        ai: {{ \Illuminate\Support\Js::from(['provider' => $aiCfg['provider'] ?? 'anthropic', 'model' => $aiCfg['model'] ?? '', 'apiKey' => '', 'hasKey' => $aiCfg['hasKey'] ?? false]) }},
        whatsapp: {{ \Illuminate\Support\Js::from(['number' => $waCfg['number'] ?? '', 'provider' => $waCfg['provider'] ?? 'meta', 'token' => '', 'hasToken' => $waCfg['hasToken'] ?? false]) }},
        webhookUrl: window.location.origin + '/api/whatsapp/webhook',
        billing: {{ \Illuminate\Support\Js::from([
            'enabled'    => $biCfg['enabled'],
            'connector'  => $biCfg['connector'],
            'endpoint'   => $biCfg['endpoint'],
            'events'     => array_values($biCfg['events']),
            'hasSecret'  => $biCfg['hasSecret'],
            'hasSigning' => $biCfg['hasSigning'],
            'connectors' => $biCfg['connectors'],
            'secret'     => '',
            'signing_secret' => '',
            'testMsg'    => '',
            'testOk'     => null,
        ]) }},

        async _post(url, payload) {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                body: JSON.stringify(payload),
            });
            return res.json();
        },
        async saveDepts() {
            try { const d = await this._post('{{ route("web.admin.settings.departments") }}', { departments: this.departments }); alert(d.message || 'Saved!'); }
            catch(e) { alert('Failed to save departments.'); }
        },
        async saveAi() {
            try { const d = await this._post('{{ route("web.admin.settings.ai") }}', { provider: this.ai.provider, model: this.ai.model, api_key: this.ai.apiKey }); if (this.ai.apiKey) this.ai.hasKey = true; this.ai.apiKey = ''; alert(d.message || 'Saved!'); }
            catch(e) { alert('Failed to save AI settings.'); }
        },
        async saveWhatsapp() {
            try { const d = await this._post('{{ route("web.admin.settings.whatsapp") }}', { number: this.whatsapp.number, provider: this.whatsapp.provider, token: this.whatsapp.token }); if (this.whatsapp.token) this.whatsapp.hasToken = true; this.whatsapp.token = ''; alert(d.message || 'Saved!'); }
            catch(e) { alert('Failed to save WhatsApp settings.'); }
        },
        async saveBilling() {
            try {
                const d = await this._post('{{ route("web.admin.settings.billing-integration") }}', {
                    enabled: this.billing.enabled, connector: this.billing.connector, endpoint: this.billing.endpoint,
                    events: this.billing.events, secret: this.billing.secret, signing_secret: this.billing.signing_secret,
                });
                if (this.billing.secret) this.billing.hasSecret = true;
                if (this.billing.signing_secret) this.billing.hasSigning = true;
                this.billing.secret = ''; this.billing.signing_secret = '';
                alert(d.message || 'Saved!');
            } catch(e) { alert('Failed to save billing integration.'); }
        },
        async testBilling() {
            this.billing.testMsg = 'Testing…'; this.billing.testOk = null;
            try {
                const d = await this._post('{{ route("web.admin.settings.billing-integration.test") }}', {});
                this.billing.testOk = !!d.success; this.billing.testMsg = d.message || (d.success ? 'OK' : 'Failed');
            } catch(e) { this.billing.testOk = false; this.billing.testMsg = 'Test request failed.'; }
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
