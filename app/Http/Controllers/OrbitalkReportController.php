<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use DateTime;
use Illuminate\Support\Facades\Log;


class OrbitalkReportController extends Controller
{


    public function paymentReport(Request $request)
    {
        try {

            
            $startDate = $request->start_date
                ? $request->start_date . " 00:00:00"
                : date("Y-m-01 00:00:00");

            $endDate = $request->end_date
                ? $request->end_date . " 23:59:59"
                : date("Y-m-d 23:59:59");

            $startMonth = date("Y_m", strtotime($startDate));
            $endMonth   = date("Y_m", strtotime($endDate));

            $tableRows = DB::connection('mysql5')->select("
                SELECT TABLE_NAME
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = 'iTelBillingiptsp'
                  AND TABLE_NAME LIKE 'vbPayment_%'
            ");

            $selectedTables = [];
            foreach ($tableRows as $t) {
                $month = str_replace("vbPayment_", "", $t->TABLE_NAME);
                if ($month >= $startMonth && $month <= $endMonth) {
                    $selectedTables[] = $t->TABLE_NAME;
                }
            }

            if (empty($selectedTables)) {
                return response()->json([
                    "data" => [],
                    "tables_used" => [],
                    "message" => "No tables found for date range"
                ]);
            }

            
            $unionParts = [];
            $allBindings = [];

            foreach ($selectedTables as $table) {

               
                $where = "
                    FROM_UNIXTIME(p.pyDate/1000) BETWEEN ? AND ?
                    AND p.pyAmount > 0
                    
                    AND c.clCustomerID LIKE '8801%'
                    AND p.pyPaymentGatewayType = 19
                ";

                
                $allBindings[] = $startDate;
                $allBindings[] = $endDate;

                if (!empty($request->pyUserName)) {
                    $where .= " AND p.pyUserName LIKE ? ";
                    
                    $allBindings[] = "%" . $request->pyUserName . "%";
                }

                if (!empty($request->clCustomerID)) {
                    $where .= " AND c.clCustomerID LIKE ? ";
                    
                    $allBindings[] = "%" . $request->clCustomerID . "%";
                }

                $unionParts[] = "
                    SELECT
                        FROM_UNIXTIME(p.pyDate/1000, '%Y-%m-%d %H:%i:%s') AS Payment_Date,
                        p.pyAmount AS Amount,
                        p.pyUserName,
                        c.clCustomerID,
                        c.clParentAccountID,
                        d.cdBillingName,
                        d.cdCompanyName
                    FROM iTelBillingiptsp.$table p
                    JOIN iTelBillingiptsp.vbClient c ON p.pyAccountID = c.clAccountID
                    JOIN iTelBillingiptsp.vbClientDetails d ON c.clAccountID = d.cdClientAccountID
                    WHERE $where
                ";
            }

            $unionSql = implode(" UNION ALL ", $unionParts);

            
            $page = $request->page ?? 1;
            $perPage = 25;
            $offset = ($page - 1) * $perPage;

            $sqlPaginated = $unionSql . " LIMIT $perPage OFFSET $offset";

            
            $countSql = "SELECT COUNT(*) AS total FROM ($unionSql) AS t";

            
            $data = DB::connection('mysql5')->select($sqlPaginated, $allBindings);
            $total = DB::connection('mysql5')->selectOne($countSql, $allBindings)->total;

            return response()->json([
                "tables_used" => $selectedTables,
                "data"        => $data,
                "currentPage" => (int)$page,
                "perPage"     => $perPage,
                "total"       => $total,
                "lastPage"    => ceil($total / $perPage)
            ]);

        } catch (\Exception $e) {
            
            Log::error("Payment Report Error: " . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                "error" => true,
                "message" => $e->getMessage()
            ], 500);
        }
    }



    // public function grossProfitDayWise(Request $request)
    // {
    //     try {
    //         $tables = DB::connection('mysql5')->select("
    //             SELECT TABLE_NAME
    //             FROM INFORMATION_SCHEMA.TABLES
    //             WHERE TABLE_SCHEMA = 'Successfuliptsp'
    //             AND TABLE_NAME LIKE 'vbSuccessfulCDR_%'
    //             AND TABLE_NAME NOT LIKE '%_bkp%'
    //             ORDER BY TABLE_NAME DESC
                
