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

function getDynamicTables()
{
    $tables = DB::connection('mysql5')->select("
                SELECT TABLE_NAME
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = 'Successfuliptsp'
                AND TABLE_NAME LIKE 'vbSuccessfulCDR_%'
                AND TABLE_NAME NOT LIKE '%_bkp%'
                ORDER BY TABLE_NAME DESC
            ");
    $mapping = [];
    $currentDate = new DateTime(date('Y') . '-11-01');

    foreach ($tables as $item) {
        $tableName = $item->TABLE_NAME;
        $key = $currentDate->format('Y-m');

        $mapping[$key] = $tableName;

        $currentDate->modify('-1 month');
    }

    return $mapping;

}

function filterRange($mapping, $start, $end)
{
    $result = [];

    foreach ($mapping as $key => $value) {
        if ($key <= $start && $key >= $end) {
            $result[$key] = $value;
        }
    }

    return $result;
}

function filterRangeWithNext($mapping, $start, $end)
{
    $result = [];
    $foundEnd = false;

    foreach ($mapping as $key => $value) {

        if ($key <= $start && $key >= $end) {
            $result[$key] = "Successfuliptsp." . $value;

            if ($key == $end) {
                $foundEnd = true;
                continue;
            }
        }

        if ($foundEnd) {
            $result[$key] = "Successfuliptsp." . $value;
            break;
        }
    }

    return $result;
}
