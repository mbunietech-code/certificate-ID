/**
 * Template designer (ID cards & certificates).
 *
 * Works in millimetres, exactly like the server renderer
 * (App\Services\Documents\TemplateRenderer): positions/sizes in mm, font sizes in pt.
 * Only the layout lives here; the server sanitises and renders the final output.
 */

const cfg = JSON.parse(document.getElementById('designer-config').textContent);
const MM_PER_PT = 25.4 / 72;
const ID_PREFIX = 'el';

const $ = (sel, root = document) => root.querySelector(sel);
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const round = (n, step = 0.01) => Math.round(n / step) * step;
const clamp = (n, min, max) => Math.min(max, Math.max(min, n));

const state = {
    design: normalize(cfg.design || {}),
    side: 'front',
    selectedId: null,
    scale: 4,
    snap: true,
    dirty: false,
    undo: [],
    redo: [],
};

const els = {
    stage: $('#dz-stage'),
    card: null,
    layers: $('#dz-layers'),
    props: $('#dz-props'),
    status: $('#dz-status'),
};

function normalize(design) {
    for (const side of cfg.sides) {
        design[side] ??= {};
        design[side].background ??= { color: '#ffffff', image: null };
        design[side].elements = Array.isArray(design[side].elements) ? design[side].elements : [];
    }
    return design;
}

const side = () => state.design[state.side];
const elements = () => side().elements;
const selected = () => elements().find((e) => e.id === state.selectedId) || null;

/* ------------------------------------------------------------------ history */

function snapshot() {
    state.undo.push(JSON.stringify(state.design));
    if (state.undo.length > 60) state.undo.shift();
    state.redo = [];
    markDirty();
}

function undo() {
    if (!state.undo.length) return;
    state.redo.push(JSON.stringify(state.design));
    state.design = normalize(JSON.parse(state.undo.pop()));
    markDirty();
    renderAll();
}

function redo() {
    if (!state.redo.length) return;
    state.undo.push(JSON.stringify(state.design));
    state.design = normalize(JSON.parse(state.redo.pop()));
    markDirty();
    renderAll();
}

function markDirty(dirty = true) {
    state.dirty = dirty;
    els.status.textContent = dirty ? 'Unsaved changes' : els.status.textContent;
    els.status.className = dirty ? 'text-xs font-medium text-amber-600' : 'text-xs text-emerald-600';
}

window.addEventListener('beforeunload', (e) => {
    if (state.dirty) {
        e.preventDefault();
        e.returnValue = '';
    }
});

/* ------------------------------------------------------------------ colors & text */

function color(value, fallback = '#000000') {
    if (typeof value !== 'string') return fallback;
    if (value === 'transparent') return 'transparent';
    if (value.startsWith('@')) return cfg.colors[value.slice(1)] || fallback;
    return /^#[0-9a-f]{3}([0-9a-f]{3})?$/i.test(value) ? value : fallback;
}

function substitute(content) {
    return esc(content).replace(/\{([a-z_]+)\}/g, (m, key) => (key in cfg.sample ? esc(cfg.sample[key]) : m)).replace(/\n/g, '<br>');
}

function plain(content) {
    return String(content ?? '').replace(/\{([a-z_]+)\}/g, (m, key) => (key in cfg.sample ? cfg.sample[key] : m));
}

const measureCtx = document.createElement('canvas').getContext('2d');

/** Mirror of TextMeasurer::fitSize using the browser font (close to the PDF metrics). */
function fittedSize(el) {
    const size = Number(el.fontSize) || 10;
    if (!el.shrink) return size;
    const min = Number(el.minFontSize) || size / 2;
    let text = plain(el.content);
    if (el.uppercase) text = text.toUpperCase();
    const font = cfg.fonts[el.fontFamily]?.css || cfg.fonts.helvetica.css;
    measureCtx.font = `${el.italic ? 'italic ' : ''}${el.fontWeight === 'bold' ? 'bold ' : ''}${size}pt ${font}`;
    const spacing = (Number(el.letterSpacing) || 0);
    let widest = 0;
    for (const line of text.split('\n')) {
        const ptWidth = measureCtx.measureText(line).width * 0.75 + Math.max(0, line.length - 1) * spacing; // px@96dpi -> pt
        widest = Math.max(widest, ptWidth * MM_PER_PT);
    }
    if (widest <= el.w * 0.98 || widest <= 0) return size;
    return Math.max(min, Math.floor(size * (el.w * 0.97) / widest * 10) / 10);
}

/* ------------------------------------------------------------------ rendering */

