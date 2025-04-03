<?php
if (!defined('ECLO')) die("Hacking attempt");
$jatbi = new Jatbi($app);
$setting = $app->getValueData('setting');

// GET Route: Display the attendance page
$app->router("/manager/attendance", 'GET', function($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Chấm công");

    // Lấy danh sách nhân viên để hiển thị trong bộ lọc
    $vars['employees'] = $app->select("employee", ["sn", "name"], [
        "ORDER" => ["name" => "ASC"],
        "status" => "A",
    ]);

    // Lấy tháng và năm từ query string (mặc định là tháng hiện tại)
    $month = $app->xss($_GET['month'] ?? date('m'));
    $year = $app->xss($_GET['year'] ?? date('Y'));
    $vars['month'] = $month;
    $vars['year'] = $year;

    // Tính số ngày trong tháng
    $firstDayOfMonth = new DateTime("$year-$month-01");
    $totalDays = (int)$firstDayOfMonth->format('t');
    $vars['total_days'] = $totalDays;

    // Tạo mảng ngày và thứ trong tuần
    $daysOfWeek = [];
    for ($day = 1; $day <= $totalDays; $day++) {
        $date = new DateTime("$year-$month-$day");
        $daysOfWeek[$day] = $date->format('w'); // 0 (Chủ nhật) đến 6 (Thứ 7)
    }
    $vars['days_of_week'] = $daysOfWeek;

    // Lấy danh sách loại nghỉ phép để hiển thị trong phần chú thích
    $leaveTypes = $app->select("leavetype", [
        "LeaveTypeID",
        "Code",
        "Name"
    ], [
        "Status" => "A",
    ]);
    $vars['leave_types'] = $leaveTypes;

    echo $app->render('templates/employee/attendance.html', $vars);
})->setPermissions(['attendance']);