    //         ");

    //         // LIMIT 3

    //         if (count($tables) < 3) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => "Not enough CDR tables found"
    //             ], 404);
    //         }

    //         $allTables = [
    //             "Successfuliptsp." . $tables[0]->TABLE_NAME,
    //             "Successfuliptsp." . $tables[1]->TABLE_NAME,
    //             // "Successfuliptsp." . $tables[2]->TABLE_NAME,
    //         ];

    //         $buildUnion = function($tableArray) {
    //             return implode(" UNION ALL ", array_map(fn($t) => "SELECT * FROM $t", $tableArray));
    //         };

    //         $unionAll = "(" . $buildUnion($allTables) . ") AS cdr";

           
    //         $incoming = DB::connection('mysql5')->selectOne("
    //             SELECT (ROUND((SUM(terBilledDuration)/60) * 0.1, 0) - (ROUND((SUM(terBilledDuration)/60) * 0.1, 0) * 0.03)) AS incoming_bill_this_month
    //             FROM $unionAll
    //             WHERE INET_NTOA(orgIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
    //             AND INET_NTOA(terIPAddress) = '59.152.98.70'
    //             AND FROM_UNIXTIME(connectTime/1000)
    //                     BETWEEN LAST_DAY(CURDATE() - INTERVAL 1 MONTH) + INTERVAL 1 DAY
    //                     AND LAST_DAY(CURDATE())
    //         ");

    //         $outgoing = DB::connection('mysql5')->selectOne("
    //             SELECT 
    //                 ROUND(SUM(orgBilledDuration)/60, 0) AS outgoing_minutes_this_month,
    //                 (ROUND((SUM(orgBilledDuration)/60) * 0.35, 0) - (ROUND((SUM(orgBilledDuration)/60) * 0.35, 0) * 0.03 + ROUND(((SUM(orgBilledDuration)/60) * 0.14), 0)))
    //                     AS outgoing_bill_this_month
    //             FROM $unionAll
    //             WHERE INET_NTOA(orgIPAddress) = '59.152.98.70'
    //             AND INET_NTOA(terIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
    //             AND FROM_UNIXTIME(connectTime/1000)
    //                     BETWEEN LAST_DAY(CURDATE() - INTERVAL 1 MONTH) + INTERVAL 1 DAY
    //                     AND LAST_DAY(CURDATE())
    //         ");

    //         $grossProfit = intval($incoming->incoming_bill_this_month ?? 0)
    //                     + intval($outgoing->outgoing_bill_this_month ?? 0);

    //         $incomingLast = DB::connection('mysql5')->selectOne("
    //             SELECT (ROUND((SUM(terBilledDuration)/60) * 0.1, 0) - (ROUND((SUM(terBilledDuration)/60) * 0.1, 0) * 0.03)) AS incoming_bill_last_month
    //             FROM $unionAll
    //             WHERE INET_NTOA(orgIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
    //             AND INET_NTOA(terIPAddress) = '59.152.98.70'
    //             AND FROM_UNIXTIME(connectTime/1000)
    //                     BETWEEN LAST_DAY(CURDATE() - INTERVAL 2 MONTH) + INTERVAL 1 DAY
    //                     AND LAST_DAY(CURDATE() - INTERVAL 1 MONTH)
    //         ");

    //         $outgoingLast = DB::connection('mysql5')->selectOne("
    //             SELECT 
    //                 ROUND(SUM(orgBilledDuration)/60, 0) AS outgoing_minutes_last_month,
    //                 (ROUND((SUM(orgBilledDuration)/60) * 0.35, 0) - (ROUND((SUM(orgBilledDuration)/60) * 0.35, 0) * 0.03 + ROUND(((SUM(orgBilledDuration)/60) * 0.14), 0)))
    //                     AS outgoing_bill_last_month
    //             FROM $unionAll
    //             WHERE INET_NTOA(orgIPAddress) = '59.152.98.70'
    //             AND INET_NTOA(terIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
    //             AND FROM_UNIXTIME(connectTime/1000)
    //                     BETWEEN LAST_DAY(CURDATE() - INTERVAL 2 MONTH) + INTERVAL 1 DAY
    //                     AND LAST_DAY(CURDATE() - INTERVAL 1 MONTH)
    //         ");