const QR_SVG = 'data:image/svg+xml;utf8,' + encodeURIComponent(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 29 29" shape-rendering="crispEdges"><rect width="29" height="29" fill="#fff"/>'
    + '<path d="M1 1h7v7H1zM2 2v5h5V2zM3 3h3v3H3zM21 1h7v7h-7zm1 1v5h5V2zm1 1h3v3h-3zM1 21h7v7H1zm1 1v5h5v-5zm1 1h3v3H3z'
    + 'M10 1h2v2h-2zM13 2h2v3h-2zM10 5h2v3h-2zM16 1h3v2h-3zM15 6h2v3h-2zM10 10h3v2h-3zM14 10h2v4h-2zM18 10h3v2h-3zM22 10h5v2h-5zM1 10h3v2H1zM5 12h3v2H5z'
    + 'M10 14h2v3h-2zM17 13h3v3h-3zM22 14h2v3h-2zM25 13h3v2h-3zM1 15h2v3H1zM4 16h3v2H4zM13 16h3v2h-3zM10 19h3v2h-3zM15 19h2v3h-2zM19 18h2v2h-2zM23 19h3v2h-3z'
    + 'M10 23h2v3h-2zM13 24h3v2h-3zM18 22h2v3h-2zM21 23h2v4h-2zM24 23h4v2h-4zM12 27h4v1h-4zM17 26h3v2h-3zM25 26h3v2h-3z" fill="currentColor"/></svg>',
);

function barcodeSvg(fill) {
    let x = 0;
    let bars = '';
    const pattern = [2, 1, 1, 2, 3, 1, 1, 1, 2, 2, 1, 3, 1, 1, 2, 1, 3, 2, 1, 1, 2, 2, 1, 1, 3, 1, 2, 1, 1, 2, 3, 1, 1, 2, 1, 1, 2, 3];
    pattern.forEach((w, i) => {
        if (i % 2 === 0) bars += `<rect x="${x}" width="${w}" height="10"/>`;
        x += w;
    });
    return 'data:image/svg+xml;utf8,' + encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${x} 10" preserveAspectRatio="none" fill="${fill}">${bars}</svg>`);
}

function px(mm) {
    return `${mm * state.scale}px`;
}

function elementNode(el) {
    const node = document.createElement('div');
    node.className = 'dz-el';
    node.dataset.id = el.id;
    positionNode(node, el);

    const s = state.scale;
    if (el.type === 'text') {
        const size = fittedSize(el);
        Object.assign(node.style, {
            fontFamily: cfg.fonts[el.fontFamily]?.css || cfg.fonts.helvetica.css,
            fontSize: `${size * MM_PER_PT * s}px`,
            lineHeight: String(el.lineHeight ?? 1.2),
            color: color(el.color),
            textAlign: el.align || 'left',
            fontWeight: el.fontWeight === 'bold' ? 'bold' : 'normal',
            fontStyle: el.italic ? 'italic' : 'normal',
            textTransform: el.uppercase ? 'uppercase' : 'none',
            letterSpacing: `${(Number(el.letterSpacing) || 0) * MM_PER_PT * s}px`,
            whiteSpace: el.shrink ? 'nowrap' : 'normal',
            overflow: 'hidden',
            wordWrap: 'break-word',
        });
        node.innerHTML = substitute(el.content) || '<span class="dz-muted">Empty text</span>';
    } else if (el.type === 'image') {
        const border = Number(el.borderWidth) || 0;
        Object.assign(node.style, {
            borderRadius: px(Number(el.radius) || 0),
            border: border ? `${px(border)} solid ${color(el.borderColor)}` : '0',
            boxSizing: 'border-box',
            overflow: 'hidden',
        });
        const url = el.source === 'custom' ? (el.src ? cfg.assetBase + el.src : null) : cfg.sampleImages[el.source];
        if (url) {
            const fit = el.fit === 'stretch' ? 'fill' : (el.fit || (el.source === 'photo' ? 'cover' : 'contain'));
            node.innerHTML = `<img src="${esc(url)}" alt="" draggable="false" style="width:100%;height:100%;object-fit:${fit};display:block">`;
        } else if (el.source === 'photo') {
            node.innerHTML = '<div class="dz-silhouette"><span></span><span></span></div>';
        } else {
            node.innerHTML = `<div class="dz-placeholder">${esc(cfg.imageSources[el.source] || 'Image')}</div>`;
        }
    } else if (el.type === 'qr') {
        const size = Math.min(el.w, el.h);
        node.style.width = px(size);
        node.style.height = px(size);
        node.style.color = color(el.color);
        node.innerHTML = `<img src="${QR_SVG.replace('currentColor', encodeURIComponent(color(el.color)))}" alt="" draggable="false" style="width:100%;height:100%;display:block">`;
    } else if (el.type === 'barcode') {
        node.innerHTML = `<img src="${barcodeSvg(color(el.color))}" alt="" draggable="false" style="width:100%;height:100%;display:block">`;
    } else if (el.type === 'rect') {
        const border = Number(el.borderWidth) || 0;
        Object.assign(node.style, {
            background: el.fill && el.fill !== 'transparent' ? color(el.fill, '#ffffff') : 'transparent',
            border: border ? `${px(border)} solid ${color(el.borderColor)}` : '0',
            borderRadius: el.ellipse ? '50%' : px(Number(el.radius) || 0),
            boxSizing: 'border-box',
        });
    } else if (el.type === 'line') {
        node.style.height = px(Number(el.thickness) || 0.3);
        node.style.background = color(el.color);
    }

    if (el.locked) node.classList.add('dz-locked');
    return node;
}

