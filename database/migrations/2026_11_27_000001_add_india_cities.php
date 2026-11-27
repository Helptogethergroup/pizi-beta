<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pizi goes beyond Delhi NCR: adds the big Indian cities and a few well-known localities in each (the owner app
 * asks for a locality when a PG is added). A city or locality that already exists is left exactly as it is.
 */
return new class extends Migration
{
    private const CITIES = [
        // slug => [name, state, localities]
        'mumbai' => ['Mumbai', 'Maharashtra', ['Andheri', 'Powai', 'Bandra', 'Malad', 'Goregaon', 'Vile Parle', 'Thane', 'Navi Mumbai']],
        'bangalore' => ['Bangalore', 'Karnataka', ['Koramangala', 'HSR Layout', 'BTM Layout', 'Whitefield', 'Electronic City', 'Marathahalli', 'Indiranagar', 'Bellandur']],
        'hyderabad' => ['Hyderabad', 'Telangana', ['Gachibowli', 'Madhapur', 'Hitech City', 'Kondapur', 'Kukatpally', 'Ameerpet', 'Miyapur', 'Dilsukhnagar']],
        'chennai' => ['Chennai', 'Tamil Nadu', ['Velachery', 'OMR', 'Adyar', 'T Nagar', 'Anna Nagar', 'Tambaram', 'Porur', 'Guindy']],
        'pune' => ['Pune', 'Maharashtra', ['Hinjewadi', 'Kothrud', 'Viman Nagar', 'Wakad', 'Hadapsar', 'Baner', 'Shivaji Nagar', 'Kharadi']],
        'kolkata' => ['Kolkata', 'West Bengal', ['Salt Lake', 'New Town', 'Rajarhat', 'Park Street', 'Jadavpur', 'Behala', 'Dum Dum', 'Garia']],
        'ahmedabad' => ['Ahmedabad', 'Gujarat', ['Navrangpura', 'Satellite', 'Vastrapur', 'Bopal', 'Maninagar', 'Gota', 'SG Highway', 'Thaltej']],
        'jaipur' => ['Jaipur', 'Rajasthan', ['Malviya Nagar', 'Mansarovar', 'Vaishali Nagar', 'C Scheme', 'Jagatpura', 'Raja Park', 'Tonk Road', 'Sitapura']],
        'lucknow' => ['Lucknow', 'Uttar Pradesh', ['Gomti Nagar', 'Hazratganj', 'Aliganj', 'Indira Nagar', 'Aashiana', 'Jankipuram', 'Mahanagar', 'Chinhat']],
        'indore' => ['Indore', 'Madhya Pradesh', ['Vijay Nagar', 'Bhawarkua', 'Palasia', 'Rau', 'Sudama Nagar', 'Geeta Bhawan', 'Annapurna', 'Nipania']],
        'chandigarh' => ['Chandigarh', 'Chandigarh', ['Sector 15', 'Sector 22', 'Sector 34', 'Sector 37', 'Sector 44', 'Manimajra', 'Mohali', 'Zirakpur']],
        'kochi' => ['Kochi', 'Kerala', ['Kakkanad', 'Edappally', 'Kaloor', 'Palarivattom', 'Vyttila', 'Aluva', 'Kalamassery', 'MG Road']],
        'coimbatore' => ['Coimbatore', 'Tamil Nadu', ['Peelamedu', 'Gandhipuram', 'RS Puram', 'Saravanampatti', 'Singanallur', 'Saibaba Colony', 'Ganapathy', 'Vadavalli']],
        'goa' => ['Goa', 'Goa', ['Panaji', 'Margao', 'Mapusa', 'Vasco da Gama', 'Porvorim', 'Ponda', 'Calangute', 'Taleigao']],
        'bhubaneswar' => ['Bhubaneswar', 'Odisha', ['Patia', 'Saheed Nagar', 'Chandrasekharpur', 'Jaydev Vihar', 'Nayapalli', 'Khandagiri', 'KIIT Road', 'Rasulgarh']],
    ];

    public function up(): void
    {
        $order = (int) DB::table('cities')->max('display_order');
        $now = now();

        foreach (self::CITIES as $slug => [$name, $state, $localities]) {
            $city = DB::table('cities')->where('slug', $slug)->orWhereRaw('LOWER(name) = ?', [strtolower($name)])->first();
            if (!$city) {
                $id = DB::table('cities')->insertGetId([
                    'name' => $name,
                    'slug' => $slug,
                    'state' => $state,
                    'description' => "Find PGs, hostels and co-living spaces in {$name} for students and working professionals. Compare rents, amenities and locations, with zero brokerage on Pizi.",
                    'meta_title' => "PG in {$name} – Verified Boys & Girls PGs | Pizi",
                    'meta_description' => "Find PGs in {$name} for boys, girls and working professionals. Compare rents, amenities and locations with zero brokerage and free site visits on Pizi.",
                    'is_active' => 1,
                    'display_order' => ++$order,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $id = $city->id;
            }

            foreach ($localities as $locality) {
                $exists = DB::table('localities')->where('city_id', $id)
                    ->where(fn ($q) => $q->where('slug', Str::slug($locality))->orWhereRaw('LOWER(name) = ?', [strtolower($locality)]))
                    ->exists();
                if (!$exists) {
                    DB::table('localities')->insert([
                        'city_id' => $id, 'name' => $locality, 'slug' => Str::slug($locality),
                        'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
        }

        Cache::forget('nav_cities');
        Cache::forget('home_city_pg_counts');
    }

    public function down(): void
    {
        // Cities are not removed: owners may have listed PGs in them by then.
    }
};
