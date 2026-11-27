<?php

namespace App\Services;

use App\Models\Property;
use Illuminate\Support\Collection;

/**
 * Where a PG is, for "near me". Many listings have no map pin, so the place is worked out in this order:
 *   exact  the PG's own pin, if it agrees with the written locality / address
 *   area   its sector / locality: the centre of a well-known area, or the pins of other PGs there
 *   city   the centre of its city (only a rough idea)
 * The area and city centres are approximate (to about a kilometre or two) and are always shown as such.
 *
 * A pin is often saved from wherever the person adding the PG was sitting. So a pin that is far from every place
 * named in the locality and address (or, when none is recognised, far outside the city) is not used.
 */
class PgLocation
{
    private const CITIES = [
        'delhi' => [28.6315, 77.2167], 'noida' => [28.5700, 77.3600], 'gurgaon' => [28.4595, 77.0266],
        'ghaziabad' => [28.6692, 77.4538], 'faridabad' => [28.4089, 77.3178], 'mumbai' => [19.0760, 72.8777],
        'bangalore' => [12.9716, 77.5946], 'hyderabad' => [17.3850, 78.4867], 'chennai' => [13.0827, 80.2707],
        'pune' => [18.5204, 73.8567], 'kolkata' => [22.5726, 88.3639], 'ahmedabad' => [23.0225, 72.5714],
        'jaipur' => [26.9124, 75.7873], 'lucknow' => [26.8467, 80.9462], 'indore' => [22.7196, 75.8577],
        'chandigarh' => [30.7333, 76.7794], 'kochi' => [9.9312, 76.2673], 'coimbatore' => [11.0168, 76.9558],
        'goa' => [15.4909, 73.8278], 'bhubaneswar' => [20.2961, 85.8245],
    ];

    /** How far (km) a pin may be from the place named before it is taken to be wrong. */
    private const SECTOR_KM = 5;
    private const AREA_KM = 6;
    private const CITY_KM = 40;

    private const SECTORS = [
        'noida' => [
            5 => [28.588, 77.318], 15 => [28.585, 77.311], 16 => [28.578, 77.317], 18 => [28.570, 77.326], 19 => [28.580, 77.327],
            20 => [28.575, 77.332], 22 => [28.596, 77.344], 27 => [28.574, 77.330], 37 => [28.566, 77.340], 44 => [28.553, 77.342],
            45 => [28.550, 77.353], 50 => [28.570, 77.368], 51 => [28.578, 77.365], 52 => [28.586, 77.368], 58 => [28.605, 77.365],
            59 => [28.610, 77.370], 61 => [28.596, 77.369], 62 => [28.622, 77.365], 63 => [28.624, 77.379], 66 => [28.600, 77.385],
            70 => [28.593, 77.390], 71 => [28.589, 77.380], 76 => [28.569, 77.388], 104 => [28.538, 77.368], 125 => [28.544, 77.327],
            126 => [28.543, 77.333], 127 => [28.535, 77.347], 128 => [28.523, 77.360], 130 => [28.511, 77.375], 132 => [28.510, 77.376],
            135 => [28.500, 77.404], 137 => [28.512, 77.407],
        ],
        'gurgaon' => [14 => [28.473, 77.045], 21 => [28.512, 77.072], 22 => [28.504, 77.072], 45 => [28.444, 77.068]],
        'faridabad' => [15 => [28.395, 77.320], 21 => [28.418, 77.305]],
    ];

    /** name => [lat, lng, km a pin may be away (only for wide areas)] */
    private const AREAS = [
        'delhi' => [
            'govindpuri' => [28.536, 77.264], 'kalkaji' => [28.549, 77.251], 'nehru place' => [28.549, 77.253],
            'kamla nagar' => [28.681, 77.203], 'shakti nagar' => [28.681, 77.192], 'mukherjee nagar' => [28.712, 77.214],
            'gtb nagar' => [28.698, 77.206], 'north campus' => [28.689, 77.210], 'laxmi nagar' => [28.630, 77.277],
            'karol bagh' => [28.652, 77.191], 'pusa road' => [28.645, 77.185], 'patel nagar' => [28.651, 77.169],
            'tilak nagar' => [28.637, 77.097], 'rajouri garden' => [28.649, 77.121], 'shivaji vihar' => [28.644, 77.118],
            'janakpuri' => [28.622, 77.088], 'uttam nagar' => [28.620, 77.056], 'krishna park' => [28.630, 77.075],
            'dwarka' => [28.592, 77.046, 8], 'kotla mubarakpur' => [28.574, 77.226], 'lajpat nagar' => [28.568, 77.243],
            'amar colony' => [28.564, 77.241], 'okhla' => [28.536, 77.275], 'saket' => [28.525, 77.207],
            'hauz khas' => [28.549, 77.200], 'satya niketan' => [28.588, 77.167], 'south campus' => [28.588, 77.168],
            'mayur vihar' => [28.608, 77.295], 'rohini' => [28.738, 77.112, 10],
        ],
        'noida' => [
            'knowledge park' => [28.469, 77.496], 'dadri' => [28.553, 77.554, 8], 'greater noida west' => [28.610, 77.430, 8],
            'noida extension' => [28.610, 77.430, 8], 'gaur city' => [28.610, 77.430, 8], 'greater noida' => [28.474, 77.504, 17],
        ],
        'gurgaon' => ['cyber city' => [28.495, 77.089], 'dlf' => [28.475, 77.094], 'sohna road' => [28.410, 77.042, 10]],
        'ghaziabad' => [
            'indirapuram' => [28.641, 77.371], 'vaishali' => [28.645, 77.339], 'vasundhara' => [28.660, 77.373], 'raj nagar' => [28.680, 77.443],
        ],
        'faridabad' => ['greenfield' => [28.456, 77.288], 'nit' => [28.389, 77.305, 8]],
    ];

