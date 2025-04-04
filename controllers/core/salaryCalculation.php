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
    $vars['employeeFilter'] = array_map([$app, 'xss'], explode(',', $_GET['employee'] ?? '')?? "");

    $vars['salary'] = $app->select("staff-salary", ["id", "name", "price"], [
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
    $month = $app->xss($_POST['month'] ?? date('m'));
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

    $leaveRequests = $app->select("leave_requests", [
        "[>]leavetype" => ["LeaveId" => "LeaveTypeID"]
    ], [
        "leave_requests.personSN", "leave_requests.start_date", "leave_requests.end_date",
        "leavetype.Code"
    ], [
        "AND" => ["leave_requests.start_date[<=]" => $monthEnd, "leave_requests.end_date[>=]" => $monthStart]
    ]) ?? [];
    $leaveData = [];
    foreach ($leaveRequests as $request) {
        $leaveData[$request['personSN']][] = $request;
    }

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

    $rewards = $app->select("reward_discipline", ["personSn", "amount", "type"], [
        "date[~]" => "$year-$month%"
    ]) ?? [];
    $rewardData = [];
    foreach ($rewards as $rd) {
        $rewardData[$rd['personSn']][] = $rd;
    }

    $advances = $app->select("salaryadvances", ["sn", "Amount", "TypeID"], [
        "AppliedDate[~]" => "$year-$month%"
    ]) ?? [];
    $advanceData = [];
    foreach ($advances as $ad) {
        $advanceData[$ad['sn']][] = $ad;
    }

    $salaries = $app->select("staff-salary", ["id", "name", "price", "type"], ["type IN" => [1, 2], "status" => 'A']) ?? [];
    error_log("Salaries in POST: " . print_r($salaries, true));

    foreach ($employees as $index => $employee) {
        $sn = $employee['sn'];
        $department = $app->select("department", ["personName"], ["departmentId" => $employee['departmentId']])[0]['personName'] ?? 'Không xác định';

        $salaryData = [];
        $totalSalary = 0;
        $basicSalary = 0;
        foreach ($salaries as $s) {
            $price = is_numeric($s['price']) ? (int)$s['price'] : 0; // Đảm bảo price là số
            $salaryData["salaryData_" . $s['id']] = number_format($price, 0, ',', '.') . ' / ' . number_format($price / 12, 0, ',', '.');
            $totalSalary += $price;
            if ($s['type'] == 1) {
                $basicSalary = $price;
            }
        }
        error_log("Salary Data for employee $sn: " . print_r($salaryData, true));

        $dailySalary = $basicSalary ? $basicSalary / 26 : 0;
        $insurance = $basicSalary ? $basicSalary * 0.1 : 0;

        $totalWorkingDays = 0;
        $actualWorkingDays = 0;
        $overtimeHours = 0;
        $assignments = $assignmentMap[$sn] ?? [];
        $timeperiodId = end($assignments)['timeperiod_id'] ?? '1';
        $timePeriod = $timePeriodMap[$timeperiodId] ?? [];

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = "$year-$month-" . sprintf("%02d", $day);
            $dayOfWeek = date('N', strtotime($date));
            $offKey = $dayMap[$dayOfWeek]['off'];

            if (!$timePeriod || !isset($timePeriod[$offKey]) || !$timePeriod[$offKey]) {
                $totalWorkingDays++;
                $records = $attendanceData[$sn][$date] ?? [];
                if (!empty($records)) {
                    $actualWorkingDays++;
                    $checkIn = min(array_map('strtotime', $records));
                    $checkOut = max(array_map('strtotime', $records));
                    $startTime = strtotime("$date " . ($timePeriod[$dayMap[$dayOfWeek]['start']] ?? '09:00'));
                    $endTime = strtotime("$date " . ($timePeriod[$dayMap[$dayOfWeek]['end']] ?? '17:00'));
                    $workHours = ($endTime - $startTime) / 3600;
                    $actualHours = ($checkOut - $checkIn) / 3600;
                    if ($actualHours > $workHours) {
                        $overtimeHours += $actualHours - $workHours;
                    }
                }
            }
        }

        $overtimePay = $app->select("staff-salary", ["price"], ["type" => 3, "status" => 'A'])[0]['price'] ?? 0;
        $overtime = isset($overtimeData[$sn]) ? array_sum(array_map(function($ot) use ($overtimePay) {
            return (strtotime($ot['end']) - strtotime($ot['start'])) / 3600 * ($overtimePay / 8);
        }, $overtimeData[$sn])) : $overtimeHours * ($overtimePay / 8);

        $lateArrival = count($latetimeData[$sn] ?? []);
        $earlyLeave = 0;

        $unpaidLeave = 0;
        $paidLeave = 0;
        $unauthorizedLeave = 0;
        foreach ($leaveData[$sn] ?? [] as $leave) {
            $start = max(strtotime($monthStart), strtotime($leave['start_date']));
            $end = min(strtotime($monthEnd), strtotime($leave['end_date']));
            $days = ($end - $start) / (60 * 60 * 24) + 1;
            switch ($leave['Code']) {
                case 'NKL': $unpaidLeave += $days; break;
                case 'NCL': $paidLeave += $days; break;
                case 'NKP': $unauthorizedLeave += $days; break;
            }
        }

        $totalAttendance = $actualWorkingDays + $paidLeave;

        $reward = array_sum(array_map(fn($rd) => $rd['type'] == 1 ? $rd['amount'] : 0, $rewardData[$sn] ?? []));
        $discipline = array_sum(array_map(fn($rd) => $rd['type'] == 2 ? $rd['amount'] : 0, $rewardData[$sn] ?? []));

        $salaryAdvance = array_sum(array_map(fn($ad) => $ad['TypeID'] == 1 ? $ad['Amount'] : 0, $advanceData[$sn] ?? []));

        $totalReceived = ($totalSalary / 12) + $overtime + $reward - $discipline - $insurance - $salaryAdvance;

        $entry = [
            "personSn" => $employee['name'],
            "departmentId" => $department,
        ];

        // Sửa lỗi ánh xạ salaryData_ để sử dụng dữ liệu từ $salaryData
        foreach ($salaries as $s) {
            $salaryKey = "salaryData_" . $s['id'];
            $salaryValue = $salaryData[$salaryKey] ?? '0 / 0'; // Lấy giá trị từ $salaryData
            $entry[$salaryKey] = is_string($salaryValue) ? $salaryValue : '0 / 0'; // Đảm bảo giá trị là chuỗi
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
        $entry["salaryAdvance"] = number_format($salaryAdvance, 0, ',', '.');
        $entry["totalReceived"] = number_format($totalReceived, 0, ',', '.');

        error_log("Entry for employee $sn: " . print_r($entry, true));
        $datas[] = $entry;
    }

    $totalReward = array_sum(array_map(fn($d) => (int)str_replace(',', '', $d['reward']), $datas));
    $totalDiscipline = array_sum(array_map(fn($d) => (int)str_replace(',', '', $d['discipline']), $datas));
    $totalSalaryAdvance = array_sum(array_map(fn($d) => (int)str_replace(',', '', $d['salaryAdvance']), $datas));
    $totalReceived = array_sum(array_map(fn($d) => (int)str_replace(',', '', $d['totalReceived']), $datas));

    $response = [
        "draw" => $draw,
        "recordsTotal" => $count,
        "recordsFiltered" => $count,
        "data" => $datas,
        "footer" => [
            "totalReward" => number_format($totalReward, 0, ',', '.'),
            "totalDiscipline" => number_format($totalDiscipline, 0, ',', '.'),
            "totalSalaryAdvance" => number_format($totalSalaryAdvance, 0, ',', '.'),
            "totalReceived" => number_format($totalReceived, 0, ',', '.')
        ]
    ];

    echo json_encode($response);
})->setPermissions(['salaryCalculation']);