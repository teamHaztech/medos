@php
    // Determine region from hospital or default
    $hospital = \App\Modules\Core\Models\Hospital::where('is_active', true)->first();
    $regionCode = $hospital?->country ?? 'IN';
    $region = \App\Modules\Core\Services\RegionService::get($regionCode);
@endphp

<script>
    // Global region config available to all Alpine.js components
    window.MEDOS_REGION = {
        country: @json($regionCode),
        name: @json($region['name']),
        currency: @json($region['currency']),
        phonePrefix: @json($region['phone_prefix']),
        phoneDigits: @json($region['phone_digits']),
        languages: @json($region['languages']),
        defaultLanguage: @json($region['default_language']),
        voiceLanguages: @json($region['voice_languages']),
        healthId: @json($region['health_id']),
        insurance: @json($region['insurance']),
        paymentMethods: @json($region['payment_methods']),
        emergencyNumber: @json($region['emergency_number']),
        isIndia: @json($regionCode === 'IN'),
        isUAE: @json($regionCode === 'AE'),
    };
</script>
