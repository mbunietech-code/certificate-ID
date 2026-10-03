<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ setting('system_name') }} · Smart ID Cards & Certificates</title>
    <x-favicon/>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .landing-grid {
            background-image:
                linear-gradient(rgba(255, 255, 255, .07) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, .07) 1px, transparent 1px);
            background-size: 44px 44px;
        }

        .sample-doc {
            position: relative;
            overflow: hidden;
            flex: none;
            border-radius: 18px;
            background: white;
            box-shadow: 0 28px 70px rgba(15, 23, 42, .22);
        }

        .sample-doc-inner {
            position: absolute;
            left: 0;
            top: 0;
            transform-origin: top left;
        }

        .flip-card {
            perspective: 1400px;
        }

        .flip-card-inner {
            position: relative;
            transform-style: preserve-3d;
            transition: transform .6s cubic-bezier(.2, .7, .2, 1);
        }

        .flip-card:hover .flip-card-inner {
            transform: rotateY(180deg);
        }

        .flip-card-side {
            backface-visibility: hidden;
        }

        .flip-card-back {
            inset: 0;
            position: absolute;
            transform: rotateY(180deg);
        }

        .lanyard::before {
            content: "";
            position: absolute;
            left: 50%;
            top: -88px;
            width: 12px;
            height: 110px;
            border-radius: 999px;
            background: linear-gradient(#22c55e, #0f766e);
            transform: translateX(-50%);
            box-shadow: inset 0 0 0 2px rgba(255, 255, 255, .2);
        }

        @media (prefers-reduced-motion: reduce) {
            .flip-card-inner {
                transition: none;
            }

            .flip-card:hover .flip-card-inner {
                transform: none;
            }
        }
    </style>
</head>
<body class="bg-slate-950 font-sans text-white antialiased">
    <header class="landing-grid relative isolate overflow-hidden">
        <div class="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_18%_12%,rgba(34,197,94,.26),transparent_28%),radial-gradient(circle_at_78%_18%,rgba(56,189,248,.22),transparent_30%),linear-gradient(135deg,#020617_0%,#111827_48%,#0f172a_100%)]"></div>
        <nav class="mx-auto flex max-w-7xl items-center justify-between px-5 py-5 sm:px-6 lg:px-8">
            <a href="{{ route('home') }}" class="flex items-center gap-3">
                <span class="grid size-10 place-items-center rounded-lg bg-emerald-400 text-slate-950">
                    <x-icon name="id-card" class="size-5"/>
                </span>
                <span class="leading-tight">
                    <span class="block text-sm font-semibold tracking-wide">{{ setting('system_name') }}</span>
                    <span class="block text-xs text-slate-400">School document studio</span>
                </span>
            </a>
            <div class="flex items-center gap-2">
                <a href="{{ route('verify.index') }}" class="hidden rounded-md px-3 py-2 text-sm font-medium text-slate-300 hover:bg-white/10 hover:text-white sm:inline-flex">Verify</a>
                <a href="{{ route('login') }}" class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-emerald-100">Login</a>
            </div>
        </nav>

        <section class="mx-auto grid max-w-7xl items-center gap-12 px-5 pb-20 pt-10 sm:px-6 lg:grid-cols-[1fr_1.05fr] lg:px-8 lg:pb-28 lg:pt-16">
            <div>
                <h1 class="max-w-3xl text-4xl font-semibold leading-tight tracking-normal text-white sm:text-5xl lg:text-6xl">
                    Print-ready ID cards and certificates that look current from day one.
                </h1>
                <p class="mt-5 max-w-2xl text-base leading-7 text-slate-300 sm:text-lg">
                    Design student IDs, staff cards, certificates, QR verification pages and print batches from one Laravel system. The public samples below use invented schools and illustrated avatars.
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="#samples" class="inline-flex items-center gap-2 rounded-md bg-emerald-400 px-5 py-3 text-sm font-bold text-slate-950 hover:bg-emerald-300">
                        View samples
                        <x-icon name="arrow-right" class="size-4"/>
                    </a>
                    <a href="{{ route('login') }}" class="inline-flex items-center gap-2 rounded-md border border-white/15 px-5 py-3 text-sm font-semibold text-white hover:bg-white/10">
                        Open dashboard
                    </a>
                </div>
                <dl class="mt-10 grid max-w-xl grid-cols-3 gap-3">
                    <div class="rounded-lg border border-white/10 bg-white/8 p-4">
                        <dt class="text-xs text-slate-400">Formats</dt>
                        <dd class="mt-1 text-2xl font-semibold">6+</dd>
                    </div>
                    <div class="rounded-lg border border-white/10 bg-white/8 p-4">
                        <dt class="text-xs text-slate-400">Verification</dt>
                        <dd class="mt-1 text-2xl font-semibold">QR</dd>
                    </div>
                    <div class="rounded-lg border border-white/10 bg-white/8 p-4">
                        <dt class="text-xs text-slate-400">Output</dt>
                        <dd class="mt-1 text-2xl font-semibold">PDF</dd>
                    </div>
                </dl>
            </div>

            <div class="relative min-h-[580px]">
                <div class="absolute left-4 top-16 rotate-[-8deg]">
                    <div class="lanyard relative rounded-[28px] border border-white/15 bg-white/10 p-3 shadow-2xl backdrop-blur">
                        <x-sample-doc :html="$samples['student_classic']['front']" :w="$samples['student_classic']['w']" :h="$samples['student_classic']['h']" :scale="1.18"/>
                    </div>
                </div>
                <div class="absolute right-2 top-5 rotate-[7deg]">
                    <div class="lanyard relative rounded-[28px] border border-white/15 bg-white/10 p-3 shadow-2xl backdrop-blur">
                        <x-sample-doc :html="$samples['staff_portrait']['front']" :w="$samples['staff_portrait']['w']" :h="$samples['staff_portrait']['h']" :scale=".92"/>
                    </div>
                </div>
                <div class="absolute bottom-2 left-1/2 w-[560px] max-w-[92vw] -translate-x-1/2 rounded-2xl border border-white/12 bg-white/10 p-4 shadow-2xl backdrop-blur">
                    <x-sample-doc :html="$samples['certificate_completion']['front']" :w="$samples['certificate_completion']['w']" :h="$samples['certificate_completion']['h']" :scale=".28" class="mx-auto rounded-xl"/>
                </div>
                <div class="absolute right-8 top-72 rounded-lg border border-white/12 bg-slate-950/80 px-4 py-3 shadow-xl backdrop-blur">
                    <p class="text-xs text-slate-400">Batch status</p>
                    <p class="mt-1 text-sm font-semibold text-emerald-200">128 documents ready</p>
                </div>
            </div>
        </section>
    </header>

    <main class="bg-slate-50 text-slate-900">
        <section id="samples" class="mx-auto max-w-7xl px-5 py-18 sm:px-6 lg:px-8 lg:py-24">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm font-bold uppercase tracking-wide text-emerald-700">Sample gallery</p>
                    <h2 class="mt-2 max-w-2xl text-3xl font-semibold tracking-normal text-slate-950 sm:text-4xl">Cards and certificates rendered by the actual print engine.</h2>
                </div>
                <p class="max-w-md text-sm leading-6 text-slate-600">Hover a two-sided card to flip it. All identities, school names, emblems and portraits are generic samples.</p>
            </div>

            <div class="mt-10 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($samples as $key => $sample)
                    <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="font-semibold text-slate-950">{{ $sample['label'] }}</h3>
                                <p class="mt-1 text-sm text-slate-500">{{ $sample['caption'] }}</p>
                            </div>
                            <span class="rounded bg-slate-100 px-2 py-1 text-xs font-semibold uppercase tracking-wide text-slate-600">{{ str_replace('_', ' ', $sample['kind']) }}</span>
                        </div>
                        <div class="mt-5 grid min-h-72 place-items-center rounded-lg bg-slate-100 p-5">
                            @php
                                $scale = $sample['kind'] === 'certificate' ? .23 : ($sample['h'] > $sample['w'] ? .78 : .94);
                            @endphp
                            @if ($sample['back'])
                                <div class="flip-card">
                                    <div class="flip-card-inner">
                                        <div class="flip-card-side">
                                            <x-sample-doc :html="$sample['front']" :w="$sample['w']" :h="$sample['h']" :scale="$scale"/>
                                        </div>
                                        <div class="flip-card-side flip-card-back">
                                            <x-sample-doc :html="$sample['back']" :w="$sample['w']" :h="$sample['h']" :scale="$scale"/>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <x-sample-doc :html="$sample['front']" :w="$sample['w']" :h="$sample['h']" :scale="$scale"/>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="border-y border-slate-200 bg-white">
            <div class="mx-auto grid max-w-7xl gap-4 px-5 py-14 sm:px-6 md:grid-cols-3 lg:px-8">
                <div class="rounded-lg bg-slate-950 p-6 text-white">
                    <x-icon name="shield" class="size-6 text-emerald-300"/>
                    <h3 class="mt-4 text-lg font-semibold">Public verification</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-300">QR codes resolve to dedicated verification pages for issued IDs and certificates.</p>
                </div>
                <div class="rounded-lg border border-slate-200 p-6">
                    <x-icon name="template" class="size-6 text-sky-600"/>
                    <h3 class="mt-4 text-lg font-semibold">Reusable templates</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Create layouts once, switch school colours and render consistent batches.</p>
                </div>
                <div class="rounded-lg border border-slate-200 p-6">
                    <x-icon name="printer" class="size-6 text-amber-600"/>
                    <h3 class="mt-4 text-lg font-semibold">Print center</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Prepare card sheets, certificates and history exports with audit visibility.</p>
                </div>
            </div>
        </section>

        <section class="mx-auto grid max-w-7xl gap-8 px-5 py-16 sm:px-6 lg:grid-cols-[.85fr_1.15fr] lg:px-8">
            <div>
                <p class="text-sm font-bold uppercase tracking-wide text-emerald-700">Workflow</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-normal text-slate-950">From student data to verified document in a few steps.</h2>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach (['Import students and staff', 'Choose a card or certificate template', 'Preview with sample data', 'Generate print-ready PDFs'] as $step)
                    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center gap-3">
                            <span class="grid size-8 place-items-center rounded bg-emerald-100 text-sm font-bold text-emerald-800">{{ $loop->iteration }}</span>
                            <h3 class="font-semibold">{{ $step }}</h3>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    </main>
</body>
</html>