function positionNode(node, el) {
    Object.assign(node.style, {
        left: px(el.x),
        top: px(el.y),
        width: px(el.w),
        height: px(el.type === 'line' ? (Number(el.thickness) || 0.3) : el.h),
        transform: el.rotation ? `rotate(${el.rotation}deg)` : '',
        opacity: el.opacity ?? 1,
        zIndex: String(10 + (Number(el.z) || 0)),
    });
}

function renderCanvas() {
    const bg = side().background;
    els.stage.innerHTML = '';
    const card = document.createElement('div');
    card.id = 'dz-card';
    card.style.width = px(cfg.width);
    card.style.height = px(cfg.height);
    card.style.background = color(bg.color, '#ffffff');
    if (bg.image) {
        card.style.backgroundImage = `url("${cfg.assetBase}${bg.image}")`;
        card.style.backgroundSize = '100% 100%';
    }
    if (state.snap) card.classList.add('dz-grid');
    card.style.setProperty('--grid', px(5));

    [...elements()].sort((a, b) => (a.z || 0) - (b.z || 0)).forEach((el) => card.appendChild(elementNode(el)));
    els.stage.appendChild(card);
    els.card = card;
    renderSelection();
}

function renderSelection() {
    els.card.querySelector('.dz-sel')?.remove();
    const el = selected();
    if (!el) return;
    const sel = document.createElement('div');
    sel.className = 'dz-sel';
    positionNode(sel, el);
    sel.style.zIndex = '9999';
    if (el.type === 'qr') {
        const size = Math.min(el.w, el.h);
        sel.style.width = px(size);
        sel.style.height = px(size);
    }
    if (!el.locked) {
        for (const h of ['nw', 'n', 'ne', 'e', 'se', 's', 'sw', 'w']) {
            if (el.type === 'line' && !['e', 'w'].includes(h)) continue;
            const handle = document.createElement('span');
            handle.className = `dz-h dz-h-${h}`;
            handle.dataset.handle = h;
            sel.appendChild(handle);
        }
    }
    els.card.appendChild(sel);
}

function renderLayers() {
    const list = [...elements()].sort((a, b) => (b.z || 0) - (a.z || 0));
    els.layers.innerHTML = list.length ? list.map((el) => `
        <button type="button" data-layer="${esc(el.id)}" class="flex w-full items-center gap-2 rounded px-2 py-1 text-left text-xs ${el.id === state.selectedId ? 'bg-blue-100 text-blue-900' : 'hover:bg-slate-100'}">
            <span class="w-12 shrink-0 font-mono text-[10px] uppercase text-slate-400">${esc(el.type)}</span>
            <span class="truncate">${esc(layerLabel(el))}</span>
            ${el.locked ? '<span class="ml-auto text-[10px] text-slate-400">locked</span>' : ''}
        </button>`).join('') : '<p class="px-2 py-3 text-xs text-slate-400">No elements yet. Use “Add” above.</p>';
}

function layerLabel(el) {
    if (el.name) return el.name;
    if (el.type === 'text') return (el.content || '').replace(/\s+/g, ' ').slice(0, 40) || 'Text';
    if (el.type === 'image') return cfg.imageSources[el.source] || 'Image';
    if (el.type === 'rect') return el.ellipse ? 'Ellipse' : 'Rectangle';
    return { qr: 'QR code', barcode: 'Barcode', line: 'Line' }[el.type] || el.type;
}

function renderAll() {
    renderCanvas();
    renderLayers();
    renderProps();
    document.querySelectorAll('[data-side]').forEach((b) => b.classList.toggle('btn-primary', b.dataset.side === state.side));
    document.querySelectorAll('[data-side]').forEach((b) => b.classList.toggle('btn-secondary', b.dataset.side !== state.side));
    $('#dz-zoom-label').textContent = `${Math.round(state.scale / 3.7795 * 100)}%`;
}

/* ------------------------------------------------------------------ properties panel */

