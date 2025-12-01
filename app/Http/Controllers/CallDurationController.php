<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CallDurationController extends Controller
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
            COALESCE(
                (SELECT ROUND(SUM(orgBilledDuration) / 60)
                FROM $currentMonthTableName
                WHERE INET_NTOA(orgIPAddress) = '59.152.98.70'
                    AND DATE(FROM_UNIXTIME(connectTime / 1000)) = CURDATE()
                    )
            ,0)
            + COALESCE(
                (SELECT ROUND(SUM(terBilledDuration) / 60)
                FROM $currentMonthTableName
                WHERE INET_NTOA(terIPAddress) = '59.152.98.70'
                    AND DATE(FROM_UNIXTIME(connectTime / 1000)) = CURDATE()
                    )
            ,0) AS total_minutes");



            $totalMinutes = 0;

            foreach ($tables as $table) {
                $result = DB::connection('mysql5')->selectOne("SELECT
                    (
                        COALESCE(
                        (SELECT SUM(orgBilledDuration) / 60
                        FROM $table
                        WHERE INET_NTOA(orgIPAddress) = '59.152.98.70'
                            AND FROM_UNIXTIME(connectTime / 1000) >= DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01')
                            AND FROM_UNIXTIME(connectTime / 1000) < DATE_FORMAT(CURDATE(), '%Y-%m-01'))
                        ,0)
                    + COALESCE(
                        (SELECT SUM(terBilledDuration) / 60
                        FROM $table
                        WHERE INET_NTOA(terIPAddress) = '59.152.98.70'
                            AND FROM_UNIXTIME(connectTime / 1000) >= DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01')
                            AND FROM_UNIXTIME(connectTime / 1000) < DATE_FORMAT(CURDATE(), '%Y-%m-01'))
                        ,0)
                    ) 
                    / DAY(LAST_DAY(CURDATE() - INTERVAL 1 MONTH)) AS avg_daily_minutes;
                    ");

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
                
                -- OrbiTalk to OrbiTalk
                IFNULL(ROUND(SUM(CASE 
                    WHEN INET_NTOA(terIPAddress) = '59.152.98.70' 
                    AND INET_NTOA(orgIPAddress) = '59.152.98.70' 
                    THEN terBilledDuration END)/60,0), 0) AS orbitalk_to_orbitalk,
                
                -- Incoming GSM to OrbiTalk
                IFNULL(ROUND(SUM(CASE 
                    WHEN INET_NTOA(orgIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106') 
                    AND INET_NTOA(terIPAddress) = '59.152.98.70' 
                    THEN terBilledDuration END)/60,0), 0) AS incoming_gsm_to_orbitalk,
                
                -- OrbiTalk to IPTSP
                IFNULL(ROUND(SUM(CASE 
                    WHEN INET_NTOA(orgIPAddress) = '59.152.98.70' 
                    AND INET_NTOA(terIPAddress) IN ('59.152.98.66','202.59.208.119','119.40.82.242') 
                    THEN terBilledDuration END)/60,0), 0) AS orbitalk_to_iptsp,


                
                -- Outgoing OrbiTalk to GSM
                IFNULL(ROUND(SUM(CASE 
                    WHEN INET_NTOA(terIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106') 
                    AND INET_NTOA(orgIPAddress) = '59.152.98.70' 
                    THEN orgBilledDuration END)/60,0), 0) AS outgoing_orbitalk_to_gsm
                


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
                            "orbitalk_to_orbitalk"     => 0,
                            "incoming_gsm_to_orbitalk" => 0,
                            "orbitalk_to_iptsp"        => 0,
                            "outgoing_orbitalk_to_gsm" => 0
                        ];
                    }

                    // add values from table
                    $merged[$date]["orbitalk_to_orbitalk"]     += floatval($row->orbitalk_to_orbitalk);
                    $merged[$date]["incoming_gsm_to_orbitalk"] += floatval($row->incoming_gsm_to_orbitalk);
                    $merged[$date]["orbitalk_to_iptsp"]        += floatval($row->orbitalk_to_iptsp);
                    $merged[$date]["outgoing_orbitalk_to_gsm"] += floatval($row->outgoing_orbitalk_to_gsm);
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

                        -- OrbiTalk to OrbiTalk (count rows)
                        COUNT(CASE 
                            WHEN INET_NTOA(terIPAddress) = '59.152.98.70'
                            AND INET_NTOA(orgIPAddress) = '59.152.98.70'
                            THEN 1 END
                        ) AS orbitalk_to_orbitalk,

                        -- Incoming GSM to OrbiTalk (count rows)
                        COUNT(CASE 
                            WHEN INET_NTOA(orgIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
                            AND INET_NTOA(terIPAddress) = '59.152.98.70'
                            THEN 1 END
                        ) AS incoming_gsm_to_orbitalk,

                        -- OrbiTalk to IPTSP (count rows)
                        COUNT(CASE 
                            WHEN INET_NTOA(orgIPAddress) = '59.152.98.70'
                            AND INET_NTOA(terIPAddress) IN ('59.152.98.66','202.59.208.119','119.40.82.242')
                            THEN 1 END
                        ) AS orbitalk_to_iptsp,

                        -- Outgoing OrbiTalk to GSM (count rows)
                        COUNT(CASE 
                            WHEN INET_NTOA(terIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
                            AND INET_NTOA(orgIPAddress) = '59.152.98.70'
                            THEN 1 END
                        ) AS outgoing_orbitalk_to_gsm

                    FROM Successfuliptsp.vbSuccessfulCDR_680
                    WHERE FROM_UNIXTIME(connectTime / 1000) >= CURDATE() - INTERVAL 30 DAY
                    GROUP BY call_date
                    ORDER BY call_date DESC");

                foreach ($rows as $row) {
                    $date = $row->call_date;

                    if (!isset($merged[$date])) {
                        // initialize when date not exists
                        $merged[$date] = [
                            "call_date" => $date,
                            "orbitalk_to_orbitalk"     => 0,
                            "incoming_gsm_to_orbitalk" => 0,
                            "orbitalk_to_iptsp"        => 0,
                            "outgoing_orbitalk_to_gsm" => 0,
                        ];
                    }

                    // add values from table
                    $merged[$date]["orbitalk_to_orbitalk"]     += floatval($row->orbitalk_to_orbitalk);
                    $merged[$date]["incoming_gsm_to_orbitalk"] += floatval($row->incoming_gsm_to_orbitalk);
                    $merged[$date]["orbitalk_to_iptsp"]        += floatval($row->orbitalk_to_iptsp);
                    $merged[$date]["outgoing_orbitalk_to_gsm"] += floatval($row->outgoing_orbitalk_to_gsm);
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