    //         $grossProfitLast = intval($incomingLast->incoming_bill_last_month ?? 0)
    //                         + intval($outgoingLast->outgoing_bill_last_month ?? 0);

    //         $incomingToday = DB::connection('mysql5')->selectOne("
    //             SELECT (ROUND((SUM(terBilledDuration)/60) * 0.1, 0) - (ROUND((SUM(terBilledDuration)/60) * 0.1, 0) * 0.03)) AS incoming_bill_today
    //             FROM $unionAll
    //             WHERE INET_NTOA(orgIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
    //             AND INET_NTOA(terIPAddress) = '59.152.98.70'
    //             AND DATE(FROM_UNIXTIME(connectTime/1000)) = CURDATE()
    //         ");

    //         $outgoingToday = DB::connection('mysql5')->selectOne("
    //             SELECT (ROUND((SUM(orgBilledDuration)/60) * 0.35, 0) - (ROUND((SUM(orgBilledDuration)/60) * 0.35, 0) * 0.03 + ROUND(((SUM(orgBilledDuration)/60) * 0.14), 0))) AS outgoing_bill_today
    //             FROM $unionAll
    //             WHERE INET_NTOA(orgIPAddress) = '59.152.98.70'
    //             AND INET_NTOA(terIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
    //             AND DATE(FROM_UNIXTIME(connectTime/1000)) = CURDATE()
    //         ");

    //         $currentDayGrossProfit = intval($incomingToday->incoming_bill_today ?? 0)
    //                             + intval($outgoingToday->outgoing_bill_today ?? 0);

    //         // Day-wise gross profit with pagination
    //         $startDate = $request->start_date ?? date('Y-m-d', strtotime('-7 days'));
    //         $endDate = $request->end_date ?? date('Y-m-d');
    //         $page = $request->page ?? 1;
    //         $perPage = $request->per_page ?? 25;
    //         $offset = ($page - 1) * $perPage;

    //         // Get day-wise data
    //         $dayWiseQuery = "
    //             SELECT 
    //                 DATE(FROM_UNIXTIME(connectTime/1000)) as date,
    //                 ROUND(SUM(CASE 
    //                     WHEN INET_NTOA(orgIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
    //                     AND INET_NTOA(terIPAddress) = '59.152.98.70'
    //                     THEN (terBilledDuration/60) * 0.1 
    //                     ELSE 0 
    //                 END), 0) as incoming_bill,
                    
    //                 ROUND(SUM(CASE 
    //                     WHEN INET_NTOA(orgIPAddress) = '59.152.98.70'
    //                     AND INET_NTOA(terIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
    //                     THEN orgBilledAmount - ((orgBilledDuration/60) * 0.14)
    //                     ELSE 0 
    //                 END), 0) as outgoing_bill,
                    
    //                 ROUND(SUM(CASE 
    //                     WHEN INET_NTOA(orgIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
    //                     AND INET_NTOA(terIPAddress) = '59.152.98.70'
    //                     THEN (terBilledDuration/60) * 0.1 
    //                     ELSE 0 
    //                 END) + 
    //                 SUM(CASE 
    //                     WHEN INET_NTOA(orgIPAddress) = '59.152.98.70'
    //                     AND INET_NTOA(terIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
    //                     THEN orgBilledAmount - ((orgBilledDuration/60) * 0.14)
    //                     ELSE 0 
    //                 END), 0) as gross_profit
                    
    //             FROM $unionAll
    //             WHERE DATE(FROM_UNIXTIME(connectTime/1000)) BETWEEN ? AND ?
    //             GROUP BY DATE(FROM_UNIXTIME(connectTime/1000))
    //             ORDER BY date DESC
    //             LIMIT $perPage OFFSET $offset
    //         ";

    //         $dayWiseData = DB::connection('mysql5')->select($dayWiseQuery, [$startDate, $endDate]);

