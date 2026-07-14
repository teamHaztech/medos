@extends('layouts.app')

@section('title', 'Call Detail')
@section('page-title', 'Call Detail')

@section('content')
<div x-data="callDetailPage()">
    {{-- Back link --}}
    <a href="{{ route('web.voice-calls.calls') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-blue-600 mb-4">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Back to Call Log
    </a>

    {{-- Call header card --}}
    @php
        $dur = $call->duration_seconds ?? 0;
        $durMin = intdiv($dur, 60);
        $durSec = $dur % 60;

        $statusColors = [
            'ringing'      => 'bg-yellow-100 text-yellow-700',
            'in_progress'  => 'bg-blue-100 text-blue-700',
            'completed'    => 'bg-green-100 text-green-700',
            'missed'       => 'bg-red-100 text-red-700',
            'failed'       => 'bg-slate-100 text-slate-600',
            'transferred'  => 'bg-purple-100 text-purple-700',
        ];
        $statusClass = $statusColors[$call->status] ?? 'bg-slate-100 text-slate-600';
    @endphp
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
            {{-- Left: caller info --}}
            <div class="flex flex-col gap-2">
                <div class="flex items-center gap-3">
                    <span class="text-xl font-bold text-slate-800">{{ $call->caller_number ?: 'Unknown Number' }}</span>
                    @if($call->direction === 'inbound')
                        <span class="inline-flex items-center gap-1 text-xs font-medium text-blue-700 bg-blue-100 px-2 py-0.5 rounded-full">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                            Inbound
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 text-xs font-medium text-green-700 bg-green-100 px-2 py-0.5 rounded-full">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                            Outbound
                        </span>
                    @endif
                    <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $statusClass }}">{{ ucfirst(str_replace('_', ' ', $call->status ?? 'unknown')) }}</span>
                </div>
                <p class="text-sm text-slate-500">Duration: <strong class="text-slate-700">{{ $durMin }}m {{ $durSec }}s</strong></p>
            </div>

            {{-- Right: timestamps --}}
            <div class="flex flex-col gap-1 text-sm text-slate-500 lg:text-right">
                <p>Started: <strong class="text-slate-700">{{ $call->started_at?->format('M j, Y g:i:s A') ?? '' }}</strong></p>
                <p>Answered: <strong class="text-slate-700">{{ $call->answered_at?->format('M j, Y g:i:s A') ?? '' }}</strong></p>
                <p>Ended: <strong class="text-slate-700">{{ $call->ended_at?->format('M j, Y g:i:s A') ?? '' }}</strong></p>
            </div>
        </div>

        {{-- Patient card --}}
        @if($call->patient)
        @php
            $upcomingCount = \App\Modules\Appointment\Models\Appointment::where('hospital_id', $call->hospital_id)
                ->where('patient_id', $call->patient_id)
                ->where('slot_start', '>=', now())
                ->count();
        @endphp
        <div class="mt-4 p-4 bg-slate-50 rounded-lg border border-slate-200">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center text-sm font-semibold text-blue-700">
                    {{ strtoupper(substr($call->patient->name, 0, 1)) }}
                </div>
                <div>
                    <p class="text-sm font-semibold text-slate-800">{{ $call->patient->name }}</p>
                    <p class="text-xs text-slate-500">{{ $call->patient->phone ?? '' }} &middot; {{ ucfirst($call->patient->gender ?? '') }} &middot; {{ $upcomingCount }} upcoming appointment{{ $upcomingCount !== 1 ? 's' : '' }}</p>
                </div>
            </div>
        </div>
        @endif
    </div>

    {{-- Two column layout --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left 2/3: Transcript panel --}}
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-slate-800">Conversation Transcript</h2>
                    @if($call->status === 'in_progress')
                        <span class="inline-flex items-center gap-1.5 text-xs font-medium text-green-700">
                            <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                            Live
                        </span>
                    @endif
                </div>

                <div class="space-y-3" id="transcript-container">
                    @forelse($call->transcripts as $transcript)
                    @php
                        $tsMs = $transcript->timestamp_ms ?? 0;
                        $startMs = $call->started_at ? ($call->started_at->timestamp * 1000) : 0;
                        $offsetSec = $startMs > 0 ? max(0, intdiv($tsMs - $startMs, 1000)) : 0;
                        $offsetMin = intdiv($offsetSec, 60);
                        $offsetRemSec = $offsetSec % 60;
                        $offsetLabel = $offsetMin . ':' . str_pad($offsetRemSec, 2, '0', STR_PAD_LEFT);
                    @endphp
                        @if($transcript->speaker === 'system')
                            {{-- System message --}}
                            <div class="text-center">
                                <p class="text-xs italic text-slate-400">{{ $transcript->content }}</p>
                            </div>
                        @elseif($transcript->speaker === 'ai')
                            {{-- AI message: right-aligned --}}
                            <div class="flex justify-end">
                                <div class="max-w-xs sm:max-w-sm md:max-w-md">
                                    <div class="bg-blue-600 text-white rounded-xl rounded-br-sm px-4 py-2.5 text-sm">
                                        {{ $transcript->content }}
                                    </div>
                                    <p class="text-right mt-0.5 text-xs text-slate-400">{{ $offsetLabel }}</p>
                                </div>
                            </div>
                        @else
                            {{-- Caller message: left-aligned --}}
                            <div class="flex justify-start">
                                <div class="max-w-xs sm:max-w-sm md:max-w-md">
                                    <div class="bg-slate-100 text-slate-800 rounded-xl rounded-bl-sm px-4 py-2.5 text-sm">
                                        {{ $transcript->content }}
                                    </div>
                                    <p class="mt-0.5 text-xs text-slate-400">{{ $offsetLabel }}</p>
                                </div>
                            </div>
                        @endif
                    @empty
                        <p class="text-sm text-slate-400 text-center py-8">No transcript available for this call.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Right 1/3: Call metadata --}}
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                <h2 class="text-lg font-semibold text-slate-800 mb-4">Call Metadata</h2>

                @php
                    $langLabels = [
                        'en' => 'English', 'hi' => 'Hindi', 'mr' => 'Marathi', 'kok' => 'Konkani',
                        'ar' => 'Arabic', 'ta' => 'Tamil', 'te' => 'Telugu', 'kn' => 'Kannada', 'bn' => 'Bengali',
                    ];
                    $purposeLabels = [
                        'appointment_booking' => 'Appointment Booking', 'schedule_check' => 'Schedule Check',
                        'lab_results' => 'Lab Results', 'queue_status' => 'Queue Status',
                        'general_inquiry' => 'General Inquiry', 'emergency' => 'Emergency',
                        'callback' => 'Callback', 'cancellation' => 'Cancellation', 'transfer_request' => 'Transfer Request',
                    ];
                    $sentimentColors = [
                        'positive' => 'text-green-600', 'neutral' => 'text-slate-600',
                        'negative' => 'text-red-600', 'urgent' => 'text-red-700', 'frustrated' => 'text-orange-600',
                    ];
                    $confidenceVal = floatval($call->ai_confidence ?? 0);
                    $confidencePct = min(100, round($confidenceVal * 100));
                @endphp

                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Language Detected</dt>
                        <dd class="font-medium text-slate-700">{{ $langLabels[$call->language_detected] ?? ($call->language_detected ?? '') }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Language Used</dt>
                        <dd class="font-medium text-slate-700">{{ $langLabels[$call->language_used] ?? ($call->language_used ?? '') }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Call Purpose</dt>
                        <dd class="font-medium text-slate-700">{{ $purposeLabels[$call->call_purpose] ?? ($call->call_purpose ? ucfirst(str_replace('_', ' ', $call->call_purpose)) : '') }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Sentiment</dt>
                        <dd class="font-medium {{ $sentimentColors[$call->sentiment] ?? 'text-slate-700' }}">{{ $call->sentiment ? ucfirst($call->sentiment) : '' }}</dd>
                    </div>
                    <div>
                        <div class="flex justify-between mb-1">
                            <dt class="text-slate-500">AI Confidence</dt>
                            <dd class="font-medium text-slate-700">{{ $confidencePct }}%</dd>
                        </div>
                        <div class="w-full bg-slate-200 rounded-full h-2">
                            <div class="h-2 rounded-full {{ $confidencePct >= 70 ? 'bg-green-500' : ($confidencePct >= 40 ? 'bg-yellow-500' : 'bg-red-500') }}" style="width: {{ $confidencePct }}%"></div>
                        </div>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Cost</dt>
                        <dd class="font-medium text-slate-700">{{ $call->cost_amount ? number_format($call->cost_amount, 4) . ' ' . ($call->cost_currency ?? '') : '' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Provider Call SID</dt>
                        <dd class="font-medium text-slate-700 truncate" style="max-width: 150px" title="{{ $call->call_sid ?? '' }}">{{ $call->call_sid ?? '' }}</dd>
                    </div>
                    @if($call->recording_url)
                    <div>
                        <dt class="text-slate-500 mb-1">Recording</dt>
                        <dd>
                            <audio controls class="w-full" preload="none">
                                <source src="{{ $call->recording_url }}" type="audio/mpeg">
                                Your browser does not support the audio element.
                            </audio>
                        </dd>
                    </div>
                    @else
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Recording</dt>
                        <dd class="text-slate-400 text-xs">Not available</dd>
                    </div>
                    @endif
                </dl>

                {{-- Actions --}}
                <div class="mt-6 pt-4 border-t border-slate-200">
                    <button type="button" onclick="downloadTranscript()" class="btn-secondary w-full text-sm">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Download Transcript
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function callDetailPage() {
    return {
        @if($call->status === 'in_progress')
        init() {
            this.pollTranscript();
        },
        pollTranscript() {
            setInterval(async () => {
                try {
                    const res = await fetch('{{ route("web.voice-calls.calls.transcript", $call->id) }}', {
                        headers: { 'Accept': 'application/json' }
                    });
                    if (!res.ok) return;
                    const data = await res.json();
                    const container = document.getElementById('transcript-container');
                    if (!container || !data.length) return;

                    const startMs = {{ $call->started_at ? ($call->started_at->timestamp * 1000) : 0 }};

                    let html = '';
                    data.forEach(t => {
                        const offsetSec = startMs > 0 ? Math.max(0, Math.floor((t.timestamp_ms - startMs) / 1000)) : 0;
                        const offsetMin = Math.floor(offsetSec / 60);
                        const offsetRemSec = String(offsetSec % 60).padStart(2, '0');
                        const offsetLabel = offsetMin + ':' + offsetRemSec;

                        if (t.speaker === 'system') {
                            html += '<div class="text-center"><p class="text-xs italic text-slate-400">' + this.esc(t.content) + '</p></div>';
                        } else if (t.speaker === 'ai') {
                            html += '<div class="flex justify-end"><div class="max-w-xs sm:max-w-sm md:max-w-md"><div class="bg-blue-600 text-white rounded-xl rounded-br-sm px-4 py-2.5 text-sm">' + this.esc(t.content) + '</div><p class="text-right mt-0.5 text-xs text-slate-400">' + offsetLabel + '</p></div></div>';
                        } else {
                            html += '<div class="flex justify-start"><div class="max-w-xs sm:max-w-sm md:max-w-md"><div class="bg-slate-100 text-slate-800 rounded-xl rounded-bl-sm px-4 py-2.5 text-sm">' + this.esc(t.content) + '</div><p class="mt-0.5 text-xs text-slate-400">' + offsetLabel + '</p></div></div>';
                        }
                    });
                    container.innerHTML = html;
                    container.scrollTop = container.scrollHeight;
                } catch (e) { /* ignore polling errors */ }
            }, 2000);
        },
        esc(str) {
            const d = document.createElement('div');
            d.textContent = str;
            return d.innerHTML;
        },
        @endif
    };
}

function downloadTranscript() {
    const lines = [];
    @foreach($call->transcripts as $transcript)
    lines.push(@js(strtoupper($transcript->speaker) . ': ' . $transcript->content));
    @endforeach

    if (!lines.length) { alert('No transcript to download.'); return; }

    const text = 'Call Transcript - {{ $call->caller_number }} - {{ $call->created_at?->format("Y-m-d H:i") }}\n' +
                 '========================================\n\n' +
                 lines.join('\n\n');
    const blob = new Blob([text], { type: 'text/plain' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'transcript-{{ $call->id }}.txt';
    a.click();
    URL.revokeObjectURL(a.href);
}
</script>
@endpush
