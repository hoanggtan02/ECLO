<?php
if (!defined('ECLO')) die("Hacking attempt");
$jatbi = new Jatbi($app);
$setting = $app->getValueData('setting');

// Định nghĩa route GET để hiển thị giao diện
$app->router("/salaryCalculation", 'GET', function($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Tính lương");
    $vars['employee'] = $app->select("employee", ["name (text)", "sn (value)"], []);

    // Lấy tham số lọc từ URL
    $vars['month'] = $app->xss($_GET['month'] ?? date('m'));
    $vars['year'] = $app->xss($_GET['year'] ?? date('Y'));
    $vars['employeeFilter'] = array_map([$app, 'xss'], explode(',', $_GET['employee'] ?? '') ?? []);

    $vars['salary'] = $app->select("staff-salary", ["id", "name", "price", "priceValue"], [
        "type IN" => [1, 2],
        "status" => 'A'
    ]);
    error_log("Salaries in GET: " . print_r($vars['salary'], true));

    echo $app->render('templates/employee/salaryCalculation.html', $vars);
})->setPermissions(['salaryCalculation']);

// Định nghĩa route POST để trả về dữ liệu JSON cho DataTables
$app->router("/salaryCalculation", 'POST', function($vars) use ($app, $jatbi) {
    $app->header([
        'Content-Type' => 'application/json'
    ]);

    $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
    $start = $_POST['start'] ?? 0;
    $length = $_POST['length'] ?? 10;
    $searchValue = $_POST['search']['value'] ?? '';
    $orderColumnIndex = $_POST['order'][0]['column'] ?? 0;
    $orderDir = strtoupper($_POST['order'][0]['dir'] ?? 'DESC');

    $validColumns = [
        0 => "personSn",
        1 => "departmentId",
    ];
    $salaryColumns = $app->select("staff-salary", ["id"], ["type IN" => [1, 2], "status" => 'A']) ?? [];
    $columnOffset = 2;
    foreach ($salaryColumns as $index => $s) {
        $validColumns[$columnOffset + $index] = "salaryData_" . $s['id'];
    }
    $columnOffset += count($salaryColumns);
    $validColumns[$columnOffset] = "dailySalary";
    $validColumns[$columnOffset + 1] = "insurance";
    $validColumns[$columnOffset + 2] = "workingDays";
    $validColumns[$columnOffset + 3] = "overtime";
    $validColumns[$columnOffset + 4] = "lateArrival/earlyLeave";
    $validColumns[$columnOffset + 5] = "unpaidLeave";
    $validColumns[$columnOffset + 6] = "paidLeave";
    $validColumns[$columnOffset + 7] = "unauthorizedLeave";
    $validColumns[$columnOffset + 8] = "totalAttendance";
    $validColumns[$columnOffset + 9] = "discipline";
    $validColumns[$columnOffset + 10] = "reward";
    $validColumns[$columnOffset + 11] = "salaryAdvance";
    $validColumns[$columnOffset + 12] = "totalReceived";

    $orderColumn = $validColumns[$orderColumnIndex] ?? "personSn";

    // Lấy tham số lọc từ $_POST
    $month = str_pad($app->xss($_POST['month'] ?? date('m')), 2, '0', STR_PAD_LEFT);
    $year = $app->xss($_POST['year'] ?? date('Y'));
    $employeeFilter = array_map([$app, 'xss'], $_POST['employee'] ?? []);

    $where = [
        "AND" => [],
        "LIMIT" => [$start, $length],
        "ORDER" => [$orderColumn => $orderDir]
    ];
    if (!empty($searchValue)) {
        $where["AND"]["OR"] = [
            "employee.sn[~]" => $searchValue,
            "employee.name[~]" => $searchValue,
        ];
    }
    if (!empty($employeeFilter)) {
        $where["AND"]["employee.sn IN"] = $employeeFilter;
    }

    $employees = $app->select("employee", ["sn", "name", "departmentId"], $where["AND"]) ?? [];
    $count = $app->count("employee", $where["AND"]) ?? 0;
    $datas = [];

    $daysInMonth = date('t', mktime(0, 0, 0, $month, 1, $year));
    $monthStart = "$year-$month-01 00:00:00";
    $monthEnd = "$year-$month-$daysInMonth 23:59:59";

    $dayMap = [
        1 => ['start' => 'monStart', 'end' => 'monEnd', 'off' => 'mon_off'],
        2 => ['start' => 'tueStart', 'end' => 'tueEnd', 'off' => 'tue_off'],
        3 => ['start' => 'wedStart', 'end' => 'wedEnd', 'off' => 'wed_off'],
        4 => ['start' => 'thursStart', 'end' => 'thursEnd', 'off' => 'thu_off'],
        5 => ['start' => 'friStart', 'end' => 'friEnd', 'off' => 'fri_off'],
        6 => ['start' => 'satStart', 'end' => 'satEnd', 'off' => 'sat_off'],
        7 => ['start' => 'sunStart', 'end' => 'sunEnd', 'off' => 'sun_off']
    ];

    $timePeriods = $app->select("timeperiod", [
        "acTzNumber", "monStart", "monEnd", "tueStart", "tueEnd", "wedStart", "wedEnd",
        "thursStart", "thursEnd", "friStart", "friEnd", "satStart", "satEnd", "sunStart", "sunEnd",
        "mon_off", "tue_off", "wed_off", "thu_off", "fri_off", "sat_off", "sun_off"
    ]) ?? [];
    $timePeriodMap = array_column($timePeriods, null, 'acTzNumber');

    $assignments = $app->select("assignments", ["employee_id", "timeperiod_id", "apply_date"], [
        "apply_date[<=]" => $monthEnd,
        "ORDER" => ["apply_date" => "ASC"]
    ]) ?? [];
    $assignmentMap = [];
    foreach ($assignments as $assignment) {
        $assignmentMap[$assignment['employee_id']][] = $assignment;
    }

    $records = $app->select("record", ["personSn", "createTime"], [
        "AND" => ["createTime[>=]" => $monthStart, "createTime[<=]" => $monthEnd]
    ]) ?? [];
    $attendanceData = [];
    foreach ($records as $record) {
        $sn = $record['personSn'];
        $date = date('Y-m-d', strtotime($record['createTime']));
        if (!isset($attendanceData[$sn])) {
            $attendanceData[$sn] = [];
        }
        if (!isset($attendanceData[$sn][$date])) {
            $attendanceData[$sn][$date] = [];
        }
        $attendanceData[$sn][$date][] = $record['createTime'];
    }

    // Lấy dữ liệu từ leave_requests, bao gồm SalaryType từ leavetype
    $leaveRequests = $app->select("leave_requests", [
        "[>]leavetype" => ["LeaveId" => "LeaveTypeID"]
    ], [
        "leave_requests.personSN",
        "leave_requests.start_date",
        "leave_requests.end_date",
        "leave_requests.leave_days",
        "leavetype.SalaryType"
    ], [
        "AND" => ["leave_requests.start_date[<=]" => $monthEnd, "leave_requests.end_date[>=]" => $monthStart]
    ]) ?? [];
    $leaveData = [];
    $leaveDaysMap = []; // Lưu danh sách các ngày nghỉ phép cho từng nhân viên
    foreach ($leaveRequests as $request) {
        $sn = $request['personSN'];
        $leaveData[$sn][] = $request;

        // Tạo danh sách các ngày nghỉ phép
        $start = max(strtotime($monthStart), strtotime($request['start_date']));
        $end = min(strtotime($monthEnd), strtotime($request['end_date']));
        $startDate = date('Y-m-d', $start);
        $endDate = date('Y-m-d', $end);
        $currentDate = $startDate;

        if (!isset($leaveDaysMap[$sn])) {
            $leaveDaysMap[$sn] = [];
        }

        // Thêm từng ngày trong khoảng thời gian nghỉ vào leaveDaysMap
        while (strtotime($currentDate) <= strtotime($endDate)) {
            $leaveDaysMap[$sn][$currentDate] = true;
            $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
        }
    }
    error_log("Leave Data: " . print_r($leaveData, true));
    error_log("Leave Days Map: " . print_r($leaveDaysMap, true));

    $overtimes = $app->select("overtime", ["employee_sn", "start", "end"], [
        "AND" => ["start[>=]" => $monthStart, "end[<=]" => $monthEnd]
    ]) ?? [];
    $overtimeData = [];
    foreach ($overtimes as $ot) {
        $overtimeData[$ot['employee_sn']][] = $ot;
    }

    $latetimes = $app->select("latetime", ["personSn", "date"], [
        "AND" => ["date[>=]" => $monthStart, "date[<=]" => $monthEnd]
    ]) ?? [];
    $latetimeData = [];
    foreach ($latetimes as $lt) {
        $latetimeData[$lt['personSn']][] = $lt;
    }

    // Lấy dữ liệu từ reward_discipline
    $rewards = $app->select("reward_discipline", ["personSN", "amount", "type"], [
        "AND" => [
            "apply_date[>=]" => "$year-$month-01",
            "apply_date[<=]" => "$year-$month-$daysInMonth"
        ]
    ]) ?? [];
    $rewardData = [];
    foreach ($rewards as $rd) {
        $rewardData[$rd['personSN']][] = $rd;
    }
    error_log("Reward Data: " . print_r($rewardData, true));

    // Lấy dữ liệu từ salaryadvances
    $monthStart = "$year-$month-01";
    $monthEnd = "$year-$month-$daysInMonth";
    $advances = $app->select("salaryadvances", ["sn", "Amount", "TypeID"], [
        "AND" => [
            "AppliedDate[>=]" => "$monthStart",
            "AppliedDate[<=]" => "$monthEnd"
        ]
    ]) ?? [];
    $advanceData = [];
    foreach ($advances as $ad) {
        $advanceData[$ad['sn']][] = $ad;
    }
    error_log("Advance Data: " . print_r($advanceData, true));

    // Lấy thông tin bảo hiểm của nhân viên (bản ghi mới nhất)
    $insurances = $app->select("insurance", [
        "employee", "money", "statu", "day"
    ], [
        "ORDER" => ["idbh" => "DESC"]
    ]) ?? [];
    $insuranceMap = [];
    foreach ($insurances as $insurance) {
        if (!isset($insuranceMap[$insurance['employee']])) {
            $insuranceMap[$insurance['employee']] = $insurance;
        }
    }
    error_log("Insurance Map: " . print_r($insuranceMap, true));

    $contracts = $app->select("employee_contracts", ["id", "person_sn", "position_id"], [
        "ORDER" => ["working_date" => "DESC"]
    ]) ?? [];
    $contractMap = [];
    foreach ($contracts as $contract) {
        if (!isset($contractMap[$contract['person_sn']])) {
            $contractMap[$contract['person_sn']] = $contract;
        }
    }

    $contractSalaries = $app->select("contract_salary", ["Id_contract", "Id_salary"], []) ?? [];
    $contractSalaryMap = [];
    foreach ($contractSalaries as $cs) {
        $contractSalaryMap[$cs['Id_contract']][] = $cs['Id_salary'];
    }

    $salaries = $app->select("staff-salary", ["id", "name", "price", "type", "priceValue"], [
        "type IN" => [1, 2],
        "status" => 'A'
    ]) ?? [];
    error_log("Salaries in POST: " . print_r($salaries, true));

    foreach ($employees as $index => $employee) {
        $sn = $employee['sn'];
        $department = $app->select("department", ["personName"], ["departmentId" => $employee['departmentId']])[0]['personName'] ?? 'Không xác định';

        $salaryData = [];
        $totalSalary = 0;
        $basicSalary = 0;
        $dailySalary = 0;

        $totalWorkingDays = 0;
        $actualWorkingDays = 0;
        $overtimeHours = 0;
        $unauthorizedLeaveFromRecords = 0; // Nghỉ không phép từ chấm công
        $assignments = $assignmentMap[$sn] ?? [];
        $timeperiodId = end($assignments)['timeperiod_id'] ?? '1';
        $timePeriod = $timePeriodMap[$timeperiodId] ?? [];

        // Duyệt qua từng ngày trong tháng để tính ngày công và nghỉ không phép
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = "$year-$month-" . sprintf("%02d", $day);
            $dayOfWeek = date('N', strtotime($date));
            $offKey = $dayMap[$dayOfWeek]['off'];

            // Kiểm tra xem ngày đó có phải ngày nghỉ theo lịch làm việc không
            if (!$timePeriod || !isset($timePeriod[$offKey]) || !$timePeriod[$offKey]) {
                $totalWorkingDays++; // Tăng tổng số ngày làm việc
                $records = $attendanceData[$sn][$date] ?? [];
                if (!empty($records)) {
                    $actualWorkingDays++; // Có chấm công, tăng ngày công thực tế
                    $checkIn = min(array_map('strtotime', $records));
                    $checkOut = max(array_map('strtotime', $records));
                    $startTime = strtotime("$date " . ($timePeriod[$dayMap[$dayOfWeek]['start']] ?? '09:00'));
                    $endTime = strtotime("$date " . ($timePeriod[$dayMap[$dayOfWeek]['end']] ?? '17:00'));
                    $workHours = ($endTime - $startTime) / 3600;
                    $actualHours = ($checkOut - $checkIn) / 3600;
                    if ($actualHours > $workHours) {
                        $overtimeHours += $actualHours - $workHours;
                    }
                } else {
                    // Kiểm tra xem ngày này có nằm trong danh sách nghỉ phép không
                    if (!isset($leaveDaysMap[$sn][$date])) {
                        // Không có chấm công, không phải ngày nghỉ, và không có đăng ký nghỉ phép → tính là nghỉ không phép
                        $unauthorizedLeaveFromRecords++;
                    }
                }
            }
        }

        $contract = $contractMap[$sn] ?? null;
        $contractId = $contract['id'] ?? null;

        $hasContractSalary = $contractId && isset($contractSalaryMap[$contractId]) && !empty($contractSalaryMap[$contractId]);

        if ($hasContractSalary) {
            $salaryIds = $contractSalaryMap[$contractId];
            $employeeSalaries = array_filter($salaries, fn($s) => in_array($s['id'], $salaryIds));

            $monthlyTotal = 0;
            $dailyTotal = 0;

            foreach ($salaries as $s) {
                $price = 0;
                $priceValue = 3;
                $found = false;

                foreach ($employeeSalaries as $es) {
                    if ($es['id'] === $s['id']) {
                        $price = is_numeric($es['price']) ? (int)$es['price'] : 0;
                        $priceValue = $es['priceValue'];
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    $price = 0;
                    $priceValue = 3;
                }

                $unitLabel = '';
                switch ($priceValue) {
                    case 1: $unitLabel = 'Giờ'; break;
                    case 2: $unitLabel = 'Ngày'; break;
                    case 3: default: $unitLabel = 'Tháng'; break;
                }

                $salaryData["salaryData_" . $s['id']] = $unitLabel . ' / ' . number_format($price, 0, ',', '.');

                $monthlyPrice = 0;
                if ($priceValue == 1) {
                    $monthlyPrice = $price * 9 * 26;
                } elseif ($priceValue == 2) {
                    $monthlyPrice = $price * 26;
                } else {
                    $monthlyPrice = $price;
                }
                $totalSalary += $monthlyPrice;

                if ($s['type'] == 1) {
                    $basicSalary = $monthlyPrice;
                }

                if ($priceValue == 1) {
                    $dailyTotal += $price * 9;
                } elseif ($priceValue == 2) {
                    $dailyTotal += $price;
                } else {
                    $monthlyTotal += $price;
                }
            }

            $dailySalary = $dailyTotal;
            if ($monthlyTotal > 0 && $totalWorkingDays > 0) {
                $dailySalary += $monthlyTotal / $totalWorkingDays;
            }
        } else {
            foreach ($salaries as $s) {
                $salaryData["salaryData_" . $s['id']] = '/0';
            }
            $totalSalary = 0;
            $dailySalary = 0;
        }

        error_log("Salary Data for employee $sn: " . print_r($salaryData, true));

        // Lấy số tiền bảo hiểm từ bảng insurance
        $insurance = 0;
        $insuranceInfo = $insuranceMap[$sn] ?? null;
        error_log("Employee SN: $sn, Insurance Info: " . print_r($insuranceInfo, true));
        if ($insuranceInfo && $insuranceInfo['statu'] === 'A') {
            // Lấy số tiền bảo hiểm từ cột money
            $insurance = (float)str_replace(',', '', $insuranceInfo['money']);
        }

        $overtimePay = $app->select("staff-salary", ["price"], ["type" => 3, "status" => 'A'])[0]['price'] ?? 0;
        $overtime = isset($overtimeData[$sn]) ? array_sum(array_map(function($ot) use ($overtimePay) {
            return (strtotime($ot['end']) - strtotime($ot['start'])) / 3600 * ($overtimePay / 9);
        }, $overtimeData[$sn])) : $overtimeHours * ($overtimePay / 9);

        $lateArrival = count($latetimeData[$sn] ?? []);
        $earlyLeave = 0;

        // Tính nghỉ có lương và không lương từ bảng leave_requests
        $unpaidLeave = 0;
        $paidLeave = 0;
        foreach ($leaveData[$sn] ?? [] as $leave) {
            $start = max(strtotime($monthStart), strtotime($leave['start_date']));
            $end = min(strtotime($monthEnd), strtotime($leave['end_date']));
            // Tính số ngày nghỉ dựa trên leave_days
            $days = $leave['leave_days'];
            
            // Dựa trên SalaryType để xác định loại nghỉ
            if ($leave['SalaryType'] === 'Nghỉ có lương') {
                $paidLeave += $days;
            } elseif ($leave['SalaryType'] === 'Nghỉ không lương') {
                $unpaidLeave += $days;
            }
        }

        // Nghỉ không phép chỉ tính từ chấm công (đã loại trừ các ngày nghỉ phép)
        $unauthorizedLeave = $unauthorizedLeaveFromRecords;

        $totalAttendance = $actualWorkingDays + $paidLeave;

        // Tính khen thưởng và kỷ luật
        $reward = 0;
        $discipline = 0;
        foreach ($rewardData[$sn] ?? [] as $rd) {
            if ($rd['type'] === 'reward') {
                $reward += $rd['amount'];
            } elseif ($rd['type'] === 'discipline') {
                $discipline += abs($rd['amount']); // Lấy giá trị tuyệt đối vì amount đã âm
            }
        }

        // Tính ứng lương và hoàn ứng
        $salaryAdvance = array_sum(array_map(fn($ad) => $ad['TypeID'] == 1 ? (float)str_replace(',', '', $ad['Amount']) : 0, $advanceData[$sn] ?? []));
        error_log("Salary Advance for $sn: " . $salaryAdvance);

        $salaryRepayment = array_sum(array_map(fn($ad) => $ad['TypeID'] == 2 ? (float)str_replace(',', '', $ad['Amount']) : 0, $advanceData[$sn] ?? []));
        error_log("Salary Repayment for $sn: " . $salaryRepayment);

        $netAdvance = $salaryAdvance - $salaryRepayment;
        error_log("Net Advance for $sn: " . $netAdvance);

        // Tính tổng thực lãnh theo công thức mới
        $totalReceived = ($dailySalary * $totalAttendance) - $insurance + $reward - $discipline - $netAdvance;

        $entry = [
            "personSn" => $employee['name'],
            "departmentId" => $department,
        ];

        foreach ($salaries as $s) {
            $salaryKey = "salaryData_" . $s['id'];
            $salaryValue = $salaryData[$salaryKey] ?? '/0';
            $entry[$salaryKey] = is_string($salaryValue) ? $salaryValue : '/0';
        }

        $entry["dailySalary"] = is_numeric($dailySalary) ? number_format($dailySalary, 0, ',', '.') : '0';
        $entry["insurance"] = is_numeric($insurance) ? number_format($insurance, 0, ',', '.') : '0';
        $entry["workingDays"] = "$totalWorkingDays/$actualWorkingDays";
        $entry["overtime"] = number_format($overtime, 0, ',', '.');
        $entry["lateArrival/earlyLeave"] = "$lateArrival/$earlyLeave";
        $entry["unpaidLeave"] = $unpaidLeave;
        $entry["paidLeave"] = $paidLeave;
        $entry["unauthorizedLeave"] = $unauthorizedLeave;
        $entry["totalAttendance"] = $totalAttendance;
        $entry["discipline"] = number_format($discipline, 0, ',', '.');
        $entry["reward"] = number_format($reward, 0, ',', '.');
        $entry["salaryAdvance"] = number_format($netAdvance, 0, ',', '.'); // Hiển thị ứng lương - hoàn ứng
        $entry["totalReceived"] = number_format($totalReceived, 0, ',', '.');

        error_log("Entry for employee $sn: " . print_r($entry, true));
        $datas[] = $entry;
    }

    $totalReceived = array_sum(array_map(fn($d) => (int)str_replace(',', '', $d['totalReceived']), $datas));

    $response = [
        "draw" => $draw,
        "recordsTotal" => $count,
        "recordsFiltered" => $count,
        "data" => $datas,
    ];

    echo json_encode($response);
})->setPermissions(['salaryCalculation']);
?>