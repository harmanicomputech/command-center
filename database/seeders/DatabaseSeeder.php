<?php

namespace Database\Seeders;

use App\Services\PollingUnitImporter;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * The bundled PU register (LGAs, wards and polling units). The first
     * admin is created at /login with the setup key.
     */
    public function run(PollingUnitImporter $importer): void
    {
        $result = $importer->import(PollingUnitImporter::bundledPath());

        $this->command?->info("Polling units: {$result['created']} added, {$result['updated']} updated.");
    }
}
