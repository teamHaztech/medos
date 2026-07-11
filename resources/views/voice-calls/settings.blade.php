@extends('layouts.app')

@section('title', 'Voice AI Settings')
@section('page-title', 'Voice AI Settings')

@section('content')
<div x-data="voiceSettings()">

    {{-- Status Toggle --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-800">Voice AI Status</h2>
                <p class="text-sm text-slate-500 mt-1" x-text="enabled ? 'Voice AI is accepting calls' : 'Voice AI is currently disabled'"></p>
            </div>
            <button type="button" @click="toggleEnabled()" class="relative inline-flex h-7 w-12 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                :class="enabled ? 'bg-green-500' : 'bg-slate-300'">
                <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform"
                    :class="enabled ? 'translate-x-6' : 'translate-x-1'"></span>
            </button>
        </div>
    </div>

    <form method="POST" action="{{ route('web.voice-calls.settings.save') }}">
        @csrf

        {{-- Provider Section --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6">
            <h3 class="text-lg font-semibold text-slate-800 mb-4">Telephony Provider</h3>

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Provider</label>
                    <select name="provider" x-model="provider" class="input-field max-w-xs">
                        <option value="">Select Provider</option>
                        <option value="exotel">Exotel</option>
                        <option value="twilio">Twilio</option>
                        <option value="knowlarity">Knowlarity</option>
                        <option value="ozonetel">Ozonetel</option>
                    </select>
                </div>

                {{-- Exotel fields --}}
                <div x-show="provider === 'exotel'" x-transition class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">API Key</label>
                        <input type="text" name="provider_config[api_key]" x-model="providerConfig.api_key" class="input-field" placeholder="Exotel API Key">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">API Token</label>
                        <input type="password" name="provider_config[api_token]" x-model="providerConfig.api_token" class="input-field" placeholder="Exotel API Token">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">SID</label>
                        <input type="text" name="provider_config[sid]" x-model="providerConfig.sid" class="input-field" placeholder="Exotel SID">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Subdomain</label>
                        <input type="text" name="provider_config[subdomain]" x-model="providerConfig.subdomain" class="input-field" placeholder="e.g. yourcompany">
                    </div>
                </div>

                {{-- Twilio fields --}}
                <div x-show="provider === 'twilio'" x-transition class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Account SID</label>
                        <input type="text" name="provider_config[account_sid]" x-model="providerConfig.account_sid" class="input-field" placeholder="Twilio Account SID">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Auth Token</label>
                        <input type="password" name="provider_config[auth_token]" x-model="providerConfig.auth_token" class="input-field" placeholder="Twilio Auth Token">
                    </div>
                </div>

                {{-- Knowlarity fields --}}
                <div x-show="provider === 'knowlarity'" x-transition class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">API Key</label>
                        <input type="text" name="provider_config[api_key]" x-model="providerConfig.api_key" class="input-field" placeholder="Knowlarity API Key">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">SR Number</label>
                        <input type="text" name="provider_config[sr_number]" x-model="providerConfig.sr_number" class="input-field" placeholder="SR Number">
                    </div>
                </div>

                {{-- Ozonetel fields --}}
                <div x-show="provider === 'ozonetel'" x-transition class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">API Key</label>
                        <input type="text" name="provider_config[api_key]" x-model="providerConfig.api_key" class="input-field" placeholder="Ozonetel API Key">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Agent ID</label>
                        <input type="text" name="provider_config[agent_id]" x-model="providerConfig.agent_id" class="input-field" placeholder="Ozonetel Agent ID">
                    </div>
                </div>

                {{-- Phone Numbers --}}
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Phone Numbers (comma-separated)</label>
                    <input type="text" name="phone_numbers" x-model="phoneNumbersRaw" class="input-field" placeholder="+91XXXXXXXXXX, +91XXXXXXXXXX">
                    <div class="flex flex-wrap gap-1.5 mt-2" x-show="phoneNumbersList.length > 0">
                        <template x-for="(num, idx) in phoneNumbersList" :key="idx">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 text-xs font-medium" x-text="num"></span>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        {{-- AI Configuration --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6">
            <h3 class="text-lg font-semibold text-slate-800 mb-4">AI Configuration</h3>

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">AI Model</label>
                    <select name="ai_model" class="input-field max-w-sm">
                        <option value="claude-haiku-4-5-20251001" {{ ($settings->ai_model ?? '') === 'claude-haiku-4-5-20251001' ? 'selected' : '' }}>Claude Haiku 4.5</option>
                        <option value="claude-sonnet-4-20250514" {{ ($settings->ai_model ?? '') === 'claude-sonnet-4-20250514' ? 'selected' : '' }}>Claude Sonnet 4</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Temperature: <span class="font-semibold text-slate-700" x-text="temperature"></span></label>
                    <input type="range" name="ai_temperature" x-model="temperature" min="0" max="1" step="0.1" class="w-full max-w-sm accent-blue-600">
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Greeting Message</label>
                    <textarea name="greeting_message" rows="3" class="input-field" placeholder="Hello! Thank you for calling {hospital_name}...">{{ $settings->greeting_message ?? '' }}</textarea>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">After Hours Message</label>
                    <textarea name="after_hours_message" rows="3" class="input-field" placeholder="We are currently closed. Please call back during business hours...">{{ $settings->after_hours_message ?? '' }}</textarea>
                </div>
            </div>
        </div>

        {{-- Languages --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6">
            <h3 class="text-lg font-semibold text-slate-800 mb-4">Languages</h3>

            @php
                $allLanguages = [
                    'en' => 'English', 'hi' => 'Hindi', 'mr' => 'Marathi', 'kok' => 'Konkani',
                    'ar' => 'Arabic', 'ta' => 'Tamil', 'te' => 'Telugu', 'kn' => 'Kannada', 'bn' => 'Bengali',
                ];
                $supported = $settings->supported_languages ?? ['en', 'hi'];
            @endphp

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-4">
                @foreach($allLanguages as $code => $label)
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="supported_languages[]" value="{{ $code }}"
                        {{ in_array($code, $supported) ? 'checked' : '' }}
                        @change="updateLangOptions()"
                        class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                    <span class="text-sm text-slate-700">{{ $label }}</span>
                </label>
                @endforeach
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">Default Language</label>
                <select name="default_language" class="input-field max-w-xs">
                    @foreach($allLanguages as $code => $label)
                    <option value="{{ $code }}" {{ ($settings->default_language ?? 'en') === $code ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Business Hours --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6">
            <h3 class="text-lg font-semibold text-slate-800 mb-4">Business Hours</h3>

            <input type="hidden" name="business_hours" :value="JSON.stringify(businessHours)">

            <div class="space-y-3">
                @foreach(['mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday'] as $dayKey => $dayLabel)
                <div class="flex flex-wrap items-center gap-3">
                    <label class="flex items-center gap-2 w-28 cursor-pointer">
                        <input type="checkbox" x-model="businessHours.{{ $dayKey }}.enabled"
                            class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        <span class="text-sm font-medium text-slate-700">{{ $dayLabel }}</span>
                    </label>
                    <div class="flex items-center gap-2" x-show="businessHours.{{ $dayKey }}.enabled">
                        <input type="time" x-model="businessHours.{{ $dayKey }}.start"
                            class="input-field w-auto" style="min-width: 130px">
                        <span class="text-sm text-slate-400">to</span>
                        <input type="time" x-model="businessHours.{{ $dayKey }}.end"
                            class="input-field w-auto" style="min-width: 130px">
                    </div>
                    <span x-show="!businessHours.{{ $dayKey }}.enabled" class="text-xs text-slate-400">Closed</span>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Auto-Callback --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6">
            <h3 class="text-lg font-semibold text-slate-800 mb-4">Auto-Callback</h3>

            <div class="space-y-4">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="hidden" name="auto_callback_enabled" value="0">
                    <input type="checkbox" name="auto_callback_enabled" value="1"
                        {{ ($settings->auto_callback_enabled ?? false) ? 'checked' : '' }}
                        x-model="autoCallbackEnabled"
                        class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                    <span class="text-sm font-medium text-slate-700">Enable auto-callback for missed calls</span>
                </label>

                <div x-show="autoCallbackEnabled" x-transition class="grid grid-cols-1 sm:grid-cols-2 gap-4 pl-7">
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Delay (minutes)</label>
                        <input type="number" name="auto_callback_delay_minutes" min="1" max="30"
                            value="{{ $settings->auto_callback_delay_minutes ?? 5 }}" class="input-field max-w-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Max Attempts</label>
                        <input type="number" name="max_concurrent_calls" min="1" max="5"
                            value="{{ $settings->max_concurrent_calls ?? 3 }}" class="input-field max-w-xs">
                    </div>
                </div>
            </div>
        </div>

        {{-- Emergency --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6">
            <h3 class="text-lg font-semibold text-slate-800 mb-4">Emergency</h3>

            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">Emergency Transfer Number</label>
                <input type="tel" name="emergency_transfer_number"
                    value="{{ $settings->emergency_transfer_number ?? '' }}" class="input-field max-w-xs" placeholder="+91XXXXXXXXXX">
                <p class="text-xs text-slate-400 mt-1">When emergency keywords are detected, calls will be transferred to this number immediately.</p>
            </div>
        </div>

        {{-- Save button --}}
        <div>
            <button type="submit" class="btn-primary w-full sm:w-auto px-8 py-2.5">Save Settings</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
function voiceSettings() {
    @php
        $pc = $settings->provider_config ?? [];
        $bh = $settings->business_hours ?? null;
        $defaultBh = [];
        foreach (['mon','tue','wed','thu','fri','sat','sun'] as $d) {
            $defaultBh[$d] = [
                'enabled' => isset($bh[$d]['enabled']) ? (bool) $bh[$d]['enabled'] : ($d !== 'sun'),
                'start'   => $bh[$d]['start'] ?? '09:00',
                'end'     => $bh[$d]['end'] ?? '18:00',
            ];
        }
        $phoneArr = $settings->phone_numbers ?? [];
    @endphp

    return {
        enabled: @js((bool) ($settings->is_enabled ?? false)),
        provider: @js($settings->provider ?? ''),
        providerConfig: @js((object) $pc),
        phoneNumbersRaw: @js(implode(', ', $phoneArr)),
        temperature: @js($settings->ai_temperature ?? 0.3),
        businessHours: @js($defaultBh),
        autoCallbackEnabled: @js((bool) ($settings->auto_callback_enabled ?? false)),

        get phoneNumbersList() {
            if (!this.phoneNumbersRaw) return [];
            return this.phoneNumbersRaw.split(',').map(n => n.trim()).filter(n => n.length > 0);
        },

        async toggleEnabled() {
            try {
                const res = await fetch('{{ route("web.voice-calls.toggle") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                });
                if (res.ok) {
                    const data = await res.json();
                    this.enabled = data.enabled;
                } else {
                    alert('Failed to toggle Voice AI status.');
                }
            } catch (e) {
                console.error(e);
                alert('Failed to toggle Voice AI status.');
            }
        },

        updateLangOptions() {
            // Handled natively by checkboxes
        },
    };
}
</script>
@endpush
