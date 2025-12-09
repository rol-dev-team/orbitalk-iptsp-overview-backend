<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrbiTalkCallDurationController extends Controller
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

    // public function getCallDurationReport(Request $request)
    // {
    //     try {
    //         $mapping = getDynamicTables();
    //         $start = "2025-10";
    //         $end   = "2025-10";

    //         $tableNamesWithMonth = filterRangeWithNext($mapping, $start, $end);
    //         $tables = array_values($tableNamesWithMonth);
    //         // return $tables;

    //         $startDate = $request->start_date ?? '2025-10-01';
    //         $endDate   = $request->end_date ?? '2025-10-31';

    //         $filterOrbitalkToOrbitalk     = $request->orbitalk_to_orbitalk ?? null;
    //         $filterIncomingGsmToOrbitalk  = $request->incoming_gsm_to_orbitalk ?? 'orbitalk_to_orbitalk';
    //         $filterOrbitalkToIptsp        = $request->orbitalk_to_iptsp ?? null;
    //         $filterOutgoingOrbitalkToGsm  = $request->outgoing_orbitalk_to_gsm ?? null;

    //         $merged = [];
    //         $totalIncoming = 0;
    //         $totalOutgoing = 0;
    //         $totalDuration = 0;



    //         $where = "";

    //         // Call Type filters
    //         if ($filterOrbitalkToOrbitalk) {
    //             $where .= " AND (INET_NTOA(terIPAddress) = '59.152.98.70' AND INET_NTOA(orgIPAddress) = '59.152.98.70')";
    //         }

    //         if ($filterIncomingGsmToOrbitalk) {
    //             $where .= " AND (INET_NTOA(orgIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
    //             AND INET_NTOA(terIPAddress) = '59.152.98.70')";
    //         }

    //         if ($filterOrbitalkToIptsp) {
    //             $where .= " AND (INET_NTOA(orgIPAddress) = '59.152.98.70'
    //             AND INET_NTOA(terIPAddress) IN ('59.152.98.66','202.59.208.119','119.40.82.242'))";
    //         }

    //         if ($filterOutgoingOrbitalkToGsm) {
    //             $where .= " AND (INET_NTOA(terIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
    //             AND INET_NTOA(orgIPAddress) = '59.152.98.70')";
    //         }



    //         foreach ($tables as $table) {

    //             $rows = DB::connection('mysql5')->select("SELECT
    //                     FROM_UNIXTIME(connectTime / 1000) AS connect_time,
    //                     FROM_UNIXTIME(disconnectTime / 1000) AS disconnect_time,
    //                     INET_NTOA(terIPAddress) AS terIPAddress,
    //                     INET_NTOA(orgIPAddress) AS orgIPAddress,
    //                     callingStationID,
    //                     calledStationID,
    //                     terBilledDuration / 60 AS duration_minutes
    //                 FROM $table
    //                 WHERE FROM_UNIXTIME(connectTime / 1000) BETWEEN '$startDate 00:00:00' AND '$endDate 23:59:59'
    //                 $where
    //             ");

    //             $merged = array_merge($merged, $rows);

    //             // Summary calculation
    //             $summary = DB::connection('mysql5')->selectOne("SELECT
    //             SUM(CASE WHEN INET_NTOA(terIPAddress) = '59.152.98.70' THEN 1 ELSE 0 END) AS total_incoming,
    //             SUM(CASE WHEN INET_NTOA(orgIPAddress) = '59.152.98.70' THEN 1 ELSE 0 END) AS total_outgoing,
    //             ROUND(SUM(terBilledDuration / 60)) AS total_duration
    //             FROM $table WHERE FROM_UNIXTIME(connectTime / 1000) BETWEEN '$startDate 00:00:00' AND '$endDate 23:59:59' $where ");

    //             $totalIncoming += $summary->total_incoming ?? 0;
    //             $totalOutgoing += $summary->total_outgoing ?? 0;
    //             $totalDuration += $summary->total_duration ?? 0;
    //         }

    //         return response()->json([
    //             'status' => true,
    //             'summary' => [ 'total_incoming' => $totalIncoming,
    //              'total_outgoing' => $totalOutgoing,
    //               'total_duration' => $totalDuration ],
    //               'data' => $merged,
    //             'message' => "success"
    //         ]);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //         'status' => false,
    //         'message' => $e->getMessage()
    //         ]);
    //     }
    // }
    public function getCallDurationReport(Request $request)
    {
        try {
            $mapping = getDynamicTables();
            $start = "2025-10"; // Assume these dates are dynamically derived based on request if necessary, or hardcoded for example
            $end   = "2025-10";

            $tableNamesWithMonth = filterRangeWithNext($mapping, $start, $end);
            $tables = array_values($tableNamesWithMonth);

            $startDate = $request->start_date ?? '2025-10-01';
            $endDate   = $request->end_date ?? '2025-10-31';

            $filterOrbitalkToOrbitalk     = $request->orbitalk_to_orbitalk ?? null;
            $filterIncomingGsmToOrbitalk  = $request->incoming_gsm_to_orbitalk ?? 'orbitalk_to_orbitalk'; // Default value might cause issue if not managed
            $filterOrbitalkToIptsp        = $request->orbitalk_to_iptsp ?? null;
            $filterOutgoingOrbitalkToGsm  = $request->outgoing_orbitalk_to_gsm ?? null;

            $merged = [];
            $totalIncoming = 0;
            $totalOutgoing = 0;
            $totalDuration = 0;

            $whereConditions = [];
            $summaryIncomingCase = [];
            $summaryOutgoingCase = [];

            // Base IP for your internal system, used for Orbitalk identification
            $baseIP = '59.152.98.70';
            $gsmIPs = ['10.246.29.66','10.246.29.74','172.20.15.106'];
            $iptspIPs = ['59.152.98.66','202.59.208.119','119.40.82.242'];
            $gsmIPsString = "'" . implode("','", $gsmIPs) . "'";
            $iptspIPsString = "'" . implode("','", $iptspIPs) . "'";


            // --- Call Type Filter Logic (Using OR) ---

            // 1. Orbitalk to Orbitalk (Internal Traffic)
            if ($filterOrbitalkToOrbitalk) {
                $condition = "(INET_NTOA(terIPAddress) = '$baseIP' AND INET_NTOA(orgIPAddress) = '$baseIP')";
                $whereConditions[] = $condition;
                // For Summary: This call is both incoming and outgoing from the perspective of $baseIP, but typically classified as internal.
                // We can count it as BOTH for accurate reporting based on the base IP logic (assuming you want to track traffic passing through $baseIP).
                $summaryIncomingCase[] = $condition;
                $summaryOutgoingCase[] = $condition;
            }

            // 2. Incoming GSM to Orbitalk (Incoming)
            if ($filterIncomingGsmToOrbitalk) {
                $condition = "(INET_NTOA(orgIPAddress) IN ($gsmIPsString) AND INET_NTOA(terIPAddress) = '$baseIP')";
                $whereConditions[] = $condition;
                // For Summary: This is definitely Incoming to the $baseIP.
                $summaryIncomingCase[] = $condition;
            }

            // 3. Orbitalk to IPTsp (Outgoing)
            if ($filterOrbitalkToIptsp) {
                $condition = "(INET_NTOA(orgIPAddress) = '$baseIP' AND INET_NTOA(terIPAddress) IN ($iptspIPsString))";
                $whereConditions[] = $condition;
                // For Summary: This is definitely Outgoing from the $baseIP.
                $summaryOutgoingCase[] = $condition;
            }

            // 4. Outgoing Orbitalk to Gsm (Outgoing via Gateway)
            if ($filterOutgoingOrbitalkToGsm) {
                $condition = "(INET_NTOA(terIPAddress) IN ($gsmIPsString) AND INET_NTOA(orgIPAddress) = '$baseIP')";
                $whereConditions[] = $condition;
                // For Summary: This is definitely Outgoing from the $baseIP.
                $summaryOutgoingCase[] = $condition;
            }


            $where = "";
            if (!empty($whereConditions)) {
                // Join all conditions with OR and wrap in a final AND clause
                $where = " AND (" . implode(' OR ', $whereConditions) . ")";
            }

            // --- Summary Logic Creation ---

            $summaryIncomingSQL = "0";
            if (!empty($summaryIncomingCase)) {
                // If any incoming-related call type is selected, check if the record matches any of those types.
                $summaryIncomingSQL = "(CASE WHEN (" . implode(' OR ', $summaryIncomingCase) . ") THEN 1 ELSE 0 END)";
            }

            $summaryOutgoingSQL = "0";
            if (!empty($summaryOutgoingCase)) {
                // If any outgoing-related call type is selected, check if the record matches any of those types.
                $summaryOutgoingSQL = "(CASE WHEN (" . implode(' OR ', $summaryOutgoingCase) . ") THEN 1 ELSE 0 END)";
            }


            // --- Loop through Tables ---

            foreach ($tables as $table) {

                // 1. Details Query (Unchanged, uses $where)
                $rows = DB::connection('mysql5')->select("SELECT
                    FROM_UNIXTIME(connectTime / 1000) AS connect_time,
                    FROM_UNIXTIME(disconnectTime / 1000) AS disconnect_time,
                    INET_NTOA(terIPAddress) AS terIPAddress,
                    INET_NTOA(orgIPAddress) AS orgIPAddress,
                    callingStationID,
                    calledStationID,
                    terBilledDuration / 60 AS duration_minutes
                FROM $table
                WHERE FROM_UNIXTIME(connectTime / 1000) BETWEEN '$startDate 00:00:00' AND '$endDate 23:59:59'
                $where
            ");

                $merged = array_merge($merged, $rows);

                // 2. Summary Query (NOW uses the same call-type logic derived from filters)
                $summary = DB::connection('mysql5')->selectOne("SELECT 
            SUM($summaryIncomingSQL) AS total_incoming, 
            SUM($summaryOutgoingSQL) AS total_outgoing, 
            ROUND(SUM(terBilledDuration / 60)) AS total_duration 
            FROM $table 
            WHERE FROM_UNIXTIME(connectTime / 1000) BETWEEN '$startDate 00:00:00' AND '$endDate 23:59:59' $where ");
                // The $where clause ensures we only count calls already included in the details report.

                $totalIncoming += $summary->total_incoming ?? 0;
                $totalOutgoing += $summary->total_outgoing ?? 0;
                $totalDuration += $summary->total_duration ?? 0;
            }

            return response()->json([
                'status' => true,
                'summary' => [ 'total_incoming' => $totalIncoming,
                 'total_outgoing' => $totalOutgoing,
                  'total_duration' => $totalDuration ],
                  'data' => $merged,
                'message' => "success"
            ]);

        } catch (\Exception $e) {
            return response()->json([
            'status' => false,
            'message' => $e->getMessage()
            ]);
        }
    }

}
