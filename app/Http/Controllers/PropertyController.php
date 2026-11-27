<?php

namespace App\Http\Controllers;

use App\Models\Amenity;
use App\Models\City;
use App\Models\Locality;
use App\Models\Property;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    /**
     * Generic search page (with filters).
     * URL: /search
     */
   public function search(Request $request)
    {
        $filter = app(\App\Services\PgFilter::class);
        $filters = $filter->read($request);
        $q = $filter->apply(Property::active()->with(['city', 'locality', 'amenities', 'images']), $filters);

        // Lightweight JSON modes used by the homepage (live result count, PGs near me).
        if ($request->query('json') === 'count') {
            return response()->json(['count' => (clone $q)->count()]);
        }
        if ($request->query('json') === 'nearby') {
            return $this->nearbyJson($request);
        }
        $sort = (string) $request->query('sort', 'recommended');
        if ($sort === 'nearest' && $request->filled('lat') && $request->filled('lng')) {
            // nearest first; PGs without a map pin are placed by their area (see PgLocation)
            $ids = app(\App\Services\PgLocation::class)
                ->nearest((clone $q)->get(), (float) $request->lat, (float) $request->lng)
                ->pluck('id')->map(fn ($id) => (int) $id)->all();
            $ids ? $q->whereIn('properties.id', $ids)->orderByRaw('FIELD(properties.id, ' . implode(',', $ids) . ')') : $q->latest();
        } else {
            $filter->sort($q, $sort, $filters['sharing']);
        }

        $properties = $q->paginate(12)->withQueryString();

        // For the filter panel, the "in use" chips and the help when nothing matches
        $options = $filter->options($filters);
        $chips = $filter->chips($filters, $options, $request->query());
        $relaxations = $properties->total() === 0 ? $filter->relaxations($chips) : [];

        return view('public.search', compact('properties', 'filters', 'options', 'chips', 'relaxations', 'sort'));
    }

    /**
     * Lightweight autocomplete suggestions — called via JS as the user
     * types in the search box. Returns a small, fast JSON list (not a
     * full page load).
     * URL: /search/suggestions?q=...
     */
    public function suggest(Request $request)
    {
        $term = trim($request->get('q', ''));

        if (strlen($term) < 2) {
            return response()->json([]);
        }

        $properties = Property::active()
            ->where('name', 'like', '%' . $term . '%')
            ->with('locality', 'city')
            ->orderByDesc('is_featured')
            ->limit(8)
            ->get(['id', 'name', 'slug', 'locality_id', 'city_id', 'rent_min']);

        $results = $properties->map(function ($p) {
            return [
                'name' => $p->name,
                'url' => url('/pg/' . $p->slug),
                'locality' => $p->locality?->name,
                'city' => $p->city?->name,
                'rent_min' => $p->rent_min,
            ];
        });

        return response()->json($results);
    }

    /**
     * SEO-friendly city page.
     * URL: /pg-in-{city_slug}
     */
    public function city(string $slug, Request $request)
    {
        $city = City::where('slug', $slug)->where('is_active', true)->firstOrFail();
        $request->merge(['city' => $city->slug]);
        $properties = $this->filteredQuery($request)->paginate(12)->withQueryString();
     $localities = Locality::where('city_id', $city->id)
    ->where('is_active', true)
    ->withCount(['properties' => fn ($q) => $q->where('properties.is_active', true)])
    ->orderBy('name')->get();
        return view('public.city', compact('city', 'properties', 'localities'));
    }

    /**
     * SEO-friendly locality page.
     * URL: /pg-in-{city_slug}/{locality_slug}
     */
    public function locality(string $citySlug, string $localitySlug, Request $request)
    {
        $city = City::where('slug', $citySlug)->firstOrFail();
        $locality = Locality::where('city_id', $city->id)
            ->where('slug', $localitySlug)
            ->where('is_active', true)
            ->firstOrFail();

        $request->merge([
            'city' => $city->slug,
            'locality' => $locality->slug,
        ]);

        $properties = $this->filteredQuery($request)->paginate(12)->withQueryString();

        return view('public.locality', compact('city', 'locality', 'properties'));
    }

    /**
     * Property detail page.
     * URL: /pg/{slug}
     */
    public function show(string $slug)
    {
        $property = Property::where('slug', $slug)
            ->active()
            ->with(['city', 'locality', 'amenities', 'images', 'reviews', 'owner', 'landmarks'])
            ->firstOrFail();

        $property->increment('view_count');

        $similar = Property::active()
            ->where('id', '!=', $property->id)
            ->where('locality_id', $property->locality_id)
            ->with(['city', 'locality'])
            ->take(4)->get();

        if ($similar->count() < 4) {
            $more = Property::active()
                ->where('id', '!=', $property->id)
                ->where('city_id', $property->city_id)
                ->whereNotIn('id', $similar->pluck('id'))
                ->take(4 - $similar->count())->get();
            $similar = $similar->merge($more);
        }

        return view('public.property-detail', compact('property', 'similar'));
    }

    private function filteredQuery(Request $request)
{
    $q = Property::active()->with(['city', 'locality']);

        if ($request->filled('q')) {
            $term = '%' . $request->q . '%';
            $q->where(function ($qb) use ($term) {
                $qb->where('name', 'like', $term)
                   ->orWhere('address_line', 'like', $term)
                   ->orWhere('landmark', 'like', $term);
            });
        }

        if ($request->filled('city')) {
            $q->whereHas('city', fn ($c) => $c->where('slug', $request->city));
        }

        if ($request->filled('locality')) {
            $q->whereHas('locality', fn ($l) => $l->where('slug', $request->locality));
        }

        if ($request->filled('gender') && $request->gender !== 'any') {
            $q->where(function ($qb) use ($request) {
                $qb->where('gender', $request->gender)
                   ->orWhere('gender', 'unisex');
            });
        }

        if ($request->filled('min_rent')) {
            $q->where('rent_max', '>=', (float) $request->min_rent);
        }
        if ($request->filled('max_rent')) {
            $q->where('rent_min', '<=', (float) $request->max_rent);
        }

        if ($request->filled('amenities')) {
            $ids = is_array($request->amenities) ? $request->amenities : [$request->amenities];
            foreach ($ids as $aid) {
                $q->whereHas('amenities', fn ($a) => $a->where('amenities.id', $aid));
            }
        }

        if ($request->filled('sharing') && in_array($request->sharing, ['single', 'double', 'triple'])) {
            $q->whereNotNull('sharing_options->' . $request->sharing);
        }

        $sort = $request->get('sort', 'recommended');
        match ($sort) {
            'low_to_high' => $q->orderBy('rent_min', 'asc'),
            'high_to_low' => $q->orderBy('rent_max', 'desc'),
            'newest' => $q->latest(),
            default => $q->orderByDesc('is_featured')->orderByDesc('view_count'),
        };

        return $q;
    }

    /**
     * Sitemap XML for SEO.
     * URL: /sitemap.xml
     */
    public function sitemap()
    {
        $cities = City::where('is_active', true)->get();
        $localities = Locality::where('is_active', true)->with('city')->get();
        $properties = Property::active()->select('slug', 'updated_at')->get();
        $blogs = \App\Models\Blog::published()->select('slug', 'updated_at')->get();
        $landmarks = \App\Models\Landmark::where('is_active', true)->select('slug', 'updated_at')->get();

        return response()
            ->view('public.sitemap', compact('cities', 'localities', 'properties', 'blogs','landmarks'))
            ->header('Content-Type', 'application/xml');
    }
    

    /**
     * Nearest PGs to a coordinate — JSON for the homepage "PGs near you" block. PGs without a map pin are placed by
     * their sector / locality and marked as approximate. Nothing within MAX_NEAR_KM means we have no PGs there yet.
     */
    private function nearbyJson(Request $request)
    {
        $lat = (float) $request->query('lat');
        $lng = (float) $request->query('lng');
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 || ($lat == 0 && $lng == 0)) {
            return response()->json(['data' => []], 422);
        }

        $nearest = app(\App\Services\PgLocation::class)
            ->nearest(Property::active()->with(['city', 'locality'])->get(), $lat, $lng);
        $rows = $nearest->filter(fn ($p) => $p->distance_km <= 100)->take(6);

        return response()->json([
            'nearest_km' => $nearest->min('distance_km'),
            'data' => $rows->map(fn ($p) => [
                'approx' => $p->place !== 'exact',
                'place' => $p->place,
                'name' => $p->name,
                'url' => route('property.show', $p->slug),
                'image' => $p->cover_image
                    ? (str_starts_with($p->cover_image, 'http') ? $p->cover_image : asset('storage/' . $p->cover_image))
                    : null,
                'locality' => $p->locality?->name,
                'city' => $p->city?->name,
                'gender' => $p->gender,
                'rent_min' => $p->rent_min,
                'distance_km' => $p->distance_km,
            ])->values(),
        ]);
    }
}