function colorField(label, prop, value, { allowTransparent = false } = {}) {
    const isToken = typeof value === 'string' && value.startsWith('@');
    const mode = value === 'transparent' ? 'transparent' : (isToken ? value : 'custom');
    const hex = !isToken && value !== 'transparent' && value ? value : '#000000';
    return `<div>
        <label class="form-label">${label}</label>
        <div class="flex gap-1">
            <select class="form-input form-input-sm" data-color-mode="${prop}">
                <option value="custom" ${mode === 'custom' ? 'selected' : ''}>Custom</option>
                <option value="@primary" ${mode === '@primary' ? 'selected' : ''}>School primary</option>
                <option value="@secondary" ${mode === '@secondary' ? 'selected' : ''}>School secondary</option>
                ${allowTransparent ? `<option value="transparent" ${mode === 'transparent' ? 'selected' : ''}>None</option>` : ''}
            </select>
            <input type="color" value="${esc(hex.length === 4 ? '#' + [...hex.slice(1)].map((c) => c + c).join('') : hex)}" data-color-value="${prop}" class="h-7 w-10 shrink-0 cursor-pointer rounded border border-slate-300" ${mode !== 'custom' ? 'disabled' : ''}>
        </div>
    </div>`;
}

function num(label, prop, value, step = 0.1, extra = '') {
    return `<label class="block"><span class="form-label">${label}</span><input type="number" step="${step}" class="form-input form-input-sm" data-prop="${prop}" value="${esc(round(Number(value) || 0, 0.01))}" ${extra}></label>`;
}

function check(label, prop, value) {
    return `<label class="flex items-center gap-1.5 text-xs text-slate-700"><input type="checkbox" class="form-check" data-prop="${prop}" ${value ? 'checked' : ''}> ${label}</label>`;
}

function select(label, prop, options, value) {
    return `<label class="block"><span class="form-label">${label}</span><select class="form-input form-input-sm" data-prop="${prop}">${
        Object.entries(options).map(([k, v]) => `<option value="${esc(k)}" ${String(value) === k ? 'selected' : ''}>${esc(v)}</option>`).join('')}</select></label>`;
}

function placeholderSelect(target) {
    let html = `<select class="form-input form-input-sm" data-insert-placeholder="${target}"><option value="">Insert data field…</option>`;
    for (const [group, items] of Object.entries(cfg.placeholders)) {
        html += `<optgroup label="${esc(group)}">${Object.entries(items).map(([k, v]) => `<option value="{${esc(k)}}">${esc(v)}</option>`).join('')}</optgroup>`;
    }
    return html + '</select>';
}

