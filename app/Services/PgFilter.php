<?php

namespace App\Services;

use App\Models\Amenity;
use App\Models\City;
use App\Models\Property;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The filters of the PG search page: reading them from the address, applying them to the query, the "active filter"
 * chips, the choices to offer (only ones that have PGs, with counts) and what to drop when nothing matches.
 */
class PgFilter
{
    public const SHARING = ['single' => 'Single', 'double' => 'Double', 'triple' => 'Triple'];
    public const GENDERS = ['male' => 'Boys', 'female' => 'Girls', 'unisex' => 'Unisex'];
    public const TYPES = ['pg' => 'PG', 'hostel' => 'Hostel', 'coliving' => 'Co-living', 'flatmate' => 'Flatmate'];

    /** Words people type that describe the search, not the place. */
    private const FILLER = ['pg', 'pgs', 'in', 'near', 'nearby', 'me', 'for', 'the', 'a', 'room', 'rooms', 'me.', 'mein'];

    public function read(Request $request): array
    {
        $text = fn (string $key) => is_scalar($v = $request->query($key)) ? trim((string) $v) : '';
        $int = fn (string $key) => ($v = (int) preg_replace('/\D/', '', $text($key))) > 0 ? $v : null;
        $list = fn (string $key) => array_values(array_filter((array) $request->query($key, []), fn ($v) => is_scalar($v) && $v !== ''));
        $one = fn (string $key, array $allowed) => isset($allowed[$text($key)]) ? $text($key) : null;

        return [
            'q' => mb_substr($text('q'), 0, 100),
            'city' => $text('city') ?: null,
            'locality' => array_map('strval', $list('locality')),
            'nearby_university_id' => $int('nearby_university_id'),
            'gender' => $one('gender', self::GENDERS),
            'sharing' => $one('sharing', self::SHARING),
            'budget_min' => $int('budget_min'),
            'budget_max' => $int('budget_max'),
            'deposit_max' => $int('deposit_max'),
            'type' => $one('type', self::TYPES),
            'amenities' => array_values(array_unique(array_filter(array_map('intval', $list('amenities'))))),
            'food' => $request->query('food') ? 1 : null,
            'verified' => $request->query('verified') ? 1 : null,
            'photos' => $request->query('photos') ? 1 : null,
            'available' => $request->query('available') ? 1 : null,
        ];
    }

    public function apply(Builder $q, array $f): Builder
    {
        $gender = $f['gender'];
        if ($f['q'] !== '') {
            [$words, $said] = $this->words($f['q']);
            $gender ??= $said; // "girls pg karol bagh" also means: for girls
            foreach ($words as $word) {
                $like = '%' . addcslashes($word, '%_\\') . '%';
                $q->where(fn ($w) => $w->where('properties.name', 'like', $like)
                    ->orWhere('properties.address_line', 'like', $like)
                    ->orWhere('properties.landmark', 'like', $like)
                    ->orWhere('properties.pincode', 'like', $like)
                    ->orWhereHas('locality', fn ($l) => $l->where('name', 'like', $like))
                    ->orWhereHas('city', fn ($c) => $c->where('name', 'like', $like))
                    ->orWhereExists(fn ($u) => $u->selectRaw('1')->from('universities')
                        ->whereColumn('universities.id', 'properties.nearby_university_id')
                        ->where(fn ($n) => $n->where('universities.name', 'like', $like)->orWhere('universities.abbreviation', 'like', $like))));
            }
        }
        if ($f['city']) {
            $q->whereHas('city', fn ($c) => $c->where('slug', $f['city']));
        }
        if ($f['locality']) {
            $q->whereHas('locality', fn ($l) => $l->whereIn('slug', $f['locality']));
        }
        if ($f['nearby_university_id']) {
            $q->where('properties.nearby_university_id', $f['nearby_university_id']);
        }
        // a boys or girls search also shows PGs open to both
        if ($gender) {
            $gender === 'unisex' ? $q->where('properties.gender', 'unisex') : $q->whereIn('properties.gender', [$gender, 'unisex']);
        }
        if ($f['type']) {
            $q->where('properties.property_type', $f['type']);
        }
        if ($f['sharing']) {
            // offered means a price above 0; the budget is then about that room's price
            $price = $this->sharingPrice($f['sharing']);
            $q->whereRaw("$price > 0");
            if ($f['budget_max']) {
                $q->whereRaw("$price <= ?", [$f['budget_max']]);
            }
            if ($f['budget_min']) {
                $q->whereRaw("$price >= ?", [$f['budget_min']]);
            }
        } else {
            // the PG's rent range and the wanted range overlap
            if ($f['budget_max']) {
                $q->where('properties.rent_min', '<=', $f['budget_max']);
            }
            if ($f['budget_min']) {
                $q->where('properties.rent_max', '>=', $f['budget_min']);
            }
        }
        if ($f['deposit_max']) {
            $q->whereNotNull('properties.security_deposit')->where('properties.security_deposit', '<=', $f['deposit_max']);
        }
        foreach ($f['amenities'] as $id) { // every ticked amenity must be there
            $q->whereHas('amenities', fn ($a) => $a->where('amenities.id', $id));
        }
        if ($f['food']) {
            $q->where('properties.food_included', true);
        }
        if ($f['verified']) {
            $q->where('properties.is_verified', true);
        }
        if ($f['photos']) {
            $q->where(fn ($w) => $w->where(fn ($c) => $c->whereNotNull('properties.cover_image')->where('properties.cover_image', '!=', ''))->orWhereHas('images'));
        }
        if ($f['available']) {
            $q->where('properties.available_rooms', '>', 0);
        }

        return $q;
    }

