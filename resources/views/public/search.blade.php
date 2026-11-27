@extends('layouts.app')
@section('title', 'Search Verified PGs in India | Pizi')
@section('meta_description', 'Search verified PGs by city, area, college, budget, room sharing and amenities. Boys, girls and unisex PGs in Delhi NCR and other Indian cities.')
@section('content')
@php
    $f = $filters;
    $total = $properties->total();
    $label = 'text-xs font-bold uppercase tracking-wide text-ink-950 block mb-2';
    $field = 'w-full px-3 py-2.5 rounded-xl border border-ink-900/15 bg-white text-sm outline-none focus:border-coral-500';
    $chip = 'block text-center px-2 py-2 text-xs font-semibold rounded-lg border border-ink-900/15 cursor-pointer peer-checked:bg-coral-500 peer-checked:text-white peer-checked:border-coral-500 hover:border-coral-500';
    $inUse = count($chips);
@endphp

{{-- ===== SEARCH HEADER ===== --}}
<section class="bg-ink-950 py-8 lg:py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="font-display font-black text-2xl lg:text-4xl text-cream mb-4">{{ $total }} {{ $total === 1 ? 'PG' : 'PGs' }} for you</h1>

        <form method="GET" action="{{ route('search') }}" class="bg-white rounded-2xl p-2 lg:p-3 flex flex-col sm:flex-row gap-2 max-w-4xl relative">
            {{-- a new text search keeps the other filters --}}
            @foreach(request()->except(['q', 'page']) as $key => $val)
                @foreach((array) $val as $v)<input type="hidden" name="{{ is_array($val) ? $key . '[]' : $key }}" value="{{ $v }}">@endforeach
            @endforeach
            <div class="flex-1 flex items-center gap-3 px-4 py-2 relative">
                <i class="fa-solid fa-magnifying-glass text-ink-900/40"></i>
                <input name="q" id="searchInput" autocomplete="off" value="{{ $f['q'] }}" placeholder="Area, college, PG name, pincode... e.g. girls PG Karol Bagh" class="flex-1 min-w-0 outline-none text-sm">
                <div id="suggestionsBox" class="hidden absolute left-0 right-0 top-full mt-2 bg-white rounded-xl border border-ink-900/15 shadow-2xl shadow-ink-900/15 z-50 max-h-80 overflow-y-auto"></div>
            </div>
            <button class="px-6 lg:px-8 py-3 bg-coral-500 hover:bg-coral-600 text-white rounded-xl font-bold text-sm">Search</button>
        </form>
    </div>
</section>

