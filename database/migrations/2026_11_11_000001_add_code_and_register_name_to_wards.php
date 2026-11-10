<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wards get a stable code (the first 8 digits of their polling units'
 * INEC codes: EB/212/02633/… → 21202633) and keep the register's label
 * ("Abakaliki Ward 01") apart from their name, so a ward can be given its
 * real name (by an admin, or by INEC's register) without losing the
 * records attached to it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wards', function (Blueprint $table) {
            $table->string('code', 8)->nullable()->index();
            $table->string('register_name')->nullable();
        });

        DB::table('wards')->update(['register_name' => DB::raw('name')]);

        foreach (DB::table('polling_units')->selectRaw('ward_id, min(code) as code')->groupBy('ward_id')->get() as $row) {
            if (strlen((string) $row->code) === 11) {
                DB::table('wards')->where('id', $row->ward_id)->update(['code' => substr($row->code, 0, 8)]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('wards', function (Blueprint $table) {
            $table->dropIndex(['code']);
            $table->dropColumn(['code', 'register_name']);
        });
    }
};
