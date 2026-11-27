<?php

namespace App\Services;

use App\Models\City;
use App\Models\Locality;
use App\Models\Property;
use Illuminate\Support\Facades\Cache;

/**
 * The chat assistant's PG search. It shows only what the website shows (active PGs that are not deleted), asks
 * "which area?" before listing anything when no area was given, remembers that question for the visitor's chat
 * and treats their next message as the place.
 */
class ChatPgSearch
{
    private const SHOW = 5;
    private const REMEMBER_MINUTES = 20;

    /** Words that are part of asking, not part of a place name. */
    private const FILLER = [
        'pg', 'pgs', 'hostel', 'hostels', 'coliving', 'room', 'rooms', 'rent', 'me', 'mein', 'mai', 'main', 'mei', 'in', 'at',
        'near', 'nearby', 'ke', 'ka', 'ki', 'paas', 'pass', 'area', 'location', 'locality', 'chahiye', 'chaiye', 'chahie',
        'chhiye', 'chhaiye', 'chhye', 'chiye', 'chahiyea', 'mujhe', 'muje', 'mje', 'mujhko', 'mereko', 'show', 'dikhao', 'dikha',
        'batao', 'do', 'de', 'please', 'plz', 'want', 'need', 'i', 'a', 'an', 'the', 'looking', 'for', 'under', 'below',
        'budget', 'rs', 'rupees', 'tak', 'kam', 'se', 'andar', 'girls', 'girl', 'boys', 'boy', 'male', 'female', 'ladies',
        'gents', 'hai', 'h', 'koi', 'ek', 'around', 'side', 'wala', 'wale', 'sir', 'bhai', 'my', 'some', 'any',
        'में', 'चाहिए', 'मुझे', 'के', 'पास', 'पीजी', 'एक', 'का', 'की',
    ];

    /** What the message (and the AI's reading of it) says about the PG wanted. */
    public function read(string $message, array $intent = []): array
    {
        $lower = ' ' . mb_strtolower($message) . ' ';
        $city = $this->knownCity((string) ($intent['city'] ?? ''));
        $area = trim((string) ($intent['locality'] ?? ''));

        // the AI sometimes calls an area a "city" (Dwarka), or a city an "area" (Delhi)
        if ($city === null && $area === '' && filled($intent['city'] ?? null)) {
            $area = trim((string) $intent['city']);
        }
        if ($area !== '' && ($asCity = $this->knownCity($area))) {
            $city ??= $asCity;
            $area = '';
        }
        $city ??= $this->cityIn($lower);
        if ($area === '') {
            $area = (string) $this->localityIn($lower);
        }

        $gender = in_array($intent['gender'] ?? null, ['male', 'female', 'unisex'], true) ? $intent['gender'] : null;
        if (!$gender && preg_match('/\b(girls?|female|ladies|women)\b|लड़कियों|गर्ल्स/u', $lower)) {
            $gender = 'female';
        } elseif (!$gender && preg_match('/\b(boys?|male|gents|men)\b|लड़कों|बॉयज/u', $lower)) {
            $gender = 'male';
        }

        $max = (int) ($intent['budget_max'] ?? 0);
        if (!$max && preg_match('/(\d+)\s*k\b/i', $message, $m)) {
            $max = (int) $m[1] * 1000;
        } elseif (!$max && preg_match('/(?<![a-z\d-])(\d{4,6})(?!\d)/i', $message, $m)) {
            $max = (int) $m[1];
        }

        return [
            'city' => $city,
            'area' => $area !== '' ? $area : null,
            'gender' => $gender,
            // "pg" is how people name any of them, so only the special kinds narrow the search
            'type' => in_array($intent['property_type'] ?? null, ['hostel', 'coliving', 'flatmate'], true) ? $intent['property_type'] : null,
            'max' => $max ?: null,
            'min' => (int) ($intent['budget_min'] ?? 0) ?: null,
            'all' => (bool) preg_match('/\b(all|sab|sabhi|saare|sare|anywhere|koi bhi|kahin bhi|kahi bhi)\b|सभी|कोई भी|कहीं भी/u', $lower),
        ];
    }

