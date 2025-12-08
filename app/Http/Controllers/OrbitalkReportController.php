<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use DateTime;
use Illuminate\Support\Facades\Log;


class OrbitalkReportController extends Controller
{


    // public function paymentReport(Request $request)
    // {
    //     try {

            
    //         $startDate = $request->start_date
    //             ? $request->start_date . " 00:00:00"
    //             : date("Y-m-01 00:00:00");

    //         $endDate = $request->end_date
    //             ? $request->end_date . " 23:59:59"
    //             : date("Y-m-d 23:59:59");

    //         $startMonth = date("Y_m", strtotime($startDate));
    //         $endMonth   = date("Y_m", strtotime($endDate));

    //         $tableRows = DB::connection('mysql5')->select("
    //             SELECT TABLE_NAME
    //             FROM INFORMATION_SCHEMA.TABLES
    //             WHERE TABLE_SCHEMA = 'iTelBillingiptsp'
    //               AND TABLE_NAME LIKE 'vbPayment_%'
    //         ");

    //         $selectedTables = [];
    //         foreach ($tableRows as $t) {
    //             $month = str_replace("vbPayment_", "", $t->TABLE_NAME);
    //             if ($month >= $startMonth && $month <= $endMonth) {
    //                 $selectedTables[] = $t->TABLE_NAME;
    //             }
    //         }

    //         if (empty($selectedTables)) {
    //             return response()->json([
    //                 "data" => [],
    //                 "tables_used" => [],
    //                 "message" => "No tables found for date range"
    //             ]);
    //         }

            
    //         $unionParts = [];
    //         $allBindings = [];

    //         foreach ($selectedTables as $table) {

               
    //             $where = "
    //                 FROM_UNIXTIME(p.pyDate/1000) BETWEEN ? AND ?
    //                 AND p.pyAmount > 0
                    
    //                 AND c.clCustomerID LIKE '8801%'
    //                 AND p.pyPaymentGatewayType = 19
    //             ";

                
    //             $allBindings[] = $startDate;
    //             $allBindings[] = $endDate;

    //             if (!empty($request->pyUserName)) {
    //                 $where .= " AND p.pyUserName LIKE ? ";
                    
    //                 $allBindings[] = "%" . $request->pyUserName . "%";
    //             }

    //             if (!empty($request->clCustomerID)) {
    //                 $where .= " AND c.clCustomerID LIKE ? ";
                    
    //                 $allBindings[] = "%" . $request->clCustomerID . "%";
    //             }

    //             $unionParts[] = "
    //                 SELECT
    //                     FROM_UNIXTIME(p.pyDate/1000, '%Y-%m-%d %H:%i:%s') AS Payment_Date,
    //                     p.pyAmount AS Amount,
    //                     p.pyUserName,
    //                     c.clCustomerID,
    //                     c.clParentAccountID,
    //                     d.cdBillingName,
    //                     d.cdCompanyName
    //                 FROM iTelBillingiptsp.$table p
    //                 JOIN iTelBillingiptsp.vbClient c ON p.pyAccountID = c.clAccountID
    //                 JOIN iTelBillingiptsp.vbClientDetails d ON c.clAccountID = d.cdClientAccountID
    //                 WHERE $where
    //             ";
    //         }

    //         $unionSql = implode(" UNION ALL ", $unionParts);

            
    //         $page = $request->page ?? 1;
    //         $perPage = 25;
    //         $offset = ($page - 1) * $perPage;

    //         $sqlPaginated = $unionSql . " LIMIT $perPage OFFSET $offset";

            
    //         $countSql = "SELECT COUNT(*) AS total FROM ($unionSql) AS t";

            
    //         $data = DB::connection('mysql5')->select($sqlPaginated, $allBindings);
    //         $total = DB::connection('mysql5')->selectOne($countSql, $allBindings)->total;

    //         return response()->json([
    //             "tables_used" => $selectedTables,
    //             "data"        => $data,
    //             "currentPage" => (int)$page,
    //             "perPage"     => $perPage,
    //             "total"       => $total,
    //             "lastPage"    => ceil($total / $perPage)
    //         ]);

    //     } catch (\Exception $e) {
            
    //         Log::error("Payment Report Error: " . $e->getMessage(), ['exception' => $e]);

    //         return response()->json([
    //             "error" => true,
    //             "message" => $e->getMessage()
    //         ], 500);
    //     }
    // }