function renderProps() {
    const el = selected();
    if (!el) {
        const bg = side().background;
        els.props.innerHTML = `
            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">${state.side === 'back' ? 'Back' : 'Front'} background</h3>
            <div class="space-y-3">
                ${colorField('Background color', 'bg.color', bg.color || '#ffffff')}
                <div>
                    <span class="form-label">Background image</span>
                    ${bg.image ? `<img src="${esc(cfg.assetBase + bg.image)}" class="mb-1 max-h-24 rounded border border-slate-200" alt="">` : ''}
                    <div class="flex gap-1">
                        <label class="btn btn-secondary btn-sm cursor-pointer">Upload…<input type="file" accept="image/png,image/jpeg,image/webp" hidden data-upload="background"></label>
                        ${bg.image ? '<button type="button" class="btn btn-ghost btn-sm text-red-600" data-action="remove-bg">Remove</button>' : ''}
                    </div>
                    <p class="form-hint">Stretched to the full ${esc(cfg.width)} × ${esc(cfg.height)} mm. Use a high-resolution image (300 DPI).</p>
                </div>
                <p class="rounded bg-slate-50 p-2 text-xs text-slate-500">Select an element on the canvas to edit it. Drag to move, use the handles to resize, arrow keys to nudge (Shift = 5 mm).</p>
            </div>`;
        return;
    }

    let html = `<div class="mb-2 flex items-center justify-between">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">${esc(layerLabel(el))}</h3>
            <div class="flex gap-0.5">
                <button type="button" class="btn btn-ghost btn-sm" data-action="duplicate" title="Duplicate (Ctrl+D)">⧉</button>
                <button type="button" class="btn btn-ghost btn-sm" data-action="forward" title="Bring forward">▲</button>
                <button type="button" class="btn btn-ghost btn-sm" data-action="backward" title="Send backward">▼</button>
                <button type="button" class="btn btn-ghost btn-sm text-red-600" data-action="delete" title="Delete (Del)">✕</button>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-2">
            ${num('X (mm)', 'x', el.x)}${num('Y (mm)', 'y', el.y)}
            ${num('Width (mm)', 'w', el.w)}${el.type === 'line' ? num('Thickness (mm)', 'thickness', el.thickness ?? 0.3, 0.05) : num('Height (mm)', 'h', el.h)}
            ${num('Rotation (°)', 'rotation', el.rotation || 0, 1)}${num('Opacity (0–1)', 'opacity', el.opacity ?? 1, 0.05, 'min="0" max="1"')}
        </div>
        <div class="mt-2 flex flex-wrap gap-1">
            <span class="form-label w-full">Align on ${state.side}</span>
            ${[['left', '⇤'], ['hcenter', '↔'], ['right', '⇥'], ['top', '⤒'], ['vcenter', '↕'], ['bottom', '⤓']].map(([a, i]) => `<button type="button" class="btn btn-secondary btn-sm" data-align="${a}" title="Align ${a}">${i}</button>`).join('')}
        </div>
        <div class="mt-2 flex gap-3">${check('Locked', 'locked', el.locked)}</div>
        <hr class="my-3 border-slate-200">`;

    if (el.type === 'text') {
        html += `<label class="block"><span class="form-label">Text</span><textarea rows="3" class="form-input form-input-sm font-mono" data-prop="content" id="dz-content">${esc(el.content)}</textarea></label>
            <div class="mt-1">${placeholderSelect('dz-content')}</div>
            <p class="form-hint">Fields like {full_name} are replaced with each person's data when printing.</p>
            <div class="mt-3 grid grid-cols-2 gap-2">
                ${select('Font', 'fontFamily', Object.fromEntries(Object.entries(cfg.fonts).map(([k, f]) => [k, f.label])), el.fontFamily || 'helvetica')}
                ${num('Size (pt)', 'fontSize', el.fontSize || 10, 0.5)}
                ${select('Align', 'align', { left: 'Left', center: 'Center', right: 'Right', justify: 'Justify' }, el.align || 'left')}
                ${num('Line height', 'lineHeight', el.lineHeight ?? 1.2, 0.05)}
                ${num('Letter spacing (pt)', 'letterSpacing', el.letterSpacing || 0, 0.1)}
                ${colorField('Color', 'color', el.color || '#000000')}
            </div>
            <div class="mt-2 flex flex-wrap gap-3">
                ${check('Bold', 'fontWeight', el.fontWeight === 'bold')}${check('Italic', 'italic', el.italic)}${check('UPPERCASE', 'uppercase', el.uppercase)}
            </div>
            <div class="mt-2 rounded bg-slate-50 p-2">
                ${check('Shrink to fit width (one line)', 'shrink', el.shrink)}
                ${el.shrink ? `<div class="mt-1">${num('Minimum size (pt)', 'minFontSize', el.minFontSize || (el.fontSize || 10) / 2, 0.5)}</div>` : ''}
                <p class="form-hint">Long names get smaller automatically instead of wrapping.</p>
            </div>`;
    } else if (el.type === 'image') {
        html += `<div class="grid grid-cols-2 gap-2">
                ${select('Source', 'source', cfg.imageSources, el.source || 'custom')}
                ${select('Fit', 'fit', { contain: 'Contain', cover: 'Cover (crop)', stretch: 'Stretch' }, el.fit || 'contain')}
                ${num('Corner radius (mm)', 'radius', el.radius || 0, 0.5)}
                ${num('Border (mm)', 'borderWidth', el.borderWidth || 0, 0.1)}
                ${colorField('Border color', 'borderColor', el.borderColor || '#000000')}
            </div>
            ${el.source === 'custom' ? `<div class="mt-2"><label class="btn btn-secondary btn-sm cursor-pointer">Upload image…<input type="file" accept="image/png,image/jpeg,image/webp" hidden data-upload="image"></label></div>` : '<p class="form-hint mt-2">Uses the school/person image automatically. Tip: radius = half the width makes a round photo.</p>'}`;
    } else if (el.type === 'qr' || el.type === 'barcode') {
        html += `<label class="block"><span class="form-label">${el.type === 'qr' ? 'QR content' : 'Barcode value'}</span><input class="form-input form-input-sm font-mono" data-prop="content" id="dz-content" value="${esc(el.content)}"></label>
            <div class="mt-1">${placeholderSelect('dz-content')}</div>
            <p class="form-hint">${el.type === 'qr' ? '{verification_url} links to the public verification page.' : 'Code 128. Usually {card_number} or {admission_number}.'}</p>
            <div class="mt-2">${colorField('Color', 'color', el.color || '#000000')}</div>`;
    } else if (el.type === 'rect') {
        html += `<div class="grid grid-cols-2 gap-2">
                ${colorField('Fill', 'fill', el.fill || 'transparent', { allowTransparent: true })}
                ${num('Border (mm)', 'borderWidth', el.borderWidth || 0, 0.1)}
                ${colorField('Border color', 'borderColor', el.borderColor || '#000000')}
                ${num('Corner radius (mm)', 'radius', el.radius || 0, 0.5)}
            </div>
            <div class="mt-2">${check('Ellipse / circle', 'ellipse', el.ellipse)}</div>`;
    } else if (el.type === 'line') {
        html += colorField('Color', 'color', el.color || '#000000') + '<p class="form-hint">Rotate 90° for a vertical line.</p>';
    }

    els.props.innerHTML = html;
}

