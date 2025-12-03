<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    

    public function DashboardRechargedAmount(Request $request)
    {
        try {

            $currentMonth = date("Y_m"); 
            $lastMonth    = date("Y_m", strtotime("-1 month"));

            
            $tableList = DB::connection('mysql5')->select("
                SELECT DISTINCT SUBSTRING(TABLE_NAME, 11, 7) AS YEAR
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_NAME LIKE 'vbPayment_2%'
            ");

            if (!$tableList) {
                return response()->json([
                    "status" => 500,
                    "error"  => "No vbPayment_YYYY_MM tables found."
                ], 500);
            }

            $tableArr = [];
            foreach ($tableList as $row) {
                $tableArr[] = $row->YEAR;
            }

            $currentIndex = array_search($currentMonth, $tableArr);
            $lastIndex    = array_search($lastMonth, $tableArr);

            if ($currentIndex === false) {
                return response()->json([
                    "status" => 500,
                    "error"  => "Current month table NOT found: $currentMonth",
                    "available" => $tableArr
                ]);
            }

            if ($lastIndex === false) {
                return response()->json([
                    "status" => 500,
                    "error"  => "Last month table NOT found: $lastMonth",
                    "available" => $tableArr
                ]);
            }

            $currentTable = "iTelBillingiptsp.vbPayment_" . $tableArr[$currentIndex];
            $lastTable    = "iTelBillingiptsp.vbPayment_" . $tableArr[$lastIndex];

            
            $currentMonthSQL = "
                SELECT ROUND(SUM(Amount)) AS ThisMonth_Recharged
                FROM (
                    SELECT 
                        FROM_UNIXTIME(p.pyDate/1000, '%Y-%m-%d %H:%i:%s') AS Payment_Date,
                        p.pyAmount AS Amount
                    FROM $currentTable p
                    JOIN iTelBillingiptsp.vbClient c ON p.pyAccountID = c.clAccountID
                    JOIN iTelBillingiptsp.vbPaymentType t ON p.pyPaymentGatewayType = t.ptID
                    JOIN iTelBillingiptsp.vbClientDetails d ON c.clAccountID = d.cdClientAccountID
                    WHERE FROM_UNIXTIME(p.pyDate/1000)
                        BETWEEN LAST_DAY(CURDATE() - INTERVAL 1 MONTH) + INTERVAL 1 DAY
                        AND LAST_DAY(CURDATE())
                    AND p.pyAmount > 0
                    
                    AND c.clCustomerID LIKE '8801%'
                    AND p.pyPaymentGatewayType = 19
                ) tbl;
            ";

            
            $lastMonthSQL = "
                SELECT ROUND(SUM(Amount)) AS LastMonth_Recharged
                FROM (
                    SELECT 
                        FROM_UNIXTIME(p.pyDate/1000, '%Y-%m-%d %H:%i:%s') AS Payment_Date,
                        p.pyAmount AS Amount
                    FROM $lastTable p
                    JOIN iTelBillingiptsp.vbClient c ON p.pyAccountID = c.clAccountID
                    JOIN iTelBillingiptsp.vbPaymentType t ON p.pyPaymentGatewayType = t.ptID
                    JOIN iTelBillingiptsp.vbClientDetails d ON c.clAccountID = d.cdClientAccountID
                    WHERE FROM_UNIXTIME(p.pyDate/1000)
                        BETWEEN LAST_DAY(CURDATE() - INTERVAL 2 MONTH) + INTERVAL 1 DAY
                        AND LAST_DAY(CURDATE() - INTERVAL 1 MONTH)
                    AND p.pyAmount > 0
                    AND c.clCustomerID LIKE '8801%'
                    AND p.pyPaymentGatewayType = 19
                ) tbl;
            ";

            
            $todaySQL = "
                SELECT ROUND(SUM(Amount)) AS Today_Recharged
                FROM (
                    SELECT 
                        FROM_UNIXTIME(p.pyDate/1000) AS Payment_Date,
                        p.pyAmount AS Amount
                    FROM $currentTable p
                    JOIN iTelBillingiptsp.vbClient c ON p.pyAccountID = c.clAccountID
                    JOIN iTelBillingiptsp.vbPaymentType t ON p.pyPaymentGatewayType = t.ptID
                    JOIN iTelBillingiptsp.vbClientDetails d ON c.clAccountID = d.cdClientAccountID
                    WHERE DATE(FROM_UNIXTIME(p.pyDate/1000)) = CURDATE()
                    AND p.pyAmount > 0
                    
                    AND c.clCustomerID LIKE '8801%'
                    AND p.pyPaymentGatewayType = 19
                ) tbl;
            ";

            
            $lastMonthAvgSQL = "
                SELECT ROUND(SUM(Amount) / COUNT(DISTINCT DATE(Payment_Date))) AS Last_Month_Avg_Per_Day
                FROM (
                    SELECT 
                        FROM_UNIXTIME(p.pyDate/1000) AS Payment_Date,
                        p.pyAmount AS Amount
                    FROM $lastTable p
                    JOIN iTelBillingiptsp.vbClient c ON p.pyAccountID = c.clAccountID
                    JOIN iTelBillingiptsp.vbPaymentType t ON p.pyPaymentGatewayType = t.ptID
                    JOIN iTelBillingiptsp.vbClientDetails d ON c.clAccountID = d.cdClientAccountID
                    WHERE FROM_UNIXTIME(p.pyDate/1000)
                        BETWEEN LAST_DAY(CURDATE() - INTERVAL 2 MONTH) + INTERVAL 1 DAY
                        AND LAST_DAY(CURDATE() - INTERVAL 1 MONTH)
                    AND p.pyAmount > 0
                    
                    AND c.clCustomerID LIKE '8801%'
                    AND p.pyPaymentGatewayType = 19
                ) tbl;
            ";

            
            $thisMonthAvgSQL = "
                SELECT ROUND(SUM(Amount) / COUNT(DISTINCT DATE(Payment_Date))) AS This_Month_Avg_Per_Day
                FROM (
                    SELECT 
                        FROM_UNIXTIME(p.pyDate/1000) AS Payment_Date,
                        p.pyAmount AS Amount
                    FROM $currentTable p
                    JOIN iTelBillingiptsp.vbClient c ON p.pyAccountID = c.clAccountID
                    JOIN iTelBillingiptsp.vbPaymentType t ON p.pyPaymentGatewayType = t.ptID
                    JOIN iTelBillingiptsp.vbClientDetails d ON c.clAccountID = d.cdClientAccountID
                    WHERE FROM_UNIXTIME(p.pyDate/1000)
                        BETWEEN LAST_DAY(CURDATE() - INTERVAL 1 MONTH) + INTERVAL 1 DAY
                        AND LAST_DAY(CURDATE())
                    AND p.pyAmount > 0
                    
                    AND c.clCustomerID LIKE '8801%'
                    AND p.pyPaymentGatewayType = 19
                ) tbl;
            ";

            
            $currentMonthResult = DB::connection('mysql5')->select($currentMonthSQL);
            $lastMonthResult    = DB::connection('mysql5')->select($lastMonthSQL);
            $todayResult        = DB::connection('mysql5')->select($todaySQL);
            $lastMonthAvgResult = DB::connection('mysql5')->select($lastMonthAvgSQL);
            $thisMonthAvgResult = DB::connection('mysql5')->select($thisMonthAvgSQL);

            
            return response()->json([
                "status"                     => 200,
                "currentMonthPayment"        => $currentMonthResult[0] ?? null,
                "lastMonthPayment"           => $lastMonthResult[0] ?? null,
                "todayPayment"               => $todayResult[0] ?? null,
                "lastMonthAveragePerDay"     => $lastMonthAvgResult[0] ?? null,
                "thisMonthAveragePerDay"     => $thisMonthAvgResult[0] ?? null,
                "tables_used" => [
                    "current" => $currentTable,
                    "last"    => $lastTable
                ]
            ]);

        } catch (\Exception $e) {

            return response()->json([
                "status"  => 500,
                "error"   => $e->getMessage(),
                "line"    => $e->getLine()
            ], 500);
        }
    }


    public function grossProfit()
    {
        try {

            $tables = DB::connection('mysql5')->select("
                SELECT TABLE_NAME
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = 'Successfuliptsp'
                AND TABLE_NAME LIKE 'vbSuccessfulCDR_%'
                AND TABLE_NAME NOT LIKE '%_bkp%'
                ORDER BY TABLE_NAME DESC
                LIMIT 3
            ");

            if (count($tables) < 3) {
                return response()->json([
                    'status' => false,
                    'message' => "Not enough CDR tables found"
                ], 404);
            }

            $allTables = [
                "Successfuliptsp." . $tables[0]->TABLE_NAME,
                "Successfuliptsp." . $tables[1]->TABLE_NAME,
                // "Successfuliptsp." . $tables[2]->TABLE_NAME,
            ];

            $buildUnion = function($tableArray) {
                return implode(" UNION ALL ", array_map(fn($t) => "SELECT * FROM $t", $tableArray));
            };

            $unionAll = "(" . $buildUnion($allTables) . ") AS cdr";

            
            $incoming = DB::connection('mysql5')->selectOne("
                
                SELECT (ROUND((SUM(terBilledDuration)/60) * 0.1, 0) - (ROUND((SUM(terBilledDuration)/60) * 0.1, 0) * 0.03)) AS incoming_bill_this_month
                FROM $unionAll
                WHERE INET_NTOA(orgIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
                AND INET_NTOA(terIPAddress) = '59.152.98.70'
                AND FROM_UNIXTIME(connectTime/1000)
                        BETWEEN LAST_DAY(CURDATE() - INTERVAL 1 MONTH) + INTERVAL 1 DAY
                        AND LAST_DAY(CURDATE())
            ");

            $outgoing = DB::connection('mysql5')->selectOne("
                SELECT 
                    ROUND(SUM(orgBilledDuration)/60, 0) AS outgoing_minutes_this_month,
                    (ROUND((SUM(orgBilledDuration)/60) * 0.35, 0) - (ROUND((SUM(orgBilledDuration)/60) * 0.35, 0) * 0.03 + ROUND(((SUM(orgBilledDuration)/60) * 0.14), 0)))
                        AS outgoing_bill_this_month
                FROM $unionAll
                WHERE INET_NTOA(orgIPAddress) = '59.152.98.70'
                AND INET_NTOA(terIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
                AND FROM_UNIXTIME(connectTime/1000)
                        BETWEEN LAST_DAY(CURDATE() - INTERVAL 1 MONTH) + INTERVAL 1 DAY
                        AND LAST_DAY(CURDATE())
            ");

            $grossProfit = intval($incoming->incoming_bill_this_month ?? 0)
                        + intval($outgoing->outgoing_bill_this_month ?? 0);

            
            $incomingLast = DB::connection('mysql5')->selectOne("
                SELECT (ROUND((SUM(terBilledDuration)/60) * 0.1, 0) - (ROUND((SUM(terBilledDuration)/60) * 0.1, 0) * 0.03)) AS incoming_bill_last_month
                FROM $unionAll
                WHERE INET_NTOA(orgIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
                AND INET_NTOA(terIPAddress) = '59.152.98.70'
                AND FROM_UNIXTIME(connectTime/1000)
                        BETWEEN LAST_DAY(CURDATE() - INTERVAL 2 MONTH) + INTERVAL 1 DAY
                        AND LAST_DAY(CURDATE() - INTERVAL 1 MONTH)
            ");

            $outgoingLast = DB::connection('mysql5')->selectOne("
                SELECT 
                    ROUND(SUM(orgBilledDuration)/60, 0) AS outgoing_minutes_last_month,
                    (ROUND((SUM(orgBilledDuration)/60) * 0.35, 0) - (ROUND((SUM(orgBilledDuration)/60) * 0.35, 0) * 0.03 + ROUND(((SUM(orgBilledDuration)/60) * 0.14), 0)))
                        AS outgoing_bill_last_month
                FROM $unionAll
                WHERE INET_NTOA(orgIPAddress) = '59.152.98.70'
                AND INET_NTOA(terIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
                AND FROM_UNIXTIME(connectTime/1000)
                        BETWEEN LAST_DAY(CURDATE() - INTERVAL 2 MONTH) + INTERVAL 1 DAY
                        AND LAST_DAY(CURDATE() - INTERVAL 1 MONTH)
            ");

            $grossProfitLast = intval($incomingLast->incoming_bill_last_month ?? 0)
                            + intval($outgoingLast->outgoing_bill_last_month ?? 0);

            
            $incomingToday = DB::connection('mysql5')->selectOne("
                SELECT (ROUND((SUM(terBilledDuration)/60) * 0.1, 0) - (ROUND((SUM(terBilledDuration)/60) * 0.1, 0) * 0.03)) AS incoming_bill_today
                FROM $unionAll
                WHERE INET_NTOA(orgIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
                AND INET_NTOA(terIPAddress) = '59.152.98.70'
                AND DATE(FROM_UNIXTIME(connectTime/1000)) = CURDATE()
            ");

            $outgoingToday = DB::connection('mysql5')->selectOne("
                SELECT (ROUND((SUM(orgBilledDuration)/60) * 0.35, 0) - (ROUND((SUM(orgBilledDuration)/60) * 0.35, 0) * 0.03 + ROUND(((SUM(orgBilledDuration)/60) * 0.14), 0))) AS outgoing_bill_today
                FROM $unionAll
                WHERE INET_NTOA(orgIPAddress) = '59.152.98.70'
                AND INET_NTOA(terIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
                AND DATE(FROM_UNIXTIME(connectTime/1000)) = CURDATE()
            ");

            $currentDayGrossProfit = intval($incomingToday->incoming_bill_today ?? 0)
                                + intval($outgoingToday->outgoing_bill_today ?? 0);

            return response()->json([
                // "outgoing_minutes_this_month" => intval($outgoing->outgoing_minutes_this_month ?? 0),
                "incoming_bill_this_month"    => intval($incoming->incoming_bill_this_month ?? 0),
                "outgoing_bill_this_month"    => intval($outgoing->outgoing_bill_this_month ?? 0),
                "gross_profit_this_month"     => intval($grossProfit),

                // "outgoing_minutes_last_month" => intval($outgoingLast->outgoing_minutes_last_month ?? 0),
                "incoming_bill_last_month"    => intval($incomingLast->incoming_bill_last_month ?? 0),
                "outgoing_bill_last_month"    => intval($outgoingLast->outgoing_bill_last_month ?? 0),
                "gross_profit_last_month"     => intval($grossProfitLast),

                "current_day_gross_profit"    => intval($currentDayGrossProfit),

                "tables_used" => [
                    "checked" => $allTables
                ],

                "date_column_used" => "connectTime"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                "status" => false,
                "error"  => $e->getMessage(),
                "line"   => $e->getLine()
            ], 500);
        }
    }

    public function revenue()
    {
        try {

            
            $tables = DB::connection('mysql5')->select("
                SELECT TABLE_NAME
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = 'Successfuliptsp'
                AND TABLE_NAME LIKE 'vbSuccessfulCDR_%'
                AND TABLE_NAME NOT LIKE '%_bkp%'
                ORDER BY TABLE_NAME DESC
                LIMIT 3
            ");

            if (count($tables) < 3) {
                return response()->json([
                    'status' => false,
                    'message' => "Not enough CDR tables found"
                ], 404);
            }

            
            $allTables = [
                "Successfuliptsp." . $tables[0]->TABLE_NAME,
                "Successfuliptsp." . $tables[1]->TABLE_NAME,
                // "Successfuliptsp." . $tables[2]->TABLE_NAME,
            ];

            
            $buildUnion = function($tableArray) {
                return implode(" UNION ALL ", array_map(fn($t) => "SELECT * FROM $t", $tableArray));
            };

            $unionAll = "(" . $buildUnion($allTables) . ") AS cdr";

          
            $incoming = DB::connection('mysql5')->selectOne("
                SELECT ROUND((SUM(terBilledDuration)/60) * 0.1, 0) AS incoming_bill_this_month
                FROM $unionAll
                WHERE INET_NTOA(orgIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
                AND INET_NTOA(terIPAddress) = '59.152.98.70'
                AND FROM_UNIXTIME(connectTime/1000)
                        BETWEEN LAST_DAY(CURDATE() - INTERVAL 1 MONTH) + INTERVAL 1 DAY
                        AND LAST_DAY(CURDATE())
            ");

            $outgoing = DB::connection('mysql5')->selectOne("
                SELECT 
                    ROUND((SUM(orgBilledDuration)/60) * 0.35, 0) AS outgoing_bill_this_month
                    FROM $unionAll
                WHERE INET_NTOA(terIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
                AND INET_NTOA(orgIPAddress) = '59.152.98.70'
                AND FROM_UNIXTIME(connectTime/1000)
                        BETWEEN LAST_DAY(CURDATE() - INTERVAL 1 MONTH) + INTERVAL 1 DAY
                        AND LAST_DAY(CURDATE())
            ");

            $revenueThisMonth = intval($incoming->incoming_bill_this_month ?? 0)
                            + intval($outgoing->outgoing_bill_this_month ?? 0);

            $incomingLast = DB::connection('mysql5')->selectOne("
                SELECT ROUND((SUM(terBilledDuration)/60) * 0.1, 0) AS incoming_bill_last_month
                FROM $unionAll
                WHERE INET_NTOA(orgIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
                AND INET_NTOA(terIPAddress) = '59.152.98.70'
                AND FROM_UNIXTIME(connectTime/1000)
                        BETWEEN LAST_DAY(CURDATE() - INTERVAL 2 MONTH) + INTERVAL 1 DAY
                        AND LAST_DAY(CURDATE() - INTERVAL 1 MONTH)
            ");

            $outgoingLast = DB::connection('mysql5')->selectOne("
                SELECT 
                    
                    ROUND((SUM(orgBilledDuration)/60) * 0.35, 0) AS outgoing_bill_last_month
                FROM $unionAll
                WHERE INET_NTOA(terIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
                AND INET_NTOA(orgIPAddress) = '59.152.98.70'
                AND FROM_UNIXTIME(connectTime/1000)
                        BETWEEN LAST_DAY(CURDATE() - INTERVAL 2 MONTH) + INTERVAL 1 DAY
                        AND LAST_DAY(CURDATE() - INTERVAL 1 MONTH)
            ");

            $revenueLastMonth = intval($incomingLast->incoming_bill_last_month ?? 0)
                            + intval($outgoingLast->outgoing_bill_last_month ?? 0);

            return response()->json([
                // "outgoing_minutes_this_month" => intval($outgoing->outgoing_minutes_this_month ?? 0),
                "incoming_bill_this_month"    => intval($incoming->incoming_bill_this_month ?? 0),
                "outgoing_bill_this_month"    => intval($outgoing->outgoing_bill_this_month ?? 0),
                "revenue_this_month"          => $revenueThisMonth,

                // "outgoing_minutes_last_month" => intval($outgoingLast->outgoing_minutes_last_month ?? 0),
                "incoming_bill_last_month"    => intval($incomingLast->incoming_bill_last_month ?? 0),
                "outgoing_bill_last_month"    => intval($outgoingLast->outgoing_bill_last_month ?? 0),
                "revenue_last_month"          => $revenueLastMonth,

                "tables_used" => [
                    "checked" => $allTables
                ],

                "date_column_used" => "connectTime"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                "status" => false,
                "error"  => $e->getMessage(),
                "line"   => $e->getLine()
            ], 500);
        }
    }




    public function DashboardRechargedAmountIptsp(Request $request)
    {
        try {

            $currentMonth = date("Y_m"); 
            $lastMonth    = date("Y_m", strtotime("-1 month"));

            
            $tableList = DB::connection('mysql5')->select("
                SELECT DISTINCT SUBSTRING(TABLE_NAME, 11, 7) AS YEAR
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_NAME LIKE 'vbPayment_2%'
            ");

            if (!$tableList) {
                return response()->json([
                    "status" => 500,
                    "error"  => "No vbPayment_YYYY_MM tables found."
                ], 500);
            }

            $tableArr = [];
            foreach ($tableList as $row) {
                $tableArr[] = $row->YEAR;
            }

            $currentIndex = array_search($currentMonth, $tableArr);
            $lastIndex    = array_search($lastMonth, $tableArr);

            if ($currentIndex === false) {
                return response()->json([
                    "status" => 500,
                    "error"  => "Current month table NOT found: $currentMonth",
                    "available" => $tableArr
                ]);
            }

            if ($lastIndex === false) {
                return response()->json([
                    "status" => 500,
                    "error"  => "Last month table NOT found: $lastMonth",
                    "available" => $tableArr
                ]);
            }

            $currentTable = "iTelBillingiptsp.vbPayment_" . $tableArr[$currentIndex];
            $lastTable    = "iTelBillingiptsp.vbPayment_" . $tableArr[$lastIndex];

            
            $currentMonthSQL = "
                SELECT ROUND(SUM(Amount)) AS ThisMonth_Recharged
                FROM (
                    SELECT 
                        FROM_UNIXTIME(p.pyDate/1000, '%Y-%m-%d %H:%i:%s') AS Payment_Date,
                        p.pyAmount AS Amount
                    FROM $currentTable p
                    JOIN iTelBillingiptsp.vbClient c ON p.pyAccountID = c.clAccountID
                    JOIN iTelBillingiptsp.vbPaymentType t ON p.pyPaymentGatewayType = t.ptID
                    JOIN iTelBillingiptsp.vbClientDetails d ON c.clAccountID = d.cdClientAccountID
                    WHERE FROM_UNIXTIME(p.pyDate/1000)
                        BETWEEN LAST_DAY(CURDATE() - INTERVAL 1 MONTH) + INTERVAL 1 DAY
                        AND LAST_DAY(CURDATE())
                    AND p.pyAmount > 0
                    
                    AND c.clCustomerID NOT LIKE '8801%'
                    AND p.pyPaymentGatewayType = 19
                ) tbl;
            ";

            
            $lastMonthSQL = "
                SELECT ROUND(SUM(Amount)) AS LastMonth_Recharged
                FROM (
                    SELECT 
                        FROM_UNIXTIME(p.pyDate/1000, '%Y-%m-%d %H:%i:%s') AS Payment_Date,
                        p.pyAmount AS Amount
                    FROM $lastTable p
                    JOIN iTelBillingiptsp.vbClient c ON p.pyAccountID = c.clAccountID
                    JOIN iTelBillingiptsp.vbPaymentType t ON p.pyPaymentGatewayType = t.ptID
                    JOIN iTelBillingiptsp.vbClientDetails d ON c.clAccountID = d.cdClientAccountID
                    WHERE FROM_UNIXTIME(p.pyDate/1000)
                        BETWEEN LAST_DAY(CURDATE() - INTERVAL 2 MONTH) + INTERVAL 1 DAY
                        AND LAST_DAY(CURDATE() - INTERVAL 1 MONTH)
                    AND p.pyAmount > 0
                    AND c.clCustomerID NOT LIKE '8801%'
                    AND p.pyPaymentGatewayType = 19
                ) tbl;
            ";

            
            $todaySQL = "
                SELECT ROUND(SUM(Amount)) AS Today_Recharged
                FROM (
                    SELECT 
                        FROM_UNIXTIME(p.pyDate/1000) AS Payment_Date,
                        p.pyAmount AS Amount
                    FROM $currentTable p
                    JOIN iTelBillingiptsp.vbClient c ON p.pyAccountID = c.clAccountID
                    JOIN iTelBillingiptsp.vbPaymentType t ON p.pyPaymentGatewayType = t.ptID
                    JOIN iTelBillingiptsp.vbClientDetails d ON c.clAccountID = d.cdClientAccountID
                    WHERE DATE(FROM_UNIXTIME(p.pyDate/1000)) = CURDATE()
                    AND p.pyAmount > 0
                    
                    AND c.clCustomerID NOT LIKE '8801%'
                    AND p.pyPaymentGatewayType = 19
                ) tbl;
            ";

            
            $lastMonthAvgSQL = "
                SELECT ROUND(SUM(Amount) / COUNT(DISTINCT DATE(Payment_Date))) AS Last_Month_Avg_Per_Day
                FROM (
                    SELECT 
                        FROM_UNIXTIME(p.pyDate/1000) AS Payment_Date,
                        p.pyAmount AS Amount
                    FROM $lastTable p
                    JOIN iTelBillingiptsp.vbClient c ON p.pyAccountID = c.clAccountID
                    JOIN iTelBillingiptsp.vbPaymentType t ON p.pyPaymentGatewayType = t.ptID
                    JOIN iTelBillingiptsp.vbClientDetails d ON c.clAccountID = d.cdClientAccountID
                    WHERE FROM_UNIXTIME(p.pyDate/1000)
                        BETWEEN LAST_DAY(CURDATE() - INTERVAL 2 MONTH) + INTERVAL 1 DAY
                        AND LAST_DAY(CURDATE() - INTERVAL 1 MONTH)
                    AND p.pyAmount > 0
                    
                    AND c.clCustomerID NOT LIKE '8801%'
                    AND p.pyPaymentGatewayType = 19
                ) tbl;
            ";

            
            $thisMonthAvgSQL = "
                SELECT ROUND(SUM(Amount) / COUNT(DISTINCT DATE(Payment_Date))) AS This_Month_Avg_Per_Day
                FROM (
                    SELECT 
                        FROM_UNIXTIME(p.pyDate/1000) AS Payment_Date,
                        p.pyAmount AS Amount
                    FROM $currentTable p
                    JOIN iTelBillingiptsp.vbClient c ON p.pyAccountID = c.clAccountID
                    JOIN iTelBillingiptsp.vbPaymentType t ON p.pyPaymentGatewayType = t.ptID
                    JOIN iTelBillingiptsp.vbClientDetails d ON c.clAccountID = d.cdClientAccountID
                    WHERE FROM_UNIXTIME(p.pyDate/1000)
                        BETWEEN LAST_DAY(CURDATE() - INTERVAL 1 MONTH) + INTERVAL 1 DAY
                        AND LAST_DAY(CURDATE())
                    AND p.pyAmount > 0
                    
                    AND c.clCustomerID NOT LIKE '8801%'
                    AND p.pyPaymentGatewayType = 19
                ) tbl;
            ";

            
            $currentMonthResult = DB::connection('mysql5')->select($currentMonthSQL);
            $lastMonthResult    = DB::connection('mysql5')->select($lastMonthSQL);
            $todayResult        = DB::connection('mysql5')->select($todaySQL);
            $lastMonthAvgResult = DB::connection('mysql5')->select($lastMonthAvgSQL);
            $thisMonthAvgResult = DB::connection('mysql5')->select($thisMonthAvgSQL);

            
            return response()->json([
                "status"                     => 200,
                "currentMonthPayment"        => $currentMonthResult[0] ?? null,
                "lastMonthPayment"           => $lastMonthResult[0] ?? null,
                "todayPayment"               => $todayResult[0] ?? null,
                "lastMonthAveragePerDay"     => $lastMonthAvgResult[0] ?? null,
                "thisMonthAveragePerDay"     => $thisMonthAvgResult[0] ?? null,
                "tables_used" => [
                    "current" => $currentTable,
                    "last"    => $lastTable
                ]
            ]);

        } catch (\Exception $e) {

            return response()->json([
                "status"  => 500,
                "error"   => $e->getMessage(),
                "line"    => $e->getLine()
            ], 500);
        }
    }


    public function grossProfitIptsp()
    {
        try {

            $tables = DB::connection('mysql5')->select("
                SELECT TABLE_NAME
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = 'Successfuliptsp'
                AND TABLE_NAME LIKE 'vbSuccessfulCDR_%'
                AND TABLE_NAME NOT LIKE '%_bkp%'
                ORDER BY TABLE_NAME DESC
                LIMIT 3
            ");

            if (count($tables) < 3) {
                return response()->json([
                    'status' => false,
                    'message' => "Not enough CDR tables found"
                ], 404);
            }

            $allTables = [
                "Successfuliptsp." . $tables[0]->TABLE_NAME,
                "Successfuliptsp." . $tables[1]->TABLE_NAME,
                // "Successfuliptsp." . $tables[2]->TABLE_NAME,
            ];

            $buildUnion = function($tableArray) {
                return implode(" UNION ALL ", array_map(fn($t) => "SELECT * FROM $t", $tableArray));
            };

            $unionAll = "(" . $buildUnion($allTables) . ") AS cdr";

            
            $incoming = DB::connection('mysql5')->selectOne("
                SELECT (ROUND((SUM(terBilledDuration)/60) * 0.1, 0) - (ROUND((SUM(terBilledDuration)/60) * 0.1, 0) * 0.03)) AS incoming_bill_this_month
                FROM $unionAll
                WHERE INET_NTOA(orgIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
                AND INET_NTOA(terIPAddress) = '59.152.98.66'
                AND FROM_UNIXTIME(connectTime/1000)
                        BETWEEN LAST_DAY(CURDATE() - INTERVAL 1 MONTH) + INTERVAL 1 DAY
                        AND LAST_DAY(CURDATE())
            ");

            $outgoing = DB::connection('mysql5')->selectOne("
                SELECT 
                    ROUND(SUM(orgBilledDuration)/60, 0) AS outgoing_minutes_this_month,
                    ((ROUND(SUM(CASE WHEN orgBilledAmount > 0 THEN orgBilledAmount END))
                    - (ROUND((SUM(CASE WHEN orgBilledAmount > 0 THEN orgBilledAmount END)), 0) * 0.03
                    + ROUND((SUM(CASE WHEN orgBilledAmount > 0 THEN orgBilledAmount END)) * 0.14, 0)))

                    + 

                    ROUND((SUM(CASE WHEN orgBilledAmount = 0 THEN orgBilledDuration END) / 60) * 0.35, 0)
                    - (ROUND((SUM(CASE WHEN orgBilledAmount = 0 THEN orgBilledDuration END) / 60) * 0.35, 0) * 0.03
                    + ROUND((SUM(CASE WHEN orgBilledAmount = 0 THEN orgBilledDuration END) / 60) * 0.14, 0)))
                    AS outgoing_bill_last_month
                FROM $unionAll
                WHERE INET_NTOA(orgIPAddress) != '59.152.98.70'
                AND INET_NTOA(terIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
                AND FROM_UNIXTIME(connectTime/1000)
                        BETWEEN LAST_DAY(CURDATE() - INTERVAL 1 MONTH) + INTERVAL 1 DAY
                        AND LAST_DAY(CURDATE())
            ");

            $grossProfit = intval($incoming->incoming_bill_this_month ?? 0)
                        + intval($outgoing->outgoing_bill_this_month ?? 0);

            
            $incomingLast = DB::connection('mysql5')->selectOne("
                SELECT (ROUND((SUM(terBilledDuration)/60) * 0.1, 0) - (ROUND((SUM(terBilledDuration)/60) * 0.1, 0) * 0.03)) AS incoming_bill_last_month
                FROM $unionAll
                WHERE INET_NTOA(orgIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
                AND INET_NTOA(terIPAddress) = '59.152.98.66'
                AND FROM_UNIXTIME(connectTime/1000)
                        BETWEEN LAST_DAY(CURDATE() - INTERVAL 2 MONTH) + INTERVAL 1 DAY
                        AND LAST_DAY(CURDATE() - INTERVAL 1 MONTH)
            ");

            $outgoingLast = DB::connection('mysql5')->selectOne("
                SELECT 
                    ROUND(SUM(orgBilledDuration)/60, 0) AS outgoing_minutes_last_month,
                    ((ROUND(SUM(CASE WHEN orgBilledAmount > 0 THEN orgBilledAmount END))
                    - (ROUND((SUM(CASE WHEN orgBilledAmount > 0 THEN orgBilledAmount END)), 0) * 0.03
                    + ROUND((SUM(CASE WHEN orgBilledAmount > 0 THEN orgBilledAmount END)) * 0.14, 0)))

                    + 

                    ROUND((SUM(CASE WHEN orgBilledAmount = 0 THEN orgBilledDuration END) / 60) * 0.35, 0)
                    - (ROUND((SUM(CASE WHEN orgBilledAmount = 0 THEN orgBilledDuration END) / 60) * 0.35, 0) * 0.03
                    + ROUND((SUM(CASE WHEN orgBilledAmount = 0 THEN orgBilledDuration END) / 60) * 0.14, 0)))
                        AS outgoing_bill_last_month
                FROM $unionAll
                WHERE INET_NTOA(orgIPAddress) != '59.152.98.70'
                AND INET_NTOA(terIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
                AND FROM_UNIXTIME(connectTime/1000)
                        BETWEEN LAST_DAY(CURDATE() - INTERVAL 2 MONTH) + INTERVAL 1 DAY
                        AND LAST_DAY(CURDATE() - INTERVAL 1 MONTH)
            ");

            $grossProfitLast = intval($incomingLast->incoming_bill_last_month ?? 0)
                            + intval($outgoingLast->outgoing_bill_last_month ?? 0);

            
            $incomingToday = DB::connection('mysql5')->selectOne("
                SELECT (ROUND((SUM(terBilledDuration)/60) * 0.1, 0) - (ROUND((SUM(terBilledDuration)/60) * 0.1, 0) * 0.03)) AS incoming_bill_today
                FROM $unionAll
                WHERE INET_NTOA(orgIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
                AND INET_NTOA(terIPAddress) = '59.152.98.66'
                AND DATE(FROM_UNIXTIME(connectTime/1000)) = CURDATE()
            ");

            $outgoingToday = DB::connection('mysql5')->selectOne("
                SELECT ((ROUND(SUM(CASE WHEN orgBilledAmount > 0 THEN orgBilledAmount END))
                - (ROUND((SUM(CASE WHEN orgBilledAmount > 0 THEN orgBilledAmount END)), 0) * 0.03
                + ROUND((SUM(CASE WHEN orgBilledAmount > 0 THEN orgBilledAmount END)) * 0.14, 0)))

                + 

                ROUND((SUM(CASE WHEN orgBilledAmount = 0 THEN orgBilledDuration END) / 60) * 0.35, 0)
                - (ROUND((SUM(CASE WHEN orgBilledAmount = 0 THEN orgBilledDuration END) / 60) * 0.35, 0) * 0.03
                + ROUND((SUM(CASE WHEN orgBilledAmount = 0 THEN orgBilledDuration END) / 60) * 0.14, 0))) AS outgoing_bill_today
                FROM $unionAll
                WHERE INET_NTOA(orgIPAddress) != '59.152.98.70'
                AND INET_NTOA(terIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
                AND DATE(FROM_UNIXTIME(connectTime/1000)) = CURDATE()
            ");

            $currentDayGrossProfit = intval($incomingToday->incoming_bill_today ?? 0)
                                + intval($outgoingToday->outgoing_bill_today ?? 0);

            return response()->json([
                // "outgoing_minutes_this_month" => intval($outgoing->outgoing_minutes_this_month ?? 0),
                "incoming_bill_this_month"    => intval($incoming->incoming_bill_this_month ?? 0),
                "outgoing_bill_this_month"    => intval($outgoing->outgoing_bill_this_month ?? 0),
                "gross_profit_this_month"     => intval($grossProfit),

                // "outgoing_minutes_last_month" => intval($outgoingLast->outgoing_minutes_last_month ?? 0),
                "incoming_bill_last_month"    => intval($incomingLast->incoming_bill_last_month ?? 0),
                "outgoing_bill_last_month"    => intval($outgoingLast->outgoing_bill_last_month ?? 0),
                "gross_profit_last_month"     => intval($grossProfitLast),

                "current_day_gross_profit"    => intval($currentDayGrossProfit),

                "tables_used" => [
                    "checked" => $allTables
                ],

                "date_column_used" => "connectTime"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                "status" => false,
                "error"  => $e->getMessage(),
                "line"   => $e->getLine()
            ], 500);
        }
    }



    public function revenueIptsp()
    {
        try {

            $tables = DB::connection('mysql5')->select("
                SELECT TABLE_NAME
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = 'Successfuliptsp'
                AND TABLE_NAME LIKE 'vbSuccessfulCDR_%'
                AND TABLE_NAME NOT LIKE '%_bkp%'
                ORDER BY TABLE_NAME DESC
                LIMIT 3
            ");

            if (count($tables) < 3) {
                return response()->json([
                    'status' => false,
                    'message' => "Not enough CDR tables found"
                ], 404);
            }

            $allTables = [
                "Successfuliptsp." . $tables[0]->TABLE_NAME,
                "Successfuliptsp." . $tables[1]->TABLE_NAME,
                // "Successfuliptsp." . $tables[2]->TABLE_NAME,
            ];

            $buildUnion = function($tableArray) {
                return implode(" UNION ALL ", array_map(fn($t) => "SELECT * FROM $t", $tableArray));
            };

            $unionAll = "(" . $buildUnion($allTables) . ") AS cdr";

            
            $incoming = DB::connection('mysql5')->selectOne("
                SELECT (ROUND((SUM(terBilledDuration)/60) * 0.1, 0)) AS incoming_bill_this_month
                FROM $unionAll
                WHERE INET_NTOA(orgIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
                AND INET_NTOA(terIPAddress) = '59.152.98.66'
                AND FROM_UNIXTIME(connectTime/1000)
                        BETWEEN LAST_DAY(CURDATE() - INTERVAL 1 MONTH) + INTERVAL 1 DAY
                        AND LAST_DAY(CURDATE())
            ");

            $outgoing = DB::connection('mysql5')->selectOne("
                SELECT 
                    ROUND(SUM(orgBilledDuration)/60, 0) AS outgoing_minutes_this_month,
                    (ROUND(SUM(CASE WHEN orgBilledAmount > 0 THEN orgBilledAmount END)))
                    AS outgoing_bill_last_month
                FROM $unionAll
                WHERE INET_NTOA(orgIPAddress) != '59.152.98.70'
                AND INET_NTOA(terIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
                AND FROM_UNIXTIME(connectTime/1000)
                        BETWEEN LAST_DAY(CURDATE() - INTERVAL 1 MONTH) + INTERVAL 1 DAY
                        AND LAST_DAY(CURDATE())
            ");

            $grossProfit = intval($incoming->incoming_bill_this_month ?? 0)
                        + intval($outgoing->outgoing_bill_this_month ?? 0);

            
            $incomingLast = DB::connection('mysql5')->selectOne("
                SELECT (ROUND((SUM(terBilledDuration)/60) * 0.1, 0) - (ROUND((SUM(terBilledDuration)/60) * 0.1, 0) * 0.03)) AS incoming_bill_last_month
                FROM $unionAll
                WHERE INET_NTOA(orgIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
                AND INET_NTOA(terIPAddress) = '59.152.98.66'
                AND FROM_UNIXTIME(connectTime/1000)
                        BETWEEN LAST_DAY(CURDATE() - INTERVAL 2 MONTH) + INTERVAL 1 DAY
                        AND LAST_DAY(CURDATE() - INTERVAL 1 MONTH)
            ");

            $outgoingLast = DB::connection('mysql5')->selectOne("
                SELECT 
                    ROUND(SUM(orgBilledDuration)/60, 0) AS outgoing_minutes_last_month,
                    (ROUND(SUM(CASE WHEN orgBilledAmount > 0 THEN orgBilledAmount END)))
                        AS outgoing_bill_last_month
                FROM $unionAll
                WHERE INET_NTOA(orgIPAddress) != '59.152.98.70'
                AND INET_NTOA(terIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
                AND FROM_UNIXTIME(connectTime/1000)
                        BETWEEN LAST_DAY(CURDATE() - INTERVAL 2 MONTH) + INTERVAL 1 DAY
                        AND LAST_DAY(CURDATE() - INTERVAL 1 MONTH)
            ");

            $grossProfitLast = intval($incomingLast->incoming_bill_last_month ?? 0)
                            + intval($outgoingLast->outgoing_bill_last_month ?? 0);

            
            $incomingToday = DB::connection('mysql5')->selectOne("
                SELECT (ROUND((SUM(terBilledDuration)/60) * 0.1, 0) - (ROUND((SUM(terBilledDuration)/60) * 0.1, 0) * 0.03)) AS incoming_bill_today
                FROM $unionAll
                WHERE INET_NTOA(orgIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
                AND INET_NTOA(terIPAddress) = '59.152.98.66'
                AND DATE(FROM_UNIXTIME(connectTime/1000)) = CURDATE()
            ");

            $outgoingToday = DB::connection('mysql5')->selectOne("
                SELECT (ROUND(SUM(CASE WHEN orgBilledAmount > 0 THEN orgBilledAmount END))) AS outgoing_bill_today
                FROM $unionAll
                WHERE INET_NTOA(orgIPAddress) != '59.152.98.70'
                AND INET_NTOA(terIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
                AND DATE(FROM_UNIXTIME(connectTime/1000)) = CURDATE()
            ");

            $currentDayGrossProfit = intval($incomingToday->incoming_bill_today ?? 0)
                                + intval($outgoingToday->outgoing_bill_today ?? 0);

            return response()->json([
                // "outgoing_minutes_this_month" => intval($outgoing->outgoing_minutes_this_month ?? 0),
                "incoming_bill_this_month"    => intval($incoming->incoming_bill_this_month ?? 0),
                "outgoing_bill_this_month"    => intval($outgoing->outgoing_bill_this_month ?? 0),
                "gross_profit_this_month"     => intval($grossProfit),

                // "outgoing_minutes_last_month" => intval($outgoingLast->outgoing_minutes_last_month ?? 0),
                "incoming_bill_last_month"    => intval($incomingLast->incoming_bill_last_month ?? 0),
                "outgoing_bill_last_month"    => intval($outgoingLast->outgoing_bill_last_month ?? 0),
                "gross_profit_last_month"     => intval($grossProfitLast),

                "current_day_gross_profit"    => intval($currentDayGrossProfit),

                "tables_used" => [
                    "checked" => $allTables
                ],

                "date_column_used" => "connectTime"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                "status" => false,
                "error"  => $e->getMessage(),
                "line"   => $e->getLine()
            ], 500);
        }
    }




    public function callStats()
    {
        try {

            
            $activeCalls = DB::connection('mysql5')->select("
                SELECT COUNT(ID) AS Active_Calls 
                FROM iTelBillingiptsp.vbRunningCall
            ");

            
            $liveCalls = DB::connection('mysql5')->select("
                SELECT COUNT(*) AS liveCalls
                FROM vbRunningCall
                WHERE connectTime IS NOT NULL
            ");

            
            $onProcessing = DB::connection('mysql5')->select("
                SELECT COUNT(*) AS onProcessing
                FROM vbRunningCall
                WHERE connectTime IS NULL
            ");

            return response()->json([
                'status' => true,
                'active_calls' => $activeCalls[0]->Active_Calls ?? 0,
                'live_calls' => $liveCalls[0]->liveCalls ?? 0,
                'on_processing' => $onProcessing[0]->onProcessing ?? 0
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    


}