    // date wise payment report orbitalk

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
            $summaryParts = [];
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

                
                $summaryParts[] = "
                    SELECT
                        DATE(FROM_UNIXTIME(p.pyDate/1000)) AS Payment_Date,
                        SUM(p.pyAmount) AS Total_Amount
                    FROM iTelBillingiptsp.$table p
                    JOIN iTelBillingiptsp.vbClient c ON p.pyAccountID = c.clAccountID
                    WHERE $where
                    GROUP BY DATE(FROM_UNIXTIME(p.pyDate/1000))
                ";
            }

            $unionSql = implode(" UNION ALL ", $unionParts);
            $summarySql = implode(" UNION ALL ", $summaryParts);

            $page = $request->page ?? 1;
            $perPage = 25;
            $offset = ($page - 1) * $perPage;

            
            $sqlPaginated = $unionSql . " LIMIT $perPage OFFSET $offset";

            
            $countSql = "SELECT COUNT(*) AS total FROM ($unionSql) AS t";

            
            $data = DB::connection('mysql5')->select($sqlPaginated, $allBindings);
            $total = DB::connection('mysql5')->selectOne($countSql, $allBindings)->total;

            
            $summaryData = DB::connection('mysql5')->select($summarySql, $allBindings);

            
            $totalAmount = array_reduce($summaryData, function ($carry, $item) {
                return $carry + (float)$item->Total_Amount;
            }, 0);

            return response()->json([
                "tables_used" => $selectedTables,
                "data"        => $data,
                "summary"     => $summaryData,
                "total_summary" => [
                    "Payment_Date" => "$startDate to $endDate",
                    "Total_Amount" => number_format($totalAmount, 4)
                ],
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




    public function dateWiseGrossProfit(Request $request)
    {
        try {
            
            $startDate = $request->start_date ?? date('Y-m-d', strtotime('-7 days'));
            $endDate = $request->end_date ?? date('Y-m-d');

            
            if (strtotime($startDate) > strtotime($endDate)) {
                return response()->json([
                    'status' => false,
                    'message' => "Start date cannot be after end date"
                ], 400);
            }

            
            $mapping = getDynamicTables();
            
            if (empty($mapping)) {
                return response()->json([
                    'status' => false,
                    'message' => "No table mapping found"
                ], 404);
            }

            
            $startMonth = date('Y-m', strtotime($startDate));
            $endMonth = date('Y-m', strtotime($endDate));
            
            
            if ($startMonth < $endMonth) {
                $temp = $startMonth;
                $startMonth = $endMonth;
                $endMonth = $temp;
            }

            
            $tables = filterRangeWithNext($mapping, $startMonth, $endMonth);
            
            if (empty($tables)) {
                
                $tables = [
                    $mapping[array_key_first($mapping)] ?? null,
                    $mapping[array_key_next($mapping)] ?? null
                ];
                $tables = array_filter($tables);
                
                if (empty($tables)) {
                    return response()->json([
                        'status' => false,
                        'message' => "No CDR tables found"
                    ], 404);
                }
            }

            
            $allTables = array_map(function($tableName) {
                return "Successfuliptsp." . $tableName;
            }, array_values($tables));

            
            $buildUnion = function($tableArray) {
                return implode(" UNION ALL ", array_map(fn($t) => "SELECT * FROM $t", $tableArray));
            };

            $unionAll = "(" . $buildUnion($allTables) . ") AS cdr";

            
            $dateWiseQuery = "
                SELECT 
                    DATE(FROM_UNIXTIME(connectTime/1000)) as call_date,
                    
                    -- Incoming calculations
                    SUM(CASE WHEN INET_NTOA(orgIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
                            AND INET_NTOA(terIPAddress) = '59.152.98.70'
                        THEN (ROUND((terBilledDuration/60) * 0.1, 0) - (ROUND((terBilledDuration/60) * 0.1, 0) * 0.03))
                        ELSE 0 END) as incoming_bill_day_wise,
                    
                    -- Outgoing calculations
                    SUM(CASE WHEN INET_NTOA(orgIPAddress) = '59.152.98.70'
                            AND INET_NTOA(terIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
                        THEN (ROUND((orgBilledDuration/60) * 0.35, 0) - (ROUND((orgBilledDuration/60) * 0.35, 0) * 0.03 + ROUND(((orgBilledDuration/60) * 0.14), 0)))
                        ELSE 0 END) as outgoing_bill_day_wise
                    
                FROM $unionAll
                WHERE DATE(FROM_UNIXTIME(connectTime/1000)) BETWEEN '$startDate' AND '$endDate'
                GROUP BY DATE(FROM_UNIXTIME(connectTime/1000))
                ORDER BY DATE(FROM_UNIXTIME(connectTime/1000)) ASC
            ";

            $dateWiseData = DB::connection('mysql5')->select($dateWiseQuery);

            
            $formattedData = array_map(function($row) {
                return [
                    'date' => $row->call_date,
                    'incoming_bill_day_wise' => intval($row->incoming_bill_day_wise ?? 0),
                    'outgoing_bill_day_wise' => intval($row->outgoing_bill_day_wise ?? 0),
                    'total_day_wise' => intval($row->incoming_bill_day_wise ?? 0) + intval($row->outgoing_bill_day_wise ?? 0)
                ];
            }, $dateWiseData);

            
            $totalIncoming = array_sum(array_column($formattedData, 'incoming_bill_day_wise'));
            $totalOutgoing = array_sum(array_column($formattedData, 'outgoing_bill_day_wise'));
            $grandTotal = $totalIncoming + $totalOutgoing;

            
            $periodSummary = DB::connection('mysql5')->selectOne("
                SELECT 
                    -- Incoming total for period
                    SUM(CASE WHEN INET_NTOA(orgIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
                            AND INET_NTOA(terIPAddress) = '59.152.98.70'
                        THEN (ROUND((terBilledDuration/60) * 0.1, 0) - (ROUND((terBilledDuration/60) * 0.1, 0) * 0.03))
                        ELSE 0 END) as incoming_total_period,
                    
                    -- Outgoing total for period
                    SUM(CASE WHEN INET_NTOA(orgIPAddress) = '59.152.98.70'
                            AND INET_NTOA(terIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
                        THEN (ROUND((orgBilledDuration/60) * 0.35, 0) - (ROUND((orgBilledDuration/60) * 0.35, 0) * 0.03 + ROUND(((orgBilledDuration/60) * 0.14), 0)))
                        ELSE 0 END) as outgoing_total_period
                    
                FROM $unionAll
                WHERE DATE(FROM_UNIXTIME(connectTime/1000)) BETWEEN '$startDate' AND '$endDate'
            ");

            return response()->json([
                'status' => true,
                'date_range' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ],
                'month_range' => [
                    'start_month' => $startMonth,
                    'end_month' => $endMonth
                ],
                'date_wise_data' => $formattedData,
                'summary' => [
                    'period_summary' => [
                        'date_range' => "$startDate to $endDate",
                        // 'total_incoming' => intval($periodSummary->incoming_total_period ?? 0),
                        // 'total_outgoing' => intval($periodSummary->outgoing_total_period ?? 0),
                        // 'grand_total' => intval($periodSummary->incoming_total_period ?? 0) + intval($periodSummary->outgoing_total_period ?? 0)
                    ],
                    'calculated_totals' => [
                        'total_incoming' => $totalIncoming,
                        'total_outgoing' => $totalOutgoing,
                        'grand_total' => $grandTotal
                    ]
                ],
                'tables_used' => [
                    'month_mapping' => $tables,
                    'table_names' => $allTables
                ],
                'date_column_used' => 'connectTime'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                "status" => false,
                "error"  => $e->getMessage(),
                "line"   => $e->getLine()
            ], 500);
        }
    }



    public function dateWiseRevenue(Request $request)
    {
        try {
            
            $startDate = $request->start_date ?? date('Y-m-d', strtotime('-7 days'));
            $endDate = $request->end_date ?? date('Y-m-d');

            
            if (strtotime($startDate) > strtotime($endDate)) {
                return response()->json([
                    'status' => false,
                    'message' => "Start date cannot be after end date"
                ], 400);
            }

            
            $mapping = getDynamicTables();
            
            if (empty($mapping)) {
                return response()->json([
                    'status' => false,
                    'message' => "No table mapping found"
                ], 404);
            }

            
            $startMonth = date('Y-m', strtotime($startDate));
            $endMonth = date('Y-m', strtotime($endDate));
            
            
            if ($startMonth < $endMonth) {
                $temp = $startMonth;
                $startMonth = $endMonth;
                $endMonth = $temp;
            }

            
            $tables = filterRangeWithNext($mapping, $startMonth, $endMonth);
            
            if (empty($tables)) {
                
                $tables = [
                    $mapping[array_key_first($mapping)] ?? null,
                    $mapping[array_key_next($mapping)] ?? null
                ];
                $tables = array_filter($tables);
                
                if (empty($tables)) {
                    return response()->json([
                        'status' => false,
                        'message' => "No CDR tables found"
                    ], 404);
                }
            }

            
            $allTables = array_map(function($tableName) {
                return "Successfuliptsp." . $tableName;
            }, array_values($tables));

            
            $buildUnion = function($tableArray) {
                return implode(" UNION ALL ", array_map(fn($t) => "SELECT * FROM $t", $tableArray));
            };

            $unionAll = "(" . $buildUnion($allTables) . ") AS cdr";

            
            $dateWiseQuery = "
                SELECT 
                    DATE(FROM_UNIXTIME(connectTime/1000)) as call_date,
                    
                    -- Incoming revenue calculations (without tax deduction)
                    SUM(CASE WHEN INET_NTOA(orgIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
                            AND INET_NTOA(terIPAddress) = '59.152.98.70'
                        THEN ROUND((terBilledDuration/60) * 0.1, 0)
                        ELSE 0 END) as incoming_revenue_day_wise,
                    
                    -- Outgoing revenue calculations (without tax deduction)
                    SUM(CASE WHEN INET_NTOA(terIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
                            AND INET_NTOA(orgIPAddress) = '59.152.98.70'
                        THEN ROUND((orgBilledDuration/60) * 0.35, 0)
                        ELSE 0 END) as outgoing_revenue_day_wise
                    
                FROM $unionAll
                WHERE DATE(FROM_UNIXTIME(connectTime/1000)) BETWEEN '$startDate' AND '$endDate'
                GROUP BY DATE(FROM_UNIXTIME(connectTime/1000))
                ORDER BY DATE(FROM_UNIXTIME(connectTime/1000)) ASC
            ";

            $dateWiseData = DB::connection('mysql5')->select($dateWiseQuery);

            
            $formattedData = array_map(function($row) {
                return [
                    'date' => $row->call_date,
                    'incoming_revenue_day_wise' => intval($row->incoming_revenue_day_wise ?? 0),
                    'outgoing_revenue_day_wise' => intval($row->outgoing_revenue_day_wise ?? 0),
                    'total_revenue_day_wise' => intval($row->incoming_revenue_day_wise ?? 0) + intval($row->outgoing_revenue_day_wise ?? 0)
                ];
            }, $dateWiseData);

            
            $totalIncoming = array_sum(array_column($formattedData, 'incoming_revenue_day_wise'));
            $totalOutgoing = array_sum(array_column($formattedData, 'outgoing_revenue_day_wise'));
            $grandTotal = $totalIncoming + $totalOutgoing;

            
            $periodSummary = DB::connection('mysql5')->selectOne("
                SELECT 
                    -- Incoming total for period (without tax deduction)
                    SUM(CASE WHEN INET_NTOA(orgIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
                            AND INET_NTOA(terIPAddress) = '59.152.98.70'
                        THEN ROUND((terBilledDuration/60) * 0.1, 0)
                        ELSE 0 END) as incoming_total_period,
                    
                    -- Outgoing total for period (without tax deduction)
                    SUM(CASE WHEN INET_NTOA(terIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
                            AND INET_NTOA(orgIPAddress) = '59.152.98.70'
                        THEN ROUND((orgBilledDuration/60) * 0.35, 0)
                        ELSE 0 END) as outgoing_total_period
                    
                FROM $unionAll
                WHERE DATE(FROM_UNIXTIME(connectTime/1000)) BETWEEN '$startDate' AND '$endDate'
            ");

            return response()->json([
                'status' => true,
                'date_range' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ],
                'month_range' => [
                    'start_month' => $startMonth,
                    'end_month' => $endMonth
                ],
                'date_wise_data' => $formattedData,
                'summary' => [
                    'period_summary' => [
                        'date_range' => "$startDate to $endDate",
                        'total_incoming_revenue' => intval($periodSummary->incoming_total_period ?? 0),
                        'total_outgoing_revenue' => intval($periodSummary->outgoing_total_period ?? 0),
                        'total_revenue' => intval($periodSummary->incoming_total_period ?? 0) + intval($periodSummary->outgoing_total_period ?? 0)
                    ],
                    'calculated_totals' => [
                        'total_incoming_revenue' => $totalIncoming,
                        'total_outgoing_revenue' => $totalOutgoing,
                        'total_revenue' => $grandTotal
                    ]
                ],
                'tables_used' => [
                    'month_mapping' => $tables,
                    'table_names' => $allTables
                ],
                'calculation_details' => [
                    'incoming_rate' => '0.1 per minute (GSM to Orbitalk)',
                    'outgoing_rate' => '0.35 per minute (Orbitalk to GSM)',
                    'note' => 'Revenue calculation without tax deduction',
                    'incoming_ips' => ['10.246.29.66', '10.246.29.74', '172.20.15.106'],
                    'termination_ip' => '59.152.98.70'
                ],
                'date_column_used' => 'connectTime'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                "status" => false,
                "error"  => $e->getMessage(),
                "line"   => $e->getLine()
            ], 500);
        }
    }


    public function getClients(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $phone = $request->input('phone');
        $callerid = $request->input('callerid');
        $nid = $request->input('nid');

        
        if (!$startDate || !$endDate) {
            $startDate = now()->subDays(7)->format('Y-m-d');
            $endDate = now()->format('Y-m-d');
        }

        
        $where = "c.clCustomerID LIKE '8801%' AND c.clIsDeleted = 0 AND ci.ciIsDeleted = 0 AND c.clStatus = 1
                AND FROM_UNIXTIME(vc.cdLastModificationTime/1000, '%Y-%m-%d') BETWEEN '$startDate' AND '$endDate'";

        if ($phone) {
            $where .= " AND c.clCustomerID LIKE '%$phone%'";
        }
        if ($callerid) {
            $where .= " AND ci.ciCallerIDCode LIKE '%$callerid%'";
        }
        if ($nid) {
            $where .= " AND vc.cdReferenceFormNo LIKE '%$nid%'";
        }

        
        $clients = DB::connection('mysql5')->select("
            SELECT DISTINCT
                c.clCustomerID AS phone,
                ci.ciCallerIDCode AS callerid,
                vc.cdReferenceFormNo AS nid,
                FROM_UNIXTIME(vc.cdLastModificationTime/1000, '%Y-%m-%d %H:%i:%s') AS createddate
            FROM iTelBillingiptsp.vbClient c
            LEFT JOIN iTelBillingiptsp.vbCallerID ci ON ci.ciAccountID = c.clAccountID
            LEFT JOIN iTelBillingiptsp.vbClientDetails vc ON vc.cdClientAccountID = c.clAccountID
            WHERE $where
            ORDER BY vc.cdLastModificationTime DESC
        ");

        
        $countResult = DB::connection('mysql5')->select("
            SELECT COUNT(DISTINCT c.clCustomerID) AS total
            FROM iTelBillingiptsp.vbClient c
            LEFT JOIN iTelBillingiptsp.vbCallerID ci ON ci.ciAccountID = c.clAccountID
            LEFT JOIN iTelBillingiptsp.vbClientDetails vc ON vc.cdClientAccountID = c.clAccountID
            WHERE $where
        ");
        $totalCount = $countResult[0]->total ?? 0;

        return response()->json([
            'totalCount' => $totalCount,
            'clients' => $clients
        ]);
    }



    



    // public function dateWiseRechargedAmountIptsp(Request $request)
    // {
    //     try {
            
    //         $startDate = $request->start_date
    //             ? $request->start_date . " 00:00:00"
    //             : date("Y-m-01 00:00:00");

    //         $endDate = $request->end_date
    //             ? $request->end_date . " 23:59:59"
    //             : date("Y-m-d 23:59:59");

    //         $startMonth = date("Y_m", strtotime($startDate));
    //         $endMonth   = date("Y_m", strtotime($endDate));

            
    //         $tableRows = DB::connection('mysql5')->select("
    //             SELECT TABLE_NAME
    //             FROM INFORMATION_SCHEMA.TABLES
    //             WHERE TABLE_SCHEMA = 'iTelBillingiptsp'
    //             AND TABLE_NAME LIKE 'vbPayment_%'
    //             ORDER BY TABLE_NAME DESC
    //         ");

    //         $selectedTables = [];
    //         foreach ($tableRows as $t) {
    //             $month = str_replace("vbPayment_", "", $t->TABLE_NAME);
    //             if ($month >= $startMonth && $month <= $endMonth) {
    //                 $selectedTables[] = $t->TABLE_NAME;
    //             }
    //         }

    //         if (empty($selectedTables)) {
    //             return response()->json([
    //                 "status" => false,
    //                 "message" => "No payment tables found for date range",
    //                 "date_range" => [
    //                     "start_date" => $startDate,
    //                     "end_date" => $endDate,
    //                     "start_month" => $startMonth,
    //                     "end_month" => $endMonth
    //                 ]
    //             ], 404);
    //         }

            
    //         $unionParts = [];
    //         $allBindings = [];

    //         foreach ($selectedTables as $table) {

                
    //             $where = "
    //                 FROM_UNIXTIME(p.pyDate/1000) BETWEEN ? AND ?
    //                 AND p.pyAmount > 0
                    
    //                 AND c.clCustomerID NOT LIKE '8801%'
    //                 AND p.pyPaymentGatewayType = 19
    //             ";

                
    //             $allBindings[] = $startDate;
    //             $allBindings[] = $endDate;

                
    //             if (!empty($request->pyUserName)) {
    //                 $where .= " AND p.pyUserName LIKE ? ";
    //                 $allBindings[] = "%" . $request->pyUserName . "%";
    //             }

    //             if (!empty($request->clCustomerID)) {
    //                 $where .= " AND c.clCustomerID LIKE ? ";
    //                 $allBindings[] = "%" . $request->clCustomerID . "%";
    //             }

    //             if (!empty($request->cdBillingName)) {
    //                 $where .= " AND d.cdBillingName LIKE ? ";
    //                 $allBindings[] = "%" . $request->cdBillingName . "%";
    //             }

    //             $unionParts[] = "
    //                 SELECT
    //                     DATE(FROM_UNIXTIME(p.pyDate/1000)) AS recharge_date,
    //                     SUM(p.pyAmount) AS daily_recharge_amount,
    //                     COUNT(*) AS transaction_count
    //                 FROM iTelBillingiptsp.$table p
    //                 JOIN iTelBillingiptsp.vbClient c ON p.pyAccountID = c.clAccountID
    //                 JOIN iTelBillingiptsp.vbPaymentType t ON p.pyPaymentGatewayType = t.ptID
    //                 JOIN iTelBillingiptsp.vbClientDetails d ON c.clAccountID = d.cdClientAccountID
    //                 WHERE $where
    //                 GROUP BY DATE(FROM_UNIXTIME(p.pyDate/1000))
    //             ";
    //         }

    //         $unionSql = implode(" UNION ALL ", $unionParts);

            
    //         $summarySql = "
    //             SELECT 
    //                 SUM(daily_recharge_amount) as total_recharge_amount,
    //                 SUM(transaction_count) as total_transactions,
    //                 COUNT(*) as total_days
    //             FROM ($unionSql) AS daily_summary
    //         ";

            
    //         $dailySql = $unionSql . " ORDER BY recharge_date ASC";

            
    //         $dailyData = DB::connection('mysql5')->select($dailySql, $allBindings);
    //         $summaryData = DB::connection('mysql5')->selectOne($summarySql, $allBindings);

            
    //         $formattedDailyData = array_map(function($row) {
    //             return [
    //                 'date' => $row->recharge_date,
    //                 'recharge_amount' => floatval($row->daily_recharge_amount ?? 0),
    //                 'transaction_count' => intval($row->transaction_count ?? 0),
    //                 'average_per_transaction' => $row->transaction_count > 0 
    //                     ? round($row->daily_recharge_amount / $row->transaction_count, 2)
    //                     : 0
    //             ];
    //         }, $dailyData);

            
    //         $totalRecharge = floatval($summaryData->total_recharge_amount ?? 0);
    //         $totalTransactions = intval($summaryData->total_transactions ?? 0);
    //         $totalDays = intval($summaryData->total_days ?? 0);

    //         return response()->json([
    //             "status" => true,
    //             "date_range" => [
    //                 "start_date" => $startDate,
    //                 "end_date" => $endDate,
    //                 "start_month" => $startMonth,
    //                 "end_month" => $endMonth
    //             ],
    //             "date_wise_data" => $formattedDailyData,
    //             "summary" => [
    //                 "period_summary" => [
    //                     "date_range" => date('Y-m-d', strtotime($startDate)) . " to " . date('Y-m-d', strtotime($endDate)),
    //                     "total_recharge_amount" => $totalRecharge,
    //                     "total_transactions" => $totalTransactions,
    //                     "total_days_with_recharge" => $totalDays,
    //                     "average_per_day" => $totalDays > 0 ? round($totalRecharge / $totalDays, 2) : 0,
    //                     "average_per_transaction" => $totalTransactions > 0 ? round($totalRecharge / $totalTransactions, 2) : 0
    //                 ]
    //             ],
    //             "filters_applied" => [
    //                 "clCustomerID_not_like" => "8801%",
    //                 "pyPaymentGatewayType" => 19,
    //                 "pyAmount_greater_than" => 0,
    //                 "pyUserName" => $request->pyUserName ?? "Not applied",
    //                 "clCustomerID" => $request->clCustomerID ?? "Not applied",
    //                 "cdBillingName" => $request->cdBillingName ?? "Not applied"
    //             ],
    //             "tables_used" => $selectedTables,
    //             "total_tables_used" => count($selectedTables)
    //         ]);

    //     } catch (\Exception $e) {
            
    //         Log::error("Date Wise Recharged Amount IPTSP Error: " . $e->getMessage(), ['exception' => $e]);

    //         return response()->json([
    //             "status" => false,
    //             "error" => $e->getMessage(),
    //             "line" => $e->getLine()
    //         ], 500);
    //     }
    // }


    public function dateWiseRechargedAmountIptsp(Request $request)
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
                ORDER BY TABLE_NAME DESC
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
                    "status" => false,
                    "message" => "No payment tables found for date range",
                    "date_range" => [
                        "start_date" => $startDate,
                        "end_date" => $endDate,
                        "start_month" => $startMonth,
                        "end_month" => $endMonth
                    ]
                ], 404);
            }

            $unionParts = [];
            $allBindings = [];

            foreach ($selectedTables as $table) {
                $where = "
                    FROM_UNIXTIME(p.pyDate/1000) BETWEEN ? AND ?
                    AND p.pyAmount > 0
                    AND c.clCustomerID NOT LIKE '8801%'
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

                if (!empty($request->cdBillingName)) {
                    $where .= " AND d.cdBillingName LIKE ? ";
                    $allBindings[] = "%" . $request->cdBillingName . "%";
                }

                $unionParts[] = "
                    SELECT
                        DATE(FROM_UNIXTIME(p.pyDate/1000)) AS recharge_date,
                        SUM(p.pyAmount) AS daily_recharge_amount,
                        COUNT(*) AS transaction_count
                    FROM iTelBillingiptsp.$table p
                    JOIN iTelBillingiptsp.vbClient c ON p.pyAccountID = c.clAccountID
                    JOIN iTelBillingiptsp.vbPaymentType t ON p.pyPaymentGatewayType = t.ptID
                    JOIN iTelBillingiptsp.vbClientDetails d ON c.clAccountID = d.cdClientAccountID
                    WHERE $where
                    GROUP BY DATE(FROM_UNIXTIME(p.pyDate/1000))
                ";
            }

            $unionSql = implode(" UNION ALL ", $unionParts);

            
            $summarySql = "
                SELECT 
                    SUM(daily_recharge_amount) AS total_recharge_amount,
                    SUM(transaction_count) AS total_transactions,
                    COUNT(*) AS total_days
                FROM ($unionSql) AS daily_summary
            ";

            
            $dailySql = $unionSql . " ORDER BY recharge_date ASC";

            
            $dailyData = DB::connection('mysql5')->select($dailySql, $allBindings);
            $summaryData = DB::connection('mysql5')->selectOne($summarySql, $allBindings);

            
            $formattedDailyData = array_map(function($row) {
                return [
                    'date' => $row->recharge_date,
                    'recharge_amount' => floatval($row->daily_recharge_amount ?? 0),
                    'transaction_count' => intval($row->transaction_count ?? 0),
                    'average_per_transaction' => $row->transaction_count > 0 
                        ? round($row->daily_recharge_amount / $row->transaction_count, 2)
                        : 0
                ];
            }, $dailyData);

            
            $totalRecharge = floatval($summaryData->total_recharge_amount ?? 0);
            $totalTransactions = intval($summaryData->total_transactions ?? 0);
            $totalDays = intval($summaryData->total_days ?? 0);

            
            $overallSummary = [
                "period_summary" => [
                    "date_range" => date('Y-m-d', strtotime($startDate)) . " to " . date('Y-m-d', strtotime($endDate)),
                    "total_recharge_amount" => $totalRecharge,
                    "total_transactions" => $totalTransactions,
                    "total_days_with_recharge" => $totalDays,
                    "average_per_day" => $totalDays > 0 ? round($totalRecharge / $totalDays, 2) : 0,
                    "average_per_transaction" => $totalTransactions > 0 ? round($totalRecharge / $totalTransactions, 2) : 0
                ],
                
                "total_summary" => [
                    "Payment_Date" => date('Y-m-d', strtotime($startDate)) . " to " . date('Y-m-d', strtotime($endDate)),
                    "Total_Amount" => number_format($totalRecharge, 4)
                ]
            ];

            return response()->json([
                "status" => true,
                "date_range" => [
                    "start_date" => $startDate,
                    "end_date" => $endDate,
                    "start_month" => $startMonth,
                    "end_month" => $endMonth
                ],
                "date_wise_data" => $formattedDailyData,
                "summary" => $overallSummary,
                "filters_applied" => [
                    "clCustomerID_not_like" => "8801%",
                    "pyPaymentGatewayType" => 19,
                    "pyAmount_greater_than" => 0,
                    "pyUserName" => $request->pyUserName ?? "Not applied",
                    "clCustomerID" => $request->clCustomerID ?? "Not applied",
                    "cdBillingName" => $request->cdBillingName ?? "Not applied"
                ],
                "tables_used" => $selectedTables,
                "total_tables_used" => count($selectedTables)
            ]);

        } catch (\Exception $e) {
            Log::error("Date Wise Recharged Amount IPTSP Error: " . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                "status" => false,
                "error" => $e->getMessage(),
                "line" => $e->getLine()
            ], 500);
        }
    }



    public function dateWiseGrossProfitIptsp(Request $request)
    {
        try {
            
            $startDate = $request->start_date ?? date('Y-m-d', strtotime('-7 days'));
            $endDate = $request->end_date ?? date('Y-m-d');

        
            if (strtotime($startDate) > strtotime($endDate)) {
                return response()->json([
                    'status' => false,
                    'message' => "Start date cannot be after end date"
                ], 400);
            }

            
            $mapping = getDynamicTables();
            
            if (empty($mapping)) {
                return response()->json([
                    'status' => false,
                    'message' => "No table mapping found"
                ], 404);
            }

            
            $startMonth = date('Y-m', strtotime($startDate));
            $endMonth = date('Y-m', strtotime($endDate));
            
            
            if ($startMonth < $endMonth) {
                $temp = $startMonth;
                $startMonth = $endMonth;
                $endMonth = $temp;
            }

            
            $tables = filterRangeWithNext($mapping, $startMonth, $endMonth);
            
            if (empty($tables)) {
                
                $tables = [
                    $mapping[array_key_first($mapping)] ?? null,
                    $mapping[array_key_next($mapping)] ?? null
                ];
                $tables = array_filter($tables);
                
                if (empty($tables)) {
                    return response()->json([
                        'status' => false,
                        'message' => "No CDR tables found"
                    ], 404);
                }
            }

            
            $allTables = array_map(function($tableName) {
                return "Successfuliptsp." . $tableName;
            }, array_values($tables));

            
            $buildUnion = function($tableArray) {
                return implode(" UNION ALL ", array_map(fn($t) => "SELECT * FROM $t", $tableArray));
            };

            $unionAll = "(" . $buildUnion($allTables) . ") AS cdr";

            
            $dateWiseQuery = "
                SELECT 
                    DATE(FROM_UNIXTIME(connectTime/1000)) as call_date,
                    
                    -- Incoming calculations (same as your grossProfitIptsp function)
                    SUM(CASE WHEN INET_NTOA(orgIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
                            AND INET_NTOA(terIPAddress) = '59.152.98.66'
                        THEN (ROUND((terBilledDuration/60) * 0.1, 0) - (ROUND((terBilledDuration/60) * 0.1, 0) * 0.03))
                        ELSE 0 END) as incoming_bill_day_wise,
                    
                    -- Outgoing calculations (same as your grossProfitIptsp function)
                    SUM(
                        -- For calls with billed amount > 0
                        (CASE WHEN INET_NTOA(orgIPAddress) != '59.152.98.70'
                            AND INET_NTOA(terIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
                            AND orgBilledAmount > 0
                        THEN (ROUND(orgBilledAmount, 0) - (ROUND(orgBilledAmount, 0) * 0.03 + ROUND(orgBilledAmount * 0.14, 0)))
                        ELSE 0 END)
                        
                        +
                        
                        -- For calls with billed amount = 0 (calculate based on duration)
                        (CASE WHEN INET_NTOA(orgIPAddress) != '59.152.98.70'
                            AND INET_NTOA(terIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
                            AND orgBilledAmount = 0
                        THEN (ROUND((orgBilledDuration/60) * 0.35, 0) - (ROUND((orgBilledDuration/60) * 0.35, 0) * 0.03 + ROUND(((orgBilledDuration/60) * 0.14), 0)))
                        ELSE 0 END)
                    ) as outgoing_bill_day_wise
                    
                FROM $unionAll
                WHERE DATE(FROM_UNIXTIME(connectTime/1000)) BETWEEN '$startDate' AND '$endDate'
                GROUP BY DATE(FROM_UNIXTIME(connectTime/1000))
                ORDER BY DATE(FROM_UNIXTIME(connectTime/1000)) ASC
            ";

            $dateWiseData = DB::connection('mysql5')->select($dateWiseQuery);

            
            $formattedData = array_map(function($row) {
                return [
                    'date' => $row->call_date,
                    'incoming_bill_day_wise' => intval($row->incoming_bill_day_wise ?? 0),
                    'outgoing_bill_day_wise' => intval($row->outgoing_bill_day_wise ?? 0),
                    'total_day_wise' => intval($row->incoming_bill_day_wise ?? 0) + intval($row->outgoing_bill_day_wise ?? 0)
                ];
            }, $dateWiseData);

            
            $totalIncoming = array_sum(array_column($formattedData, 'incoming_bill_day_wise'));
            $totalOutgoing = array_sum(array_column($formattedData, 'outgoing_bill_day_wise'));
            $grandTotal = $totalIncoming + $totalOutgoing;

            
            $periodSummary = DB::connection('mysql5')->selectOne("
                SELECT 
                    -- Incoming total for period
                    SUM(CASE WHEN INET_NTOA(orgIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
                            AND INET_NTOA(terIPAddress) = '59.152.98.66'
                        THEN (ROUND((terBilledDuration/60) * 0.1, 0) - (ROUND((terBilledDuration/60) * 0.1, 0) * 0.03))
                        ELSE 0 END) as incoming_total_period,
                    
                    -- Outgoing total for period
                    SUM(
                        -- For calls with billed amount > 0
                        (CASE WHEN INET_NTOA(orgIPAddress) != '59.152.98.70'
                            AND INET_NTOA(terIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
                            AND orgBilledAmount > 0
                        THEN (ROUND(orgBilledAmount, 0) - (ROUND(orgBilledAmount, 0) * 0.03 + ROUND(orgBilledAmount * 0.14, 0)))
                        ELSE 0 END)
                        
                        +
                        
                        -- For calls with billed amount = 0 (calculate based on duration)
                        (CASE WHEN INET_NTOA(orgIPAddress) != '59.152.98.70'
                            AND INET_NTOA(terIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
                            AND orgBilledAmount = 0
                        THEN (ROUND((orgBilledDuration/60) * 0.35, 0) - (ROUND((orgBilledDuration/60) * 0.35, 0) * 0.03 + ROUND(((orgBilledDuration/60) * 0.14), 0)))
                        ELSE 0 END)
                    ) as outgoing_total_period,
                    
                    -- Additional breakdown for outgoing calls
                    SUM(CASE WHEN INET_NTOA(orgIPAddress) != '59.152.98.70'
                            AND INET_NTOA(terIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
                            AND orgBilledAmount > 0
                        THEN ROUND(orgBilledDuration/60, 0) ELSE 0 END) as outgoing_minutes_with_amount,
                    
                    SUM(CASE WHEN INET_NTOA(orgIPAddress) != '59.152.98.70'
                            AND INET_NTOA(terIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
                            AND orgBilledAmount = 0
                        THEN ROUND(orgBilledDuration/60, 0) ELSE 0 END) as outgoing_minutes_no_amount
                    
                FROM $unionAll
                WHERE DATE(FROM_UNIXTIME(connectTime/1000)) BETWEEN '$startDate' AND '$endDate'
            ");

            return response()->json([
                'status' => true,
                'date_range' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ],
                'month_range' => [
                    'start_month' => $startMonth,
                    'end_month' => $endMonth
                ],
                'date_wise_data' => $formattedData,
                'summary' => [
                    'period_summary' => [
                        'date_range' => "$startDate to $endDate",
                        // 'total_incoming' => intval($periodSummary->incoming_total_period ?? 0),
                        // 'total_outgoing' => intval($periodSummary->outgoing_total_period ?? 0),
                        // 'total_gross_profit' => intval($periodSummary->incoming_total_period ?? 0) + intval($periodSummary->outgoing_total_period ?? 0),
                        // 'outgoing_breakdown' => [
                        //     'minutes_with_amount' => intval($periodSummary->outgoing_minutes_with_amount ?? 0),
                        //     'minutes_no_amount' => intval($periodSummary->outgoing_minutes_no_amount ?? 0),
                        //     'total_outgoing_minutes' => intval($periodSummary->outgoing_minutes_with_amount ?? 0) + intval($periodSummary->outgoing_minutes_no_amount ?? 0)
                        // ]
                    ],
                    'calculated_totals_from_daily' => [
                        'total_incoming' => $totalIncoming,
                        'total_outgoing' => $totalOutgoing,
                        'grand_total' => $grandTotal
                    ]
                ],
                'tables_used' => [
                    'month_mapping' => $tables,
                    'table_names' => $allTables
                ],
                'calculation_details' => [
                    'incoming_rate' => '0.1 per minute with 3% tax',
                    'outgoing_rate_with_amount' => 'orgBilledAmount with 3% tax + 14% commission',
                    'outgoing_rate_no_amount' => '0.35 per minute with 3% tax + 14% commission',
                    'incoming_ip' => '59.152.98.66',
                    'outgoing_exclude_ip' => '59.152.98.70',
                    'gsm_ips' => ['10.246.29.66', '10.246.29.74', '172.20.15.106']
                ],
                'date_column_used' => 'connectTime'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                "status" => false,
                "error"  => $e->getMessage(),
                "line"   => $e->getLine()
            ], 500);
        }
    }


    public function dateWiseRevenueIptsp(Request $request)
    {
        try {
            
            $startDate = $request->start_date ?? date('Y-m-d', strtotime('-7 days'));
            $endDate = $request->end_date ?? date('Y-m-d');

            
            if (strtotime($startDate) > strtotime($endDate)) {
                return response()->json([
                    'status' => false,
                    'message' => "Start date cannot be after end date"
                ], 400);
            }

            
            $mapping = getDynamicTables();
            
            if (empty($mapping)) {
                return response()->json([
                    'status' => false,
                    'message' => "No table mapping found"
                ], 404);
            }

            
            $startMonth = date('Y-m', strtotime($startDate));
            $endMonth = date('Y-m', strtotime($endDate));
            
            
            if ($startMonth < $endMonth) {
                $temp = $startMonth;
                $startMonth = $endMonth;
                $endMonth = $temp;
            }

            
            $tables = filterRangeWithNext($mapping, $startMonth, $endMonth);
            
            if (empty($tables)) {
                
                $tables = [
                    $mapping[array_key_first($mapping)] ?? null,
                    $mapping[array_key_next($mapping)] ?? null
                ];
                $tables = array_filter($tables);
                
                if (empty($tables)) {
                    return response()->json([
                        'status' => false,
                        'message' => "No CDR tables found"
                    ], 404);
                }
            }

            
            $allTables = array_map(function($tableName) {
                return "Successfuliptsp." . $tableName;
            }, array_values($tables));

            
            $buildUnion = function($tableArray) {
                return implode(" UNION ALL ", array_map(fn($t) => "SELECT * FROM $t", $tableArray));
            };

            $unionAll = "(" . $buildUnion($allTables) . ") AS cdr";

            
            $dateWiseQuery = "
                SELECT 
                    DATE(FROM_UNIXTIME(connectTime/1000)) as call_date,
                    
                    -- Incoming revenue calculations (IPTSP - without tax deduction)
                    SUM(CASE WHEN INET_NTOA(orgIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
                            AND INET_NTOA(terIPAddress) = '59.152.98.66'
                        THEN ROUND((terBilledDuration/60) * 0.1, 0)
                        ELSE 0 END) as incoming_revenue_day_wise,
                    
                    -- Outgoing revenue calculations (IPTSP - uses orgBilledAmount)
                    SUM(CASE WHEN INET_NTOA(orgIPAddress) != '59.152.98.70'
                            AND INET_NTOA(terIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
                            AND orgBilledAmount > 0
                        THEN ROUND(orgBilledAmount, 0)
                        ELSE 0 END) as outgoing_revenue_day_wise
                    
                FROM $unionAll
                WHERE DATE(FROM_UNIXTIME(connectTime/1000)) BETWEEN '$startDate' AND '$endDate'
                GROUP BY DATE(FROM_UNIXTIME(connectTime/1000))
                ORDER BY DATE(FROM_UNIXTIME(connectTime/1000)) ASC
            ";

            $dateWiseData = DB::connection('mysql5')->select($dateWiseQuery);

            
            $formattedData = array_map(function($row) {
                return [
                    'date' => $row->call_date,
                    'incoming_revenue_day_wise' => intval($row->incoming_revenue_day_wise ?? 0),
                    'outgoing_revenue_day_wise' => intval($row->outgoing_revenue_day_wise ?? 0),
                    'total_revenue_day_wise' => intval($row->incoming_revenue_day_wise ?? 0) + intval($row->outgoing_revenue_day_wise ?? 0)
                ];
            }, $dateWiseData);

            
            $totalIncoming = array_sum(array_column($formattedData, 'incoming_revenue_day_wise'));
            $totalOutgoing = array_sum(array_column($formattedData, 'outgoing_revenue_day_wise'));
            $grandTotal = $totalIncoming + $totalOutgoing;

            
            $periodSummary = DB::connection('mysql5')->selectOne("
                SELECT 
                    -- Incoming total for period (IPTSP - without tax deduction)
                    SUM(CASE WHEN INET_NTOA(orgIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
                            AND INET_NTOA(terIPAddress) = '59.152.98.66'
                        THEN ROUND((terBilledDuration/60) * 0.1, 0)
                        ELSE 0 END) as incoming_total_period,
                    
                    -- Outgoing total for period (IPTSP - uses orgBilledAmount)
                    SUM(CASE WHEN INET_NTOA(orgIPAddress) != '59.152.98.70'
                            AND INET_NTOA(terIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
                            AND orgBilledAmount > 0
                        THEN ROUND(orgBilledAmount, 0)
                        ELSE 0 END) as outgoing_total_period,
                    
                    -- Additional: Total outgoing minutes
                    SUM(CASE WHEN INET_NTOA(orgIPAddress) != '59.152.98.70'
                            AND INET_NTOA(terIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
                        THEN ROUND(orgBilledDuration/60, 0)
                        ELSE 0 END) as total_outgoing_minutes
                    
                FROM $unionAll
                WHERE DATE(FROM_UNIXTIME(connectTime/1000)) BETWEEN '$startDate' AND '$endDate'
            ");

            return response()->json([
                'status' => true,
                'date_range' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ],
                'month_range' => [
                    'start_month' => $startMonth,
                    'end_month' => $endMonth
                ],
                'date_wise_data' => $formattedData,
                'summary' => [
                    'period_summary' => [
                        'date_range' => "$startDate to $endDate",
                        'total_incoming_revenue' => intval($periodSummary->incoming_total_period ?? 0),
                        'total_outgoing_revenue' => intval($periodSummary->outgoing_total_period ?? 0),
                        'total_revenue' => intval($periodSummary->incoming_total_period ?? 0) + intval($periodSummary->outgoing_total_period ?? 0),
                        'total_outgoing_minutes' => intval($periodSummary->total_outgoing_minutes ?? 0)
                    ],
                    'calculated_totals' => [
                        'total_incoming_revenue' => $totalIncoming,
                        'total_outgoing_revenue' => $totalOutgoing,
                        'total_revenue' => $grandTotal
                    ]
                ],
                'tables_used' => [
                    'month_mapping' => $tables,
                    'table_names' => $allTables
                ],
                'calculation_details' => [
                    'incoming_rate' => '0.1 per minute (GSM to IPTSP)',
                    'outgoing_calculation' => 'Uses orgBilledAmount directly (when > 0)',
                    'note' => 'IPTSP revenue calculation - incoming uses duration rate, outgoing uses billed amount',
                    'incoming_ips' => ['10.246.29.66', '10.246.29.74', '172.20.15.106'],
                    'termination_ip' => '59.152.98.66',
                    'excluded_ip' => '59.152.98.70 (excluded from outgoing)'
                ],
                'date_column_used' => 'connectTime'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                "status" => false,
                "error"  => $e->getMessage(),
                "line"   => $e->getLine()
            ], 500);
        }
    }



    
    public function getClientsIptsp(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $phone = $request->input('phone');
        $callerid = $request->input('callerid');
        $nid = $request->input('nid');

        
        if (!$startDate || !$endDate) {
            $startDate = now()->subDays(7)->format('Y-m-d');
            $endDate = now()->format('Y-m-d');
        }

        
        $where = "c.clCustomerID NOT LIKE '8801%' AND c.clIsDeleted = 0 AND ci.ciIsDeleted = 0 AND c.clStatus = 1
                AND FROM_UNIXTIME(vc.cdLastModificationTime/1000, '%Y-%m-%d') BETWEEN '$startDate' AND '$endDate'";

        if ($phone) {
            $where .= " AND c.clCustomerID LIKE '%$phone%'";
        }
        if ($callerid) {
            $where .= " AND ci.ciCallerIDCode LIKE '%$callerid%'";
        }
        if ($nid) {
            $where .= " AND vc.cdReferenceFormNo LIKE '%$nid%'";
        }

        
        $clients = DB::connection('mysql5')->select("
            SELECT DISTINCT
                c.clCustomerID AS phone,
                ci.ciCallerIDCode AS callerid,
                vc.cdReferenceFormNo AS nid,
                FROM_UNIXTIME(vc.cdLastModificationTime/1000, '%Y-%m-%d %H:%i:%s') AS createddate
            FROM iTelBillingiptsp.vbClient c
            LEFT JOIN iTelBillingiptsp.vbCallerID ci ON ci.ciAccountID = c.clAccountID
            LEFT JOIN iTelBillingiptsp.vbClientDetails vc ON vc.cdClientAccountID = c.clAccountID
            WHERE $where
            ORDER BY vc.cdLastModificationTime DESC
        ");

        
        $countResult = DB::connection('mysql5')->select("
            SELECT COUNT(DISTINCT c.clCustomerID) AS total
            FROM iTelBillingiptsp.vbClient c
            LEFT JOIN iTelBillingiptsp.vbCallerID ci ON ci.ciAccountID = c.clAccountID
            LEFT JOIN iTelBillingiptsp.vbClientDetails vc ON vc.cdClientAccountID = c.clAccountID
            WHERE $where
        ");
        $totalCount = $countResult[0]->total ?? 0;

        return response()->json([
            'totalCount' => $totalCount,
            'clients' => $clients
        ]);
    }


}