    /**
     * The PGs of a query, nearest first, each with `distance_km` and `place` ('exact' | 'area' | 'city').
     * A PG placed only at its city's centre comes after those whose place is known better and is as near.
     */
    public function nearest(Collection $properties, float $lat, float $lng): Collection
    {
        $areaPins = $this->pinsByLocality();

        return $properties->map(function (Property $p) use ($lat, $lng, $areaPins) {
            [$point, $place] = $this->locate($p, $areaPins);
            if (!$point) {
                return null;
            }
            $p->setAttribute('distance_km', round($this->km($lat, $lng, $point[0], $point[1]), 1));
            $p->setAttribute('place', $place);

            return $p;
        })->filter()
            // a city-centre guess counts as a few kilometres further than it looks
            ->sortBy(fn ($p) => $p->distance_km + ($p->place === 'city' ? 8 : 0))->values();
    }

    /** @return array{0: ?array{0: float, 1: float}, 1: ?string} */
    public function locate(Property $p, ?array $areaPins = null): array
    {
        $hints = $this->hints($p);
        if ($this->inIndia($p->latitude, $p->longitude) && $this->pinAgrees($p, $hints)) {
            return [[(float) $p->latitude, (float) $p->longitude], 'exact'];
        }
        if ($hints) {
            // the most precise place named (a sector or small area before a wide one)
            usort($hints, fn ($a, $b) => $a[1] <=> $b[1]);

            return [$hints[0][0], 'area'];
        }
        $areaPins ??= $this->pinsByLocality();
        if ($p->locality_id && isset($areaPins[$p->locality_id])) {
            return [$areaPins[$p->locality_id], 'area'];
        }
        $city = (string) $p->city?->slug;

        return isset(self::CITIES[$city]) ? [self::CITIES[$city], 'city'] : [null, null];
    }

    /** The PG has a map pin, but it is nowhere near its written address. */
    public function pinLooksWrong(Property $p): bool
    {
        return $this->inIndia($p->latitude, $p->longitude) && !$this->pinAgrees($p, $this->hints($p));
    }

    /** The places named in the locality and address, each as [[lat, lng], km a pin may be away]. */
    private function hints(Property $p): array
    {
        $city = (string) $p->city?->slug;
        $hints = [];
        foreach ([$p->locality?->name, $p->address_line . ' ' . $p->landmark] as $text) {
            $text = preg_replace('/\s+/', ' ', mb_strtolower((string) $text));
            // Greater Noida has its own sector numbers, far from the Noida ones
            $otherSectors = $city === 'noida' && preg_match('/greater noida|noida extension/', $text);
            if (!$otherSectors && preg_match('/\bsec(?:tor)?[\s\-.]*0*(\d{1,3})(?!\d)/', $text, $m) && isset(self::SECTORS[$city][(int) $m[1]])) {
                $hints[] = [self::SECTORS[$city][(int) $m[1]], self::SECTOR_KM];
            }
            foreach (self::AREAS[$city] ?? [] as $name => $area) {
                if (preg_match('/\b' . preg_quote($name, '/') . '\b/', $text)) {
                    $hints[] = [[$area[0], $area[1]], $area[2] ?? self::AREA_KM];
                }
            }
        }

        return $hints;
    }

    /** Close to any of the named places; with none recognised, at least inside the city. */
    private function pinAgrees(Property $p, array $hints): bool
    {
        $lat = (float) $p->latitude;
        $lng = (float) $p->longitude;
        if (!$hints) {
            $centre = self::CITIES[(string) $p->city?->slug] ?? null;

            return !$centre || $this->km($lat, $lng, $centre[0], $centre[1]) <= self::CITY_KM;
        }
        foreach ($hints as [$point, $allowed]) {
            if ($this->km($lat, $lng, $point[0], $point[1]) <= $allowed) {
                return true;
            }
        }

        return false;
    }

    /** The middle of the (believable) pins of each locality. */
    private function pinsByLocality(): array
    {
        return Property::active()->with(['city', 'locality'])->whereNotNull('locality_id')
            ->whereBetween('latitude', [6, 38])->whereBetween('longitude', [68, 98])->get()
            ->reject(fn ($p) => $this->pinLooksWrong($p))
            ->groupBy('locality_id')
            ->map(fn ($rows) => [(float) $rows->avg('latitude'), (float) $rows->avg('longitude')])->all();
    }

    private function inIndia($lat, $lng): bool
    {
        return $lat !== null && $lng !== null && $lat >= 6 && $lat <= 38 && $lng >= 68 && $lng <= 98;
    }

    public function km(float $aLat, float $aLng, float $bLat, float $bLng): float
    {
        $d = sin(deg2rad($bLat - $aLat) / 2) ** 2 + cos(deg2rad($aLat)) * cos(deg2rad($bLat)) * sin(deg2rad($bLng - $aLng) / 2) ** 2;

        return 12742 * asin(min(1, sqrt($d)));
    }
}