    public function sort(Builder $q, string $sort, ?string $sharing): Builder
    {
        $price = $sharing ? DB::raw($this->sharingPrice($sharing)) : 'properties.rent_min';

        return match ($sort) {
            'price_low' => $q->orderBy($price, 'asc'),
            'price_high' => $q->orderBy($price, 'desc'),
            'popular' => $q->orderByDesc('properties.view_count'),
            'latest' => $q->latest(),
            // recommended: featured, then verified, then newest
            default => $q->orderByDesc('properties.is_featured')->orderByDesc('properties.is_verified')->latest(),
        };
    }

    /** The choices offered on the page; counts are of live PGs (inside the chosen city, where one is chosen). */
    public function options(array $f): array
    {
        $live = fn () => Property::active()->when($f['city'], fn ($q) => $q->whereHas('city', fn ($c) => $c->where('slug', $f['city'])));

        $cityCounts = Property::active()->selectRaw('city_id, COUNT(*) as n')->groupBy('city_id')->pluck('n', 'city_id');
        $cities = City::where('is_active', true)->orderBy('name')->get(['id', 'name', 'slug'])
            ->each(fn ($c) => $c->setAttribute('n', (int) ($cityCounts[$c->id] ?? 0)))
            ->sortBy(fn ($c) => [$c->n ? 0 : 1, $c->name])->values();

        $localities = collect();
        if ($f['city']) {
            $localities = DB::table('localities')->joinSub($live()->selectRaw('locality_id, COUNT(*) as n')->groupBy('locality_id'), 'p', 'p.locality_id', '=', 'localities.id')
                ->orderByDesc('p.n')->orderBy('localities.name')->get(['localities.name', 'localities.slug', 'p.n']);
        }
        $universities = DB::table('universities')->joinSub($live()->whereNotNull('nearby_university_id')->selectRaw('nearby_university_id, COUNT(*) as n')->groupBy('nearby_university_id'), 'p', 'p.nearby_university_id', '=', 'universities.id')
            ->orderBy('universities.name')->get(['universities.id', 'universities.name', 'p.n']);

        $amenityCounts = DB::table('property_amenities')->whereIn('property_id', $live()->select('properties.id'))
            ->selectRaw('amenity_id, COUNT(*) as n')->groupBy('amenity_id')->pluck('n', 'amenity_id');
        $amenities = Amenity::orderBy('name')->get()->each(fn ($a) => $a->setAttribute('n', (int) ($amenityCounts[$a->id] ?? 0)))
            ->filter(fn ($a) => $a->n > 0 || in_array($a->id, $f['amenities'], true))->sortByDesc('n')->values();

        $types = $live()->selectRaw('property_type, COUNT(*) as n')->groupBy('property_type')->pluck('n', 'property_type');

        return compact('cities', 'localities', 'universities', 'amenities', 'types');
    }