    //         // Count total days for pagination
    //         $countQuery = "
    //             SELECT COUNT(DISTINCT DATE(FROM_UNIXTIME(connectTime/1000))) as total_days
    //             FROM $unionAll
    //             WHERE DATE(FROM_UNIXTIME(connectTime/1000)) BETWEEN ? AND ?
    //         ";

    //         $totalDays = DB::connection('mysql5')->selectOne($countQuery, [$startDate, $endDate])->total_days;

    //         return response()->json([
                
    //             // "outgoing_minutes_this_month" => intval($outgoing->outgoing_minutes_this_month ?? 0),
    //             "incoming_bill_this_month"    => intval($incoming->incoming_bill_this_month ?? 0),
    //             "outgoing_bill_this_month"    => intval($outgoing->outgoing_bill_this_month ?? 0),
    //             "gross_profit_this_month"     => intval($grossProfit),

    //             // "outgoing_minutes_last_month" => intval($outgoingLast->outgoing_minutes_last_month ?? 0),
    //             "incoming_bill_last_month"    => intval($incomingLast->incoming_bill_last_month ?? 0),
    //             "outgoing_bill_last_month"    => intval($outgoingLast->outgoing_bill_last_month ?? 0),
    //             "gross_profit_last_month"     => intval($grossProfitLast),

    //             "current_day_gross_profit"    => intval($currentDayGrossProfit),

    //             // Day-wise data
    //             "day_wise_gross_profit" => [
    //                 "data" => $dayWiseData,
    //                 "current_page" => (int)$page,
    //                 "per_page" => (int)$perPage,
    //                 "total_days" => (int)$totalDays,
    //                 "last_page" => ceil($totalDays / $perPage),
    //                 "date_range" => [
    //                     "start_date" => $startDate,
    //                     "end_date" => $endDate
    //                 ]
    //             ],

    //             "tables_used" => [
    //                 "checked" => $allTables
    //             ],

    //             "date_column_used" => "connectTime"
    //         ]);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             "status" => false,
    //             "error"  => $e->getMessage(),
    //             "line"   => $e->getLine()
    //         ], 500);
    //     }
    // }


