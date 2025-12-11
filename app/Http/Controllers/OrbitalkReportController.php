<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use DateTime;
use Illuminate\Support\Facades\Log;


class OrbitalkReportController extends Controller
{


    // date wise payment report orbitalk

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
    //             AND TABLE_NAME LIKE 'vbPayment_%'
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
    //         $summaryParts = [];
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

                
    //             $summaryParts[] = "
    //                 SELECT
    //                     DATE(FROM_UNIXTIME(p.pyDate/1000)) AS Payment_Date,
    //                     SUM(p.pyAmount) AS Total_Amount
    //                 FROM iTelBillingiptsp.$table p
    //                 JOIN iTelBillingiptsp.vbClient c ON p.pyAccountID = c.clAccountID
    //                 WHERE $where
    //                 GROUP BY DATE(FROM_UNIXTIME(p.pyDate/1000))
    //             ";
    //         }

    //         $unionSql = implode(" UNION ALL ", $unionParts);
    //         $summarySql = implode(" UNION ALL ", $summaryParts);

    //         $page = $request->page ?? 1;
    //         $perPage = 25;
    //         $offset = ($page - 1) * $perPage;

            
    //         $sqlPaginated = $unionSql . " LIMIT $perPage OFFSET $offset";

            
    //         $countSql = "SELECT COUNT(*) AS total FROM ($unionSql) AS t";

            
    //         $data = DB::connection('mysql5')->select($sqlPaginated, $allBindings);
    //         $total = DB::connection('mysql5')->selectOne($countSql, $allBindings)->total;

            
    //         $summaryData = DB::connection('mysql5')->select($summarySql, $allBindings);

            
    //         $totalAmount = array_reduce($summaryData, function ($carry, $item) {
    //             return $carry + (float)$item->Total_Amount;
    //         }, 0);

    //         return response()->json([
    //             "tables_used" => $selectedTables,
    //             "data"        => $data,
    //             "summary"     => $summaryData,
    //             "total_summary" => [
    //                 "Payment_Date" => "$startDate to $endDate",
    //                 "Total_Amount" => number_format($totalAmount, 4)
    //             ],
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