/* ------------------------------------------------------------------ mutations */

function update(el, prop, value) {
    if (prop.startsWith('bg.')) {
        side().background[prop.slice(3)] = value;
    } else {
        el[prop] = value;
    }
}

function addElement(type, extra = {}) {
    const z = Math.max(0, ...elements().map((e) => Number(e.z) || 0)) + 1;
    const w = Math.min(cfg.width * 0.4, type === 'qr' ? 15 : 30);
    const defaults = {
        text: { w: Math.min(cfg.width * 0.5, 40), h: 6, content: 'New text', fontFamily: 'helvetica', fontSize: cfg.kind === 'certificate' ? 14 : 8, color: '#111111', align: 'left', lineHeight: 1.2 },
        image: { w: 20, h: 20, source: 'school_logo', fit: 'contain' },
        qr: { w: 15, h: 15, content: '{verification_url}', color: '#000000' },
        barcode: { w: 40, h: 8, content: cfg.kind === 'certificate' ? '{certificate_number}' : '{card_number}', color: '#000000' },
        rect: { w: 30, h: 15, fill: '@primary', borderWidth: 0, borderColor: '#000000', radius: 0 },
        line: { w: 30, h: 0.3, thickness: 0.3, color: '#000000' },
    }[type];
    const el = {
        id: `${ID_PREFIX}${Date.now().toString(36)}${Math.random().toString(36).slice(2, 5)}`,
        type, x: round((cfg.width - (defaults.w ?? w)) / 2, 0.5), y: round((cfg.height - (defaults.h ?? 5)) / 2, 0.5), rotation: 0, z,
        ...defaults, ...extra,
    };
    snapshot();
    elements().push(el);
    state.selectedId = el.id;
    renderAll();
}

function removeSelected() {
    if (!selected()) return;
    snapshot();
    side().elements = elements().filter((e) => e.id !== state.selectedId);
    state.selectedId = null;
    renderAll();
}

function duplicateSelected() {
    const el = selected();
    if (!el) return;
    snapshot();
    const copy = { ...JSON.parse(JSON.stringify(el)), id: `${ID_PREFIX}${Date.now().toString(36)}`, x: el.x + 2, y: el.y + 2, z: Math.max(...elements().map((e) => Number(e.z) || 0)) + 1, locked: false };
    elements().push(copy);
    state.selectedId = copy.id;
    renderAll();
}

function restack(direction) {
    const el = selected();
    if (!el) return;
    snapshot();
    const sorted = [...elements()].sort((a, b) => (a.z || 0) - (b.z || 0));
    sorted.forEach((e, i) => { e.z = i + 1; });
    const i = sorted.indexOf(el);
    const j = direction === 'forward' ? i + 1 : i - 1;
    if (sorted[j]) {
        [sorted[i].z, sorted[j].z] = [sorted[j].z, sorted[i].z];
    }
    renderAll();
}

function align(where) {
    const el = selected();
    if (!el) return;
    snapshot();
    const h = el.type === 'line' ? (el.thickness || 0.3) : el.h;
    if (where === 'left') el.x = 0;
    if (where === 'right') el.x = round(cfg.width - el.w);
    if (where === 'hcenter') el.x = round((cfg.width - el.w) / 2);
    if (where === 'top') el.y = 0;
    if (where === 'bottom') el.y = round(cfg.height - h);
    if (where === 'vcenter') el.y = round((cfg.height - h) / 2);
    renderAll();
}

/* ------------------------------------------------------------------ pointer interaction */

let drag = null;

els.stage.addEventListener('pointerdown', (e) => {
    const handle = e.target.closest('[data-handle]');
    const node = e.target.closest('.dz-el');
    if (handle) {
        const el = selected();
        drag = { mode: 'resize', handle: handle.dataset.handle, el, start: { x: e.clientX, y: e.clientY }, orig: { ...el }, moved: false };
    } else if (node) {
        state.selectedId = node.dataset.id;
        const el = selected();
        renderSelection();
        renderLayers();
        renderProps();
        if (!el.locked) {
            drag = { mode: 'move', el, start: { x: e.clientX, y: e.clientY }, orig: { ...el }, moved: false };
        }
    } else if (e.target.closest('#dz-card') || e.target === els.stage) {
        state.selectedId = null;
        renderAll();
        return;
    } else {
        return;
    }
    e.preventDefault();
    els.stage.setPointerCapture(e.pointerId);
});

