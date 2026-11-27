{{-- Pizi's own line drawings of a landmark per city: grey lines with a yellow accent. Use: @include('partials.city-icon', ['slug' => $city->slug]) --}}
<svg viewBox="0 0 64 64" fill="none" stroke="#5b6270" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="{{ $class ?? 'w-16 h-16' }}" aria-hidden="true">
@switch($slug)
    @case('delhi') {{-- India Gate --}}
        <path fill="#fff" d="M17 54V20h30v34H39V37a7 7 0 0 0-14 0v17z"/><path d="M15 20h34M20 20v-5h24v5M26 15v-4h12v4M21 27h6M37 27h6"/><path stroke="#f5b50a" stroke-width="2.4" d="M20 32h4M40 32h4M27 11h10"/>
        @break
    @case('noida') {{-- office towers --}}
        <path fill="#fff" d="M12 54V28h12v26M26 54V12h14v42M42 54V22h10v32"/><path d="M30 18h6M30 24h6M30 30h6M30 36h6M15 34h6M15 40h6M15 46h6M45 28h4M45 34h4M45 40h4"/><path stroke="#f5b50a" stroke-width="2.4" d="M30 12V8h6v4M30 44h6"/>
        @break
    @case('gurgaon') {{-- Cyber City glass towers --}}
        <path fill="#fff" d="M14 54V22l10-6v38M28 54V14l9 5v35M41 54V30h10v24"/><path d="M17 26l4-2M17 33l4-2M17 40l4-2M31 22v26M41 36h10M41 42h10M41 48h10"/><path stroke="#f5b50a" stroke-width="2.4" d="M34 25v8M46 30v-6"/>
        @break
    @case('ghaziabad') {{-- clock tower --}}
        <path fill="#fff" d="M25 54V20h14v34M16 54V40h9M39 40h9v14"/><path d="M23 20h18l-9-10zM29 54v-9a3 3 0 0 1 6 0v9"/><circle cx="32" cy="29" r="4.5" stroke="#f5b50a" stroke-width="2"/><path d="M32 27v2h2M32 10V7"/>
        @break
    @case('faridabad') {{-- Surajkund gateway --}}
        <path fill="#fff" d="M14 54V30h36v24H40V44a8 8 0 0 0-16 0v10z"/><path d="M22 30a10 10 0 0 1 20 0M32 20v-6M18 30v-6h4v6M42 30v-6h4v6"/><path stroke="#f5b50a" stroke-width="2.4" d="M14 36h36M32 14h4"/>
        @break
    @case('mumbai') {{-- Gateway of India --}}
        <path fill="#fff" d="M12 54V24h40v30H40V39a8 8 0 0 0-16 0v15z"/><path d="M12 24v-8h8v8M44 24v-8h8v8M24 24v-5h16v5"/><path stroke="#f5b50a" stroke-width="2.2" d="M13 16a3 3.5 0 0 1 6 0M45 16a3 3.5 0 0 1 6 0M27 19a5 5 0 0 1 10 0"/><path d="M16 34v8M48 34v8"/>
        @break
    @case('bangalore') {{-- Vidhana Soudha --}}
        <path fill="#fff" d="M9 54V34h46v20M20 34v-8h24v8"/><path d="M15 54V40M23 54V40M32 54V40M41 54V40M49 54V40M9 40h46"/><path fill="#fff" stroke="#f5b50a" stroke-width="2.2" d="M24 26a8 9 0 0 1 16 0z"/><path d="M32 17v-6"/>
        @break
    @case('hyderabad') {{-- Charminar --}}
        <path fill="#fff" d="M20 54V28h24v26H38V43a6 6 0 0 0-12 0v11z"/><path fill="#fff" d="M13 54V18h6v36M45 54V18h6v36"/><path d="M13 30h6M45 30h6M20 35h24M23 28v-5h18v5"/><path stroke="#f5b50a" stroke-width="2.2" d="M13 18a3 4 0 0 1 6 0M45 18a3 4 0 0 1 6 0"/><path d="M16 14v-4M48 14v-4"/>
        @break
    @case('chennai') {{-- Central station tower --}}
        <path fill="#fff" d="M10 54V36h16v18M38 54V36h16v18M26 54V20h12v34"/><path d="M14 54v-9a3 3 0 0 1 6 0v9M44 54v-9a3 3 0 0 1 6 0v9M10 36l8-6 8 6M38 36l8-6 8 6"/><path stroke="#f5b50a" stroke-width="2.2" d="M25 20l7-11 7 11"/><circle cx="32" cy="28" r="3.5"/><path d="M29 54V44h6v10"/>
        @break
    @case('pune') {{-- Shaniwar Wada gate --}}
        <path fill="#fff" d="M10 54V22h14v32M40 54V22h14v32M24 54V28h16v26"/><path d="M10 22v-4h3v4M17 22v-4h3v4M40 22v-4h3v4M47 22v-4h3v4M28 28v-4h8v4"/><path stroke="#f5b50a" stroke-width="2.2" d="M27 54V42a5 6 0 0 1 10 0v12"/><path d="M32 37v17M14 32h6M44 32h6"/>
        @break
    @case('kolkata') {{-- Howrah bridge --}}
        <path d="M6 44h52M14 44V18M50 44V18M14 18l18 14 18-14M14 18L6 40M50 18l8 22M22 25v19M32 32v12M42 25v19M22 25l10 19 10-19"/><path stroke="#f5b50a" stroke-width="2.4" d="M10 14h8M46 14h8"/><path d="M8 52q6-4 12 0t12 0 12 0 12 0"/>
        @break
    @case('ahmedabad') {{-- the stone lattice window --}}
        <path fill="#fff" d="M12 54V32a20 20 0 0 1 40 0v22z"/><path stroke="#f5b50a" stroke-width="2" d="M32 54V24M32 40c-8 0-12-5-12-11M32 36c7 0 11-5 11-11M32 47c-9 0-13-3-15-8M32 47c9 0 13-3 15-8"/><path d="M12 54h40M8 54h48"/>
        @break
    @case('jaipur') {{-- Hawa Mahal --}}
        <path fill="#fff" d="M10 54V40h44v14zM15 40V30h34v10zM21 30v-9h22v9zM27 21v-7h10v7z"/><path stroke="#f5b50a" stroke-width="2.2" d="M16 50v-3a2 2 0 0 1 4 0v3M30 50v-3a2 2 0 0 1 4 0v3M44 50v-3a2 2 0 0 1 4 0v3M23 37v-2a2 2 0 0 1 4 0v2M37 37v-2a2 2 0 0 1 4 0v2M30 27v-2a2 2 0 0 1 4 0v2"/><path d="M32 14V9"/>
        @break
    @case('lucknow') {{-- Rumi Darwaza --}}
        <path fill="#fff" d="M12 54V30c0-12 9-18 20-22 11 4 20 10 20 22v24H42V38c0-7-4-10-10-13-6 3-10 6-10 13v16z"/><path d="M12 34h10M42 34h10M7 54h50"/><path stroke="#f5b50a" stroke-width="2.4" d="M32 8V5M18 24c2-4 5-6 8-8M46 24c-2-4-5-6-8-8"/>
        @break
    @case('indore') {{-- Rajwada --}}
        <path fill="#fff" d="M12 54V42h40v12zM17 42V32h30v10zM22 32V22h20v10z"/><path d="M19 22l13-10 13 10M26 28h2M36 28h2M23 38h2M31 38h2M39 38h2"/><path stroke="#f5b50a" stroke-width="2.2" d="M28 54v-6a4 4 0 0 1 8 0v6M32 12V7l6 2.500-6 2.500"/>
        @break
    @case('chandigarh') {{-- the Open Hand --}}
        <path fill="#fff" d="M20 36c-6-4-9-10-8-17l7 6V13l6 9 3-13 5 13 6-9v13l8-4c0 7-4 12-11 15-6 3-11 2-16-1z"/><path d="M31 39v15M22 54h18"/><path stroke="#f5b50a" stroke-width="2.4" d="M23 31c6 3 13 2 17-2M14 54h34"/>
        @break
    @case('kochi') {{-- Chinese fishing net and palm --}}
        <path d="M7 48l19-28M26 20l15 6M26 20l-3 22M26 20l12 22M23 42q8 7 15 0M41 26l-3 16"/><path d="M51 54q3-12 0-24"/><path stroke="#f5b50a" stroke-width="2.4" d="M51 30q-7-6-11 1M51 30q-2-9-9-9M51 30q2-9 8-8M51 30q7-3 8 6"/><path d="M6 50q5-3 10 0t10 0 10 0"/>
        @break
    @case('coimbatore') {{-- temple gopuram --}}
        <path fill="#fff" d="M14 54V44h36v10zM18 44l3-8h22l3 8zM22 36l3-8h14l3 8zM26 28l2-8h8l2 8z"/><path stroke="#f5b50a" stroke-width="2.4" d="M28 20q4-8 8 0M32 14V9"/><path d="M28 54v-6h8v6M25 40h3M36 40h3"/>
        @break
    @case('goa') {{-- old church --}}
        <path fill="#fff" d="M16 54V28l16-10 16 10v26z"/><path d="M10 54V34h6M48 34h6v20M27 54V44a5 5 0 0 1 10 0v10"/><circle cx="32" cy="32" r="4" stroke="#f5b50a" stroke-width="2.2"/><path stroke="#f5b50a" stroke-width="2.4" d="M32 18V8M28 12h8"/>
        @break
    @case('bhubaneswar') {{-- Lingaraj temple tower --}}
        <path fill="#fff" d="M22 54c0-18 3-34 10-40 7 6 10 22 10 40z"/><path d="M23 44h18M24 36h16M26 28h12M28 21h8M8 54V44l7-6 7 6M42 44l7-6 7 6v10"/><path stroke="#f5b50a" stroke-width="2.4" d="M28 13h8M32 13V7"/>
        @break
    @default {{-- any other city --}}
        <path fill="#fff" d="M14 54V30h12v24M28 54V18h12v36M42 54V34h10v20"/><path d="M18 36h4M18 42h4M32 24h4M32 30h4M32 36h4M45 40h4"/><path stroke="#f5b50a" stroke-width="2.4" d="M34 18v-6"/>
@endswitch
    <path d="M5 54h54" stroke="#9aa0ab"/>
</svg>
