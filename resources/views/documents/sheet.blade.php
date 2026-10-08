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
        .toolbar select { background: #1e293b; color: #e2e8f0; border: 1px solid #334155; border-radius: 6px; padding: 7px 8px; font: 13px system-ui, sans-serif; }
        .toolbar button.png { background: #059669; }
        .toolbar button:disabled { opacity: .6; cursor: wait; }
        .png-btn { position: absolute; top: 6px; right: 6px; z-index: 50; background: rgba(15,23,42,.8); color: #fff; border: 0; border-radius: 5px;
            padding: 4px 8px; font: 600 11px system-ui, sans-serif; cursor: pointer; opacity: 0; transition: opacity .15s; }
        .page:hover .png-btn, .png-btn:focus { opacity: 1; }
        @media print {
            html, body { background: none; padding: 0; }
            .toolbar, .png-btn { display: none; }
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
        <select id="png-dpi" title="PNG resolution">
            <option value="600" selected>PNG 600 DPI (high quality)</option>
            <option value="300">PNG 300 DPI</option>
        </select>
        <button type="button" class="png" id="png-all">Download PNG</button>
        <button type="button" onclick="window.print()">Print</button>
    </div>
@endif
@foreach ($composed['pages'] as $pageIndex => $slots)
    <div class="page" data-png-name="{{ \Illuminate\Support\Str::slug($composed['labels'][$pageIndex] ?? 'page-'.($pageIndex + 1)) }}">
        @if ($mode === 'web')
            <button type="button" class="png-btn" title="Download this page as PNG">PNG</button>
        @endif
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
@if ($mode === 'web')
<script>
    /**
     * High-quality PNG export without extra libraries: each page's exact markup is
     * drawn into an SVG <foreignObject> at the chosen DPI and rasterised on a canvas.
     * Images are embedded as data URLs first so the canvas can be exported.
     */
    (() => {
        const MM_PER_INCH = 25.4;
        const pageWidthMm = {{ $pw }};
        const pageHeightMm = {{ $ph }};
        const baseName = @json(\Illuminate\Support\Str::slug($title) ?: 'document');
        const pageCss = '* { margin: 0; padding: 0; } .page { position: relative; overflow: hidden; background: #fff; font-family: helvetica, Arial, sans-serif; }'
            + ' .slot { position: absolute; overflow: hidden; } .mark { position: absolute; background-color: #000; font-size: 0; line-height: 0; }';
        const dataUrlCache = new Map();

        const toDataUrl = (url) => {
            if (!dataUrlCache.has(url)) {
                dataUrlCache.set(url, fetch(url, { credentials: 'same-origin' })
                    .then((r) => r.blob())
                    .then((blob) => new Promise((resolve) => {
                        const reader = new FileReader();
                        reader.onload = () => resolve(reader.result);
                        reader.readAsDataURL(blob);
                    })));
            }
            return dataUrlCache.get(url);
        };

        const renderPage = async (page, dpi) => {
            const pxWidth = Math.round(pageWidthMm / MM_PER_INCH * dpi);
            const pxHeight = Math.round(pageHeightMm / MM_PER_INCH * dpi);
            const clone = page.cloneNode(true);
            clone.querySelectorAll('.png-btn').forEach((b) => b.remove());
            clone.style.cssText = `width:${pageWidthMm}mm;height:${pageHeightMm}mm;margin:0;box-shadow:none;`;
            await Promise.all([...clone.querySelectorAll('img')].map(async (img) => {
                const src = img.getAttribute('src');
                if (src && !src.startsWith('data:')) {
                    img.setAttribute('src', await toDataUrl(new URL(src, location.href).href));
                }
            }));

            // CSS mm are 96 DPI; scale the page up to the requested resolution.
            const scale = dpi / 96;
            const markup = new XMLSerializer().serializeToString(clone);
            const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${pxWidth}" height="${pxHeight}">`
                + `<foreignObject x="0" y="0" width="100%" height="100%"><div xmlns="http://www.w3.org/1999/xhtml" style="transform:scale(${scale});transform-origin:0 0;">`
                + `<style>${pageCss}</style>${markup}</div></foreignObject></svg>`;

            const image = new Image();
            image.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
            await image.decode();

            const canvas = document.createElement('canvas');
            canvas.width = pxWidth;
            canvas.height = pxHeight;
            const ctx = canvas.getContext('2d');
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, pxWidth, pxHeight);
            ctx.drawImage(image, 0, 0, pxWidth, pxHeight);

            return new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
        };

        const download = (blob, name) => {
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = name;
            document.body.appendChild(link);
            link.click();
            link.remove();
            setTimeout(() => URL.revokeObjectURL(link.href), 5000);
        };

        const exportPages = async (pages, button) => {
            const dpi = Number(document.getElementById('png-dpi').value) || 600;
            const label = button.textContent;
            button.disabled = true;
            try {
                for (const [i, page] of pages.entries()) {
                    button.textContent = pages.length > 1 ? `PNG ${i + 1}/${pages.length}…` : 'PNG…';
                    const blob = await renderPage(page, dpi);
                    download(blob, `${baseName}-${page.dataset.pngName}-${dpi}dpi.png`);
                    await new Promise((r) => setTimeout(r, 350)); // let the browser start each download
                }
            } catch (error) {
                console.error(error);
                alert('Could not create the PNG. Please try again or use Print → Save as PDF.');
            } finally {
                button.disabled = false;
                button.textContent = label;
            }
        };

        document.getElementById('png-all').addEventListener('click', (e) => exportPages([...document.querySelectorAll('.page')], e.currentTarget));
        document.querySelectorAll('.png-btn').forEach((btn) => btn.addEventListener('click', (e) => exportPages([btn.closest('.page')], e.currentTarget)));
    })();
</script>
@endif
</body>
</html>