els.stage.addEventListener('pointermove', (e) => {
    if (!drag) return;
    const dx = (e.clientX - drag.start.x) / state.scale;
    const dy = (e.clientY - drag.start.y) / state.scale;
    if (!drag.moved && Math.abs(dx) + Math.abs(dy) < 0.2) return;
    if (!drag.moved) {
        snapshot();
        drag.moved = true;
    }
    const step = state.snap && !e.altKey ? 0.5 : 0.01;
    const { el, orig } = drag;

    if (drag.mode === 'move') {
        el.x = round(orig.x + dx, step);
        el.y = round(orig.y + dy, step);
    } else {
        const h = drag.handle;
        const min = 0.5;
        if (h.includes('e')) el.w = Math.max(min, round(orig.w + dx, step));
        if (h.includes('s')) el.h = Math.max(min, round(orig.h + dy, step));
        if (h.includes('w')) {
            const w = Math.max(min, round(orig.w - dx, step));
            el.x = round(orig.x + (orig.w - w), step);
            el.w = w;
        }
        if (h.includes('n')) {
            const hh = Math.max(min, round(orig.h - dy, step));
            el.y = round(orig.y + (orig.h - hh), step);
            el.h = hh;
        }
        if (e.shiftKey && ['image', 'qr'].includes(el.type) && h.length === 2) {
            el.h = round(el.w * (orig.h / orig.w), step);
        }
    }

    const node = els.card.querySelector(`.dz-el[data-id="${CSS.escape(el.id)}"]`);
    if (drag.mode === 'resize' || el.type === 'text') {
        node.replaceWith(elementNode(el));
    } else if (node) {
        positionNode(node, el);
    }
    renderSelection();
});

els.stage.addEventListener('pointerup', () => {
    if (drag?.moved) {
        renderProps();
    }
    drag = null;
});

/* ------------------------------------------------------------------ panel events */

document.addEventListener('input', (e) => {
    const t = e.target;
    const el = selected();
    if (t.dataset.prop && el) {
        let value = t.type === 'checkbox' ? t.checked : t.value;
        if (t.type === 'number') value = Number(t.value);
        if (t.dataset.prop === 'fontWeight') value = t.checked ? 'bold' : 'normal';
        if (!t.dataset.editing) {
            snapshot();
            t.dataset.editing = '1';
        }
        update(el, t.dataset.prop, value);
        renderCanvas();
        renderLayers();
        if (['source', 'shrink'].includes(t.dataset.prop)) renderProps();
    } else if (t.dataset.colorValue) {
        snapshot();
        update(el, t.dataset.colorValue, t.value);
        renderCanvas();
    }
});

document.addEventListener('change', (e) => {
    const t = e.target;
    delete t.dataset.editing;

    if (t.dataset.colorMode) {
        const prop = t.dataset.colorMode;
        const valueInput = t.parentElement.querySelector('[data-color-value]');
        valueInput.disabled = t.value !== 'custom';
        snapshot();
        update(selected(), prop, t.value === 'custom' ? valueInput.value : t.value);
        renderCanvas();
    }

    if (t.dataset.insertPlaceholder && t.value) {
        const target = document.getElementById(t.dataset.insertPlaceholder);
        const pos = target.selectionStart ?? target.value.length;
        target.value = target.value.slice(0, pos) + t.value + target.value.slice(target.selectionEnd ?? pos);
        target.dispatchEvent(new Event('input', { bubbles: true }));
        target.focus();
        t.value = '';
    }

    if (t.dataset.upload && t.files?.[0]) {
        upload(t.files[0], t.dataset.upload);
    }
});

document.addEventListener('click', (e) => {
    const t = e.target.closest('button, [data-layer]');
    if (!t) return;
    if (t.dataset.add) {
        const extra = t.dataset.extra ? JSON.parse(t.dataset.extra) : {};
        addElement(t.dataset.add, extra);
    }
    if (t.dataset.layer) {
        state.selectedId = t.dataset.layer;
        renderAll();
    }
    if (t.dataset.side) {
        state.side = t.dataset.side;
        state.selectedId = null;
        renderAll();
    }
    if (t.dataset.align) align(t.dataset.align);
    const action = t.dataset.action;
    if (action === 'delete') removeSelected();
    if (action === 'duplicate') duplicateSelected();
    if (action === 'forward' || action === 'backward') restack(action);
    if (action === 'remove-bg') {
        snapshot();
        side().background.image = null;
        renderAll();
    }
    if (action === 'undo') undo();
    if (action === 'redo') redo();
    if (action === 'zoom-in') zoom(state.scale * 1.2);
    if (action === 'zoom-out') zoom(state.scale / 1.2);
    if (action === 'zoom-fit') fit();
    if (action === 'save') save();
    if (action === 'preview') save().then((ok) => ok && window.open(cfg.previewUrl, '_blank'));
    if (action === 'toggle-snap') {
        state.snap = !state.snap;
        t.classList.toggle('btn-primary', state.snap);
        t.classList.toggle('btn-secondary', !state.snap);
        renderCanvas();
    }
});

