<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IptspCallDurationController extends Controller
{
    public function getCallDuration(Request $request)
    {
        try {
            $getAllTables = getCdrTableByMonth();


            $currentMonthTableName = "Successfuliptsp." . $getAllTables[0]->TABLE_NAME;
            $tables = [
            "Successfuliptsp." . $getAllTables[1]->TABLE_NAME,
            "Successfuliptsp." . $getAllTables[2]->TABLE_NAME
        ];

            $todayDurations = DB::connection('mysql5')->selectOne("SELECT 
                ROUND((SUM(orgBilledDuration) + SUM(terBilledDuration)) / 60) AS total_minutes
            FROM $currentMonthTableName
            WHERE INET_NTOA(orgIPAddress) = '59.152.98.66'
            OR INET_NTOA(terIPAddress) = '59.152.98.66'
            AND DATE(connectTime) = CURDATE()");



            $totalMinutes = 0;

            foreach ($tables as $table) {
                $result = DB::connection('mysql5')->selectOne("SELECT
                ROUND((SUM(orgBilledDuration) + SUM(terBilledDuration)) / 60 / DAY(LAST_DAY(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)))) AS avg_daily_minutes
                FROM $table
                WHERE (INET_NTOA(orgIPAddress) = '59.152.98.66' OR INET_NTOA(terIPAddress) = '59.152.98.66')
                AND FROM_UNIXTIME(connectTime / 1000) >= DATE_SUB(DATE_SUB(CURDATE(), INTERVAL DAYOFMONTH(CURDATE())-1 DAY), INTERVAL 1 MONTH)
                AND FROM_UNIXTIME(connectTime / 1000) < DATE_SUB(CURDATE(), INTERVAL DAYOFMONTH(CURDATE())-1 DAY)");

                $totalMinutes += $result->avg_daily_minutes ?? 0;
            }
            return response()->json([
                'status' => true,
                'today_minutes' => $todayDurations->total_minutes ?? 0,
                'last_two_months_total_minutes' => $totalMinutes,
                'message' => "Call duration fetched successfully"
                ]);
        } catch (\Execption $e) {
            return response()->json([
               'status' => true,
               'message' => $e->getMessage()
               ]);
        }
    }


    public function getMonthlyMinutes(Request $request)
    {
        try {
            $getAllTables = getCdrTableByMonth();
            $merged = [];

            $tables = [
            "Successfuliptsp." . $getAllTables[0]->TABLE_NAME,
            "Successfuliptsp." . $getAllTables[1]->TABLE_NAME
        ];

            foreach ($tables as $table) {

                $rows = DB::connection('mysql5')->select("SELECT 
                DATE(FROM_UNIXTIME(connectTime / 1000)) AS call_date,
                
                -- Iptsp to Iptsp
                IFNULL(ROUND(SUM(CASE 
                    WHEN INET_NTOA(terIPAddress) = '59.152.98.66' 
                    AND INET_NTOA(orgIPAddress) = '59.152.98.66' 
                    THEN terBilledDuration END)/60,0), 0) AS iptsp_to_iptsp,

                --  IPTSP to OrbiTalk
                IFNULL(ROUND(SUM(CASE 
                    WHEN INET_NTOA(orgIPAddress) = '59.152.98.66' 
                    AND INET_NTOA(terIPAddress) IN ('59.152.98.70','202.59.208.119','119.40.82.242') 
                    THEN terBilledDuration END)/60,0), 0) AS orbitalk_to_iptsp,

                -- GSM to Iptsp
                IFNULL(ROUND(SUM(CASE 
                    WHEN INET_NTOA(orgIPAddress) IN ('59.152.98.70','10.246.29.74','172.20.15.106') 
                    AND INET_NTOA(terIPAddress) = '59.152.98.66' 
                    THEN terBilledDuration END)/60,0), 0) AS gsm_to_iptsp
            FROM $table
            WHERE FROM_UNIXTIME(connectTime / 1000) >= CURDATE() - INTERVAL 30 DAY
            GROUP BY call_date
            ORDER BY call_date DESC");

                foreach ($rows as $row) {
                    $date = $row->call_date;

                    if (!isset($merged[$date])) {
                        // initialize when date not exists
                        $merged[$date] = [
                            "call_date" => $date,
                            "iptsp_to_iptsp" => 0,
                            "orbitalk_to_iptsp" => 0,
                            "gsm_to_iptsp" => 0,
                        ];
                    }

                    // add values from table
                    $merged[$date]["iptsp_to_iptsp"]     += floatval($row->iptsp_to_iptsp);
                    $merged[$date]["orbitalk_to_iptsp"] += floatval($row->orbitalk_to_iptsp);
                    $merged[$date]["gsm_to_iptsp"]        += floatval($row->gsm_to_iptsp);
                }



            }




            $result = array_values($merged);

            return response()->json([
                'status' => true,
                'data' => $result,
                'message' => "success"
            ]);
        } catch (\Execption $e) {
            return response()->json([
               'status' => true,
               'message' => $e->getMessage()
               ]);
        }
    }



    public function getMonthlyCallVolume(Request $request)
    {
        try {
            $getAllTables = getCdrTableByMonth();
            $merged = [];

            $tables = [
            "Successfuliptsp." . $getAllTables[0]->TABLE_NAME,
            "Successfuliptsp." . $getAllTables[1]->TABLE_NAME
        ];

            foreach ($tables as $table) {

                $rows = DB::connection('mysql5')->select("SELECT 
                DATE(FROM_UNIXTIME(connectTime / 1000)) AS call_date,
                
                -- IPTSP to IPTSP (count)
    COUNT(CASE 
        WHEN INET_NTOA(terIPAddress) = '59.152.98.66' 
        AND INET_NTOA(orgIPAddress) = '59.152.98.66' 
        THEN 1 END) AS iptsp_to_iptsp,

    -- IPTSP to OrbiTalk (count)
    COUNT(CASE 
        WHEN INET_NTOA(orgIPAddress) = '59.152.98.66' 
        AND INET_NTOA(terIPAddress) IN ('59.152.98.70','202.59.208.119','119.40.82.242') 
        THEN 1 END) AS orbitalk_to_iptsp,

    -- GSM to IPTSP (count)
    COUNT(CASE 
        WHEN INET_NTOA(orgIPAddress) IN ('59.152.98.70','10.246.29.74','172.20.15.106') 
        AND INET_NTOA(terIPAddress) = '59.152.98.66' 
        THEN 1 END) AS gsm_to_iptsp
        
            FROM $table
            WHERE FROM_UNIXTIME(connectTime / 1000) >= CURDATE() - INTERVAL 30 DAY
            GROUP BY call_date
            ORDER BY call_date DESC");

                foreach ($rows as $row) {
                    $date = $row->call_date;

                    if (!isset($merged[$date])) {
                        // initialize when date not exists
                        $merged[$date] = [
                            "call_date" => $date,
                            "iptsp_to_iptsp" => 0,
                            "orbitalk_to_iptsp" => 0,
                            "gsm_to_iptsp" => 0,
                        ];
                    }

                    // add values from table
                    $merged[$date]["iptsp_to_iptsp"]     += floatval($row->iptsp_to_iptsp);
                    $merged[$date]["orbitalk_to_iptsp"] += floatval($row->orbitalk_to_iptsp);
                    $merged[$date]["gsm_to_iptsp"]        += floatval($row->gsm_to_iptsp);
                }



            }




            $result = array_values($merged);

            return response()->json([
                'status' => true,
                'data' => $result,
                'message' => "success"
            ]);
        } catch (\Execption $e) {
            return response()->json([
               'status' => true,
               'message' => $e->getMessage()
               ]);
        }
    }

}
