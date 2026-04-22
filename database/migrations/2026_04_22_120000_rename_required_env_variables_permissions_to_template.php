<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('permission.table_names.permissions', 'permissions');

        $rows = DB::table($table)->get(['id', 'name']);

        foreach ($rows as $row) {
            $name = $row->name;
            $new = str_replace('RequiredEnvVariables', 'TemplateEnvVariables', $name);
            $new = str_replace('required::env::variables', 'template::env::variables', $new);
            if ($new !== $name) {
                DB::table($table)->where('id', $row->id)->update(['name' => $new]);
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        $table = config('permission.table_names.permissions', 'permissions');

        $rows = DB::table($table)->get(['id', 'name']);

        foreach ($rows as $row) {
            $name = $row->name;
            $new = str_replace('TemplateEnvVariables', 'RequiredEnvVariables', $name);
            $new = str_replace('template::env::variables', 'required::env::variables', $new);
            if ($new !== $name) {
                DB::table($table)->where('id', $row->id)->update(['name' => $new]);
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
