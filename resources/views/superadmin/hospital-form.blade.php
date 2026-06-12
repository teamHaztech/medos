@extends('layouts.app')
@section('title', $hospital ? 'Edit Hospital' : 'Add Hospital')
@section('page-title', $hospital ? 'Edit Hospital' : 'Add New Hospital')

@section('content')
<div class="max-w-2xl">
    <a href="{{ route('web.superadmin.index') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700 mb-4">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Back to Hospitals
    </a>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6" x-data="{ country: '{{ $hospital?->country ?? 'IN' }}' }">
        <form method="POST" action="{{ $hospital ? route('web.superadmin.hospitals.update', $hospital->id) : route('web.superadmin.hospitals.store') }}" class="space-y-5">
            @csrf
            @if($hospital) @method('PUT') @endif

            {{-- Region selector --}}
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Region / Country *</label>
                <div class="grid grid-cols-2 gap-3">
                    <button type="button" @click="country = 'IN'"
                        :class="country === 'IN' ? 'ring-2 ring-blue-400 bg-blue-50 border-blue-300' : 'bg-white border-slate-200 hover:bg-slate-50'"
                        class="flex items-center gap-3 p-4 rounded-xl border transition-all text-left">
                        <span class="text-4xl">🇮🇳</span>
                        <div>
                            <p class="font-bold text-slate-900">India</p>
                            <p class="text-xs text-slate-500">₹ INR · ABHA · Hindi/English</p>
                        </div>
                    </button>
                    <button type="button" @click="country = 'AE'"
                        :class="country === 'AE' ? 'ring-2 ring-blue-400 bg-blue-50 border-blue-300' : 'bg-white border-slate-200 hover:bg-slate-50'"
                        class="flex items-center gap-3 p-4 rounded-xl border transition-all text-left">
                        <span class="text-4xl">🇦🇪</span>
                        <div>
                            <p class="font-bold text-slate-900">UAE</p>
                            <p class="text-xs text-slate-500">AED · Emirates ID · Arabic/English</p>
                        </div>
                    </button>
                </div>
                <input type="hidden" name="country" :value="country">
            </div>

            {{-- What changes per region --}}
            <div class="p-4 rounded-lg text-sm" :class="country === 'IN' ? 'bg-orange-50 border border-orange-200' : 'bg-green-50 border border-green-200'">
                <p class="font-semibold mb-1" :class="country === 'IN' ? 'text-orange-800' : 'text-green-800'" x-text="country === 'IN' ? '🇮🇳 India System' : '🇦🇪 UAE System'"></p>
                <div x-show="country === 'IN'" class="text-orange-700 space-y-0.5">
                    <p>• Currency: ₹ (INR) · Tax: GST 18%</p>
                    <p>• Health ID: ABHA (Ayushman Bharat)</p>
                    <p>• Insurance: PMJAY + TPA</p>
                    <p>• Languages: English, Hindi, Marathi, Konkani, Tamil, Telugu, Kannada, Bengali, Gujarati, Malayalam</p>
                    <p>• Emergency: 108 · Payment: UPI, Cash, Card</p>
                </div>
                <div x-show="country === 'AE'" class="text-green-700 space-y-0.5" style="display:none">
                    <p>• Currency: AED · Tax: VAT 5%</p>
                    <p>• Health ID: Emirates ID</p>
                    <p>• Insurance: DHA / DoH (mandatory)</p>
                    <p>• Languages: English, Arabic (RTL)</p>
                    <p>• Emergency: 999 · Payment: Card, Apple Pay, Insurance</p>
                </div>
            </div>

            {{-- Hospital details --}}
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Hospital Name *</label>
                <input type="text" name="name" value="{{ old('name', $hospital?->name) }}" required class="input-field" placeholder="City Care Hospital">
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Slug (URL identifier) *</label>
                <input type="text" name="slug" value="{{ old('slug', $hospital?->slug) }}" required class="input-field" placeholder="city-care">
                <p class="text-xs text-slate-400 mt-1">Used in URLs. Lowercase, no spaces. e.g. "city-care", "gulf-medical"</p>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">City *</label>
                    <input type="text" name="city" value="{{ old('city', $hospital?->city) }}" required class="input-field" :placeholder="country === 'IN' ? 'Bangalore' : 'Dubai'">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">State / Emirate</label>
                    <input type="text" name="state" value="{{ old('state', $hospital?->state) }}" class="input-field" :placeholder="country === 'IN' ? 'Karnataka' : 'Dubai'">
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Address</label>
                <input type="text" name="address" value="{{ old('address', $hospital?->address) }}" class="input-field" placeholder="Full address">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Phone</label>
                    <input type="tel" name="phone" value="{{ old('phone', $hospital?->phone) }}" class="input-field" :placeholder="country === 'IN' ? '+91 80 1234 5678' : '+971 4 321 5678'">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Email</label>
                    <input type="email" name="email" value="{{ old('email', $hospital?->email) }}" class="input-field" placeholder="admin@hospital.com">
                </div>
            </div>

            @if($hospital)
            <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-lg">
                <input type="checkbox" name="is_active" value="1" {{ $hospital->is_active ? 'checked' : '' }} class="rounded border-slate-300 text-blue-500">
                <label class="text-sm text-slate-700">Hospital is active</label>
            </div>
            @else
            {{-- Hospital Admin account — created with the hospital --}}
            <div class="pt-4 border-t border-slate-200">
                <p class="text-sm font-bold text-slate-800">Hospital Admin Account</p>
                <p class="text-xs text-slate-500 mb-3">This is the only account you create. The Hospital Admin logs in and creates their own staff (doctors, nurses, lab, billing…).</p>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Admin name *</label>
                        <input type="text" name="admin_name" value="{{ old('admin_name') }}" required class="input-field" placeholder="Dr. / Mr. Full Name">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Admin email (login) *</label>
                        <input type="email" name="admin_email" value="{{ old('admin_email') }}" required class="input-field" placeholder="admin@hospital.com">
                    </div>
                </div>
                <div class="mt-3">
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Temporary password</label>
                    <input type="text" name="admin_password" class="input-field" placeholder="Leave blank to auto-generate (shown after creation)">
                    <p class="text-xs text-slate-400 mt-1">You'll see the login + password to hand over after creating. They should change it on first login.</p>
                </div>
            </div>
            @endif

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="btn-primary px-6 py-2.5">{{ $hospital ? 'Update Hospital' : 'Create Hospital' }}</button>
                <a href="{{ route('web.superadmin.index') }}" class="btn-secondary px-6 py-2.5">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
