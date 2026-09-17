<?php

namespace Database\Seeders;

use App\Models\Municipality;
use App\Models\Province;
use App\Models\Region;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MoliseGeographySeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $region = Region::query()->updateOrCreate(['code' => 'MOL'], ['name' => 'Molise']);
            $provinces = collect([
                'CB' => Province::query()->updateOrCreate(['code' => 'CB'], ['region_id' => $region->id, 'name' => 'Campobasso']),
                'IS' => Province::query()->updateOrCreate(['code' => 'IS'], ['region_id' => $region->id, 'name' => 'Isernia']),
            ]);

            foreach ($this->municipalities() as $municipality) {
                $attributes = ['province_id' => $provinces[$municipality['province']]->id, 'name' => $municipality['name']];
                if (isset($municipality['postal_code'])) {
                    $attributes['postal_code'] = $municipality['postal_code'];
                }
                Municipality::query()->updateOrCreate(
                    ['istat_code' => $municipality['istat_code']],
                    $attributes,
                );
            }
        });
    }

    /** @return list<array{istat_code: string, name: string, province: 'CB'|'IS', postal_code?: string}> */
    private function municipalities(): array
    {
        return [
            ['istat_code' => '070001', 'name' => 'Acquaviva Collecroce', 'province' => 'CB'],
            ['istat_code' => '070002', 'name' => 'Baranello', 'province' => 'CB'],
            ['istat_code' => '070003', 'name' => 'Bojano', 'province' => 'CB'],
            ['istat_code' => '070004', 'name' => 'Bonefro', 'province' => 'CB'],
            ['istat_code' => '070005', 'name' => 'Busso', 'province' => 'CB'],
            ['istat_code' => '070006', 'name' => 'Campobasso', 'province' => 'CB'],
            ['istat_code' => '070007', 'name' => 'Campochiaro', 'province' => 'CB'],
            ['istat_code' => '070008', 'name' => 'Campodipietra', 'province' => 'CB'],
            ['istat_code' => '070009', 'name' => 'Campolieto', 'province' => 'CB'],
            ['istat_code' => '070010', 'name' => 'Campomarino', 'province' => 'CB'],
            ['istat_code' => '070011', 'name' => 'Casacalenda', 'province' => 'CB'],
            ['istat_code' => '070012', 'name' => 'Casalciprano', 'province' => 'CB'],
            ['istat_code' => '070013', 'name' => 'Castelbottaccio', 'province' => 'CB'],
            ['istat_code' => '070014', 'name' => 'Castellino del Biferno', 'province' => 'CB'],
            ['istat_code' => '070015', 'name' => 'Castelmauro', 'province' => 'CB'],
            ['istat_code' => '070016', 'name' => 'Castropignano', 'province' => 'CB'],
            ['istat_code' => '070017', 'name' => 'Cercemaggiore', 'province' => 'CB'],
            ['istat_code' => '070018', 'name' => 'Cercepiccola', 'province' => 'CB'],
            ['istat_code' => '070019', 'name' => 'Civitacampomarano', 'province' => 'CB'],
            ['istat_code' => '070020', 'name' => 'Colle d\'Anchise', 'province' => 'CB'],
            ['istat_code' => '070021', 'name' => 'Colletorto', 'province' => 'CB'],
            ['istat_code' => '070022', 'name' => 'Duronia', 'province' => 'CB'],
            ['istat_code' => '070023', 'name' => 'Ferrazzano', 'province' => 'CB'],
            ['istat_code' => '070024', 'name' => 'Fossalto', 'province' => 'CB'],
            ['istat_code' => '070025', 'name' => 'Gambatesa', 'province' => 'CB'],
            ['istat_code' => '070026', 'name' => 'Gildone', 'province' => 'CB'],
            ['istat_code' => '070027', 'name' => 'Guardialfiera', 'province' => 'CB'],
            ['istat_code' => '070028', 'name' => 'Guardiaregia', 'province' => 'CB'],
            ['istat_code' => '070029', 'name' => 'Guglionesi', 'province' => 'CB'],
            ['istat_code' => '070030', 'name' => 'Jelsi', 'province' => 'CB'],
            ['istat_code' => '070031', 'name' => 'Larino', 'province' => 'CB'],
            ['istat_code' => '070032', 'name' => 'Limosano', 'province' => 'CB'],
            ['istat_code' => '070033', 'name' => 'Lucito', 'province' => 'CB'],
            ['istat_code' => '070034', 'name' => 'Lupara', 'province' => 'CB'],
            ['istat_code' => '070035', 'name' => 'Macchia Valfortore', 'province' => 'CB'],
            ['istat_code' => '070036', 'name' => 'Mafalda', 'province' => 'CB'],
            ['istat_code' => '070037', 'name' => 'Matrice', 'province' => 'CB'],
            ['istat_code' => '070038', 'name' => 'Mirabello Sannitico', 'province' => 'CB'],
            ['istat_code' => '070039', 'name' => 'Molise', 'province' => 'CB'],
            ['istat_code' => '070040', 'name' => 'Monacilioni', 'province' => 'CB'],
            ['istat_code' => '070041', 'name' => 'Montagano', 'province' => 'CB'],
            ['istat_code' => '070042', 'name' => 'Montecilfone', 'province' => 'CB'],
            ['istat_code' => '070043', 'name' => 'Montefalcone nel Sannio', 'province' => 'CB'],
            ['istat_code' => '070044', 'name' => 'Montelongo', 'province' => 'CB'],
            ['istat_code' => '070045', 'name' => 'Montemitro', 'province' => 'CB'],
            ['istat_code' => '070046', 'name' => 'Montenero di Bisaccia', 'province' => 'CB'],
            ['istat_code' => '070047', 'name' => 'Montorio nei Frentani', 'province' => 'CB'],
            ['istat_code' => '070048', 'name' => 'Morrone del Sannio', 'province' => 'CB'],
            ['istat_code' => '070049', 'name' => 'Oratino', 'province' => 'CB'],
            ['istat_code' => '070050', 'name' => 'Palata', 'province' => 'CB'],
            ['istat_code' => '070051', 'name' => 'Petacciato', 'province' => 'CB'],
            ['istat_code' => '070052', 'name' => 'Petrella Tifernina', 'province' => 'CB'],
            ['istat_code' => '070053', 'name' => 'Pietracatella', 'province' => 'CB'],
            ['istat_code' => '070054', 'name' => 'Pietracupa', 'province' => 'CB'],
            ['istat_code' => '070055', 'name' => 'Portocannone', 'province' => 'CB'],
            ['istat_code' => '070056', 'name' => 'Provvidenti', 'province' => 'CB'],
            ['istat_code' => '070057', 'name' => 'Riccia', 'province' => 'CB'],
            ['istat_code' => '070058', 'name' => 'Ripabottoni', 'province' => 'CB'],
            ['istat_code' => '070059', 'name' => 'Ripalimosani', 'province' => 'CB', 'postal_code' => '86025'],
            ['istat_code' => '070060', 'name' => 'Roccavivara', 'province' => 'CB'],
            ['istat_code' => '070061', 'name' => 'Rotello', 'province' => 'CB'],
            ['istat_code' => '070062', 'name' => 'Salcito', 'province' => 'CB'],
            ['istat_code' => '070063', 'name' => 'San Biase', 'province' => 'CB'],
            ['istat_code' => '070064', 'name' => 'San Felice del Molise', 'province' => 'CB'],
            ['istat_code' => '070065', 'name' => 'San Giacomo degli Schiavoni', 'province' => 'CB'],
            ['istat_code' => '070066', 'name' => 'San Giovanni in Galdo', 'province' => 'CB'],
            ['istat_code' => '070067', 'name' => 'San Giuliano del Sannio', 'province' => 'CB'],
            ['istat_code' => '070068', 'name' => 'San Giuliano di Puglia', 'province' => 'CB'],
            ['istat_code' => '070069', 'name' => 'San Martino in Pensilis', 'province' => 'CB'],
            ['istat_code' => '070070', 'name' => 'San Massimo', 'province' => 'CB'],
            ['istat_code' => '070071', 'name' => 'San Polo Matese', 'province' => 'CB'],
            ['istat_code' => '070072', 'name' => 'Santa Croce di Magliano', 'province' => 'CB'],
            ['istat_code' => '070073', 'name' => 'Sant\'Angelo Limosano', 'province' => 'CB'],
            ['istat_code' => '070074', 'name' => 'Sant\'Elia a Pianisi', 'province' => 'CB'],
            ['istat_code' => '070075', 'name' => 'Sepino', 'province' => 'CB'],
            ['istat_code' => '070076', 'name' => 'Spinete', 'province' => 'CB'],
            ['istat_code' => '070077', 'name' => 'Tavenna', 'province' => 'CB'],
            ['istat_code' => '070078', 'name' => 'Termoli', 'province' => 'CB'],
            ['istat_code' => '070079', 'name' => 'Torella del Sannio', 'province' => 'CB'],
            ['istat_code' => '070080', 'name' => 'Toro', 'province' => 'CB'],
            ['istat_code' => '070081', 'name' => 'Trivento', 'province' => 'CB'],
            ['istat_code' => '070082', 'name' => 'Tufara', 'province' => 'CB'],
            ['istat_code' => '070083', 'name' => 'Ururi', 'province' => 'CB'],
            ['istat_code' => '070084', 'name' => 'Vinchiaturo', 'province' => 'CB'],
            ['istat_code' => '094001', 'name' => 'Acquaviva d\'Isernia', 'province' => 'IS'],
            ['istat_code' => '094002', 'name' => 'Agnone', 'province' => 'IS'],
            ['istat_code' => '094003', 'name' => 'Bagnoli del Trigno', 'province' => 'IS'],
            ['istat_code' => '094004', 'name' => 'Belmonte del Sannio', 'province' => 'IS'],
            ['istat_code' => '094005', 'name' => 'Cantalupo nel Sannio', 'province' => 'IS'],
            ['istat_code' => '094006', 'name' => 'Capracotta', 'province' => 'IS'],
            ['istat_code' => '094007', 'name' => 'Carovilli', 'province' => 'IS'],
            ['istat_code' => '094008', 'name' => 'Carpinone', 'province' => 'IS'],
            ['istat_code' => '094009', 'name' => 'Castel del Giudice', 'province' => 'IS'],
            ['istat_code' => '094010', 'name' => 'Castelpetroso', 'province' => 'IS'],
            ['istat_code' => '094011', 'name' => 'Castelpizzuto', 'province' => 'IS'],
            ['istat_code' => '094012', 'name' => 'Castel San Vincenzo', 'province' => 'IS'],
            ['istat_code' => '094013', 'name' => 'Castelverrino', 'province' => 'IS'],
            ['istat_code' => '094014', 'name' => 'Cerro al Volturno', 'province' => 'IS'],
            ['istat_code' => '094015', 'name' => 'Chiauci', 'province' => 'IS'],
            ['istat_code' => '094016', 'name' => 'Civitanova del Sannio', 'province' => 'IS'],
            ['istat_code' => '094017', 'name' => 'Colli a Volturno', 'province' => 'IS'],
            ['istat_code' => '094018', 'name' => 'Conca Casale', 'province' => 'IS'],
            ['istat_code' => '094019', 'name' => 'Filignano', 'province' => 'IS'],
            ['istat_code' => '094020', 'name' => 'Forlì del Sannio', 'province' => 'IS'],
            ['istat_code' => '094021', 'name' => 'Fornelli', 'province' => 'IS'],
            ['istat_code' => '094022', 'name' => 'Frosolone', 'province' => 'IS'],
            ['istat_code' => '094023', 'name' => 'Isernia', 'province' => 'IS'],
            ['istat_code' => '094024', 'name' => 'Longano', 'province' => 'IS'],
            ['istat_code' => '094025', 'name' => 'Macchia d\'Isernia', 'province' => 'IS'],
            ['istat_code' => '094026', 'name' => 'Macchiagodena', 'province' => 'IS'],
            ['istat_code' => '094027', 'name' => 'Miranda', 'province' => 'IS'],
            ['istat_code' => '094028', 'name' => 'Montaquila', 'province' => 'IS'],
            ['istat_code' => '094029', 'name' => 'Montenero Val Cocchiara', 'province' => 'IS'],
            ['istat_code' => '094030', 'name' => 'Monteroduni', 'province' => 'IS'],
            ['istat_code' => '094031', 'name' => 'Pesche', 'province' => 'IS'],
            ['istat_code' => '094032', 'name' => 'Pescolanciano', 'province' => 'IS'],
            ['istat_code' => '094033', 'name' => 'Pescopennataro', 'province' => 'IS'],
            ['istat_code' => '094034', 'name' => 'Pettoranello del Molise', 'province' => 'IS'],
            ['istat_code' => '094035', 'name' => 'Pietrabbondante', 'province' => 'IS'],
            ['istat_code' => '094036', 'name' => 'Pizzone', 'province' => 'IS'],
            ['istat_code' => '094037', 'name' => 'Poggio Sannita', 'province' => 'IS'],
            ['istat_code' => '094038', 'name' => 'Pozzilli', 'province' => 'IS'],
            ['istat_code' => '094039', 'name' => 'Rionero Sannitico', 'province' => 'IS'],
            ['istat_code' => '094040', 'name' => 'Roccamandolfi', 'province' => 'IS'],
            ['istat_code' => '094041', 'name' => 'Roccasicura', 'province' => 'IS'],
            ['istat_code' => '094042', 'name' => 'Rocchetta a Volturno', 'province' => 'IS'],
            ['istat_code' => '094043', 'name' => 'San Pietro Avellana', 'province' => 'IS'],
            ['istat_code' => '094044', 'name' => 'Sant\'Agapito', 'province' => 'IS'],
            ['istat_code' => '094045', 'name' => 'Santa Maria del Molise', 'province' => 'IS'],
            ['istat_code' => '094046', 'name' => 'Sant\'Angelo del Pesco', 'province' => 'IS'],
            ['istat_code' => '094047', 'name' => 'Sant\'Elena Sannita', 'province' => 'IS'],
            ['istat_code' => '094048', 'name' => 'Scapoli', 'province' => 'IS'],
            ['istat_code' => '094049', 'name' => 'Sessano del Molise', 'province' => 'IS'],
            ['istat_code' => '094050', 'name' => 'Sesto Campano', 'province' => 'IS'],
            ['istat_code' => '094051', 'name' => 'Vastogirardi', 'province' => 'IS'],
            ['istat_code' => '094052', 'name' => 'Venafro', 'province' => 'IS'],
        ];
    }
}
