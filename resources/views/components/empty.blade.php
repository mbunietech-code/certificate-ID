@props(['icon' => 'list', 'title' => 'Nothing here yet'])
<div class="flex flex-col items-center justify-center gap-2 px-4 py-12 text-center">
    <span class="grid size-11 place-items-center rounded-full bg-slate-100 text-slate-400"><x-icon :name="$icon" class="size-5"/></span>
    <div class="text-sm font-medium text-slate-700">{{ $title }}</div>
    @if ($slot->isNotEmpty())<div class="max-w-md text-sm text-slate-500">{{ $slot }}</div>@endif
</div>