    /** The filters in use, each as [label, the query string without it]. */
    public function chips(array $f, array $options, array $query): array
    {
        $rs = fn ($v) => '₹' . number_format($v);
        $chips = [];
        $add = function (string $label, string $key, $value = null) use (&$chips, $query) {
            $rest = $query;
            unset($rest['page']);
            if ($value === null) {
                unset($rest[$key]);
            } else {
                $rest[$key] = array_values(array_filter((array) ($rest[$key] ?? []), fn ($v) => (string) $v !== (string) $value));
            }
            if ($key === 'city') {
                unset($rest['locality']); // areas belong to the city
            }
            $chips[] = ['label' => $label, 'query' => $rest];
        };

        if ($f['q'] !== '') $add('“' . $f['q'] . '”', 'q');
        if ($f['city']) $add($options['cities']->firstWhere('slug', $f['city'])?->name ?? ucfirst($f['city']), 'city');
        foreach ($f['locality'] as $slug) {
            $add($options['localities']->firstWhere('slug', $slug)?->name ?? ucwords(str_replace('-', ' ', $slug)), 'locality', $slug);
        }
        if ($f['nearby_university_id']) $add('Near ' . (DB::table('universities')->where('id', $f['nearby_university_id'])->value('name') ?? 'college'), 'nearby_university_id');
        if ($f['gender']) $add(self::GENDERS[$f['gender']], 'gender');
        if ($f['sharing']) $add(self::SHARING[$f['sharing']] . ' sharing', 'sharing');
        if ($f['budget_min']) $add('From ' . $rs($f['budget_min']), 'budget_min');
        if ($f['budget_max']) $add('Up to ' . $rs($f['budget_max']), 'budget_max');
        if ($f['deposit_max']) $add('Deposit up to ' . $rs($f['deposit_max']), 'deposit_max');
        if ($f['type']) $add(self::TYPES[$f['type']], 'type');
        foreach ($f['amenities'] as $id) {
            $add($options['amenities']->firstWhere('id', $id)?->name ?? 'Amenity', 'amenities', $id);
        }
        foreach (['food' => 'Food included', 'verified' => 'Verified only', 'photos' => 'With photos', 'available' => 'Rooms available'] as $key => $label) {
            if ($f[$key]) $add($label, $key);
        }

        return $chips;
    }

    /** When nothing matches: which single filter to drop, and how many PGs that would show. */
    public function relaxations(array $chips): array
    {
        $out = [];
        foreach ($chips as $chip) {
            $f = $this->read(Request::create('/search', 'GET', $chip['query']));
            $n = $this->apply(Property::active(), $f)->count();
            if ($n > 0) {
                $out[] = ['label' => $chip['label'], 'query' => $chip['query'], 'count' => $n];
            }
        }
        usort($out, fn ($a, $b) => $b['count'] <=> $a['count']);

        return array_slice($out, 0, 6);
    }

    /** @return array{0: string[], 1: ?string} the words to look for, and the gender said in the text */
    private function words(string $text): array
    {
        $gender = null;
        $words = [];
        foreach (preg_split('/[\s,]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) as $word) {
            if (in_array($word, ['girls', 'girl', 'female', 'ladies'], true)) {
                $gender = 'female';
            } elseif (in_array($word, ['boys', 'boy', 'male', 'gents'], true)) {
                $gender = 'male';
            } elseif (!in_array($word, self::FILLER, true)) {
                $words[] = $word;
            }
        }

        return [array_slice($words, 0, 8), $gender];
    }

    /** SQL for the price of one sharing type; $type is one of the SHARING keys only. */
    private function sharingPrice(string $type): string
    {
        $type = isset(self::SHARING[$type]) ? $type : 'single';

        return "CAST(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(properties.sharing_options, '$.{$type}')), '0') AS UNSIGNED)";
    }
}
