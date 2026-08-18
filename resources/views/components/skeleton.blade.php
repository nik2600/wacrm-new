@props([
    'kind' => 'row', // row | card | tableRow | text
    'rows' => 4,
])

{{--
 Reusable skeleton-shimmer placeholder. Uses the .skeleton class
 from resources/css/wadesk.css (background-shift animation).

 Variants:
 kind="row" — list row: circle avatar + 2 text lines (default)
 kind="card" — list card: title + 2 lines, padded box
 kind="tableRow" — single <tr> with 6 cells (for stat tables)
 kind="text" — single text line, no avatar

 Render N of them with the `rows` prop.

 Usage:
 <x-skeleton kind="row" :rows="3" />
 <x-skeleton kind="card" :rows="5" />
 <x-skeleton kind="tableRow" :rows="4" />
--}}

@if ($kind === 'row')
    @for ($i = 0; $i < $rows; $i++)
        <div class="flex items-center gap-3 px-3 py-2.5">
            <div class="skeleton w-9 h-9 rounded-full shrink-0"></div>
            <div class="flex-1 space-y-1.5 min-w-0">
                <div class="skeleton h-3 rounded" style="width: {{ rand(45, 80) }}%;"></div>
                <div class="skeleton h-2.5 rounded" style="width: {{ rand(30, 60) }}%;"></div>
            </div>
        </div>
    @endfor
@elseif ($kind === 'card')
    @for ($i = 0; $i < $rows; $i++)
        <div class="bg-paper-0 border border-paper-200 rounded-xl p-3 space-y-2">
            <div class="flex items-center gap-2">
                <div class="skeleton h-3 rounded w-1/3"></div>
                <div class="skeleton h-2.5 rounded w-12 ml-auto"></div>
            </div>
            <div class="skeleton h-2.5 rounded" style="width: {{ rand(60, 90) }}%;"></div>
            <div class="skeleton h-2.5 rounded" style="width: {{ rand(40, 70) }}%;"></div>
        </div>
    @endfor
@elseif ($kind === 'tableRow')
    @for ($i = 0; $i < $rows; $i++)
        <tr class="border-t border-paper-200">
            <td class="px-3 py-2.5">
                <div class="skeleton h-3 rounded w-3/4"></div>
            </td>
            <td class="px-3 py-2.5">
                <div class="skeleton h-3 rounded w-14"></div>
            </td>
            <td class="px-3 py-2.5 text-center">
                <div class="skeleton h-3 rounded w-6 mx-auto"></div>
            </td>
            <td class="px-3 py-2.5 text-center">
                <div class="skeleton h-3 rounded w-6 mx-auto"></div>
            </td>
            <td class="px-3 py-2.5 text-center">
                <div class="skeleton h-3 rounded w-6 mx-auto"></div>
            </td>
            <td class="px-3 py-2.5">
                <div class="skeleton h-3 rounded w-16"></div>
            </td>
        </tr>
    @endfor
@elseif ($kind === 'text')
    @for ($i = 0; $i < $rows; $i++)
        <div class="skeleton h-3 rounded mb-2" style="width: {{ rand(50, 85) }}%;"></div>
    @endfor
@endif
