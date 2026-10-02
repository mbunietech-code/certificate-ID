@php
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.') ?: '0';
    $pw = $fmt($composed['width']);
    $ph = $fmt($composed['height']);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { size: {{ $pw }}mm {{ $ph }}mm; margin: 0; }
        * { margin: 0; padding: 0; }
        html, body { margin: 0; padding: 0; }
        body { font-family: helvetica, Arial, sans-serif; }
        .page { position: relative; width: {{ $pw }}mm; height: {{ $ph }}mm; overflow: hidden; }
        .page + .page { page-break-before: always; }
        .slot { position: absolute; overflow: hidden; }
        .mark { position: absolute; background-color: #000; font-size: 0; line-height: 0; }
        @if ($mode === 'web')
        html { background: #e5e7eb; }
        body { -webkit-print-color-adjust: exact; print-color-adjust: exact; padding: 72px 0 32px; }
        .page { margin: 0 auto 16px; background: #fff; box-shadow: 0 1px 4px rgba(0,0,0,.25); }
        .toolbar { position: fixed; top: 0; left: 0; right: 0; height: 56px; display: flex; align-items: center; gap: 12px;
            padding: 0 20px; background: #0f172a; color: #e2e8f0; font: 14px/1.4 system-ui, sans-serif; z-index: 10; }
        .toolbar strong { color: #fff; }
        .toolbar .spacer { flex: 1; }
        .toolbar button, .toolbar a { background: #2563eb; color: #fff; border: 0; border-radius: 6px; padding: 8px 14px;
            font: 600 13px system-ui, sans-serif; cursor: pointer; text-decoration: none; }
        .toolbar a.secondary { background: #334155; }
        .toolbar .hint { color: #94a3b8; font-size: 12px; }
        @media print {
            html, body { background: none; padding: 0; }
            .toolbar { display: none; }
            .page { margin: 0; box-shadow: none; }
        }
        @endif
    </style>
</head>
<body>
@if ($mode === 'web')
    <div class="toolbar">
        <strong>{{ $title }}</strong>
        <span class="hint">{{ count($composed['pages']) }} page(s) · {{ $pw }} × {{ $ph }} mm · set printer scale to 100% / "Actual size", margins none</span>
        <span class="spacer"></span>
        @foreach ($toolbar as $link)
            <a href="{{ $link['url'] }}" class="secondary">{{ $link['label'] }}</a>
        @endforeach
        <button type="button" onclick="window.print()">Print</button>
    </div>
@endif
@foreach ($composed['pages'] as $slots)
    <div class="page">
        @foreach ($slots as $slot)
            @if ($slot['marks'])
                @php
                    $len = 4; $off = 1; $t = 0.15;
                    $x1 = $slot['x']; $y1 = $slot['y']; $x2 = $slot['x'] + $slot['w']; $y2 = $slot['y'] + $slot['h'];
                    $marks = [
                        // horizontal ticks [x, y, w, h]
                        [$x1 - $off - $len, $y1, $len, $t], [$x2 + $off, $y1, $len, $t],
                        [$x1 - $off - $len, $y2 - $t, $len, $t], [$x2 + $off, $y2 - $t, $len, $t],
                        // vertical ticks
                        [$x1, $y1 - $off - $len, $t, $len], [$x2 - $t, $y1 - $off - $len, $t, $len],
                        [$x1, $y2 + $off, $t, $len], [$x2 - $t, $y2 + $off, $t, $len],
                    ];
                @endphp
                @foreach ($marks as [$mx, $my, $mw, $mh])
                    @if ($mx >= 0 && $my >= 0)
                        <div class="mark" style="left:{{ $fmt($mx) }}mm;top:{{ $fmt($my) }}mm;width:{{ $fmt($mw) }}mm;height:{{ $fmt($mh) }}mm;"></div>
                    @endif
                @endforeach
            @endif
            <div class="slot" style="left:{{ $fmt($slot['x']) }}mm;top:{{ $fmt($slot['y']) }}mm;width:{{ $fmt($slot['w']) }}mm;height:{{ $fmt($slot['h']) }}mm;">{!! $slot['html'] !!}</div>
        @endforeach
    </div>
@endforeach
</body>
</html>