const fieldPicker = $('#dz-add-field');
fieldPicker?.addEventListener('change', () => {
    if (!fieldPicker.value) return;
    addElement('text', { content: fieldPicker.value, w: Math.min(cfg.width * 0.5, 40), h: cfg.kind === 'certificate' ? 10 : 5 });
    fieldPicker.value = '';
});

const imagePicker = $('#dz-add-image');
imagePicker?.addEventListener('change', () => {
    if (!imagePicker.value) return;
    const source = imagePicker.value;
    const size = source === 'photo' ? { w: 20, h: 25, fit: 'cover' } : (source === 'principal_signature' ? { w: 25, h: 10 } : { w: 18, h: 18 });
    addElement('image', { source, ...size });
    imagePicker.value = '';
});

document.addEventListener('keydown', (e) => {
    const typing = ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName);
    const mod = e.ctrlKey || e.metaKey;
    if (mod && e.key.toLowerCase() === 's') {
        e.preventDefault();
        save();
        return;
    }
    if (typing) return;
    if (mod && e.key.toLowerCase() === 'z') {
        e.preventDefault();
        e.shiftKey ? redo() : undo();
    } else if (mod && e.key.toLowerCase() === 'y') {
        e.preventDefault();
        redo();
    } else if (mod && e.key.toLowerCase() === 'd') {
        e.preventDefault();
        duplicateSelected();
    } else if (['Delete', 'Backspace'].includes(e.key)) {
        e.preventDefault();
        removeSelected();
    } else if (e.key.startsWith('Arrow') && selected() && !selected().locked) {
        e.preventDefault();
        const el = selected();
        const step = e.shiftKey ? 5 : 0.5;
        if (!state.nudging) snapshot();
        state.nudging = true;
        if (e.key === 'ArrowLeft') el.x = round(el.x - step);
        if (e.key === 'ArrowRight') el.x = round(el.x + step);
        if (e.key === 'ArrowUp') el.y = round(el.y - step);
        if (e.key === 'ArrowDown') el.y = round(el.y + step);
        renderCanvas();
    } else if (e.key === 'Escape') {
        state.selectedId = null;
        renderAll();
    }
});
document.addEventListener('keyup', (e) => {
    if (e.key.startsWith('Arrow') && state.nudging) {
        state.nudging = false;
        renderProps();
    }
});

/* ------------------------------------------------------------------ zoom, save, upload */

function zoom(scale) {
    state.scale = clamp(scale, 1, 30);
    renderAll();
}

function fit() {
    const box = els.stage.getBoundingClientRect();
    zoom(Math.min((box.width - 48) / cfg.width, (window.innerHeight - box.top - 48) / cfg.height));
}

async function request(url, options) {
    const res = await fetch(url, {
        ...options,
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, Accept: 'application/json', ...(options.headers || {}) },
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        throw new Error(data.message || Object.values(data.errors || {})[0]?.[0] || `Request failed (${res.status})`);
    }
    return data;
}

async function save() {
    els.status.textContent = 'Saving…';
    els.status.className = 'text-xs text-slate-500';
    try {
        const data = await request(cfg.saveUrl, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ design: state.design }),
        });
        state.dirty = false;
        els.status.textContent = `Saved at ${data.savedAt}`;
        els.status.className = 'text-xs text-emerald-600';
        return true;
    } catch (err) {
        els.status.textContent = `Not saved: ${err.message}`;
        els.status.className = 'text-xs font-medium text-red-600';
        return false;
    }
}

async function upload(file, kind) {
    const form = new FormData();
    form.append('image', file);
    form.append('kind', kind === 'background' ? 'background' : 'image');
    els.status.textContent = 'Uploading…';
    try {
        const data = await request(cfg.uploadUrl, { method: 'POST', body: form });
        snapshot();
        if (kind === 'background') {
            side().background.image = data.path;
        } else if (selected()) {
            selected().src = data.path;
        }
        els.status.textContent = 'Image uploaded – remember to save';
        renderAll();
    } catch (err) {
        els.status.textContent = `Upload failed: ${err.message}`;
        els.status.className = 'text-xs font-medium text-red-600';
    }
}

window.addEventListener('resize', () => fit());
renderAll();
fit();
els.status.textContent = 'All changes saved';
els.status.className = 'text-xs text-emerald-600';
