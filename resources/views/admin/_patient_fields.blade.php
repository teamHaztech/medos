@php
    $p = $patient ?? null;
    $healthLabel = \App\Modules\Core\Services\RegionService::healthIdLabel();
    $healthFmt   = \App\Modules\Core\Services\RegionService::healthIdSystem()['format'] ?? '';
    $ins = (array) ($p?->insurance_details ?? []);
    $langs = ['en' => 'English', 'hi' => 'Hindi', 'mr' => 'Marathi', 'kok' => 'Konkani', 'ar' => 'Arabic', 'ta' => 'Tamil', 'te' => 'Telugu', 'kn' => 'Kannada', 'bn' => 'Bengali'];
    $bgs = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
@endphp

<div x-data="patientForm({ verified: {{ $p?->abha_verified ? 'true' : 'false' }}, hasIns: {{ ! empty($ins) ? 'true' : 'false' }} })" class="space-y-6">

    {{-- 1. Demographics --}}
    <div>
        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">1 · Demographics</h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Full Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" required x-ref="name" value="{{ old('name', $p?->name) }}" class="input-field">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Phone <span class="text-red-500">*</span></label>
                <input type="tel" name="phone" required value="{{ old('phone', $p?->phone) }}" class="input-field" placeholder="10-digit or +country">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Gender</label>
                <select name="gender" x-ref="gender" class="input-field">
                    <option value="">-- Select --</option>
                    @foreach(['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $val => $lbl)
                        <option value="{{ $val }}" @selected(old('gender', $p?->gender) === $val)>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Date of Birth</label>
                    <input type="date" name="date_of_birth" x-ref="dob" value="{{ old('date_of_birth', optional($p?->date_of_birth)->format('Y-m-d')) }}" class="input-field">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">or Age</label>
                    <input type="number" name="age_approximate" min="0" max="130" value="{{ old('age_approximate', $p?->age_approximate) }}" class="input-field" placeholder="yrs">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Blood Group</label>
                <select name="blood_group" class="input-field">
                    <option value="">-- Select --</option>
                    @foreach($bgs as $bg)
                        <option value="{{ $bg }}" @selected(old('blood_group', $p?->blood_group) === $bg)>{{ $bg }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Language</label>
                <select name="language_preference" class="input-field">
                    @foreach($langs as $code => $lbl)
                        <option value="{{ $code }}" @selected(old('language_preference', $p?->language_preference ?? 'en') === $code)>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                <input type="email" name="email" value="{{ old('email', $p?->email) }}" class="input-field">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">City</label>
                <input type="text" name="city" value="{{ old('city', $p?->city) }}" class="input-field">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700 mb-1">Address</label>
                <textarea name="address" x-ref="address" rows="2" class="input-field">{{ old('address', $p?->address) }}</textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Emergency Contact Name</label>
                <input type="text" name="emergency_contact_name" value="{{ old('emergency_contact_name', $p?->emergency_contact_name) }}" class="input-field">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Emergency Contact Phone</label>
                <input type="tel" name="emergency_contact_phone" value="{{ old('emergency_contact_phone', $p?->emergency_contact_phone) }}" class="input-field">
            </div>
        </div>
    </div>

    {{-- 2. Insurance --}}
    @if(!isset($moduleOn) || $moduleOn('insurance'))
    <div>
        <div class="flex items-center justify-between mb-3">
            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400">2 · Insurance</h4>
            <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" x-model="hasIns" class="rounded border-slate-300 text-blue-600"> Has insurance
            </label>
        </div>
        <div x-show="hasIns" x-transition class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Provider / Payer</label>
                <input type="text" name="ins_provider" value="{{ old('ins_provider', $ins['provider'] ?? '') }}" class="input-field" placeholder="e.g. Star Health, CGHS">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Policy / Member No.</label>
                <input type="text" name="ins_policy_no" value="{{ old('ins_policy_no', $ins['policy_no'] ?? '') }}" class="input-field">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">TPA</label>
                <input type="text" name="ins_tpa" value="{{ old('ins_tpa', $ins['tpa'] ?? '') }}" class="input-field" placeholder="Third-party administrator">
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Valid Till</label>
                    <input type="date" name="ins_valid_till" value="{{ old('ins_valid_till', $ins['valid_till'] ?? '') }}" class="input-field">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Coverage</label>
                    <input type="number" step="0.01" min="0" name="ins_coverage" value="{{ old('ins_coverage', $ins['coverage'] ?? '') }}" class="input-field" placeholder="amount">
                </div>
            </div>
        </div>
    </div>

    @endif

    {{-- 3. ID verification --}}
    <div>
        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">3 · ID Verification — {{ $healthLabel }}</h4>
        <div class="flex flex-col sm:flex-row gap-2 sm:items-end">
            <div class="flex-1">
                <label class="block text-sm font-medium text-slate-700 mb-1">{{ $healthLabel }}</label>
                <input type="text" name="health_id" x-ref="healthId" value="{{ old('health_id', $p?->abha_number) }}" @input="verified=false" class="input-field" placeholder="{{ $healthFmt }}">
            </div>
            <button type="button" @click="verifyId()" :disabled="verifying" class="btn-secondary whitespace-nowrap" :class="verifying ? 'opacity-50' : ''">
                <span x-show="!verifying">Verify</span><span x-show="verifying" style="display:none">Verifying…</span>
            </button>
        </div>
        <div class="mt-2 flex items-center gap-2">
            <span x-show="verified" style="display:none" class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full bg-green-100 text-green-700">✓ Verified</span>
            <p x-show="verifyMsg" x-text="verifyMsg" :class="verified ? 'text-green-600' : 'text-amber-600'" class="text-xs"></p>
        </div>
        <input type="hidden" name="health_id_verified" :value="verified ? 1 : 0">
    </div>
</div>

@once
@push('scripts')
<script>
function patientForm(init) {
    return {
        verifying: false,
        verifyMsg: '',
        verified: init.verified,
        hasIns: init.hasIns,
        async verifyId() {
            const val = (this.$refs.healthId?.value || '').trim();
            if (!val) { this.verified = false; this.verifyMsg = 'Enter the number first.'; return; }
            this.verifying = true; this.verifyMsg = '';
            try {
                const r = await fetch('{{ route('web.admin.patients.verify-id') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ health_id: val }),
                });
                const d = await r.json();
                this.verified = !!d.verified;
                this.verifyMsg = d.message || (d.verified ? 'Verified' : 'Could not verify.');
                if (d.verified && d.profile) {
                    const pr = d.profile;
                    if (pr.name && this.$refs.name && !this.$refs.name.value) this.$refs.name.value = pr.name;
                    if (pr.gender && this.$refs.gender) this.$refs.gender.value = pr.gender;
                    if (pr.date_of_birth && this.$refs.dob && !this.$refs.dob.value) this.$refs.dob.value = pr.date_of_birth;
                    if (pr.address && this.$refs.address && !this.$refs.address.value) this.$refs.address.value = pr.address;
                }
            } catch (e) {
                this.verified = false; this.verifyMsg = 'Verification failed.';
            }
            this.verifying = false;
        },
    };
}
</script>
@endpush
@endonce