    /** The reply to a PG search: the list, or the question "which area?". */
    public function answer(array $want, string $language, ?string $sessionId): string
    {
        if (!$want['area'] && !$want['all']) {
            return $this->ask($want, $language, $sessionId);
        }

        $query = $this->query($want);
        $total = (clone $query)->count();

        if ($total === 0) {
            $place = trim(implode(', ', array_filter([$want['area'], $want['city']]))) ?: 'Pizi';
            $none = $language === 'hi'
                ? "😕 \"{$place}\" में अभी इस हिसाब से कोई PG नहीं मिला।"
                : "😕 No PG matching that in \"{$place}\" right now.";

            return $none . "\n\n" . $this->ask(['area' => null, 'all' => false] + $want, $language, $sessionId);
        }

        $this->forget($sessionId);
        $properties = $query->orderByDesc('is_featured')->orderByDesc('is_verified')->orderBy('rent_min')->limit(self::SHOW)->get();
        $place = trim(implode(', ', array_filter([$want['area'] ? mb_convert_case($want['area'], MB_CASE_TITLE) : null, $want['city']])));

        $reply = $language === 'hi'
            ? '✅ ' . ($place ?: 'Pizi') . " में {$total} PG मिले" . ($total > self::SHOW ? ' (पहले ' . self::SHOW . ')' : '') . ":\n\n"
            : '✅ ' . $total . ' PG' . ($total > 1 ? 's' : '') . ' found' . ($place ? " in {$place}" : '') . ($total > self::SHOW ? ' (showing ' . self::SHOW . ')' : '') . ":\n\n";

        foreach ($properties as $p) {
            $where = $p->locality?->name ?: mb_strimwidth((string) $p->address_line, 0, 50, '…');
            $rent = '₹' . number_format((float) $p->rent_min) . ($p->rent_max > $p->rent_min ? ' - ₹' . number_format((float) $p->rent_max) : '');
            $reply .= "🏠 {$p->name}\n📍 {$where}" . ($p->city?->name ? ", {$p->city->name}" : '') . "\n💰 {$rent} · " . ucfirst((string) $p->gender)
                . "\n🔗 " . url('/pg/' . $p->slug) . "\n\n";
        }

        $all = url('/search') . '?' . http_build_query(array_filter(['q' => $want['area'] ?: $want['city'], 'gender' => $want['gender'], 'budget_max' => $want['max']]));
        $reply .= ($language === 'hi' ? 'सारे देखें: ' : 'See all: ') . $all;

        return $reply;
    }

    /**
     * The visitor's message after we asked "which area?". Returns null when it is not an answer to that question
     * (a new question, thanks, small talk), so the normal assistant takes it.
     *
     * @param  \Closure(string): array  $aiReads  asks the AI to read the message (used only when we cannot)
     */
    public function followUp(string $message, string $language, ?string $sessionId, \Closure $aiReads): ?string
    {
        $pending = $sessionId ? Cache::get($this->key($sessionId)) : null;
        if (!$pending) {
            return null;
        }
        $text = trim(mb_strtolower($message));
        if (preg_match('/\?|^(hi+|hey+|hello|hy+|thanks?|thank you|ok(ay)?|bye|नमस्ते|धन्यवाद)\b|\b(what|how|why|when|who|kaise|kyu|kyon|kab|kaun)\b|क्या|कैसे|क्यों/u', $text)) {
            $this->forget($sessionId);

            return null;
        }

        $said = $this->read($message);
        $term = $this->placeWords($message);

        // a script or spelling we cannot match ourselves: let the AI read it
        if (!$said['area'] && !$said['city'] && !$said['all'] && $term !== '' && preg_match('/\p{Devanagari}/u', $term)) {
            $said = $this->read($message, $aiReads($message));
        }

        $want = $pending;
        if ($said['city'] && $said['city'] !== $pending['city']) {
            $want['city'] = $said['city'];
        }
        foreach (['gender', 'max', 'min', 'type'] as $k) {
            $want[$k] = $said[$k] ?: $want[$k];
        }
        $want['all'] = $said['all'];
        $want['area'] = $said['area'];

        if (!$want['area'] && !$want['all']) {
            $onlyCity = $said['city'] && str_replace(mb_strtolower($said['city']), '', $term) === '';
            if (!$onlyCity && $term !== '' && !$said['city']) {
                if (count(explode(' ', $term)) > 4) {
                    $this->forget($sessionId);

                    return null; // a long sentence with no place in it
                }
                $want['area'] = $term;
            } elseif (!$onlyCity && !$said['gender'] && !$said['max'] && !$said['city']) {
                $this->forget($sessionId);

                return null;
            }
        }

        return $this->answer($want, $language, $sessionId);
    }

