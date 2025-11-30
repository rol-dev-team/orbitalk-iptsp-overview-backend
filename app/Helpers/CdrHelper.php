<?php

use Illuminate\Support\Facades\DB;

if (!function_exists('getCdrTableByMonth')) {

    function getCdrTableByMonth()
    {
        $tableInfo = DB::connection('mysql5')->select("
                SELECT TABLE_NAME
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = 'Successfuliptsp'
                AND TABLE_NAME LIKE 'vbSuccessfulCDR_%'
                AND TABLE_NAME NOT LIKE '%_bkp%'
                ORDER BY TABLE_NAME DESC
            ");


        if (!$tableInfo) {
            return null;
        }

        return $tableInfo;
    }
}
