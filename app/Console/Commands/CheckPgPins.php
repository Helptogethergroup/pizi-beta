<?php

namespace App\Console\Commands;

use App\Models\Property;
use App\Services\PgLocation;
use Illuminate\Console\Command;

/** Lists live PGs whose map pin does not match their written address, so they can be corrected. Changes nothing. */
class CheckPgPins extends Command
{
    protected $signature = 'pg:check-pins';
    protected $description = 'List live PGs whose map pin is far from their written address';

    public function handle(PgLocation $location): int
    {
        $wrong = Property::active()->with(['city', 'locality', 'owner'])->orderBy('name')->get()
            ->filter(fn (Property $p) => $location->pinLooksWrong($p));

        foreach ($wrong as $p) {
            $this->line("#{$p->id}  {$p->name}" . ($p->owner ? "  (owner: {$p->owner->name})" : ''));
            $this->line('     address: ' . trim($p->address_line . ', ' . $p->locality?->name . ', ' . $p->city?->name . ' ' . $p->pincode));
            $this->line("     saved pin: https://www.google.com/maps?q={$p->latitude},{$p->longitude}");
        }
        $this->info($wrong->count() . ' PG(s) with a doubtful map pin.');

        return self::SUCCESS;
    }
}
