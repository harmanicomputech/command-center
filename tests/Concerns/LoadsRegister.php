<?php

namespace Tests\Concerns;

use App\Models\Lga;
use App\Models\Ward;
use App\Services\PollingUnitImporter;
use Illuminate\Support\Str;

/**
 * A small register for tests: two LGAs with two wards each (fake names and
 * codes). Tests that need the real bundled register import it themselves.
 */
trait LoadsRegister
{
    protected function loadSmallRegister(): void
    {
        $csv = "code,name,ward,lga,registered_voters\n"
            ."EB/212/00001/001,Test PU 1,Abakaliki Ward 01,Abakaliki,500\n"
            ."EB/212/00001/002,Test PU 2,Abakaliki Ward 01,Abakaliki,300\n"
            ."EB/212/00002/001,Test PU 3,Abakaliki Ward 02,Abakaliki,400\n"
            ."EB/219/00003/001,Test PU 4,Izzi Ward 01,Izzi,700\n"
            ."EB/219/00004/001,Test PU 5,Izzi Ward 02,Izzi,600\n";

        $path = tempnam(sys_get_temp_dir(), 'register');
        file_put_contents($path, $csv);
        app(PollingUnitImporter::class)->import($path);
        unlink($path);
    }

    protected function lga(string $name): Lga
    {
        return Lga::query()->where('slug', Str::slug($name))->firstOrFail();
    }

    protected function ward(string $name): Ward
    {
        return Ward::query()->where('name', $name)->firstOrFail();
    }
}
