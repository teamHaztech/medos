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

            {{-- Region Selector --}}
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Region</label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex items-center gap-3 p-4 rounded-xl border cursor-pointer transition-all"
                        :class="document.querySelector('input[name=country]:checked')?.value === 'IN' || '{{ $hospital->country ?? 'IN' }}' === 'IN' ? 'ring-2 ring-blue-400 bg-blue-50 border-blue-300' : 'bg-white border-slate-200 hover:bg-slate-50'">
                        <input type="radio" name="country" value="IN" {{ ($hospital->country ?? 'IN') === 'IN' ? 'checked' : '' }}
                            class="text-blue-500" onchange="this.closest('form').querySelectorAll('label.flex').forEach(l => l.className = l.className.replace(/ring-2.*border-blue-300/,'bg-white border-slate-200')); this.closest('label').classList.add('ring-2','ring-blue-400','bg-blue-50','border-blue-300')">
                        <span class="text-3xl">🇮🇳</span>
                        <div>
                            <p class="font-bold text-slate-900">India</p>
                            <p class="text-xs text-slate-500">₹ · ABHA · Hindi + Regional Languages</p>
                        </div>
                    </label>
                    <label class="flex items-center gap-3 p-4 rounded-xl border cursor-pointer transition-all"
                        :class="'{{ $hospital->country ?? 'IN' }}' === 'AE' ? 'ring-2 ring-blue-400 bg-blue-50 border-blue-300' : 'bg-white border-slate-200 hover:bg-slate-50'">
                        <input type="radio" name="country" value="AE" {{ ($hospital->country ?? '') === 'AE' ? 'checked' : '' }}
                            class="text-blue-500" onchange="this.closest('form').querySelectorAll('label.flex').forEach(l => l.className = l.className.replace(/ring-2.*border-blue-300/,'bg-white border-slate-200')); this.closest('label').classList.add('ring-2','ring-blue-400','bg-blue-50','border-blue-300')">
                        <span class="text-3xl">🇦🇪</span>
                        <div>
                            <p class="font-bold text-slate-900">UAE</p>
                            <p class="text-xs text-slate-500">AED · Emirates ID · English + Arabic</p>
                        </div>
                    </label>
                </div>
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
            <button @click="departments.push({ name: '', active: true, editing: true })" class="text-sm text-blue-600 hover:text-blue-800 font-medium">+ Add Department</button>
        </div>
        <div class="space-y-2">
            <template x-for="(dept, index) in departments" :key="index">
                <div class="flex items-center gap-3 py-2 border-b border-slate-100 last:border-0">
                    <template x-if="dept.editing">
                        <input type="text" x-model="dept.name" @keydown.enter="dept.editing = false" class="input-field flex-1" placeholder="Department name">
                    </template>
                    <template x-if="!dept.editing">
                        <span class="flex-1 text-sm text-slate-700" x-text="dept.name"></span>
                    </template>
                    <label class="flex items-center gap-1">
                        <input type="checkbox" x-model="dept.active" class="rounded border-slate-300 text-blue-600">
                        <span class="text-xs text-slate-500">Active</span>
                    </label>
                    <button @click="dept.editing = !dept.editing" class="text-xs text-blue-600 hover:text-blue-800">
                        <span x-text="dept.editing ? 'Save' : 'Edit'"></span>
                    </button>
                    <button @click="departments.splice(index, 1)" class="text-xs text-red-500 hover:text-red-700">Remove</button>
                </div>
            </template>
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
        days: [
            { name: 'monday', label: 'Monday', open: true, start: '08:00', end: '18:00' },
            { name: 'tuesday', label: 'Tuesday', open: true, start: '08:00', end: '18:00' },
            { name: 'wednesday', label: 'Wednesday', open: true, start: '08:00', end: '18:00' },
            { name: 'thursday', label: 'Thursday', open: true, start: '08:00', end: '18:00' },
            { name: 'friday', label: 'Friday', open: true, start: '08:00', end: '18:00' },
            { name: 'saturday', label: 'Saturday', open: true, start: '09:00', end: '14:00' },
            { name: 'sunday', label: 'Sunday', open: false, start: '09:00', end: '14:00' },
        ],
        departments: [
            { name: 'General Medicine', active: true, editing: false },
            { name: 'Cardiology', active: true, editing: false },
            { name: 'Pediatrics', active: true, editing: false },
            { name: 'Orthopedics', active: true, editing: false },
        ],
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
        saveHours() {
            // TODO: POST to API
            alert('Operating hours saved (placeholder)');
        },
    };
}
</script>
@endpush