    private function ask(array $want, string $language, ?string $sessionId): string
    {
        if ($sessionId) {
            Cache::put($this->key($sessionId), $want, now()->addMinutes(self::REMEMBER_MINUTES));
        }
        $areas = $this->areasWithPgs($want['city']);
        $list = $areas ? "\n\n• " . implode("\n• ", $areas) : '';

        if ($language === 'hi') {
            return ($want['city'] ? "📍 {$want['city']} में किस area / locality में PG चाहिए?" : '📍 किस city और area में PG चाहिए?')
                . ($list ? "\n\nइन जगहों पर Pizi के PG हैं:" . $list : '')
                . "\n\nArea का नाम लिखिए" . ($want['city'] ? " (या \"सभी\" लिखिए पूरे {$want['city']} के लिए)" : '') . '।';
        }

        return ($want['city'] ? "📍 Which area in {$want['city']} do you want the PG in?" : '📍 Which city and area do you want the PG in?')
            . ($list ? "\n\nPizi has PGs here:" . $list : '')
            . "\n\nType the area name" . ($want['city'] ? " (or \"all\" for the whole of {$want['city']})" : '') . '.';
    }

    private function query(array $want)
    {
        $q = Property::active()->with(['city', 'locality']); // the model leaves out deleted PGs, as the website does
        if ($want['city']) {
            $q->whereHas('city', fn ($c) => $c->where('name', $want['city']));
        }
        if ($want['area']) {
            // "laxmi nagar" also finds "Laxmi  Nagar" (typed with two spaces) or "Laxmi-Nagar"
            $words = preg_split('/[\s,-]+/u', trim($want['area']), -1, PREG_SPLIT_NO_EMPTY);
            $like = '%' . implode('%', array_map(fn ($w) => addcslashes($w, '%_\\'), $words)) . '%';
            $q->where(fn ($w) => $w->where('address_line', 'like', $like)->orWhere('landmark', 'like', $like)
                ->orWhereHas('locality', fn ($l) => $l->where('name', 'like', $like)));
        }
        if (in_array($want['gender'], ['male', 'female'], true)) {
            $q->whereIn('gender', [$want['gender'], 'unisex']);
        } elseif ($want['gender'] === 'unisex') {
            $q->where('gender', 'unisex');
        }
        if ($want['type']) {
            $q->where('property_type', $want['type']);
        }
        if ($want['max']) {
            $q->where('rent_min', '<=', $want['max']);
        }
        if ($want['min']) {
            $q->where('rent_max', '>=', $want['min']);
        }

        return $q;
    }

    /** Up to 8 places that really have PGs: localities of the city, or the cities when no city was given. */
    private function areasWithPgs(?string $city): array
    {
        if (!$city) {
            return City::whereIn('id', Property::active()->select('city_id'))->orderBy('name')->limit(8)->pluck('name')->all();
        }
        $ids = Property::active()->whereHas('city', fn ($c) => $c->where('name', $city))->whereNotNull('locality_id')
            ->selectRaw('locality_id, COUNT(*) as n')->groupBy('locality_id')->orderByDesc('n')->limit(8)->pluck('n', 'locality_id');
        $names = Locality::whereIn('id', $ids->keys())->pluck('name', 'id');

        return $ids->map(fn ($n, $id) => isset($names[$id])
            ? mb_convert_case(trim(preg_replace('/\s+/u', ' ', $names[$id])), MB_CASE_TITLE) . " ({$n})"
            : null)->filter()->values()->all();
    }

    private function knownCity(string $name): ?string
    {
        $name = mb_strtolower(trim($name));

        return $name === '' ? null : City::pluck('name')->first(fn ($c) => mb_strtolower($c) === $name);
    }

    /** A city named inside the message, the longest name first ("Greater Noida" before "Noida"). */
    private function cityIn(string $lower): ?string
    {
        return City::pluck('name')->sortByDesc(fn ($n) => mb_strlen($n))->first(fn ($n) => str_contains($lower, mb_strtolower($n)));
    }

    private function localityIn(string $lower): ?string
    {
        return Locality::pluck('name')->sortByDesc(fn ($n) => mb_strlen($n))
            ->first(fn ($n) => preg_match('/(?<![a-z0-9])' . preg_quote(mb_strtolower($n), '/') . '(?![a-z0-9])/u', $lower));
    }

    /** What is left of the message once the asking words and the budget are taken out. */
    private function placeWords(string $message): string
    {
        $text = mb_strtolower(preg_replace('/\d+\s*k\b|(?<![\d-])\d{4,6}(?!\d)/i', ' ', $message));
        $words = preg_split('/[^\p{L}\p{M}\p{N}-]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $words = array_values(array_diff($words, self::FILLER));
        $left = implode(' ', $words);

        return mb_strlen($left) >= 3 ? $left : '';
    }

    private function key(string $sessionId): string
    {
        return 'chat:ask:' . sha1($sessionId);
    }

    private function forget(?string $sessionId): void
    {
        if ($sessionId) {
            Cache::forget($this->key($sessionId));
        }
    }
}
