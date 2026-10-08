@props(['class' => 'h-10 w-11'])

{{--
    The folder itself: the paper folder the office pulls out of the drawer — a
    gold back with its tab standing up on the left, and a paler front laid over
    it that settles into a slightly deeper gold at its bottom edge.

    Drawn in fixed colours on purpose — this one object is the folder the module
    is built around, while everything around it keeps the ISU green. Nothing is
    written on it: the name beside it is the label, and what is inside is what
    opening it shows.

    Usage: <x-folder-icon class="h-11 w-11" />
--}}
<svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 48 44" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    {{-- The back and its tab. The tab stands above the front on the left and
         curves down into the back's top edge; the back then runs on behind the
         front, so a sliver of gold shows above the front on the right. --}}
    <path d="M6.5 5h13.4a4 4 0 0 1 4 4h17.1a2.5 2.5 0 0 1 2.5 2.5v24a2.5 2.5 0 0 1 -2.5 2.5H6.5A2.5 2.5 0 0 1 4 35.5v-28A2.5 2.5 0 0 1 6.5 5Z" fill="#E9A32B"/>

    {{-- The front laid over it --}}
    <path d="M7 12.5h33.5a3 3 0 0 1 3 3v19.5a3 3 0 0 1 -3 3H7a3 3 0 0 1 -3 -3V15.5a3 3 0 0 1 3 -3Z" fill="#FADD9E"/>

    {{-- The shade its bottom edge settles into --}}
    <path d="M4 33.5h39.5V35a3 3 0 0 1 -3 3H7a3 3 0 0 1 -3 -3v-1.5Z" fill="#DFA84F" opacity=".35"/>
</svg>