    public function paymentReport(Request $request)
    {
        try {
            $startDate = $request->start_date
                ? $request->start_date . " 00:00:00"
                : null;

            $endDate = $request->end_date
                ? $request->end_date . " 23:59:59"
                : null;

            $viewType = $request->view_type ?? 'details'; // 'details' or 'day_wise'

            // Don't auto-fetch if dates are not provided
            if (!$startDate || !$endDate) {
                return response()->json([
                    "status" => true,
                    "data" => [],
                    "summary" => [],
                    "total_summary" => [],
                    "tables_used" => [],
                    "pagination" => [
                        "current_page" => 1,
                        "per_page" => 25,
                        "total" => 0,
                        "last_page" => 1,
                        "from" => 0,
                        "to" => 0
                    ],
                    "message" => "Select date range to view payment data"
                ]);
            }

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
                    "status" => true,
                    "data" => [],
                    "summary" => [],
                    "total_summary" => [
                        "Payment_Date" => "$startDate to $endDate",
                        "Total_Amount" => "0.0000"
                    ],
                    "tables_used" => [],
                    "pagination" => [
                        "current_page" => 1,
                        "per_page" => 25,
                        "total" => 0,
                        "last_page" => 1,
                        "from" => 0,
                        "to" => 0
                    ],
                    "message" => "No payment tables found for date range"
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

                $bindings = [$startDate, $endDate];

                if (!empty($request->pyUserName)) {
                    $where .= " AND p.pyUserName LIKE ? ";
                    $bindings[] = "%" . $request->pyUserName . "%";
                }

                if (!empty($request->clCustomerID)) {
                    $where .= " AND c.clCustomerID LIKE ? ";
                    $bindings[] = "%" . $request->clCustomerID . "%";
                }

                if (!empty($request->cdBillingName)) {
                    $where .= " AND d.cdBillingName LIKE ? ";
                    $bindings[] = "%" . $request->cdBillingName . "%";
                }

                if ($viewType === 'day_wise') {
                    // Day wise query
                    $unionParts[] = "
                        SELECT
                            DATE(FROM_UNIXTIME(p.pyDate/1000)) AS date,
                            SUM(p.pyAmount) AS recharge_amount,
                            COUNT(*) AS transaction_count
                        FROM iTelBillingiptsp.$table p
                        JOIN iTelBillingiptsp.vbClient c ON p.pyAccountID = c.clAccountID
                        JOIN iTelBillingiptsp.vbClientDetails d ON c.clAccountID = d.cdClientAccountID
                        WHERE $where
                        GROUP BY DATE(FROM_UNIXTIME(p.pyDate/1000))
                    ";
                } else {
                    // Details query
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

                $allBindings = array_merge($allBindings, $bindings);
            }

            $unionSql = implode(" UNION ALL ", $unionParts);

            $page = $request->page ?? 1;
            $perPage = $request->per_page ?? 25;
            $offset = ($page - 1) * $perPage;

            // Count total records
            $countSql = "SELECT COUNT(*) AS total FROM ($unionSql) AS t";
            $totalResult = DB::connection('mysql5')->selectOne($countSql, $allBindings);
            $total = $totalResult ? $totalResult->total : 0;

            // Get paginated data with appropriate ordering
            if ($viewType === 'day_wise') {
                $sqlPaginated = $unionSql . " ORDER BY date DESC LIMIT $perPage OFFSET $offset";
            } else {
                $sqlPaginated = $unionSql . " ORDER BY Payment_Date DESC LIMIT $perPage OFFSET $offset";
            }

            $data = DB::connection('mysql5')->select($sqlPaginated, $allBindings);

            // Get summary statistics
            $summarySql = "
                SELECT 
                    SUM(p.pyAmount) AS total_recharge_amount,
                    COUNT(*) AS total_transactions,
                    COUNT(DISTINCT DATE(FROM_UNIXTIME(p.pyDate/1000))) AS total_days
                FROM (
                    " . implode(" UNION ALL ", array_map(fn($table) => "SELECT * FROM iTelBillingiptsp.$table", $selectedTables)) . "
                ) p
                JOIN iTelBillingiptsp.vbClient c ON p.pyAccountID = c.clAccountID
                WHERE FROM_UNIXTIME(p.pyDate/1000) BETWEEN ? AND ?
                AND p.pyAmount > 0
                AND c.clCustomerID LIKE '8801%'
                AND p.pyPaymentGatewayType = 19
            ";

            $summaryData = DB::connection('mysql5')->selectOne($summarySql, [$startDate, $endDate]);

            $totalRecharge = floatval($summaryData->total_recharge_amount ?? 0);
            $totalTransactions = intval($summaryData->total_transactions ?? 0);
            $totalDays = intval($summaryData->total_days ?? 0);

            // Calculate averages
            $averagePerDay = $totalDays > 0 ? round($totalRecharge / $totalDays, 2) : 0;
            $averagePerTransaction = $totalTransactions > 0 ? round($totalRecharge / $totalTransactions, 2) : 0;

            // Format response based on view type
            if ($viewType === 'day_wise') {
                $formattedData = array_map(function($row) {
                    return [
                        'date' => $row->date,
                        'recharge_amount' => floatval($row->recharge_amount ?? 0),
                        'transaction_count' => intval($row->transaction_count ?? 0),
                        'average_per_transaction' => $row->transaction_count > 0 
                            ? round($row->recharge_amount / $row->transaction_count, 2)
                            : 0
                    ];
                }, $data);
            } else {
                $formattedData = $data;
            }

            return response()->json([
                "status" => true,
                "tables_used" => $selectedTables,
                "view_type" => $viewType,
                "data" => $formattedData,
                "summary" => [
                    "period_summary" => [
                        "date_range" => date("Y-m-d", strtotime($startDate)) . " to " . date("Y-m-d", strtotime($endDate)),
                        "total_recharge_amount" => $totalRecharge,
                        "total_transactions" => $totalTransactions,
                        "total_days_with_recharge" => $totalDays,
                        "average_per_day" => $averagePerDay,
                        "average_per_transaction" => $averagePerTransaction
                    ],
                    "total_summary" => [
                        "Payment_Date" => date("Y-m-d", strtotime($startDate)) . " to " . date("Y-m-d", strtotime($endDate)),
                        "Total_Amount" => number_format($totalRecharge, 4)
                    ]
                ],
                "pagination" => [
                    "current_page" => (int)$page,
                    "per_page" => (int)$perPage,
                    "total" => (int)$total,
                    "last_page" => ceil($total / $perPage),
                    "from" => $offset + 1,
                    "to" => min($offset + $perPage, $total)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Payment Report Error: " . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                "status" => false,
                "error" => $e->getMessage()
            ], 500);
        }
    }

    public function exportPaymentReport(Request $request)
    {
        try {
            $startDate = $request->start_date
                ? $request->start_date . " 00:00:00"
                : null;

            $endDate = $request->end_date
                ? $request->end_date . " 23:59:59"
                : null;

            // Don't export if dates are not provided
            if (!$startDate || !$endDate) {
                return response()->json([
                    "status" => false,
                    "message" => "Please select date range to export"
                ], 400);
            }

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
                    "status" => false,
                    "message" => "No payment tables found for date range"
                ], 404);
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

                // Main data query for export
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

                // Summary query for export
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

            // Get all data for export
            $data = DB::connection('mysql5')->select($unionSql . " ORDER BY Payment_Date DESC", $allBindings);
            
            // Get summary data for export
            $summaryData = DB::connection('mysql5')->select($summarySql, $allBindings);

            // Calculate total amount
            $totalAmount = array_reduce($summaryData, function ($carry, $item) {
                return $carry + (float)($item->Total_Amount ?? 0);
            }, 0);

            // Create CSV content
            $csvData = [];
            
            // Headers
            $csvData[] = ['Payment Date', 'Amount', 'User Name', 'Customer ID', 'Parent Account ID', 'Billing Name', 'Company Name'];
            
            // Data rows
            foreach ($data as $row) {
                $csvData[] = [
                    $row->Payment_Date,
                    number_format($row->Amount, 4),
                    $row->pyUserName,
                    $row->clCustomerID,
                    $row->clParentAccountID,
                    $row->cdBillingName,
                    $row->cdCompanyName
                ];
            }
            
            // Add summary
            $csvData[] = ['', '', '', '', '', '', ''];
            $csvData[] = ['Summary by Date', 'Total Amount', '', '', '', '', ''];
            
            foreach ($summaryData as $summary) {
                $csvData[] = [
                    $summary->Payment_Date,
                    number_format($summary->Total_Amount, 4),
                    '', '', '', '', ''
                ];
            }
            
            $csvData[] = ['', '', '', '', '', '', ''];
            $csvData[] = ['Total Summary', '', '', '', '', '', ''];
            $csvData[] = ['Date Range', date("Y-m-d", strtotime($startDate)) . " to " . date("Y-m-d", strtotime($endDate)), '', '', '', '', ''];
            $csvData[] = ['Total Amount', number_format($totalAmount, 4), '', '', '', '', ''];

            // Convert to CSV string
            $output = fopen('php://output', 'w');
            ob_start();
            foreach ($csvData as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
            $csv = ob_get_clean();

            // Set headers for CSV download
            $filename = "payment-report-" . date("Y-m-d", strtotime($startDate)) . "-to-" . date("Y-m-d", strtotime($endDate)) . ".csv";
            
            return response($csv)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');

        } catch (\Exception $e) {
            Log::error("Payment Export Error: " . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                "status" => false,
                "error" => $e->getMessage()
            ], 500);
        }
    }




    // public function dateWiseGrossProfit(Request $request)
    // {
    //     try {
            
    //         $startDate = $request->start_date ?? date('Y-m-d', strtotime('-7 days'));
    //         $endDate = $request->end_date ?? date('Y-m-d');

            
    //         if (strtotime($startDate) > strtotime($endDate)) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => "Start date cannot be after end date"
    //             ], 400);
    //         }

            
    //         $mapping = getDynamicTables();
            
    //         if (empty($mapping)) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => "No table mapping found"
    //             ], 404);
    //         }

            
    //         $startMonth = date('Y-m', strtotime($startDate));
    //         $endMonth = date('Y-m', strtotime($endDate));
            
            
    //         if ($startMonth < $endMonth) {
    //             $temp = $startMonth;
    //             $startMonth = $endMonth;
    //             $endMonth = $temp;
    //         }

            
    //         $tables = filterRangeWithNext($mapping, $startMonth, $endMonth);
            
    //         // if (empty($tables)) {
                
    //         //     $tables = [
    //         //         $mapping[array_key_first($mapping)] ?? null,
    //         //         $mapping[array_key_next($mapping)] ?? null
    //         //     ];
    //         //     $tables = array_filter($tables);
                
    //         //     if (empty($tables)) {
    //         //         return response()->json([
    //         //             'status' => false,
    //         //             'message' => "No CDR tables found"
    //         //         ], 404);
    //         //     }
    //         // }


    //         if (empty($tables)) {

    //             // Get first 2 values from $mapping
    //             $firstTwo = array_slice(array_values($mapping), 0, 2);

    //             $tables = array_filter($firstTwo);

    //             if (empty($tables)) {
    //                 return response()->json([
    //                     'status' => false,
    //                     'message' => "No CDR tables found"
    //                 ], 404);
    //             }
    //         }


            
    //         $allTables = array_map(function($tableName) {
    //             return "Successfuliptsp." . $tableName;
    //         }, array_values($tables));

            
    //         $buildUnion = function($tableArray) {
    //             return implode(" UNION ALL ", array_map(fn($t) => "SELECT * FROM $t", $tableArray));
    //         };

    //         $unionAll = "(" . $buildUnion($allTables) . ") AS cdr";

            
    //         $dateWiseQuery = "
    //             SELECT 
    //                 DATE(FROM_UNIXTIME(connectTime/1000)) as call_date,
                    
    //                 -- Incoming calculations
    //                 SUM(CASE WHEN INET_NTOA(orgIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
    //                         AND INET_NTOA(terIPAddress) = '59.152.98.70'
    //                     THEN (ROUND((terBilledDuration/60) * 0.1, 0) - (ROUND((terBilledDuration/60) * 0.1, 0) * 0.03))
    //                     ELSE 0 END) as incoming_bill_day_wise,
                    
    //                 -- Outgoing calculations
    //                 SUM(CASE WHEN INET_NTOA(orgIPAddress) = '59.152.98.70'
    //                         AND INET_NTOA(terIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
    //                     THEN (ROUND((orgBilledDuration/60) * 0.35, 0) - (ROUND((orgBilledDuration/60) * 0.35, 0) * 0.03 + ROUND(((orgBilledDuration/60) * 0.14), 0)))
    //                     ELSE 0 END) as outgoing_bill_day_wise
                    
    //             FROM $unionAll
    //             WHERE DATE(FROM_UNIXTIME(connectTime/1000)) BETWEEN '$startDate' AND '$endDate'
    //             GROUP BY DATE(FROM_UNIXTIME(connectTime/1000))
    //             ORDER BY DATE(FROM_UNIXTIME(connectTime/1000)) ASC
    //         ";

    //         $dateWiseData = DB::connection('mysql5')->select($dateWiseQuery);

            
    //         $formattedData = array_map(function($row) {
    //             return [
    //                 'date' => $row->call_date,
    //                 'incoming_bill_day_wise' => intval($row->incoming_bill_day_wise ?? 0),
    //                 'outgoing_bill_day_wise' => intval($row->outgoing_bill_day_wise ?? 0),
    //                 'total_day_wise' => intval($row->incoming_bill_day_wise ?? 0) + intval($row->outgoing_bill_day_wise ?? 0)
    //             ];
    //         }, $dateWiseData);

            
    //         $totalIncoming = array_sum(array_column($formattedData, 'incoming_bill_day_wise'));
    //         $totalOutgoing = array_sum(array_column($formattedData, 'outgoing_bill_day_wise'));
    //         $grandTotal = $totalIncoming + $totalOutgoing;

            
    //         $periodSummary = DB::connection('mysql5')->selectOne("
    //             SELECT 
    //                 -- Incoming total for period
    //                 SUM(CASE WHEN INET_NTOA(orgIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
    //                         AND INET_NTOA(terIPAddress) = '59.152.98.70'
    //                     THEN (ROUND((terBilledDuration/60) * 0.1, 0) - (ROUND((terBilledDuration/60) * 0.1, 0) * 0.03))
    //                     ELSE 0 END) as incoming_total_period,
                    
    //                 -- Outgoing total for period
    //                 SUM(CASE WHEN INET_NTOA(orgIPAddress) = '59.152.98.70'
    //                         AND INET_NTOA(terIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
    //                     THEN (ROUND((orgBilledDuration/60) * 0.35, 0) - (ROUND((orgBilledDuration/60) * 0.35, 0) * 0.03 + ROUND(((orgBilledDuration/60) * 0.14), 0)))
    //                     ELSE 0 END) as outgoing_total_period
                    
    //             FROM $unionAll
    //             WHERE DATE(FROM_UNIXTIME(connectTime/1000)) BETWEEN '$startDate' AND '$endDate'
    //         ");

    //         return response()->json([
    //             'status' => true,
    //             'date_range' => [
    //                 'start_date' => $startDate,
    //                 'end_date' => $endDate
    //             ],
    //             'month_range' => [
    //                 'start_month' => $startMonth,
    //                 'end_month' => $endMonth
    //             ],
    //             'date_wise_data' => $formattedData,
    //             'summary' => [
    //                 'period_summary' => [
    //                     'date_range' => "$startDate to $endDate",
    //                     // 'total_incoming' => intval($periodSummary->incoming_total_period ?? 0),
    //                     // 'total_outgoing' => intval($periodSummary->outgoing_total_period ?? 0),
    //                     // 'grand_total' => intval($periodSummary->incoming_total_period ?? 0) + intval($periodSummary->outgoing_total_period ?? 0)
    //                 ],
    //                 'calculated_totals' => [
    //                     'total_incoming' => $totalIncoming,
    //                     'total_outgoing' => $totalOutgoing,
    //                     'grand_total' => $grandTotal
    //                 ]
    //             ],
    //             'tables_used' => [
    //                 'month_mapping' => $tables,
    //                 'table_names' => $allTables
    //             ],
    //             'date_column_used' => 'connectTime'
    //         ]);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             "status" => false,
    //             "error"  => $e->getMessage(),
    //             "line"   => $e->getLine()
    //         ], 500);
    //     }
    // }


    

    public function dateWiseGrossProfit(Request $request)
    {
        try {
            
            $startDate = $request->start_date ?? date('Y-m-d', strtotime('-2 days'));
            $endDate = $request->end_date ?? date('Y-m-d');
            
            
            $perPage = $request->per_page ?? 10;
            $page = $request->page ?? 1;
            $offset = ($page - 1) * $perPage;

            
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
                $firstTwo = array_slice(array_values($mapping), 0, 2);
                $tables = array_filter($firstTwo);
                
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

            
            $countQuery = "
                SELECT COUNT(*) as total
                FROM (
                    SELECT DATE(FROM_UNIXTIME(connectTime/1000)) as call_date
                    FROM $unionAll
                    WHERE DATE(FROM_UNIXTIME(connectTime/1000)) BETWEEN '$startDate' AND '$endDate'
                    GROUP BY DATE(FROM_UNIXTIME(connectTime/1000))
                ) as date_counts
            ";

            $countResult = DB::connection('mysql5')->selectOne($countQuery);
            $totalRecords = $countResult->total ?? 0;
            $lastPage = ceil($totalRecords / $perPage);

        
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
                ORDER BY DATE(FROM_UNIXTIME(connectTime/1000)) DESC
                LIMIT $perPage OFFSET $offset
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
                'data' => $formattedData,
                'summary' => [
                    'period_summary' => [
                        'date_range' => "$startDate to $endDate",
                    ],
                    'calculated_totals' => [
                        'total_incoming' => intval($periodSummary->incoming_total_period ?? 0),
                        'total_outgoing' => intval($periodSummary->outgoing_total_period ?? 0),
                        'grand_total' => intval($periodSummary->incoming_total_period ?? 0) + intval($periodSummary->outgoing_total_period ?? 0)
                    ]
                ],
                'pagination' => [
                    'current_page' => (int)$page,
                    'per_page' => (int)$perPage,
                    'total' => (int)$totalRecords,
                    'last_page' => (int)$lastPage,
                    'from' => $offset + 1,
                    'to' => min($offset + $perPage, $totalRecords)
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

    

    public function exportDateWiseGrossProfit(Request $request)
    {
        try {
            
            $startDate = $request->start_date ?? date('Y-m-d', strtotime('-2 days'));
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
                $firstTwo = array_slice(array_values($mapping), 0, 2);
                $tables = array_filter($firstTwo);
                
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
                ORDER BY DATE(FROM_UNIXTIME(connectTime/1000)) DESC
            ";

            $data = DB::connection('mysql5')->select($dateWiseQuery);

            
            $csvData = [];
            
            
            $csvData[] = ['Date', 'Incoming Bill (৳)', 'Outgoing Bill (৳)', 'Total (৳)'];
            
            
            foreach ($data as $row) {
                $total = $row->incoming_bill_day_wise + $row->outgoing_bill_day_wise;
                $csvData[] = [
                    $row->call_date,
                    number_format($row->incoming_bill_day_wise, 2),
                    number_format($row->outgoing_bill_day_wise, 2),
                    number_format($total, 2)
                ];
            }
            
            
            $totalIncoming = array_sum(array_column($data, 'incoming_bill_day_wise'));
            $totalOutgoing = array_sum(array_column($data, 'outgoing_bill_day_wise'));
            $grandTotal = $totalIncoming + $totalOutgoing;
            
            $csvData[] = ['', '', '', ''];
            $csvData[] = ['Summary', '', '', ''];
            $csvData[] = ['Total Incoming', number_format($totalIncoming, 2), '', ''];
            $csvData[] = ['Total Outgoing', '', number_format($totalOutgoing, 2), ''];
            $csvData[] = ['Grand Total', '', '', number_format($grandTotal, 2)];
            $csvData[] = ['', '', '', ''];
            $csvData[] = ['Date Range', "$startDate to $endDate", '', ''];

            
            $output = fopen('php://output', 'w');
            ob_start();
            foreach ($csvData as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
            $csv = ob_get_clean();

            
            $filename = "gross-profit-report-" . $startDate . "-to-" . $endDate . ".csv";
            
            return response($csv)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }


    // public function dateWiseRevenue(Request $request)
    // {
    //     try {
            
    //         $startDate = $request->start_date ?? date('Y-m-d', strtotime('-7 days'));
    //         $endDate = $request->end_date ?? date('Y-m-d');

            
    //         if (strtotime($startDate) > strtotime($endDate)) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => "Start date cannot be after end date"
    //             ], 400);
    //         }

            
    //         $mapping = getDynamicTables();
            
    //         if (empty($mapping)) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => "No table mapping found"
    //             ], 404);
    //         }

            
    //         $startMonth = date('Y-m', strtotime($startDate));
    //         $endMonth = date('Y-m', strtotime($endDate));
            
            
    //         if ($startMonth < $endMonth) {
    //             $temp = $startMonth;
    //             $startMonth = $endMonth;
    //             $endMonth = $temp;
    //         }

            
    //         $tables = filterRangeWithNext($mapping, $startMonth, $endMonth);
            
    //         if (empty($tables)) {

                
    //             $firstTwo = array_slice(array_values($mapping), 0, 2);

    //             $tables = array_filter($firstTwo);

    //             if (empty($tables)) {
    //                 return response()->json([
    //                     'status' => false,
    //                     'message' => "No CDR tables found"
    //                 ], 404);
    //             }
    //         }


            
    //         $allTables = array_map(function($tableName) {
    //             return "Successfuliptsp." . $tableName;
    //         }, array_values($tables));

            
    //         $buildUnion = function($tableArray) {
    //             return implode(" UNION ALL ", array_map(fn($t) => "SELECT * FROM $t", $tableArray));
    //         };

    //         $unionAll = "(" . $buildUnion($allTables) . ") AS cdr";

            
    //         $dateWiseQuery = "
    //             SELECT 
    //                 DATE(FROM_UNIXTIME(connectTime/1000)) as call_date,
                    
    //                 -- Incoming revenue calculations (without tax deduction)
    //                 SUM(CASE WHEN INET_NTOA(orgIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
    //                         AND INET_NTOA(terIPAddress) = '59.152.98.70'
    //                     THEN ROUND((terBilledDuration/60) * 0.1, 0)
    //                     ELSE 0 END) as incoming_revenue_day_wise,
                    
    //                 -- Outgoing revenue calculations (without tax deduction)
    //                 SUM(CASE WHEN INET_NTOA(terIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
    //                         AND INET_NTOA(orgIPAddress) = '59.152.98.70'
    //                     THEN ROUND((orgBilledDuration/60) * 0.35, 0)
    //                     ELSE 0 END) as outgoing_revenue_day_wise
                    
    //             FROM $unionAll
    //             WHERE DATE(FROM_UNIXTIME(connectTime/1000)) BETWEEN '$startDate' AND '$endDate'
    //             GROUP BY DATE(FROM_UNIXTIME(connectTime/1000))
    //             ORDER BY DATE(FROM_UNIXTIME(connectTime/1000)) ASC
    //         ";

    //         $dateWiseData = DB::connection('mysql5')->select($dateWiseQuery);

            
    //         $formattedData = array_map(function($row) {
    //             return [
    //                 'date' => $row->call_date,
    //                 'incoming_revenue_day_wise' => intval($row->incoming_revenue_day_wise ?? 0),
    //                 'outgoing_revenue_day_wise' => intval($row->outgoing_revenue_day_wise ?? 0),
    //                 'total_revenue_day_wise' => intval($row->incoming_revenue_day_wise ?? 0) + intval($row->outgoing_revenue_day_wise ?? 0)
    //             ];
    //         }, $dateWiseData);

            
    //         $totalIncoming = array_sum(array_column($formattedData, 'incoming_revenue_day_wise'));
    //         $totalOutgoing = array_sum(array_column($formattedData, 'outgoing_revenue_day_wise'));
    //         $grandTotal = $totalIncoming + $totalOutgoing;

            
    //         $periodSummary = DB::connection('mysql5')->selectOne("
    //             SELECT 
    //                 -- Incoming total for period (without tax deduction)
    //                 SUM(CASE WHEN INET_NTOA(orgIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
    //                         AND INET_NTOA(terIPAddress) = '59.152.98.70'
    //                     THEN ROUND((terBilledDuration/60) * 0.1, 0)
    //                     ELSE 0 END) as incoming_total_period,
                    
    //                 -- Outgoing total for period (without tax deduction)
    //                 SUM(CASE WHEN INET_NTOA(terIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
    //                         AND INET_NTOA(orgIPAddress) = '59.152.98.70'
    //                     THEN ROUND((orgBilledDuration/60) * 0.35, 0)
    //                     ELSE 0 END) as outgoing_total_period
                    
    //             FROM $unionAll
    //             WHERE DATE(FROM_UNIXTIME(connectTime/1000)) BETWEEN '$startDate' AND '$endDate'
    //         ");

    //         return response()->json([
    //             'status' => true,
    //             'date_range' => [
    //                 'start_date' => $startDate,
    //                 'end_date' => $endDate
    //             ],
    //             'month_range' => [
    //                 'start_month' => $startMonth,
    //                 'end_month' => $endMonth
    //             ],
    //             'date_wise_data' => $formattedData,
    //             'summary' => [
    //                 'period_summary' => [
    //                     'date_range' => "$startDate to $endDate",
    //                     'total_incoming_revenue' => intval($periodSummary->incoming_total_period ?? 0),
    //                     'total_outgoing_revenue' => intval($periodSummary->outgoing_total_period ?? 0),
    //                     'total_revenue' => intval($periodSummary->incoming_total_period ?? 0) + intval($periodSummary->outgoing_total_period ?? 0)
    //                 ],
    //                 'calculated_totals' => [
    //                     'total_incoming_revenue' => $totalIncoming,
    //                     'total_outgoing_revenue' => $totalOutgoing,
    //                     'total_revenue' => $grandTotal
    //                 ]
    //             ],
    //             'tables_used' => [
    //                 'month_mapping' => $tables,
    //                 'table_names' => $allTables
    //             ],
    //             'calculation_details' => [
    //                 'incoming_rate' => '0.1 per minute (GSM to Orbitalk)',
    //                 'outgoing_rate' => '0.35 per minute (Orbitalk to GSM)',
    //                 'note' => 'Revenue calculation without tax deduction',
    //                 'incoming_ips' => ['10.246.29.66', '10.246.29.74', '172.20.15.106'],
    //                 'termination_ip' => '59.152.98.70'
    //             ],
    //             'date_column_used' => 'connectTime'
    //         ]);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             "status" => false,
    //             "error"  => $e->getMessage(),
    //             "line"   => $e->getLine()
    //         ], 500);
    //     }
    // }




    public function dateWiseRevenue(Request $request)
    {
        try {
            // Get filter parameters
            $startDate = $request->start_date ?? date('Y-m-d', strtotime('-2 days'));
            $endDate = $request->end_date ?? date('Y-m-d');
            
            // Pagination parameters
            $perPage = $request->per_page ?? 10;
            $page = $request->page ?? 1;
            $offset = ($page - 1) * $perPage;

            // Validate date range
            if (strtotime($startDate) > strtotime($endDate)) {
                return response()->json([
                    'status' => false,
                    'message' => "Start date cannot be after end date"
                ], 400);
            }

            // Get table mapping
            $mapping = getDynamicTables();
            
            if (empty($mapping)) {
                return response()->json([
                    'status' => false,
                    'message' => "No table mapping found"
                ], 404);
            }

            // Calculate months
            $startMonth = date('Y-m', strtotime($startDate));
            $endMonth = date('Y-m', strtotime($endDate));
            
            if ($startMonth < $endMonth) {
                $temp = $startMonth;
                $startMonth = $endMonth;
                $endMonth = $temp;
            }

            // Get tables for the date range
            $tables = filterRangeWithNext($mapping, $startMonth, $endMonth);
            
            if (empty($tables)) {
                $firstTwo = array_slice(array_values($mapping), 0, 2);
                $tables = array_filter($firstTwo);
                
                if (empty($tables)) {
                    return response()->json([
                        'status' => false,
                        'message' => "No CDR tables found"
                    ], 404);
                }
            }

            // Prepare table names
            $allTables = array_map(function($tableName) {
                return "Successfuliptsp." . $tableName;
            }, array_values($tables));

            // Build union query
            $buildUnion = function($tableArray) {
                return implode(" UNION ALL ", array_map(fn($t) => "SELECT * FROM $t", $tableArray));
            };

            $unionAll = "(" . $buildUnion($allTables) . ") AS cdr";

            // Count total records for pagination
            $countQuery = "
                SELECT COUNT(*) as total
                FROM (
                    SELECT DATE(FROM_UNIXTIME(connectTime/1000)) as call_date
                    FROM $unionAll
                    WHERE DATE(FROM_UNIXTIME(connectTime/1000)) BETWEEN '$startDate' AND '$endDate'
                    GROUP BY DATE(FROM_UNIXTIME(connectTime/1000))
                ) as date_counts
            ";

            $countResult = DB::connection('mysql5')->selectOne($countQuery);
            $totalRecords = $countResult->total ?? 0;
            $lastPage = ceil($totalRecords / $perPage);

            // Main query with pagination
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
                ORDER BY DATE(FROM_UNIXTIME(connectTime/1000)) DESC
                LIMIT $perPage OFFSET $offset
            ";

            $dateWiseData = DB::connection('mysql5')->select($dateWiseQuery);

            // Format data
            $formattedData = array_map(function($row) {
                return [
                    'date' => $row->call_date,
                    'incoming_revenue_day_wise' => intval($row->incoming_revenue_day_wise ?? 0),
                    'outgoing_revenue_day_wise' => intval($row->outgoing_revenue_day_wise ?? 0),
                    'total_revenue_day_wise' => intval($row->incoming_revenue_day_wise ?? 0) + intval($row->outgoing_revenue_day_wise ?? 0)
                ];
            }, $dateWiseData);

            // Get period summary (total for all days in range)
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
                'data' => $formattedData,
                'summary' => [
                    'period_summary' => [
                        'date_range' => "$startDate to $endDate",
                    ],
                    'calculated_totals' => [
                        'total_incoming_revenue' => intval($periodSummary->incoming_total_period ?? 0),
                        'total_outgoing_revenue' => intval($periodSummary->outgoing_total_period ?? 0),
                        'total_revenue' => intval($periodSummary->incoming_total_period ?? 0) + intval($periodSummary->outgoing_total_period ?? 0)
                    ]
                ],
                'pagination' => [
                    'current_page' => (int)$page,
                    'per_page' => (int)$perPage,
                    'total' => (int)$totalRecords,
                    'last_page' => (int)$lastPage,
                    'from' => $offset + 1,
                    'to' => min($offset + $perPage, $totalRecords)
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

    public function exportDateWiseRevenue(Request $request)
    {
        try {
            // Get filter parameters
            $startDate = $request->start_date ?? date('Y-m-d', strtotime('-2 days'));
            $endDate = $request->end_date ?? date('Y-m-d');
            
            // Validate date range
            if (strtotime($startDate) > strtotime($endDate)) {
                return response()->json([
                    'status' => false,
                    'message' => "Start date cannot be after end date"
                ], 400);
            }

            // Get table mapping
            $mapping = getDynamicTables();
            
            if (empty($mapping)) {
                return response()->json([
                    'status' => false,
                    'message' => "No table mapping found"
                ], 404);
            }

            // Calculate months
            $startMonth = date('Y-m', strtotime($startDate));
            $endMonth = date('Y-m', strtotime($endDate));
            
            if ($startMonth < $endMonth) {
                $temp = $startMonth;
                $startMonth = $endMonth;
                $endMonth = $temp;
            }

            // Get tables for the date range
            $tables = filterRangeWithNext($mapping, $startMonth, $endMonth);
            
            if (empty($tables)) {
                $firstTwo = array_slice(array_values($mapping), 0, 2);
                $tables = array_filter($firstTwo);
                
                if (empty($tables)) {
                    return response()->json([
                        'status' => false,
                        'message' => "No CDR tables found"
                    ], 404);
                }
            }

            // Prepare table names
            $allTables = array_map(function($tableName) {
                return "Successfuliptsp." . $tableName;
            }, array_values($tables));

            // Build union query
            $buildUnion = function($tableArray) {
                return implode(" UNION ALL ", array_map(fn($t) => "SELECT * FROM $t", $tableArray));
            };

            $unionAll = "(" . $buildUnion($allTables) . ") AS cdr";

            // Main query for export
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
                ORDER BY DATE(FROM_UNIXTIME(connectTime/1000)) DESC
            ";

            $data = DB::connection('mysql5')->select($dateWiseQuery);

            // Create CSV content
            $csvData = [];
            
            // Headers
            $csvData[] = ['Date', 'Incoming Revenue (৳)', 'Outgoing Revenue (৳)', 'Total Revenue (৳)'];
            
            // Data rows
            foreach ($data as $row) {
                $total = $row->incoming_revenue_day_wise + $row->outgoing_revenue_day_wise;
                $csvData[] = [
                    $row->call_date,
                    number_format($row->incoming_revenue_day_wise, 2),
                    number_format($row->outgoing_revenue_day_wise, 2),
                    number_format($total, 2)
                ];
            }
            
            // Add summary row
            $totalIncoming = array_sum(array_column($data, 'incoming_revenue_day_wise'));
            $totalOutgoing = array_sum(array_column($data, 'outgoing_revenue_day_wise'));
            $grandTotal = $totalIncoming + $totalOutgoing;
            
            $csvData[] = ['', '', '', ''];
            $csvData[] = ['Summary', '', '', ''];
            $csvData[] = ['Total Incoming Revenue', number_format($totalIncoming, 2), '', ''];
            $csvData[] = ['Total Outgoing Revenue', '', number_format($totalOutgoing, 2), ''];
            $csvData[] = ['Total Revenue', '', '', number_format($grandTotal, 2)];
            $csvData[] = ['', '', '', ''];
            $csvData[] = ['Date Range', "$startDate to $endDate", '', ''];

            // Convert to CSV string
            $output = fopen('php://output', 'w');
            ob_start();
            foreach ($csvData as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
            $csv = ob_get_clean();

            // Set headers for CSV download
            $filename = "revenue-report-" . $startDate . "-to-" . $endDate . ".csv";
            
            return response($csv)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'error' => $e->getMessage(),
                'line' => $e->getLine()
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



    

    public function dateWiseRechargedAmountIptsp(Request $request)
    {
        try {
            // Get filter parameters
            $startDate = $request->start_date ?? date('Y-m-d', strtotime('-2 days'));
            $endDate = $request->end_date ?? date('Y-m-d');
            $viewType = $request->view_type ?? 'day_wise'; // 'day_wise' or 'details'
            
            // Pagination parameters
            $perPage = $request->per_page ?? 10;
            $page = $request->page ?? 1;
            $offset = ($page - 1) * $perPage;

            // Validate date range
            if (strtotime($startDate) > strtotime($endDate)) {
                return response()->json([
                    'status' => false,
                    'message' => "Start date cannot be after end date"
                ], 400);
            }

            // Add time to dates
            $startDateTime = $startDate . " 00:00:00";
            $endDateTime = $endDate . " 23:59:59";
            
            $startMonth = date("Y_m", strtotime($startDateTime));
            $endMonth = date("Y_m", strtotime($endDateTime));

            // Get all payment tables
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
                        "start_date" => $startDateTime,
                        "end_date" => $endDateTime,
                        "start_month" => $startMonth,
                        "end_month" => $endMonth
                    ]
                ], 404);
            }

            // Build query based on view type
            $unionParts = [];
            $allBindings = [];

            foreach ($selectedTables as $table) {
                $where = "
                    FROM_UNIXTIME(p.pyDate/1000) BETWEEN ? AND ?
                    AND p.pyAmount > 0
                    AND c.clCustomerID NOT LIKE '8801%'
                    AND p.pyPaymentGatewayType = 19
                ";

                $bindings = [$startDateTime, $endDateTime];

                if (!empty($request->pyUserName)) {
                    $where .= " AND p.pyUserName LIKE ? ";
                    $bindings[] = "%" . $request->pyUserName . "%";
                }

                if (!empty($request->clCustomerID)) {
                    $where .= " AND c.clCustomerID LIKE ? ";
                    $bindings[] = "%" . $request->clCustomerID . "%";
                }

                if (!empty($request->cdBillingName)) {
                    $where .= " AND d.cdBillingName LIKE ? ";
                    $bindings[] = "%" . $request->cdBillingName . "%";
                }

                if ($viewType === 'day_wise') {
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
                } else {
                    // Details view - individual transactions
                    $unionParts[] = "
                        SELECT
                            DATE(FROM_UNIXTIME(p.pyDate/1000)) AS date,
                            p.pyUserName,
                            c.clCustomerID,
                            d.cdBillingName,
                            d.cdCompanyName,
                            p.pyAmount AS recharge_amount,
                            FROM_UNIXTIME(p.pyDate/1000, '%Y-%m-%d %H:%i:%s') AS payment_datetime
                        FROM iTelBillingiptsp.$table p
                        JOIN iTelBillingiptsp.vbClient c ON p.pyAccountID = c.clAccountID
                        JOIN iTelBillingiptsp.vbPaymentType t ON p.pyPaymentGatewayType = t.ptID
                        JOIN iTelBillingiptsp.vbClientDetails d ON c.clAccountID = d.cdClientAccountID
                        WHERE $where
                    ";
                }

                $allBindings = array_merge($allBindings, $bindings);
            }

            $unionSql = implode(" UNION ALL ", $unionParts);
            
            // Count query for pagination
            if ($viewType === 'day_wise') {
                $countSql = "
                    SELECT COUNT(*) as total
                    FROM (
                        SELECT DATE(FROM_UNIXTIME(p.pyDate/1000)) as recharge_date
                        FROM (
                            " . implode(" UNION ALL ", array_map(fn($table) => "SELECT * FROM iTelBillingiptsp.$table", $selectedTables)) . "
                        ) p
                        JOIN iTelBillingiptsp.vbClient c ON p.pyAccountID = c.clAccountID
                        JOIN iTelBillingiptsp.vbPaymentType t ON p.pyPaymentGatewayType = t.ptID
                        JOIN iTelBillingiptsp.vbClientDetails d ON c.clAccountID = d.cdClientAccountID
                        WHERE FROM_UNIXTIME(p.pyDate/1000) BETWEEN ? AND ?
                        AND p.pyAmount > 0
                        AND c.clCustomerID NOT LIKE '8801%'
                        AND p.pyPaymentGatewayType = 19
                        GROUP BY DATE(FROM_UNIXTIME(p.pyDate/1000))
                    ) as date_counts
                ";
                $countBindings = [$startDateTime, $endDateTime];
            } else {
                $countSql = "
                    SELECT COUNT(*) as total
                    FROM ($unionSql) AS details_data
                ";
                $countBindings = $allBindings;
            }

            $countResult = DB::connection('mysql5')->selectOne($countSql, $countBindings);
            $totalRecords = $countResult->total ?? 0;
            $lastPage = ceil($totalRecords / $perPage);

            // Main data query with pagination
            if ($viewType === 'day_wise') {
                $mainSql = $unionSql . " ORDER BY recharge_date DESC LIMIT ? OFFSET ?";
                $queryBindings = array_merge($allBindings, [$perPage, $offset]);
                $data = DB::connection('mysql5')->select($mainSql, $queryBindings);
            } else {
                $mainSql = $unionSql . " ORDER BY payment_datetime DESC LIMIT ? OFFSET ?";
                $queryBindings = array_merge($allBindings, [$perPage, $offset]);
                $data = DB::connection('mysql5')->select($mainSql, $queryBindings);
            }

            // Summary query (same for both views)
            $summaryUnionParts = [];
            $summaryBindings = [];

            foreach ($selectedTables as $table) {
                $where = "
                    FROM_UNIXTIME(p.pyDate/1000) BETWEEN ? AND ?
                    AND p.pyAmount > 0
                    AND c.clCustomerID NOT LIKE '8801%'
                    AND p.pyPaymentGatewayType = 19
                ";

                $bindings = [$startDateTime, $endDateTime];

                if (!empty($request->pyUserName)) {
                    $where .= " AND p.pyUserName LIKE ? ";
                    $bindings[] = "%" . $request->pyUserName . "%";
                }

                if (!empty($request->clCustomerID)) {
                    $where .= " AND c.clCustomerID LIKE ? ";
                    $bindings[] = "%" . $request->clCustomerID . "%";
                }

                if (!empty($request->cdBillingName)) {
                    $where .= " AND d.cdBillingName LIKE ? ";
                    $bindings[] = "%" . $request->cdBillingName . "%";
                }

                $summaryUnionParts[] = "
                    SELECT
                        SUM(p.pyAmount) AS total_recharge_amount,
                        COUNT(*) AS total_transactions,
                        COUNT(DISTINCT DATE(FROM_UNIXTIME(p.pyDate/1000))) AS total_days
                    FROM iTelBillingiptsp.$table p
                    JOIN iTelBillingiptsp.vbClient c ON p.pyAccountID = c.clAccountID
                    JOIN iTelBillingiptsp.vbPaymentType t ON p.pyPaymentGatewayType = t.ptID
                    JOIN iTelBillingiptsp.vbClientDetails d ON c.clAccountID = d.cdClientAccountID
                    WHERE $where
                ";

                $summaryBindings = array_merge($summaryBindings, $bindings);
            }

            // If multiple tables, we need to combine summary results
            if (count($summaryUnionParts) > 1) {
                $summarySql = "
                    SELECT 
                        SUM(total_recharge_amount) AS total_recharge_amount,
                        SUM(total_transactions) AS total_transactions,
                        COUNT(*) AS total_days
                    FROM (
                        " . implode(" UNION ALL ", $summaryUnionParts) . "
                    ) AS summary
                ";
            } else {
                $summarySql = $summaryUnionParts[0];
            }

            $summaryData = DB::connection('mysql5')->selectOne($summarySql, $summaryBindings);

            // Format data based on view type
            if ($viewType === 'day_wise') {
                $formattedData = array_map(function($row) {
                    return [
                        'date' => $row->recharge_date,
                        'recharge_amount' => floatval($row->daily_recharge_amount ?? 0),
                        'transaction_count' => intval($row->transaction_count ?? 0),
                        'average_per_transaction' => $row->transaction_count > 0 
                            ? round($row->daily_recharge_amount / $row->transaction_count, 2)
                            : 0
                    ];
                }, $data);
            } else {
                $formattedData = array_map(function($row) {
                    return [
                        'date' => $row->date,
                        'pyUserName' => $row->pyUserName ?? '',
                        'clCustomerID' => $row->clCustomerID ?? '',
                        'cdBillingName' => $row->cdBillingName ?? '',
                        'cdCompanyName' => $row->cdCompanyName ?? '',
                        'recharge_amount' => floatval($row->recharge_amount ?? 0),
                        'payment_datetime' => $row->payment_datetime ?? ''
                    ];
                }, $data);
            }

            // Calculate totals
            $totalRecharge = floatval($summaryData->total_recharge_amount ?? 0);
            $totalTransactions = intval($summaryData->total_transactions ?? 0);
            $totalDays = intval($summaryData->total_days ?? 0);

            return response()->json([
                "status" => true,
                "date_range" => [
                    "start_date" => $startDate,
                    "end_date" => $endDate,
                    "start_month" => $startMonth,
                    "end_month" => $endMonth
                ],
                "view_type" => $viewType,
                "data" => $formattedData,
                "summary" => [
                    "period_summary" => [
                        "date_range" => $startDate . " to " . $endDate,
                        "total_recharge_amount" => $totalRecharge,
                        "total_transactions" => $totalTransactions,
                        "total_days_with_recharge" => $totalDays,
                        "average_per_day" => $totalDays > 0 ? round($totalRecharge / $totalDays, 2) : 0,
                        "average_per_transaction" => $totalTransactions > 0 ? round($totalRecharge / $totalTransactions, 2) : 0
                    ]
                ],
                "pagination" => [
                    'current_page' => (int)$page,
                    'per_page' => (int)$perPage,
                    'total' => (int)$totalRecords,
                    'last_page' => (int)$lastPage,
                    'from' => $offset + 1,
                    'to' => min($offset + $perPage, $totalRecords)
                ],
                "filters_applied" => [
                    "clCustomerID_not_like" => "8801%",
                    "pyPaymentGatewayType" => 19,
                    "pyAmount_greater_than" => 0,
                    "pyUserName" => $request->pyUserName ?? "Not applied",
                    "clCustomerID" => $request->clCustomerID ?? "Not applied",
                    "cdBillingName" => $request->cdBillingName ?? "Not applied",
                    "view_type" => $viewType
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

    public function exportDateWiseRechargedAmountIptsp(Request $request)
    {
        try {
            // Get filter parameters
            $startDate = $request->start_date ?? date('Y-m-d', strtotime('-2 days'));
            $endDate = $request->end_date ?? date('Y-m-d');
            $viewType = $request->view_type ?? 'day_wise'; // 'day_wise' or 'details'

            // Validate date range
            if (strtotime($startDate) > strtotime($endDate)) {
                return response()->json([
                    'status' => false,
                    'message' => "Start date cannot be after end date"
                ], 400);
            }

            // Add time to dates
            $startDateTime = $startDate . " 00:00:00";
            $endDateTime = $endDate . " 23:59:59";
            
            $startMonth = date("Y_m", strtotime($startDateTime));
            $endMonth = date("Y_m", strtotime($endDateTime));

            // Get all payment tables
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
                ], 404);
            }

            // Build query for export based on view type
            $unionParts = [];
            $allBindings = [];

            foreach ($selectedTables as $table) {
                $where = "
                    FROM_UNIXTIME(p.pyDate/1000) BETWEEN ? AND ?
                    AND p.pyAmount > 0
                    AND c.clCustomerID NOT LIKE '8801%'
                    AND p.pyPaymentGatewayType = 19
                ";

                $bindings = [$startDateTime, $endDateTime];

                if (!empty($request->pyUserName)) {
                    $where .= " AND p.pyUserName LIKE ? ";
                    $bindings[] = "%" . $request->pyUserName . "%";
                }

                if (!empty($request->clCustomerID)) {
                    $where .= " AND c.clCustomerID LIKE ? ";
                    $bindings[] = "%" . $request->clCustomerID . "%";
                }

                if (!empty($request->cdBillingName)) {
                    $where .= " AND d.cdBillingName LIKE ? ";
                    $bindings[] = "%" . $request->cdBillingName . "%";
                }

                if ($viewType === 'day_wise') {
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
                } else {
                    $unionParts[] = "
                        SELECT
                            DATE(FROM_UNIXTIME(p.pyDate/1000)) AS date,
                            p.pyUserName,
                            c.clCustomerID,
                            d.cdBillingName,
                            d.cdCompanyName,
                            p.pyAmount AS recharge_amount,
                            FROM_UNIXTIME(p.pyDate/1000, '%Y-%m-%d %H:%i:%s') AS payment_datetime
                        FROM iTelBillingiptsp.$table p
                        JOIN iTelBillingiptsp.vbClient c ON p.pyAccountID = c.clAccountID
                        JOIN iTelBillingiptsp.vbPaymentType t ON p.pyPaymentGatewayType = t.ptID
                        JOIN iTelBillingiptsp.vbClientDetails d ON c.clAccountID = d.cdClientAccountID
                        WHERE $where
                    ";
                }

                $allBindings = array_merge($allBindings, $bindings);
            }

            $unionSql = implode(" UNION ALL ", $unionParts);
            
            if ($viewType === 'day_wise') {
                $mainSql = $unionSql . " ORDER BY recharge_date DESC";
                $data = DB::connection('mysql5')->select($mainSql, $allBindings);
            } else {
                $mainSql = $unionSql . " ORDER BY payment_datetime DESC";
                $data = DB::connection('mysql5')->select($mainSql, $allBindings);
            }

            // Summary query for export
            $summaryUnionParts = [];
            $summaryBindings = [];

            foreach ($selectedTables as $table) {
                $where = "
                    FROM_UNIXTIME(p.pyDate/1000) BETWEEN ? AND ?
                    AND p.pyAmount > 0
                    AND c.clCustomerID NOT LIKE '8801%'
                    AND p.pyPaymentGatewayType = 19
                ";

                $bindings = [$startDateTime, $endDateTime];

                if (!empty($request->pyUserName)) {
                    $where .= " AND p.pyUserName LIKE ? ";
                    $bindings[] = "%" . $request->pyUserName . "%";
                }

                if (!empty($request->clCustomerID)) {
                    $where .= " AND c.clCustomerID LIKE ? ";
                    $bindings[] = "%" . $request->clCustomerID . "%";
                }

                if (!empty($request->cdBillingName)) {
                    $where .= " AND d.cdBillingName LIKE ? ";
                    $bindings[] = "%" . $request->cdBillingName . "%";
                }

                $summaryUnionParts[] = "
                    SELECT
                        SUM(p.pyAmount) AS total_recharge_amount,
                        COUNT(*) AS total_transactions,
                        COUNT(DISTINCT DATE(FROM_UNIXTIME(p.pyDate/1000))) AS total_days
                    FROM iTelBillingiptsp.$table p
                    JOIN iTelBillingiptsp.vbClient c ON p.pyAccountID = c.clAccountID
                    JOIN iTelBillingiptsp.vbPaymentType t ON p.pyPaymentGatewayType = t.ptID
                    JOIN iTelBillingiptsp.vbClientDetails d ON c.clAccountID = d.cdClientAccountID
                    WHERE $where
                ";

                $summaryBindings = array_merge($summaryBindings, $bindings);
            }

            if (count($summaryUnionParts) > 1) {
                $summarySql = "
                    SELECT 
                        SUM(total_recharge_amount) AS total_recharge_amount,
                        SUM(total_transactions) AS total_transactions,
                        COUNT(*) AS total_days
                    FROM (
                        " . implode(" UNION ALL ", $summaryUnionParts) . "
                    ) AS summary
                ";
            } else {
                $summarySql = $summaryUnionParts[0];
            }

            $summaryData = DB::connection('mysql5')->selectOne($summarySql, $summaryBindings);

            // Create CSV content based on view type
            $csvData = [];
            
            if ($viewType === 'day_wise') {
                // Headers for day wise
                $csvData[] = ['Date', 'Recharge Amount (৳)', 'Transaction Count', 'Average per Transaction (৳)'];
                
                // Data rows
                foreach ($data as $row) {
                    $average = $row->transaction_count > 0 ? $row->daily_recharge_amount / $row->transaction_count : 0;
                    $csvData[] = [
                        $row->recharge_date,
                        number_format($row->daily_recharge_amount, 4),
                        number_format($row->transaction_count, 0),
                        number_format($average, 2)
                    ];
                }
                
                // Add summary rows
                $totalRecharge = floatval($summaryData->total_recharge_amount ?? 0);
                $totalTransactions = intval($summaryData->total_transactions ?? 0);
                $totalDays = intval($summaryData->total_days ?? 0);
                
                $csvData[] = ['', '', '', ''];
                $csvData[] = ['Summary', '', '', ''];
                $csvData[] = ['Total Recharge Amount', number_format($totalRecharge, 4), '', ''];
                $csvData[] = ['Total Transactions', '', number_format($totalTransactions, 0), ''];
                $csvData[] = ['Total Days', '', number_format($totalDays, 0), ''];
                $csvData[] = ['Average per Day', number_format($totalDays > 0 ? $totalRecharge / $totalDays : 0, 4), '', ''];
                $csvData[] = ['Average per Transaction', '', '', number_format($totalTransactions > 0 ? $totalRecharge / $totalTransactions : 0, 4)];
            } else {
                // Headers for details
                $csvData[] = ['Date', 'User Name', 'Customer ID', 'Billing Name', 'Company Name', 'Recharge Amount (৳)', 'Payment Time'];
                
                // Data rows
                foreach ($data as $row) {
                    $csvData[] = [
                        $row->date,
                        $row->pyUserName,
                        $row->clCustomerID,
                        $row->cdBillingName,
                        $row->cdCompanyName,
                        number_format($row->recharge_amount, 4),
                        $row->payment_datetime
                    ];
                }
                
                // Add summary rows
                $totalRecharge = floatval($summaryData->total_recharge_amount ?? 0);
                $totalTransactions = intval($summaryData->total_transactions ?? 0);
                $totalDays = intval($summaryData->total_days ?? 0);
                
                $csvData[] = ['', '', '', '', '', '', ''];
                $csvData[] = ['Summary', '', '', '', '', '', ''];
                $csvData[] = ['Total Recharge Amount', '', '', '', '', number_format($totalRecharge, 4), ''];
                $csvData[] = ['Total Transactions', '', '', '', number_format($totalTransactions, 0), '', ''];
                $csvData[] = ['Total Days', '', '', number_format($totalDays, 0), '', '', ''];
            }
            
            $csvData[] = ['', '', '', '', '', '', ''];
            $csvData[] = ['Date Range', "$startDate to $endDate", '', '', '', '', ''];
            $csvData[] = ['Tables Used', implode(', ', $selectedTables), '', '', '', '', ''];
            $csvData[] = ['View Type', $viewType === 'day_wise' ? 'Day Wise Summary' : 'Transaction Details', '', '', '', '', ''];

            // Convert to CSV string
            $output = fopen('php://output', 'w');
            ob_start();
            foreach ($csvData as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
            $csv = ob_get_clean();

            // Set headers for CSV download
            $filename = "recharge-" . $viewType . "-report-" . $startDate . "-to-" . $endDate . ".csv";
            
            return response($csv)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');

        } catch (\Exception $e) {
            return response()->json([
                "status" => false,
                "error" => $e->getMessage(),
                "line" => $e->getLine()
            ], 500);
        }
    }


    // public function dateWiseGrossProfitIptsp(Request $request)
    // {
    //     try {
            
    //         $startDate = $request->start_date ?? date('Y-m-d', strtotime('-7 days'));
    //         $endDate = $request->end_date ?? date('Y-m-d');

        
    //         if (strtotime($startDate) > strtotime($endDate)) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => "Start date cannot be after end date"
    //             ], 400);
    //         }

            
    //         $mapping = getDynamicTables();
            
    //         if (empty($mapping)) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => "No table mapping found"
    //             ], 404);
    //         }

            
    //         $startMonth = date('Y-m', strtotime($startDate));
    //         $endMonth = date('Y-m', strtotime($endDate));
            
            
    //         if ($startMonth < $endMonth) {
    //             $temp = $startMonth;
    //             $startMonth = $endMonth;
    //             $endMonth = $temp;
    //         }

            
    //         $tables = filterRangeWithNext($mapping, $startMonth, $endMonth);
            
    //         if (empty($tables)) {

                
    //             $firstTwo = array_slice(array_values($mapping), 0, 2);

    //             $tables = array_filter($firstTwo);

    //             if (empty($tables)) {
    //                 return response()->json([
    //                     'status' => false,
    //                     'message' => "No CDR tables found"
    //                 ], 404);
    //             }
    //         }


            
    //         $allTables = array_map(function($tableName) {
    //             return "Successfuliptsp." . $tableName;
    //         }, array_values($tables));

            
    //         $buildUnion = function($tableArray) {
    //             return implode(" UNION ALL ", array_map(fn($t) => "SELECT * FROM $t", $tableArray));
    //         };

    //         $unionAll = "(" . $buildUnion($allTables) . ") AS cdr";

            
    //         $dateWiseQuery = "
    //             SELECT 
    //                 DATE(FROM_UNIXTIME(connectTime/1000)) as call_date,
                    
    //                 -- Incoming calculations (same as your grossProfitIptsp function)
    //                 SUM(CASE WHEN INET_NTOA(orgIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
    //                         AND INET_NTOA(terIPAddress) = '59.152.98.66'
    //                     THEN (ROUND((terBilledDuration/60) * 0.1, 0) - (ROUND((terBilledDuration/60) * 0.1, 0) * 0.03))
    //                     ELSE 0 END) as incoming_bill_day_wise,
                    
    //                 -- Outgoing calculations (same as your grossProfitIptsp function)
    //                 SUM(
    //                     -- For calls with billed amount > 0
    //                     (CASE WHEN INET_NTOA(orgIPAddress) != '59.152.98.70'
    //                         AND INET_NTOA(terIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
    //                         AND orgBilledAmount > 0
    //                     THEN (ROUND(orgBilledAmount, 0) - (ROUND(orgBilledAmount, 0) * 0.03 + ROUND(orgBilledAmount * 0.14, 0)))
    //                     ELSE 0 END)
                        
    //                     +
                        
    //                     -- For calls with billed amount = 0 (calculate based on duration)
    //                     (CASE WHEN INET_NTOA(orgIPAddress) != '59.152.98.70'
    //                         AND INET_NTOA(terIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
    //                         AND orgBilledAmount = 0
    //                     THEN (ROUND((orgBilledDuration/60) * 0.35, 0) - (ROUND((orgBilledDuration/60) * 0.35, 0) * 0.03 + ROUND(((orgBilledDuration/60) * 0.14), 0)))
    //                     ELSE 0 END)
    //                 ) as outgoing_bill_day_wise
                    
    //             FROM $unionAll
    //             WHERE DATE(FROM_UNIXTIME(connectTime/1000)) BETWEEN '$startDate' AND '$endDate'
    //             GROUP BY DATE(FROM_UNIXTIME(connectTime/1000))
    //             ORDER BY DATE(FROM_UNIXTIME(connectTime/1000)) ASC
    //         ";

    //         $dateWiseData = DB::connection('mysql5')->select($dateWiseQuery);

            
    //         $formattedData = array_map(function($row) {
    //             return [
    //                 'date' => $row->call_date,
    //                 'incoming_bill_day_wise' => intval($row->incoming_bill_day_wise ?? 0),
    //                 'outgoing_bill_day_wise' => intval($row->outgoing_bill_day_wise ?? 0),
    //                 'total_day_wise' => intval($row->incoming_bill_day_wise ?? 0) + intval($row->outgoing_bill_day_wise ?? 0)
    //             ];
    //         }, $dateWiseData);

            
    //         $totalIncoming = array_sum(array_column($formattedData, 'incoming_bill_day_wise'));
    //         $totalOutgoing = array_sum(array_column($formattedData, 'outgoing_bill_day_wise'));
    //         $grandTotal = $totalIncoming + $totalOutgoing;

            
    //         $periodSummary = DB::connection('mysql5')->selectOne("
    //             SELECT 
    //                 -- Incoming total for period
    //                 SUM(CASE WHEN INET_NTOA(orgIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
    //                         AND INET_NTOA(terIPAddress) = '59.152.98.66'
    //                     THEN (ROUND((terBilledDuration/60) * 0.1, 0) - (ROUND((terBilledDuration/60) * 0.1, 0) * 0.03))
    //                     ELSE 0 END) as incoming_total_period,
                    
    //                 -- Outgoing total for period
    //                 SUM(
    //                     -- For calls with billed amount > 0
    //                     (CASE WHEN INET_NTOA(orgIPAddress) != '59.152.98.70'
    //                         AND INET_NTOA(terIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
    //                         AND orgBilledAmount > 0
    //                     THEN (ROUND(orgBilledAmount, 0) - (ROUND(orgBilledAmount, 0) * 0.03 + ROUND(orgBilledAmount * 0.14, 0)))
    //                     ELSE 0 END)
                        
    //                     +
                        
    //                     -- For calls with billed amount = 0 (calculate based on duration)
    //                     (CASE WHEN INET_NTOA(orgIPAddress) != '59.152.98.70'
    //                         AND INET_NTOA(terIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
    //                         AND orgBilledAmount = 0
    //                     THEN (ROUND((orgBilledDuration/60) * 0.35, 0) - (ROUND((orgBilledDuration/60) * 0.35, 0) * 0.03 + ROUND(((orgBilledDuration/60) * 0.14), 0)))
    //                     ELSE 0 END)
    //                 ) as outgoing_total_period,
                    
    //                 -- Additional breakdown for outgoing calls
    //                 SUM(CASE WHEN INET_NTOA(orgIPAddress) != '59.152.98.70'
    //                         AND INET_NTOA(terIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
    //                         AND orgBilledAmount > 0
    //                     THEN ROUND(orgBilledDuration/60, 0) ELSE 0 END) as outgoing_minutes_with_amount,
                    
    //                 SUM(CASE WHEN INET_NTOA(orgIPAddress) != '59.152.98.70'
    //                         AND INET_NTOA(terIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
    //                         AND orgBilledAmount = 0
    //                     THEN ROUND(orgBilledDuration/60, 0) ELSE 0 END) as outgoing_minutes_no_amount
                    
    //             FROM $unionAll
    //             WHERE DATE(FROM_UNIXTIME(connectTime/1000)) BETWEEN '$startDate' AND '$endDate'
    //         ");

    //         return response()->json([
    //             'status' => true,
    //             'date_range' => [
    //                 'start_date' => $startDate,
    //                 'end_date' => $endDate
    //             ],
    //             'month_range' => [
    //                 'start_month' => $startMonth,
    //                 'end_month' => $endMonth
    //             ],
    //             'date_wise_data' => $formattedData,
    //             'summary' => [
    //                 'period_summary' => [
    //                     'date_range' => "$startDate to $endDate",
    //                     // 'total_incoming' => intval($periodSummary->incoming_total_period ?? 0),
    //                     // 'total_outgoing' => intval($periodSummary->outgoing_total_period ?? 0),
    //                     // 'total_gross_profit' => intval($periodSummary->incoming_total_period ?? 0) + intval($periodSummary->outgoing_total_period ?? 0),
    //                     // 'outgoing_breakdown' => [
    //                     //     'minutes_with_amount' => intval($periodSummary->outgoing_minutes_with_amount ?? 0),
    //                     //     'minutes_no_amount' => intval($periodSummary->outgoing_minutes_no_amount ?? 0),
    //                     //     'total_outgoing_minutes' => intval($periodSummary->outgoing_minutes_with_amount ?? 0) + intval($periodSummary->outgoing_minutes_no_amount ?? 0)
    //                     // ]
    //                 ],
    //                 'calculated_totals_from_daily' => [
    //                     'total_incoming' => $totalIncoming,
    //                     'total_outgoing' => $totalOutgoing,
    //                     'grand_total' => $grandTotal
    //                 ]
    //             ],
    //             'tables_used' => [
    //                 'month_mapping' => $tables,
    //                 'table_names' => $allTables
    //             ],
    //             'calculation_details' => [
    //                 'incoming_rate' => '0.1 per minute with 3% tax',
    //                 'outgoing_rate_with_amount' => 'orgBilledAmount with 3% tax + 14% commission',
    //                 'outgoing_rate_no_amount' => '0.35 per minute with 3% tax + 14% commission',
    //                 'incoming_ip' => '59.152.98.66',
    //                 'outgoing_exclude_ip' => '59.152.98.70',
    //                 'gsm_ips' => ['10.246.29.66', '10.246.29.74', '172.20.15.106']
    //             ],
    //             'date_column_used' => 'connectTime'
    //         ]);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             "status" => false,
    //             "error"  => $e->getMessage(),
    //             "line"   => $e->getLine()
    //         ], 500);
    //     }
    // }



    public function dateWiseGrossProfitIptsp(Request $request)
    {
        try {
            // Get filter parameters
            $startDate = $request->start_date ?? date('Y-m-d', strtotime('-2 days'));
            $endDate = $request->end_date ?? date('Y-m-d');
            
            // Pagination parameters
            $perPage = $request->per_page ?? 10;
            $page = $request->page ?? 1;
            $offset = ($page - 1) * $perPage;

            // Validate date range
            if (strtotime($startDate) > strtotime($endDate)) {
                return response()->json([
                    'status' => false,
                    'message' => "Start date cannot be after end date"
                ], 400);
            }

            // Get table mapping
            $mapping = getDynamicTables();
            
            if (empty($mapping)) {
                return response()->json([
                    'status' => false,
                    'message' => "No table mapping found"
                ], 404);
            }

            // Calculate months
            $startMonth = date('Y-m', strtotime($startDate));
            $endMonth = date('Y-m', strtotime($endDate));
            
            if ($startMonth < $endMonth) {
                $temp = $startMonth;
                $startMonth = $endMonth;
                $endMonth = $temp;
            }

            // Get tables for the date range
            $tables = filterRangeWithNext($mapping, $startMonth, $endMonth);
            
            if (empty($tables)) {
                $firstTwo = array_slice(array_values($mapping), 0, 2);
                $tables = array_filter($firstTwo);
                
                if (empty($tables)) {
                    return response()->json([
                        'status' => false,
                        'message' => "No CDR tables found"
                    ], 404);
                }
            }

            // Prepare table names
            $allTables = array_map(function($tableName) {
                return "Successfuliptsp." . $tableName;
            }, array_values($tables));

            // Build union query
            $buildUnion = function($tableArray) {
                return implode(" UNION ALL ", array_map(fn($t) => "SELECT * FROM $t", $tableArray));
            };

            $unionAll = "(" . $buildUnion($allTables) . ") AS cdr";

            // Count total records for pagination
            $countQuery = "
                SELECT COUNT(*) as total
                FROM (
                    SELECT DATE(FROM_UNIXTIME(connectTime/1000)) as call_date
                    FROM $unionAll
                    WHERE DATE(FROM_UNIXTIME(connectTime/1000)) BETWEEN '$startDate' AND '$endDate'
                    GROUP BY DATE(FROM_UNIXTIME(connectTime/1000))
                ) as date_counts
            ";

            $countResult = DB::connection('mysql5')->selectOne($countQuery);
            $totalRecords = $countResult->total ?? 0;
            $lastPage = ceil($totalRecords / $perPage);

            // Main query with pagination
            $dateWiseQuery = "
                SELECT 
                    DATE(FROM_UNIXTIME(connectTime/1000)) as call_date,
                    
                    -- Incoming calculations
                    SUM(CASE WHEN INET_NTOA(orgIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
                            AND INET_NTOA(terIPAddress) = '59.152.98.66'
                        THEN (ROUND((terBilledDuration/60) * 0.1, 0) - (ROUND((terBilledDuration/60) * 0.1, 0) * 0.03))
                        ELSE 0 END) as incoming_bill_day_wise,
                    
                    -- Outgoing calculations
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
                ORDER BY DATE(FROM_UNIXTIME(connectTime/1000)) DESC
                LIMIT $perPage OFFSET $offset
            ";

            $dateWiseData = DB::connection('mysql5')->select($dateWiseQuery);

            // Format data
            $formattedData = array_map(function($row) {
                return [
                    'date' => $row->call_date,
                    'incoming_bill_day_wise' => intval($row->incoming_bill_day_wise ?? 0),
                    'outgoing_bill_day_wise' => intval($row->outgoing_bill_day_wise ?? 0),
                    'total_day_wise' => intval($row->incoming_bill_day_wise ?? 0) + intval($row->outgoing_bill_day_wise ?? 0)
                ];
            }, $dateWiseData);

            // Calculate totals for current page
            $totalIncoming = array_sum(array_column($formattedData, 'incoming_bill_day_wise'));
            $totalOutgoing = array_sum(array_column($formattedData, 'outgoing_bill_day_wise'));
            $grandTotal = $totalIncoming + $totalOutgoing;

            // Get period summary (total for all days in range)
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
                    ) as outgoing_total_period
                    
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
                'data' => $formattedData, // Changed to 'data' for consistency
                'summary' => [
                    'period_summary' => [
                        'date_range' => "$startDate to $endDate",
                    ],
                    'calculated_totals' => [
                        'total_incoming' => intval($periodSummary->incoming_total_period ?? 0),
                        'total_outgoing' => intval($periodSummary->outgoing_total_period ?? 0),
                        'grand_total' => intval($periodSummary->incoming_total_period ?? 0) + intval($periodSummary->outgoing_total_period ?? 0)
                    ]
                ],
                'pagination' => [
                    'current_page' => (int)$page,
                    'per_page' => (int)$perPage,
                    'total' => (int)$totalRecords,
                    'last_page' => (int)$lastPage,
                    'from' => $offset + 1,
                    'to' => min($offset + $perPage, $totalRecords)
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

    public function exportDateWiseGrossProfitIptsp(Request $request)
    {
        try {
            // Get filter parameters
            $startDate = $request->start_date ?? date('Y-m-d', strtotime('-2 days'));
            $endDate = $request->end_date ?? date('Y-m-d');
            
            // Validate date range
            if (strtotime($startDate) > strtotime($endDate)) {
                return response()->json([
                    'status' => false,
                    'message' => "Start date cannot be after end date"
                ], 400);
            }

            // Get table mapping
            $mapping = getDynamicTables();
            
            if (empty($mapping)) {
                return response()->json([
                    'status' => false,
                    'message' => "No table mapping found"
                ], 404);
            }

            // Calculate months
            $startMonth = date('Y-m', strtotime($startDate));
            $endMonth = date('Y-m', strtotime($endDate));
            
            if ($startMonth < $endMonth) {
                $temp = $startMonth;
                $startMonth = $endMonth;
                $endMonth = $temp;
            }

            // Get tables for the date range
            $tables = filterRangeWithNext($mapping, $startMonth, $endMonth);
            
            if (empty($tables)) {
                $firstTwo = array_slice(array_values($mapping), 0, 2);
                $tables = array_filter($firstTwo);
                
                if (empty($tables)) {
                    return response()->json([
                        'status' => false,
                        'message' => "No CDR tables found"
                    ], 404);
                }
            }

            // Prepare table names
            $allTables = array_map(function($tableName) {
                return "Successfuliptsp." . $tableName;
            }, array_values($tables));

            // Build union query
            $buildUnion = function($tableArray) {
                return implode(" UNION ALL ", array_map(fn($t) => "SELECT * FROM $t", $tableArray));
            };

            $unionAll = "(" . $buildUnion($allTables) . ") AS cdr";

            // Main query for IPTSP
            $dateWiseQuery = "
                SELECT 
                    DATE(FROM_UNIXTIME(connectTime/1000)) as call_date,
                    
                    -- Incoming calculations
                    SUM(CASE WHEN INET_NTOA(orgIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
                            AND INET_NTOA(terIPAddress) = '59.152.98.66'
                        THEN (ROUND((terBilledDuration/60) * 0.1, 0) - (ROUND((terBilledDuration/60) * 0.1, 0) * 0.03))
                        ELSE 0 END) as incoming_bill_day_wise,
                    
                    -- Outgoing calculations
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
                ORDER BY DATE(FROM_UNIXTIME(connectTime/1000)) DESC
            ";

            $data = DB::connection('mysql5')->select($dateWiseQuery);

            // Create CSV content
            $csvData = [];
            
            // Headers
            $csvData[] = ['Date', 'Incoming Bill (৳)', 'Outgoing Bill (৳)', 'Total (৳)'];
            
            // Data rows
            foreach ($data as $row) {
                $total = $row->incoming_bill_day_wise + $row->outgoing_bill_day_wise;
                $csvData[] = [
                    $row->call_date,
                    number_format($row->incoming_bill_day_wise, 2),
                    number_format($row->outgoing_bill_day_wise, 2),
                    number_format($total, 2)
                ];
            }
            
            // Add summary row
            $totalIncoming = array_sum(array_column($data, 'incoming_bill_day_wise'));
            $totalOutgoing = array_sum(array_column($data, 'outgoing_bill_day_wise'));
            $grandTotal = $totalIncoming + $totalOutgoing;
            
            $csvData[] = ['', '', '', ''];
            $csvData[] = ['Summary', '', '', ''];
            $csvData[] = ['Total Incoming', number_format($totalIncoming, 2), '', ''];
            $csvData[] = ['Total Outgoing', '', number_format($totalOutgoing, 2), ''];
            $csvData[] = ['Grand Total', '', '', number_format($grandTotal, 2)];
            $csvData[] = ['', '', '', ''];
            $csvData[] = ['Date Range', "$startDate to $endDate", '', ''];

            // Convert to CSV string
            $output = fopen('php://output', 'w');
            ob_start();
            foreach ($csvData as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
            $csv = ob_get_clean();

            // Set headers for CSV download
            $filename = "gross-profit-iptsp-report-" . $startDate . "-to-" . $endDate . ".csv";
            
            return response($csv)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }


    // public function dateWiseRevenueIptsp(Request $request)
    // {
    //     try {
            
    //         $startDate = $request->start_date ?? date('Y-m-d', strtotime('-7 days'));
    //         $endDate = $request->end_date ?? date('Y-m-d');

            
    //         if (strtotime($startDate) > strtotime($endDate)) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => "Start date cannot be after end date"
    //             ], 400);
    //         }

            
    //         $mapping = getDynamicTables();
            
    //         if (empty($mapping)) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => "No table mapping found"
    //             ], 404);
    //         }

            
    //         $startMonth = date('Y-m', strtotime($startDate));
    //         $endMonth = date('Y-m', strtotime($endDate));
            
            
    //         if ($startMonth < $endMonth) {
    //             $temp = $startMonth;
    //             $startMonth = $endMonth;
    //             $endMonth = $temp;
    //         }

            
    //         $tables = filterRangeWithNext($mapping, $startMonth, $endMonth);
            
    //         if (empty($tables)) {

                
    //             $firstTwo = array_slice(array_values($mapping), 0, 2);

    //             $tables = array_filter($firstTwo);

    //             if (empty($tables)) {
    //                 return response()->json([
    //                     'status' => false,
    //                     'message' => "No CDR tables found"
    //                 ], 404);
    //             }
    //         }


            
    //         $allTables = array_map(function($tableName) {
    //             return "Successfuliptsp." . $tableName;
    //         }, array_values($tables));

            
    //         $buildUnion = function($tableArray) {
    //             return implode(" UNION ALL ", array_map(fn($t) => "SELECT * FROM $t", $tableArray));
    //         };

    //         $unionAll = "(" . $buildUnion($allTables) . ") AS cdr";

            
    //         $dateWiseQuery = "
    //             SELECT 
    //                 DATE(FROM_UNIXTIME(connectTime/1000)) as call_date,
                    
    //                 -- Incoming revenue calculations (IPTSP - without tax deduction)
    //                 SUM(CASE WHEN INET_NTOA(orgIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
    //                         AND INET_NTOA(terIPAddress) = '59.152.98.66'
    //                     THEN ROUND((terBilledDuration/60) * 0.1, 0)
    //                     ELSE 0 END) as incoming_revenue_day_wise,
                    
    //                 -- Outgoing revenue calculations (IPTSP - uses orgBilledAmount)
    //                 SUM(CASE WHEN INET_NTOA(orgIPAddress) != '59.152.98.70'
    //                         AND INET_NTOA(terIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
    //                         AND orgBilledAmount > 0
    //                     THEN ROUND(orgBilledAmount, 0)
    //                     ELSE 0 END) as outgoing_revenue_day_wise
                    
    //             FROM $unionAll
    //             WHERE DATE(FROM_UNIXTIME(connectTime/1000)) BETWEEN '$startDate' AND '$endDate'
    //             GROUP BY DATE(FROM_UNIXTIME(connectTime/1000))
    //             ORDER BY DATE(FROM_UNIXTIME(connectTime/1000)) ASC
    //         ";

    //         $dateWiseData = DB::connection('mysql5')->select($dateWiseQuery);

            
    //         $formattedData = array_map(function($row) {
    //             return [
    //                 'date' => $row->call_date,
    //                 'incoming_revenue_day_wise' => intval($row->incoming_revenue_day_wise ?? 0),
    //                 'outgoing_revenue_day_wise' => intval($row->outgoing_revenue_day_wise ?? 0),
    //                 'total_revenue_day_wise' => intval($row->incoming_revenue_day_wise ?? 0) + intval($row->outgoing_revenue_day_wise ?? 0)
    //             ];
    //         }, $dateWiseData);

            
    //         $totalIncoming = array_sum(array_column($formattedData, 'incoming_revenue_day_wise'));
    //         $totalOutgoing = array_sum(array_column($formattedData, 'outgoing_revenue_day_wise'));
    //         $grandTotal = $totalIncoming + $totalOutgoing;

            
    //         $periodSummary = DB::connection('mysql5')->selectOne("
    //             SELECT 
    //                 -- Incoming total for period (IPTSP - without tax deduction)
    //                 SUM(CASE WHEN INET_NTOA(orgIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
    //                         AND INET_NTOA(terIPAddress) = '59.152.98.66'
    //                     THEN ROUND((terBilledDuration/60) * 0.1, 0)
    //                     ELSE 0 END) as incoming_total_period,
                    
    //                 -- Outgoing total for period (IPTSP - uses orgBilledAmount)
    //                 SUM(CASE WHEN INET_NTOA(orgIPAddress) != '59.152.98.70'
    //                         AND INET_NTOA(terIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
    //                         AND orgBilledAmount > 0
    //                     THEN ROUND(orgBilledAmount, 0)
    //                     ELSE 0 END) as outgoing_total_period,
                    
    //                 -- Additional: Total outgoing minutes
    //                 SUM(CASE WHEN INET_NTOA(orgIPAddress) != '59.152.98.70'
    //                         AND INET_NTOA(terIPAddress) IN ('10.246.29.66','10.246.29.74','172.20.15.106')
    //                     THEN ROUND(orgBilledDuration/60, 0)
    //                     ELSE 0 END) as total_outgoing_minutes
                    
    //             FROM $unionAll
    //             WHERE DATE(FROM_UNIXTIME(connectTime/1000)) BETWEEN '$startDate' AND '$endDate'
    //         ");

    //         return response()->json([
    //             'status' => true,
    //             'date_range' => [
    //                 'start_date' => $startDate,
    //                 'end_date' => $endDate
    //             ],
    //             'month_range' => [
    //                 'start_month' => $startMonth,
    //                 'end_month' => $endMonth
    //             ],
    //             'date_wise_data' => $formattedData,
    //             'summary' => [
    //                 'period_summary' => [
    //                     'date_range' => "$startDate to $endDate",
    //                     'total_incoming_revenue' => intval($periodSummary->incoming_total_period ?? 0),
    //                     'total_outgoing_revenue' => intval($periodSummary->outgoing_total_period ?? 0),
    //                     'total_revenue' => intval($periodSummary->incoming_total_period ?? 0) + intval($periodSummary->outgoing_total_period ?? 0),
    //                     'total_outgoing_minutes' => intval($periodSummary->total_outgoing_minutes ?? 0)
    //                 ],
    //                 'calculated_totals' => [
    //                     'total_incoming_revenue' => $totalIncoming,
    //                     'total_outgoing_revenue' => $totalOutgoing,
    //                     'total_revenue' => $grandTotal
    //                 ]
    //             ],
    //             'tables_used' => [
    //                 'month_mapping' => $tables,
    //                 'table_names' => $allTables
    //             ],
    //             'calculation_details' => [
    //                 'incoming_rate' => '0.1 per minute (GSM to IPTSP)',
    //                 'outgoing_calculation' => 'Uses orgBilledAmount directly (when > 0)',
    //                 'note' => 'IPTSP revenue calculation - incoming uses duration rate, outgoing uses billed amount',
    //                 'incoming_ips' => ['10.246.29.66', '10.246.29.74', '172.20.15.106'],
    //                 'termination_ip' => '59.152.98.66',
    //                 'excluded_ip' => '59.152.98.70 (excluded from outgoing)'
    //             ],
    //             'date_column_used' => 'connectTime'
    //         ]);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             "status" => false,
    //             "error"  => $e->getMessage(),
    //             "line"   => $e->getLine()
    //         ], 500);
    //     }
    // }



    

    public function dateWiseRevenueIptsp(Request $request)
    {
        try {
            // Get filter parameters
            $startDate = $request->start_date ?? date('Y-m-d', strtotime('-2 days'));
            $endDate = $request->end_date ?? date('Y-m-d');
            
            // Pagination parameters
            $perPage = $request->per_page ?? 10;
            $page = $request->page ?? 1;
            $offset = ($page - 1) * $perPage;

            // Validate date range
            if (strtotime($startDate) > strtotime($endDate)) {
                return response()->json([
                    'status' => false,
                    'message' => "Start date cannot be after end date"
                ], 400);
            }

            // Get table mapping
            $mapping = getDynamicTables();
            
            if (empty($mapping)) {
                return response()->json([
                    'status' => false,
                    'message' => "No table mapping found"
                ], 404);
            }

            // Calculate months
            $startMonth = date('Y-m', strtotime($startDate));
            $endMonth = date('Y-m', strtotime($endDate));
            
            if ($startMonth < $endMonth) {
                $temp = $startMonth;
                $startMonth = $endMonth;
                $endMonth = $temp;
            }

            // Get tables for the date range
            $tables = filterRangeWithNext($mapping, $startMonth, $endMonth);
            
            if (empty($tables)) {
                $firstTwo = array_slice(array_values($mapping), 0, 2);
                $tables = array_filter($firstTwo);
                
                if (empty($tables)) {
                    return response()->json([
                        'status' => false,
                        'message' => "No CDR tables found"
                    ], 404);
                }
            }

            // Prepare table names
            $allTables = array_map(function($tableName) {
                return "Successfuliptsp." . $tableName;
            }, array_values($tables));

            // Build union query
            $buildUnion = function($tableArray) {
                return implode(" UNION ALL ", array_map(fn($t) => "SELECT * FROM $t", $tableArray));
            };

            $unionAll = "(" . $buildUnion($allTables) . ") AS cdr";

            // Count total records for pagination
            $countQuery = "
                SELECT COUNT(*) as total
                FROM (
                    SELECT DATE(FROM_UNIXTIME(connectTime/1000)) as call_date
                    FROM $unionAll
                    WHERE DATE(FROM_UNIXTIME(connectTime/1000)) BETWEEN '$startDate' AND '$endDate'
                    GROUP BY DATE(FROM_UNIXTIME(connectTime/1000))
                ) as date_counts
            ";

            $countResult = DB::connection('mysql5')->selectOne($countQuery);
            $totalRecords = $countResult->total ?? 0;
            $lastPage = ceil($totalRecords / $perPage);

            // Main query with pagination
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
                ORDER BY DATE(FROM_UNIXTIME(connectTime/1000)) DESC
                LIMIT $perPage OFFSET $offset
            ";

            $dateWiseData = DB::connection('mysql5')->select($dateWiseQuery);

            // Format data
            $formattedData = array_map(function($row) {
                return [
                    'date' => $row->call_date,
                    'incoming_revenue_day_wise' => intval($row->incoming_revenue_day_wise ?? 0),
                    'outgoing_revenue_day_wise' => intval($row->outgoing_revenue_day_wise ?? 0),
                    'total_revenue_day_wise' => intval($row->incoming_revenue_day_wise ?? 0) + intval($row->outgoing_revenue_day_wise ?? 0)
                ];
            }, $dateWiseData);

            // Get period summary (total for all days in range)
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
                'data' => $formattedData,
                'summary' => [
                    'period_summary' => [
                        'date_range' => "$startDate to $endDate",
                    ],
                    'calculated_totals' => [
                        'total_incoming_revenue' => intval($periodSummary->incoming_total_period ?? 0),
                        'total_outgoing_revenue' => intval($periodSummary->outgoing_total_period ?? 0),
                        'total_revenue' => intval($periodSummary->incoming_total_period ?? 0) + intval($periodSummary->outgoing_total_period ?? 0)
                    ]
                ],
                'pagination' => [
                    'current_page' => (int)$page,
                    'per_page' => (int)$perPage,
                    'total' => (int)$totalRecords,
                    'last_page' => (int)$lastPage,
                    'from' => $offset + 1,
                    'to' => min($offset + $perPage, $totalRecords)
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

    public function exportDateWiseRevenueIptsp(Request $request)
    {
        try {
            // Get filter parameters
            $startDate = $request->start_date ?? date('Y-m-d', strtotime('-2 days'));
            $endDate = $request->end_date ?? date('Y-m-d');
            
            // Validate date range
            if (strtotime($startDate) > strtotime($endDate)) {
                return response()->json([
                    'status' => false,
                    'message' => "Start date cannot be after end date"
                ], 400);
            }

            // Get table mapping
            $mapping = getDynamicTables();
            
            if (empty($mapping)) {
                return response()->json([
                    'status' => false,
                    'message' => "No table mapping found"
                ], 404);
            }

            // Calculate months
            $startMonth = date('Y-m', strtotime($startDate));
            $endMonth = date('Y-m', strtotime($endDate));
            
            if ($startMonth < $endMonth) {
                $temp = $startMonth;
                $startMonth = $endMonth;
                $endMonth = $temp;
            }

            // Get tables for the date range
            $tables = filterRangeWithNext($mapping, $startMonth, $endMonth);
            
            if (empty($tables)) {
                $firstTwo = array_slice(array_values($mapping), 0, 2);
                $tables = array_filter($firstTwo);
                
                if (empty($tables)) {
                    return response()->json([
                        'status' => false,
                        'message' => "No CDR tables found"
                    ], 404);
                }
            }

            // Prepare table names
            $allTables = array_map(function($tableName) {
                return "Successfuliptsp." . $tableName;
            }, array_values($tables));

            // Build union query
            $buildUnion = function($tableArray) {
                return implode(" UNION ALL ", array_map(fn($t) => "SELECT * FROM $t", $tableArray));
            };

            $unionAll = "(" . $buildUnion($allTables) . ") AS cdr";

            // Main query for export
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
                ORDER BY DATE(FROM_UNIXTIME(connectTime/1000)) DESC
            ";

            $data = DB::connection('mysql5')->select($dateWiseQuery);

            // Create CSV content
            $csvData = [];
            
            // Headers
            $csvData[] = ['Date', 'Incoming Revenue (৳)', 'Outgoing Revenue (৳)', 'Total Revenue (৳)'];
            
            // Data rows
            foreach ($data as $row) {
                $total = $row->incoming_revenue_day_wise + $row->outgoing_revenue_day_wise;
                $csvData[] = [
                    $row->call_date,
                    number_format($row->incoming_revenue_day_wise, 2),
                    number_format($row->outgoing_revenue_day_wise, 2),
                    number_format($total, 2)
                ];
            }
            
            // Add summary row
            $totalIncoming = array_sum(array_column($data, 'incoming_revenue_day_wise'));
            $totalOutgoing = array_sum(array_column($data, 'outgoing_revenue_day_wise'));
            $grandTotal = $totalIncoming + $totalOutgoing;
            
            $csvData[] = ['', '', '', ''];
            $csvData[] = ['Summary', '', '', ''];
            $csvData[] = ['Total Incoming Revenue', number_format($totalIncoming, 2), '', ''];
            $csvData[] = ['Total Outgoing Revenue', '', number_format($totalOutgoing, 2), ''];
            $csvData[] = ['Total Revenue', '', '', number_format($grandTotal, 2)];
            $csvData[] = ['', '', '', ''];
            $csvData[] = ['Date Range', "$startDate to $endDate", '', ''];

            // Convert to CSV string
            $output = fopen('php://output', 'w');
            ob_start();
            foreach ($csvData as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
            $csv = ob_get_clean();

            // Set headers for CSV download
            $filename = "iptsp-revenue-report-" . $startDate . "-to-" . $endDate . ".csv";
            
            return response($csv)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'error' => $e->getMessage(),
                'line' => $e->getLine()
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