    public function grossProfitDayWise(Request $request)
    {
        // 1. Validate and Prepare Dates
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        if (empty($startDate) || empty($endDate)) {
            return response()->json([
                'status' => false,
                'message' => 'Missing start_date or end_date parameter.'
            ], 400);
        }

        try {
            // Standardizing the date format
            $startDate = date('Y-m-d', strtotime($startDate));
            $endDate = date('Y-m-d', strtotime($endDate));
            
            $dbConnection = 'mysql5';
            $dbSchema = 'Successfuliptsp';
            $tablePrefix = 'vbSuccessfulCDR_';

            // 2. Dynamic Table Selection
            
            // Step 2a: Find the latest table to get the sequence number
            $latestTableResult = DB::connection($dbConnection)->selectOne("
                SELECT TABLE_NAME
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = ?
                AND TABLE_NAME LIKE '{$tablePrefix}%'
                AND TABLE_NAME NOT LIKE '%_bkp%'
                ORDER BY TABLE_NAME DESC
                LIMIT 1
            ", [$dbSchema]);

            if (!$latestTableResult) {
                return response()->json(['status' => false, 'message' => "No CDR tables found"], 404);
            }

            $latestTableName = $latestTableResult->TABLE_NAME;
            $latestSequence = (int) filter_var($latestTableName, FILTER_SANITIZE_NUMBER_INT);
            
            // Step 2b: Iterate backwards to find all potentially relevant tables.
            // Assuming tables are named sequentially and cover consecutive periods.
            // A more robust solution would check the minimum connectTime in each table.
            $maxTablesToCheck = 60; // Set a reasonable limit to prevent excessive table scans
            $allTables = [];
            
            for ($i = 0; $i < $maxTablesToCheck; $i++) {
                $sequence = $latestSequence - $i;
                if ($sequence <= 0) break; // Stop if sequence number is illogical
                
                $tableName = $tablePrefix . $sequence;
                $fullTableName = $dbSchema . "." . $tableName;
                
                // OPTIMIZATION: Check if the table exists AND if its MIN(connectTime) is before $endDate
                // For simplicity and to match the existing code structure, we'll just add the table
                // and let the WHERE clause filter the dates.
                
                // Check if the table actually exists
                $tableExists = DB::connection($dbConnection)->selectOne("
                    SELECT TABLE_NAME
                    FROM INFORMATION_SCHEMA.TABLES
                    WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
                ", [$dbSchema, $tableName]);

                if ($tableExists) {
                    $allTables[] = $fullTableName;
                } else {
                    // Since tables are sequential, if a table doesn't exist,
                    // we can stop checking older tables.
                    break;
                }
            }
            
            if (empty($allTables)) {
                 return response()->json(['status' => false, 'message' => "No relevant CDR tables found"], 404);
            }
            
            // 3. Build the UNION ALL Subquery
            $buildUnion = function($tableArray) {
                // Ensure the date column is present and properly named if necessary
                return implode(" UNION ALL ", array_map(fn($t) => "SELECT * FROM $t", $tableArray));
            };

            $unionAll = "(" . $buildUnion($allTables) . ") AS cdr";

            // 4. Gross Profit Calculation Query (Day-wise)
            $incomingIPs = ['10.246.29.66','10.246.29.74','172.20.15.106'];
            $outgoingIP = '59.152.98.70';
            
            $incomingIPsString = "'" . implode("','", $incomingIPs) . "'";

            $dayWiseProfit = DB::connection($dbConnection)->select("
                SELECT
                    DATE(FROM_UNIXTIME(connectTime/1000)) AS report_date,
                    
                    -- Incoming Calculation
                    SUM(CASE
                        WHEN INET_NTOA(orgIPAddress) IN ({$incomingIPsString}) AND INET_NTOA(terIPAddress) = '{$outgoingIP}'
                        THEN (ROUND((terBilledDuration/60) * 0.1, 0) - (ROUND((terBilledDuration/60) * 0.1, 0) * 0.03))
                        ELSE 0
                    END) AS incoming_bill_day,
                    
                    -- Outgoing Calculation
                    SUM(CASE
                        WHEN INET_NTOA(orgIPAddress) = '{$outgoingIP}' AND INET_NTOA(terIPAddress) IN ({$incomingIPsString})
                        THEN (ROUND((orgBilledDuration/60) * 0.35, 0) - (ROUND((orgBilledDuration/60) * 0.35, 0) * 0.03 + ROUND(((orgBilledDuration/60) * 0.14), 0)))
                        ELSE 0
                    END) AS outgoing_bill_day
                    
                FROM $unionAll
                
                WHERE FROM_UNIXTIME(connectTime/1000) BETWEEN '{$startDate} 00:00:00' AND '{$endDate} 23:59:59'
                
                -- Only include records relevant to the IP addresses
                AND (
                    (INET_NTOA(orgIPAddress) IN ({$incomingIPsString}) AND INET_NTOA(terIPAddress) = '{$outgoingIP}') OR
                    (INET_NTOA(orgIPAddress) = '{$outgoingIP}' AND INET_NTOA(terIPAddress) IN ({$incomingIPsString}))
                )
                
                GROUP BY report_date
                ORDER BY report_date ASC
            ");

            // 5. Format and Return Results
            $formattedResults = [];
            foreach ($dayWiseProfit as $day) {
                $incoming = intval($day->incoming_bill_day ?? 0);
                $outgoing = intval($day->outgoing_bill_day ?? 0);
                
                $formattedResults[] = [
                    'date' => $day->report_date,
                    'incoming_bill' => $incoming,
                    'outgoing_bill' => $outgoing,
                    'gross_profit' => $incoming + $outgoing,
                ];
            }

            return response()->json([
                'status' => true,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'gross_profit_day_wise' => $formattedResults,
                'tables_checked' => $allTables,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                "status" => false,
                "error"  => $e->getMessage(),
                "line"   => $e->getLine(),
                "trace"  => $e->getTraceAsString(), // Added for better debugging
            ], 500);
        }
    }



}
