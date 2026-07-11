@extends('layouts.app')
@section('title', 'API Keys')
@section('page-title', 'API Keys')

@section('content')
<div class="max-w-3xl space-y-6">

    @if(session('success'))
    <div class="px-4 py-2.5 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm">{{ session('success') }}</div>
    @endif

    @if($plainToken)
    <div class="bg-white rounded-xl shadow-sm border-2 border-cyan-300 p-6" x-data="{ token: @js($plainToken), copied: false }">
        <h3 class="text-sm font-semibold text-slate-800 mb-1">Your new API key</h3>
        <p class="text-xs text-slate-500 mb-3">Copy it now — for security it will not be shown again. Give it to your billing software.</p>
        <div class="flex items-center gap-2">
            <input type="text" readonly :value="token" class="input-field flex-1 font-mono text-xs bg-slate-50">
            <button type="button" @click="navigator.clipboard.writeText(token); copied = true" class="btn-primary text-sm whitespace-nowrap" x-text="copied ? 'Copied!' : 'Copy'"></button>
        </div>
    </div>
    @endif

    {{-- Create --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h3 class="text-base font-semibold text-slate-800 mb-1">Create an API key</h3>
        <p class="text-xs text-slate-500 mb-4">Lets your own billing / accounting software read (and optionally write) billing data from MedOS over the API. Scoped to this hospital.</p>
        <form method="POST" action="{{ route('web.admin.api-keys.create') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Key name</label>
                <input type="text" name="name" required maxlength="60" class="input-field" placeholder="e.g. Tally sync, Zoho Books">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Access</label>
                <div class="flex flex-wrap gap-4">
                    <label class="inline-flex items-center gap-2 text-sm"><input type="radio" name="access" value="read" checked class="border-slate-300"><span class="text-slate-600">Read-only (pull bills)</span></label>
                    <label class="inline-flex items-center gap-2 text-sm"><input type="radio" name="access" value="full" class="border-slate-300"><span class="text-slate-600">Read &amp; write (create bills / record payments)</span></label>
                </div>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="btn-primary">Generate key</button>
            </div>
        </form>
    </div>

    {{-- Existing keys --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200"><h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider">Active keys</h3></div>
        @if($tokens->isEmpty())
        <p class="text-sm text-slate-400 text-center py-8">No API keys yet.</p>
        @else
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="table-header">Name</th>
                    <th class="table-header">Access</th>
                    <th class="table-header">Last used</th>
                    <th class="table-header">Created</th>
                    <th class="table-header text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($tokens as $t)
                <tr>
                    <td class="px-4 py-3 text-sm text-slate-800">{{ \Illuminate\Support\Str::after($t['name'], 'api:') }}</td>
                    <td class="px-4 py-3 text-xs text-slate-500">{{ in_array('billing:write', $t['abilities']) ? 'Read & write' : 'Read-only' }}</td>
                    <td class="px-4 py-3 text-xs text-slate-500">{{ $t['last_used'] }}</td>
                    <td class="px-4 py-3 text-xs text-slate-500">{{ $t['created'] }}</td>
                    <td class="px-4 py-3 text-right">
                        <form method="POST" action="{{ route('web.admin.api-keys.revoke', $t['id']) }}" onsubmit="return confirm('Revoke this key? Software using it will stop working.')">
                            @csrf
                            <button type="submit" class="text-xs font-medium text-red-600 hover:text-red-800">Revoke</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    {{-- Usage --}}
    <div class="bg-slate-50 rounded-xl border border-slate-200 p-6">
        <h3 class="text-sm font-semibold text-slate-700 mb-3">How your software connects</h3>
        <p class="text-xs text-slate-500 mb-3">Send the key as a Bearer token. Base URL: <code class="text-slate-700">{{ url('/api/v1') }}</code></p>
        <pre class="bg-slate-900 text-slate-100 rounded-lg p-3 text-xs overflow-x-auto"><code>curl {{ url('/api/v1/billing/patient/{patientId}') }} \
  -H "Authorization: Bearer YOUR_KEY" \
  -H "Accept: application/json"</code></pre>
        <p class="text-xs text-slate-400 mt-3">Read: <code>GET /billing/{id}</code>, <code>GET /billing/patient/{patientId}</code>, <code>GET /billing/revenue/summary</code>. Write (full keys): <code>POST /billing/generate</code>, <code>POST /billing/{id}/pay</code>.</p>
    </div>
</div>
@endsection