// POST Route: Handle DataTables server-side processing
$app->router("/manager/attendance", 'POST', function($vars) use ($app, $jatbi) {
    $app->header([
        'Content-Type' => 'application/json',
    ]);

    // Log received POST data for debugging
    error_log("Received POST Data: " . print_r($_POST, true));

    // DataTables parameters
    $draw = $_POST['draw'] ?? 0;
    $start = $_POST['start'] ?? 0;
    $length = $_POST['length'] ?? 10;
    $searchValue = $_POST['search']['value'] ?? '';
    $month = sprintf("%02d", $_POST['month'] ?? date('m'));
    $year = $_POST['year'] ?? date('Y');
    $personnel = $_POST['personnels'] ?? '';

    // Tính số ngày trong tháng
    $firstDayOfMonth = new DateTime("$year-$month-01");
    $totalDays = (int)$firstDayOfMonth->format('t');

    // Tạo mảng nhãn thứ trong tuần
    $daysOfWeekLabels = [
        1 => 'T2',
        2 => 'T3',
        3 => 'T4',
        4 => 'T5',
        5 => 'T6',
        6 => 'T7',
        0 => 'CN'
    ];

    // Build conditions for fetching employees
    $employeeConditions = [
        "LIMIT" => [$start, $length],
        "ORDER" => ["employee.name" => "ASC"],
        "employee.status" => "A",
        
    ];
    if ($personnel) {
        $employeeConditions["AND"]["employee.sn"] = $personnel;
    }
    if ($searchValue) {
        $employeeConditions["AND"]["OR"] = [
            "employee.sn[~]" => $searchValue,
            "employee.name[~]" => $searchValue,
            "department.personName[~]" => $searchValue,
        ];
    }
    // Build conditions for counting filtered employees
    $filteredConditions = [
        "employee.status" => "A"
    ];
    if ($personnel) {
        $filteredConditions["employee.sn"] = $personnel;
    }
    if ($searchValue) {
        $filteredConditions["OR"] = [
            "employee.sn[~]" => $searchValue,
            "employee.name[~]" => $searchValue,
            "department.personName[~]" => $searchValue,
        ];
    }

    // Count total and filtered employees
    $totalEmployees = $app->count("employee", ["status" => "A"]);
    $filteredRecords = $app->select("employee", [
        "[>]department" => ["departmentId" => "departmentId"]
    ], [
        "employee.sn"
    ], $filteredConditions);
    $filteredEmployees = count($filteredRecords);
    // Fetch employees with their departments
    $employees = $app->select("employee", [
        "[>]department" => ["departmentId" => "departmentId"]
    ], [
        "employee.sn",
        "employee.name",
        "employee.departmentId",
        "department.personName(department_name)"
    ], $employeeConditions);

    // Fetch attendance records for the month
    $recordConditions = [
        "AND" => [
            "record.createTime[>=]" => "$year-$month-01 00:00:00",
            "record.createTime[<=]" => "$year-$month-$totalDays 23:59:59"
        ]
    ];
    if ($personnel) {
        $recordConditions["AND"]["record.personSn"] = $personnel;
    }
    $records = $app->select("record", [
        "personSn",
        "createTime"
    ], $recordConditions) ?? [];

    // Fetch time periods
    $timePeriods = $app->select("timeperiod", [
        "acTzNumber", "monStart", "monEnd", "tueStart", "tueEnd", "wedStart", "wedEnd",
        "thursStart", "thursEnd", "friStart", "friEnd", "satStart", "satEnd", "sunStart", "sunEnd",
        "mon_off", "tue_off", "wed_off", "thu_off", "fri_off", "sat_off", "sun_off"
    ], ["status" => "A"]) ?? [];
    $timePeriodMap = array_column($timePeriods, null, 'acTzNumber');

    // Fetch assignments to map employees to time periods
    $assignments = $app->select("assignments", ["employee_id", "timeperiod_id", "apply_date"], [
        "apply_date[<=]" => "$year-$month-$totalDays 23:59:59",
        "ORDER" => ["employee_id" => "ASC", "apply_date" => "ASC"]
    ]) ?? [];
    $assignmentMap = [];
    foreach ($assignments as $assignment) {
        // Chỉ lấy phân công gần nhất cho mỗi nhân viên
        if (!isset($assignmentMap[$assignment['employee_id']])) {
            $assignmentMap[$assignment['employee_id']] = $assignment;
        }
    }

    // Fetch leave requests
    $leaveRequests = $app->select("leave_requests", [
        "[>]leavetype" => ["LeaveId" => "LeaveTypeID"]
    ], [
        "leave_requests.personSN",
        "leave_requests.start_date",
        "leave_requests.end_date",
        "leavetype.Code",
        "leavetype.Name",
        "leavetype.LeaveTypeID"
    ], [
        "AND" => [
            "leave_requests.start_date[<=]" => "$year-$month-$totalDays 23:59:59",
            "leave_requests.end_date[>=]" => "$year-$month-01 00:00:00",
            "leave_requests.Status" => 1 // Chỉ lấy các yêu cầu nghỉ phép đã được phê duyệt
        ]
    ]) ?? [];
    $leaveData = [];
    foreach ($leaveRequests as $request) {
        $leaveData[$request['personSN']][] = $request;
    }

    // Organize attendance records by employee and date
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

    // Map days of the week to time period fields
    $dayMap = [
        1 => ['start' => 'monStart', 'end' => 'monEnd', 'off' => 'mon_off'],
        2 => ['start' => 'tueStart', 'end' => 'tueEnd', 'off' => 'tue_off'],
        3 => ['start' => 'wedStart', 'end' => 'wedEnd', 'off' => 'wed_off'],
        4 => ['start' => 'thursStart', 'end' => 'thursEnd', 'off' => 'thu_off'],
        5 => ['start' => 'friStart', 'end' => 'friEnd', 'off' => 'fri_off'],
        6 => ['start' => 'satStart', 'end' => 'satEnd', 'off' => 'sat_off'],
        0 => ['start' => 'sunStart', 'end' => 'sunEnd', 'off' => 'sun_off']
    ];

    // Format data for DataTables
    $formattedData = array_map(function($employee) use ($app, $jatbi, $totalDays, $year, $month, $attendanceData, $timePeriodMap, $assignmentMap, $leaveData, $dayMap, $daysOfWeekLabels) {
        $sn = $employee['sn'];
        $row = [
            "name" => $employee['name'] ?? 'N/A',
            "department" => $employee['department_name'] ?? 'N/A',
            "attendance" => ''
        ];

        // Determine the time period for this employee
        $assignment = $assignmentMap[$sn] ?? null;
        $timeperiodId = $assignment ? $assignment['timeperiod_id'] : '1'; // Default to '1' if no assignment
        $timePeriod = $timePeriodMap[$timeperiodId] ?? [];

        // Build attendance table for the month
        $attendanceTable = '<table class="attendance-table">';
        
        // Add header row for days and weekdays
        $attendanceTable .= '<thead><tr>';
        for ($day = 1; $day <= $totalDays; $day++) {
            $date = "$year-$month-" . sprintf("%02d", $day);
            $dayOfWeek = (int)date('w', strtotime($date));
            $dayLabel = $daysOfWeekLabels[$dayOfWeek];
            $headerClass = '';
            if ($dayOfWeek == 6) {
                $headerClass = 'saturday';
            } elseif ($dayOfWeek == 0) {
                $headerClass = 'sunday';
            }
            $attendanceTable .= '<th class="' . $headerClass . '">' . sprintf("%02d", $day) . '<br>(' . $dayLabel . ')</th>';
        }
        $attendanceTable .= '</tr></thead>';

        // Add data row for attendance
        $attendanceTable .= '<tbody><tr>';
        for ($day = 1; $day <= $totalDays; $day++) {
            $date = "$year-$month-" . sprintf("%02d", $day);
            $dayOfWeek = (int)date('w', strtotime($date));
            $offKey = $dayMap[$dayOfWeek]['off'];
            $isDayOff = isset($timePeriod[$offKey]) && $timePeriod[$offKey] == 1;

            // Check for leave requests (off-permitted)
            $isOffPermitted = false;
            $leaveTypeId = null;
            $leaveCode = null;
            $leaveName = null;
            foreach ($leaveData[$sn] ?? [] as $leave) {
                $start = strtotime($leave['start_date']);
                $end = strtotime($leave['end_date']);
                $current = strtotime($date);
                if ($current >= $start && $current <= $end) {
                    $isOffPermitted = true;
                    $leaveTypeId = $leave['LeaveTypeID'];
                    $leaveCode = $leave['Code'];
                    $leaveName = $leave['Name'];
                    break;
                }
            }

            // Get attendance records for this day
            $records = $attendanceData[$sn][$date] ?? [];
            $checkIn = null;
            $checkOut = null;
            $status = [];

            if ($isOffPermitted) {
                $status[] = 'off-permitted';
                if (!empty($records)) {
                    $times = array_map('strtotime', $records);
                    $checkIn = date('H:i', min($times));
                    $checkOut = count($records) > 1 ? date('H:i', max($times)) : null;
                }
            } elseif ($isDayOff) {
                $status[] = 'day-off';
                if (!empty($records)) {
                    $times = array_map('strtotime', $records);
                    $checkIn = date('H:i', min($times));
                    $checkOut = count($records) > 1 ? date('H:i', max($times)) : null;
                }
            } elseif (!empty($records)) {
                $times = array_map('strtotime', $records);
                $checkIn = date('H:i', min($times));
                $checkOut = count($records) > 1 ? date('H:i', max($times)) : null;

                // Determine status based on check-in/check-out times
                $startTime = $timePeriod[$dayMap[$dayOfWeek]['start']] ?? '08:00'; // Default to 08:00
                $endTime = $timePeriod[$dayMap[$dayOfWeek]['end']] ?? '17:00'; // Default to 17:00
                $checkInStd = strtotime("$date $startTime");
                $checkOutStd = strtotime("$date $endTime");

                $lateMinutes = (strtotime($records[0]) - $checkInStd) / 60;
                $earlyMinutes = ($checkOutStd - strtotime(end($records))) / 60;

                if ($checkIn && $checkOut && $lateMinutes <= 30 && $earlyMinutes <= 30) {
                    $status[] = 'checked';
                } else {
                    if ($lateMinutes > 30) $status[] = 'late';
                    if (!$checkOut || $earlyMinutes > 30) $status[] = 'not-checked';
                }
            } else {
                $status[] = 'no-record';
            }

            // Format the status
            $statusClass = '';
            $statusText = '';
            if (!empty($status)) {
                if (count($status) == 1) {
                    if ($status[0] == 'off-permitted') {
                        $statusClass = 'status-off-permitted';
                        $statusText = $leaveCode ? $leaveCode : $jatbi->lang("OFF");
                    } else {
                        $statusClass = 'status-' . $status[0];
                        $statusText = $status[0] == 'day-off' ? $jatbi->lang("OFF") : '-';
                    }
                } elseif (count($status) == 2 && in_array('late', $status) && in_array('not-checked', $status)) {
                    $statusClass = 'status-late-not-checked';
                    $statusText = '-';
                }
            }

            // Thêm class saturday hoặc sunday cho ô dữ liệu
            $cellClass = $statusClass;
            

            // Add cell to attendance table
            $attendanceTable .= '<td class="' . $cellClass . '">';
            if ($checkIn && $checkOut) {
                $attendanceTable .= $checkIn . '<br>' . $checkOut;
            } elseif ($checkIn && !$checkOut) {
                $attendanceTable .= $checkIn;
            } elseif ($statusText) {
                $attendanceTable .= $statusText;
            } else {
                $attendanceTable .= '-';
            }
            $attendanceTable .= '</td>';
        }
        $attendanceTable .= '</tr></tbody>';
        $attendanceTable .= '</table>';

        $row['attendance'] = $attendanceTable;

        return $row;
    }, $employees);

    // Prepare JSON response
    $response = json_encode([
        "draw" => $draw,
        "recordsTotal" => $totalEmployees,
        "recordsFiltered" => $filteredEmployees,
        "data" => $formattedData
    ]);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON Encode Error: " . json_last_error_msg());
    }

    echo $response;
})->setPermissions(['attendance']);