{{-- ===== RESULTS ===== --}}
<section class="py-6 lg:py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <button type="button" id="pzFilterOpen" class="lg:hidden w-full mb-4 px-4 py-3 rounded-xl border border-ink-900/15 bg-white font-bold text-sm flex items-center justify-center gap-2">
            <i class="fa-solid fa-sliders text-coral-500"></i> Filters @if($inUse)<span class="px-2 py-0.5 rounded-full bg-coral-500 text-white text-xs">{{ $inUse }}</span>@endif
        </button>

        <div class="grid grid-cols-1 lg:grid-cols-[300px_1fr] gap-8">

            {{-- FILTERS: a sidebar on laptops, a full screen panel on phones --}}
            <aside id="pzFilterPanel" class="hidden lg:block fixed lg:sticky inset-0 lg:inset-auto lg:top-24 z-[100000] lg:z-10 lg:self-start overflow-y-auto overscroll-containlg:max-h-[calc(100vh-7rem)] bg-white lg:rounded-2xl lg:border lg:border-ink-900/10">
                <form method="GET" action="{{ route('search') }}" id="pzFilterForm">
                    <input type="hidden" name="q" value="{{ $f['q'] }}">
                    @if($sort !== 'recommended')<input type="hidden" name="sort" value="{{ $sort }}">@endif
                    @if(request('lat') && request('lng'))<input type="hidden" name="lat" value="{{ request('lat') }}"><input type="hidden" name="lng" value="{{ request('lng') }}">@endif

                    <div class="sticky top-0 z-10 bg-white flex items-center justify-between px-5 py-4 border-b border-ink-900/10">
                        <h3 class="font-display font-bold text-lg">Filters</h3>
                        <div class="flex items-center gap-4">
                            @if($inUse)<a href="{{ route('search') }}" class="text-xs text-coral-600 font-bold">Clear all</a>@endif
                            <button type="button" id="pzFilterClose" class="lg:hidden text-ink-900 text-lg" aria-label="Close filters"><i class="fa-solid fa-xmark"></i></button>
                        </div>
                    </div>

                    <div class="p-5 space-y-5">
                        <div>
                            <label class="{{ $label }}" for="f-city">City</label>
                            <select name="city" id="f-city" class="{{ $field }}">
                                <option value="">All cities</option>
                                @foreach($options['cities'] as $c)
                                    <option value="{{ $c->slug }}" @selected($f['city'] === $c->slug)>{{ $c->name }}{{ $c->n ? " ({$c->n})" : ' (no PGs yet)' }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <span class="{{ $label }}">Area / locality</span>
                            @if($options['localities']->count())
                                @if($options['localities']->count() > 7)
                                    <input type="search" data-narrow="pzAreas" placeholder="Find an area..." class="{{ $field }} mb-2">
                                @endif
                                <div id="pzAreas" class="space-y-1.5 max-h-44 overflow-y-auto pr-1">
                                    @foreach($options['localities'] as $l)
                                        <label class="flex items-center gap-2 cursor-pointer text-sm">
                                            <input type="checkbox" name="locality[]" value="{{ $l->slug }}" @checked(in_array($l->slug, $f['locality'], true)) class="rounded">
                                            <span class="flex-1 capitalize">{{ $l->name }}</span><span class="text-xs text-ink-900/40">{{ $l->n }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-xs text-ink-900/50">{{ $f['city'] ? 'No PGs in this city yet.' : 'Choose a city to pick its areas.' }}</p>
                            @endif
                        </div>

                        @if($options['universities']->count())
                            <div>
                                <label class="{{ $label }}" for="f-uni">Near college / university</label>
                                <select name="nearby_university_id" id="f-uni" class="{{ $field }}">
                                    <option value="">Any</option>
                                    @foreach($options['universities'] as $u)
                                        <option value="{{ $u->id }}" @selected($f['nearby_university_id'] === (int) $u->id)>{{ $u->name }} ({{ $u->n }})</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <div>
                            <span class="{{ $label }}">Looking for</span>
                            <div class="grid grid-cols-4 gap-1.5">
                                @foreach(['' => 'Any'] + \App\Services\PgFilter::GENDERS as $val => $text)
                                    <label><input type="radio" name="gender" value="{{ $val }}" @checked((string) $f['gender'] === (string) $val) class="peer sr-only"><span class="{{ $chip }}">{{ $text }}</span></label>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <span class="{{ $label }}">Room sharing</span>
                            <div class="grid grid-cols-4 gap-1.5">
                                @foreach(['' => 'Any'] + \App\Services\PgFilter::SHARING as $val => $text)
                                    <label><input type="radio" name="sharing" value="{{ $val }}" @checked((string) $f['sharing'] === (string) $val) class="peer sr-only"><span class="{{ $chip }}">{{ $text }}</span></label>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <span class="{{ $label }}">Monthly rent (₹){{ $f['sharing'] ? ' for ' . strtolower(\App\Services\PgFilter::SHARING[$f['sharing']]) . ' room' : '' }}</span>
                            <div class="flex items-center gap-2">
                                <input type="number" name="budget_min" id="f-min" min="0" step="500" inputmode="numeric" value="{{ $f['budget_min'] }}" placeholder="Min" class="{{ $field }}" aria-label="Minimum rent">
                                <span class="text-ink-900/40">–</span>
                                <input type="number" name="budget_max" id="f-max" min="0" step="500" inputmode="numeric" value="{{ $f['budget_max'] }}" placeholder="Max" class="{{ $field }}" aria-label="Maximum rent">
                            </div>
                            <div class="flex flex-wrap gap-1.5 mt-2">
                                @foreach([6000, 8000, 10000, 15000, 25000] as $v)
                                    <button type="button" data-max="{{ $v }}" class="px-2.5 py-1 rounded-full border text-xs font-semibold {{ $f['budget_max'] === $v ? 'bg-coral-500 text-white border-coral-500' : 'border-ink-900/15 hover:border-coral-500' }}">≤ ₹{{ number_format($v / 1000) }}k</button>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <label class="{{ $label }}" for="f-dep">Security deposit</label>
                            <select name="deposit_max" id="f-dep" class="{{ $field }}">
                                <option value="">Any</option>
                                @foreach([5000, 10000, 15000, 20000, 30000] as $v)
                                    <option value="{{ $v }}" @selected($f['deposit_max'] === $v)>Up to ₹{{ number_format($v) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="space-y-2">
                            @foreach(['food' => ['fa-utensils', 'Food included'], 'verified' => ['fa-circle-check', 'Verified PGs only'], 'photos' => ['fa-image', 'With photos'], 'available' => ['fa-door-open', 'Rooms available now']] as $key => [$icon, $text])
                                <label class="flex items-center gap-2 cursor-pointer text-sm font-semibold">
                                    <input type="checkbox" name="{{ $key }}" value="1" @checked($f[$key]) class="rounded w-4 h-4">
                                    <i class="fa-solid {{ $icon }} fa-fw text-coral-500"></i> {{ $text }}
                                </label>
                            @endforeach
                        </div>

                        @if($options['amenities']->count())
                            <div>
                                <span class="{{ $label }}">Facilities (all ticked must be there)</span>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($options['amenities'] as $i => $a)
                                        <label class="{{ $i >= 10 && !in_array($a->id, $f['amenities'], true) ? 'hidden pz-more' : '' }}">
                                            <input type="checkbox" name="amenities[]" value="{{ $a->id }}" @checked(in_array($a->id, $f['amenities'], true)) class="peer sr-only">
                                            <span class="{{ $chip }}">{{ $a->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                @if($options['amenities']->count() > 10)
                                    <button type="button" id="pzMore" class="mt-2 text-xs font-bold text-coral-600">+ Show all facilities</button>
                                @endif
                            </div>
                        @endif

                        @if($options['types']->count() > 1 || $f['type'])
                            <div>
                                <label class="{{ $label }}" for="f-type">Property type</label>
                                <select name="type" id="f-type" class="{{ $field }}">
                                    <option value="">All types</option>
                                    @foreach(\App\Services\PgFilter::TYPES as $val => $text)
                                        @if(($options['types'][$val] ?? 0) || $f['type'] === $val)
                                            <option value="{{ $val }}" @selected($f['type'] === $val)>{{ $text }} ({{ $options['types'][$val] ?? 0 }})</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    </div>

                    <div class="sticky bottom-0 bg-white border-t border-ink-900/10 p-4">
                        <button id="pzApply" class="w-full py-3 bg-ink-950 text-cream rounded-xl font-bold text-sm hover:bg-ink-900 transition">Show {{ $total }} {{ $total === 1 ? 'PG' : 'PGs' }}</button>
                    </div>
                </form>
            </aside>

            {{-- RESULTS --}}
            <div class="min-w-0">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <div class="text-sm text-ink-900/70"><strong class="text-ink-950">{{ $total }}</strong> {{ $total === 1 ? 'PG' : 'PGs' }} found</div>
                    <form method="GET" action="{{ route('search') }}" id="pzSortForm" class="flex items-center gap-2">
                        @foreach(request()->except(['sort', 'page', 'lat', 'lng']) as $key => $val)
                            @foreach((array) $val as $v)<input type="hidden" name="{{ is_array($val) ? $key . '[]' : $key }}" value="{{ $v }}">@endforeach
                        @endforeach
                        <input type="hidden" name="lat" value="{{ request('lat') }}" @disabled(!request('lat'))>
                        <input type="hidden" name="lng" value="{{ request('lng') }}" @disabled(!request('lng'))>
                        <label class="text-xs font-bold text-ink-950" for="pzSort">Sort:</label>
                        <select name="sort" id="pzSort" class="px-3 py-2 rounded-lg border border-ink-900/15 bg-white text-xs font-semibold">
                            @foreach(['recommended' => 'Recommended', 'price_low' => 'Price: low to high', 'price_high' => 'Price: high to low', 'nearest' => 'Nearest to me', 'latest' => 'Newest first', 'popular' => 'Most viewed'] as $val => $text)
                                <option value="{{ $val }}" @selected($sort === $val)>{{ $text }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>

                @if($inUse)
                    <div class="flex flex-wrap gap-2 mb-5">
                        @foreach($chips as $c)
                            <a href="{{ route('search', $c['query']) }}" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-coral-50 border border-coral-200 text-xs font-bold text-ink-950 hover:bg-coral-100" title="Remove this filter">
                                {{ $c['label'] }} <i class="fa-solid fa-xmark text-coral-600"></i>
                            </a>
                        @endforeach
                        <a href="{{ route('search') }}" class="inline-flex items-center px-2 py-1.5 text-xs font-bold text-coral-600">Clear all</a>
                    </div>
                @endif

                @if($properties->count())
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                        @foreach($properties as $property)
                            @include('components.property-card', ['property' => $property])
                        @endforeach
                    </div>
                    <div class="mt-10">{{ $properties->links() }}</div>
                @else
                    <div class="bg-white p-8 sm:p-12 rounded-2xl border border-ink-900/10 text-center">
                        <div class="text-5xl text-coral-500 mb-4"><i class="fa-solid fa-magnifying-glass"></i></div>
                        <h3 class="font-display font-bold text-2xl text-ink-950">No PG matches all of these</h3>
                        @if($relaxations)
                            <p class="text-ink-900/60 mt-2">Remove one filter to see PGs:</p>
                            <div class="mt-4 flex flex-wrap justify-center gap-2">
                                @foreach($relaxations as $r)
                                    <a href="{{ route('search', $r['query']) }}" class="px-4 py-2 rounded-xl border border-ink-900/15 text-sm font-semibold hover:border-coral-500 hover:text-coral-600">
                                        Without <b>{{ $r['label'] }}</b> → {{ $r['count'] }} {{ $r['count'] === 1 ? 'PG' : 'PGs' }}
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <p class="text-ink-900/60 mt-2">Try another area or city, or fewer filters.</p>
                        @endif
                        <a href="{{ route('search') }}" class="inline-block mt-6 px-6 py-2.5 bg-coral-500 text-white rounded-xl font-bold text-sm">Clear all filters</a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>

<script>
(function () {
    const form = document.getElementById('pzFilterForm');
    const panel = document.getElementById('pzFilterPanel');
    const apply = document.getElementById('pzApply');
    const searchUrl = @json(route('search'));

    // phone: open and close the filter panel
    function show(open) {
        panel.classList.toggle('hidden', !open);
        document.body.style.overflow = open ? 'hidden' : '';
    }
    document.getElementById('pzFilterOpen').addEventListener('click', () => show(true));
    document.getElementById('pzFilterClose').addEventListener('click', () => show(false));

    // the form's filled values only, so the address stays short
    function params() {
        const p = new URLSearchParams();
        new FormData(form).forEach((v, k) => { if (String(v).trim() !== '') p.append(k, v); });
        return p;
    }
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        window.location.href = searchUrl + (params().toString() ? '?' + params().toString() : '');
    });

    // "Show N PGs" follows what is ticked
    let timer, asking;
    function count() {
        clearTimeout(timer);
        timer = setTimeout(() => {
            if (asking) asking.abort();
            asking = new AbortController();
            const p = params(); p.set('json', 'count');
            apply.textContent = 'Counting...';
            fetch(searchUrl + '?' + p.toString(), { signal: asking.signal, headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(d => { apply.textContent = d.count ? 'Show ' + d.count + (d.count === 1 ? ' PG' : ' PGs') : 'No PG with these filters'; })
                .catch(err => { if (err.name !== 'AbortError') apply.textContent = 'Show PGs'; });
        }, 350);
    }
    form.addEventListener('input', e => { if (!e.target.hasAttribute('data-narrow')) count(); });
    form.addEventListener('change', e => {
        // another city has other areas: load them
        if (e.target.name === 'city') {
            form.querySelectorAll('input[name="locality[]"]').forEach(i => { i.checked = false; });
            form.requestSubmit();
        }
    });
    form.querySelectorAll('[data-max]').forEach(b => b.addEventListener('click', () => {
        const input = document.getElementById('f-max');
        input.value = input.value === b.dataset.max ? '' : b.dataset.max;
        form.querySelectorAll('[data-max]').forEach(x => {
            const on = x.dataset.max === input.value;
            ['bg-coral-500', 'text-white', 'border-coral-500'].forEach(c => x.classList.toggle(c, on));
        });
        count();
    }));
    form.querySelectorAll('[data-narrow]').forEach(box => box.addEventListener('input', () => {
        const term = box.value.trim().toLowerCase();
        document.querySelectorAll('#' + box.dataset.narrow + ' label').forEach(l => {
            l.style.display = l.textContent.toLowerCase().includes(term) ? '' : 'none';
        });
    }));
    const more = document.getElementById('pzMore');
    if (more) more.addEventListener('click', () => { form.querySelectorAll('.pz-more').forEach(l => l.classList.remove('hidden')); more.remove(); });

    // sorting; "nearest" first asks for the location
    const sortForm = document.getElementById('pzSortForm');
    document.getElementById('pzSort').addEventListener('change', function () {
        if (this.value !== 'nearest') { sortForm.submit(); return; }
        if (!navigator.geolocation) { alert('Your browser cannot share its location.'); return; }
        navigator.geolocation.getCurrentPosition(pos => {
            const lat = sortForm.elements.lat, lng = sortForm.elements.lng;
            lat.disabled = lng.disabled = false;
            lat.value = pos.coords.latitude; lng.value = pos.coords.longitude;
            sortForm.submit();
        }, () => { alert('Allow location access to sort by distance.'); this.value = 'recommended'; }, { enableHighAccuracy: true, timeout: 20000 });
    });

    // PG name suggestions under the search box
    const input = document.getElementById('searchInput');
    const box = document.getElementById('suggestionsBox');
    let debounceTimer, current;
    const escapeHtml = s => { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; };
    input.addEventListener('input', function () {
        const term = this.value.trim();
        clearTimeout(debounceTimer);
        if (term.length < 2) { box.classList.add('hidden'); box.innerHTML = ''; return; }
        debounceTimer = setTimeout(() => {
            if (current) current.abort();
            current = new AbortController();
            fetch(@json(route('search.suggestions')) + '?q=' + encodeURIComponent(term), { signal: current.signal, headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(list => {
                    box.classList.toggle('hidden', !list.length);
                    box.innerHTML = list.map(r => `<a href="${escapeHtml(r.url)}" class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-cream border-b border-ink-900/10 last:border-0">
                        <div><div class="font-bold text-sm text-ink-950">${escapeHtml(r.name)}</div><div class="text-xs text-ink-900/50">${escapeHtml([r.locality, r.city].filter(Boolean).join(', '))}</div></div>
                        ${r.rent_min ? `<div class="text-xs font-bold text-coral-600 whitespace-nowrap">₹${Number(r.rent_min).toLocaleString('en-IN')}+</div>` : ''}</a>`).join('');
                })
                .catch(() => {});
        }, 300);
    });
    document.addEventListener('click', e => { if (!input.contains(e.target) && !box.contains(e.target)) box.classList.add('hidden'); });
})();
</script>

@endsection
