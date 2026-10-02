@props(['value'])
@php
    $map = [
        'active' => 'badge-green', 'valid' => 'badge-green', 'completed' => 'badge-green', 'done' => 'badge-green',
        'inactive' => 'badge-slate', 'cancelled' => 'badge-slate', 'replaced' => 'badge-slate', 'graduated' => 'badge-blue',
        'pending' => 'badge-amber', 'processing' => 'badge-blue', 'preview' => 'badge-amber', 'on_leave' => 'badge-amber',
        'failed' => 'badge-red', 'revoked' => 'badge-red', 'expired' => 'badge-red', 'suspended' => 'badge-red', 'terminated' => 'badge-red',
        'transferred' => 'badge-violet', 'retired' => 'badge-slate', 'invalid' => 'badge-red', 'duplicate' => 'badge-amber',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'badge '.($map[$value] ?? 'badge-slate')]) }}>{{ ucfirst(str_replace('_', ' ', (string) $value)) }}</span>
