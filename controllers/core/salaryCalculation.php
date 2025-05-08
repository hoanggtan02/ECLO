<?php
if (!defined('ECLO')) die("Hacking attempt");
$jatbi = new Jatbi($app);
$setting = $app->getValueData('setting');

// Định nghĩa route GET để hiển thị giao diện tính lương
$app->router("/salaryCalculation", 'GET', function($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Tính lương");

    // Lấy danh sách nhân viên và phòng ban
    $vars['employee'] = $app->select("employee", ["name (text)", "sn (value)"], []);
    $vars['departmentList'] = $app->select("department", ["departmentId (value)", "personName (text)"]);

    // Lấy dữ liệu lọc từ URL (nếu có)
    $vars['month'] = $app->xss($_GET['month'] ?? '');
    $vars['year'] = $app->xss($_GET['year'] ?? '');
    $vars['departmentFilter'] = $app->xss($_GET['department'] ?? '');
    $vars['employeeFilter'] = array_map([$app, 'xss'], explode(',', $_GET['employee'] ?? ''));

    // Lấy danh sách loại lương
    $vars['salary'] = $app->select("staff-salary", ["id", "name", "price", "priceValue"], [
        "type IN" => [1, 2],
        "status" => 'A'
    ]);

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

    // Định nghĩa các cột hợp lệ cho sắp xếp và ánh xạ với cột thực tế trong DB
    $validColumns = [
        0 => "employee.name", // personSn ánh xạ với employee.name
        1 => "department.personName", // departmentId ánh xạ với department.personName
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

    // Chỉ cho phép sắp xếp trên các cột thực sự tồn tại trong DB
    $orderColumn = in_array($orderColumnIndex, [0, 1]) ? $validColumns[$orderColumnIndex] : "employee.name";

    // Lấy tham số lọc từ $_POST
    $month = str_pad($app->xss($_POST['month'] ?? date('m')), 2, '0', STR_PAD_LEFT);
    $year = $app->xss($_POST['year'] ?? date('Y'));
    $employeeFilter = array_map([$app, 'xss'], $_POST['employee'] ?? []);
    $departmentFilter = $app->xss($_POST['department'] ?? '');

    // Xây dựng điều kiện lọc
    $where = [
        "LIMIT" => [$start, $length],
        "ORDER" => [$orderColumn => $orderDir]
    ];

    // Chỉ thêm $where["AND"] nếu có điều kiện lọc
    $conditions = [];
    if (!empty($searchValue)) {
        $conditions["OR"] = [
            "employee.sn[~]" => $searchValue,
            "employee.name[~]" => $searchValue,
            "department.personName[~]" => $searchValue,
        ];
    }

    if (!empty($employeeFilter)) {
        $conditions["employee.sn IN"] = $employeeFilter;
    }

    if (!empty($departmentFilter)) {
        $conditions["employee.departmentID"] = $departmentFilter;
    }

    // Nếu có điều kiện lọc, thêm vào $where["AND"]
    if (!empty($conditions)) {
        $where["AND"] = $conditions;
    }

    // Join với bảng department để lấy tên phòng ban và hỗ trợ tìm kiếm/sắp xếp
    $join = [
        "[>]department" => ["departmentId" => "departmentId"]
    ];

    // Tính tổng số nhân viên (không áp dụng bộ lọc) - recordsTotal
    $totalEmployees = $app->count("employee", []) ?? 0;

    // Tính số nhân viên sau khi áp dụng bộ lọc - recordsFiltered
    $filteredCount = $app->count("employee", $join, "*", !empty($conditions) ? ["AND" => $conditions] : []) ?? 0;

    // Lấy danh sách nhân viên với điều kiện lọc và phân trang
    $employees = $app->select("employee", $join, ["employee.sn", "employee.name", "employee.departmentId", "department.personName"], $where) ?? [];

    $datas = [];

    $daysInMonth = date('t', mktime(0, 0, 0, $month, 1, $year));
    $monthStart = "$year-$month-01"; // Ví dụ: 2025-04-01
    $monthEnd = "$year-$month-$daysInMonth"; // Ví dụ: 2025-04-30
    $monthStartTimestamp = strtotime($monthStart);
    $monthEndTimestamp = strtotime($monthEnd);

    // Ánh xạ ngày trong tuần với các cột trong bảng timeperiod, bao gồm work_credit
    $dayMap = [
        1 => ['start' => 'monStart', 'end' => 'monEnd', 'off' => 'mon_off', 'credit' => 'mon_work_credit'],
        2 => ['start' => 'tueStart', 'end' => 'tueEnd', 'off' => 'tue_off', 'credit' => 'tue_work_credit'],
        3 => ['start' => 'wedStart', 'end' => 'wedEnd', 'off' => 'wed_off', 'credit' => 'wed_work_credit'],
        4 => ['start' => 'thursStart', 'end' => 'thursEnd', 'off' => 'thu_off', 'credit' => 'thu_work_credit'],
        5 => ['start' => 'friStart', 'end' => 'friEnd', 'off' => 'fri_off', 'credit' => 'fri_work_credit'],
        6 => ['start' => 'satStart', 'end' => 'satEnd', 'off' => 'sat_off', 'credit' => 'sat_work_credit'],
        7 => ['start' => 'sunStart', 'end' => 'sunEnd', 'off' => 'sun_off', 'credit' => 'sun_work_credit']
    ];

    // Lấy dữ liệu từ bảng timeperiod, bao gồm cả work_credit
    $timePeriods = $app->select("timeperiod", [
        "acTzNumber", "monStart", "monEnd", "tueStart", "tueEnd", "wedStart", "wedEnd",
        "thursStart", "thursEnd", "friStart", "friEnd", "satStart", "satEnd", "sunStart", "sunEnd",
        "mon_off", "tue_off", "wed_off", "thu_off", "fri_off", "sat_off", "sun_off",
        "mon_work_credit", "tue_work_credit", "wed_work_credit", "thu_work_credit",
        "fri_work_credit", "sat_work_credit", "sun_work_credit"
    ]) ?? [];
    $timePeriodMap = array_column($timePeriods, null, 'acTzNumber');

    $assignments = $app->select("assignments", ["employee_id", "timeperiod_id", "apply_date"], [
        "apply_date[<=]" => "$year-$month-$daysInMonth 23:59:59",
        "ORDER" => ["apply_date" => "ASC"]
    ]) ?? [];
    $assignmentMap = [];
    foreach ($assignments as $assignment) {
        $employeeId = $assignment['employee_id'];
        if (!isset($assignmentMap[$employeeId])) {
            $assignmentMap[$employeeId] = [];
        }
        $assignmentMap[$employeeId][] = $assignment;
    }

    $records = $app->select("record", ["personSn", "createTime"], [
        "AND" => ["createTime[>=]" => "$year-$month-01 00:00:00", "createTime[<=]" => "$year-$month-$daysInMonth 23:59:59"]
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

    // Lấy dữ liệu từ bảng shift (nhảy ca)
    $shifts = $app->select("shift", [
        "employee", "day", "shift", "day2", "shift2", "statu"
    ], [
        "AND" => [
            "day[>=]" => $monthStart,
            "day[<=]" => $monthEnd,
            "statu" => 'A'
        ]
    ]) ?? [];
    $shiftData = [];
    foreach ($shifts as $shift) {
        $shiftData[$shift['employee']][$shift['day']][] = $shift;
    }
    error_log("Shift Data for month $year-$month: " . print_r($shiftData, true));

    // Lấy dữ liệu từ bảng overtime (tăng ca)
    $overtimes = $app->select("overtime", ["employee", "dayStart (start)", "dayEnd (end)", "money"], [
        "AND" => [
            "dayStart[>=]" => "$year-$month-01 00:00:00",
            "dayEnd[<=]" => "$year-$month-$daysInMonth 23:59:59",
            "statu" => 'Approved' // Chỉ lấy các bản ghi đã được phê duyệt
        ]
    ]) ?? [];
    $overtimeData = [];
    foreach ($overtimes as $ot) {
        $overtimeData[$ot['employee']][] = $ot;
    }
    error_log("Overtime Data for month $year-$month: " . print_r($overtimeData, true));

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
        "AND" => ["leave_requests.start_date[<=]" => "$year-$month-$daysInMonth 23:59:59", "leave_requests.end_date[>=]" => "$year-$month-01 00:00:00"]
    ]) ?? [];
    $leaveData = [];
    $leaveDaysMap = []; // Lưu danh sách các ngày nghỉ phép cho từng nhân viên, bao gồm số ngày và loại nghỉ
    foreach ($leaveRequests as $request) {
        $sn = $request['personSN'];
        $leaveData[$sn][] = $request;

        // Tạo danh sách các ngày nghỉ phép
        $start = max(strtotime("$year-$month-01 00:00:00"), strtotime($request['start_date']));
        $end = min(strtotime("$year-$month-$daysInMonth 23:59:59"), strtotime($request['end_date']));
        $startDate = date('Y-m-d', $start);
        $endDate = date('Y-m-d', $end);
        $currentDate = $startDate;

        if (!isset($leaveDaysMap[$sn])) {
            $leaveDaysMap[$sn] = [];
        }

        // Tính số ngày trong khoảng thời gian nghỉ
        $totalLeaveDays = $request['leave_days'];
        $daysInRange = (strtotime($endDate) - strtotime($startDate)) / (60 * 60 * 24) + 1;
        $leaveDaysPerDay = $totalLeaveDays / $daysInRange; // Số ngày nghỉ trên mỗi ngày (ví dụ: 0.5 nếu nghỉ nửa ngày)

        // Thêm từng ngày trong khoảng thời gian nghỉ vào leaveDaysMap
        while (strtotime($currentDate) <= strtotime($endDate)) {
            $leaveDaysMap[$sn][$currentDate] = [
                'days' => $leaveDaysPerDay, // Số ngày nghỉ cho ngày đó (0.5 hoặc 1)
                'type' => $request['SalaryType'] // Loại nghỉ (có lương/không lương)
            ];
            $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
        }
    }
    error_log("Leave Data: " . print_r($leaveData, true));
    error_log("Leave Days Map: " . print_r($leaveDaysMap, true));

    // Lấy dữ liệu từ bảng latetime để áp dụng luật phạt
    $latetimes = $app->select("latetime", ["sn", "type", "value", "amount", "apply_date"], [
        "AND" => [
            "apply_date[<=]" => $monthEnd,
            "status" => 'A'
        ],
        "ORDER" => ["apply_date" => "DESC"]
    ]) ?? [];
    $latetimeRules = [];
    foreach ($latetimes as $lt) {
        $sn = $lt['sn'];
        $type = $lt['type'];
        $applyDate = strtotime($lt['apply_date']);
        if (!isset($latetimeRules[$sn])) {
            $latetimeRules[$sn] = [];
        }
        if (!isset($latetimeRules[$sn][$type])) {
            $latetimeRules[$sn][$type] = [];
        }
        // Chỉ lưu luật mới nhất cho mỗi loại (Đi trễ/Về sớm) của nhân viên
        if (empty($latetimeRules[$sn][$type]) || $applyDate > strtotime($latetimeRules[$sn][$type]['apply_date'])) {
            $latetimeRules[$sn][$type] = [
                'threshold' => $lt['value'],
                'penalty' => $lt['amount'],
                'apply_date' => $lt['apply_date']
            ];
        }
    }
    error_log("Latetime Rules: " . print_r($latetimeRules, true));

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
    $advances = $app->select("salaryadvances", ["sn", "Amount", "TypeID"], [
        "AND" => [
            "AppliedDate[>=]" => "$year-$month-01",
            "AppliedDate[<=]" => "$year-$month-$daysInMonth"
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

    // Lấy tất cả hợp đồng lao động
    $contracts = $app->select("employee_contracts", [
        "id", "person_sn", "position_id", "working_date", "contract_duration", "contract_type"
    ], [
        "ORDER" => ["working_date" => "DESC"]
    ]) ?? [];
    $contractMap = [];
    foreach ($contracts as $contract) {
        if (!isset($contractMap[$contract['person_sn']])) {
            $contractMap[$contract['person_sn']] = [];
        }
        $contractMap[$contract['person_sn']][] = $contract;
    }
    error_log("Contract Map (all contracts): " . print_r($contractMap, true));

    $contractSalaries = $app->select("contract_salary", ["Id_contract", "Id_salary"], []) ?? [];
    $contractSalaryMap = [];
    foreach ($contractSalaries as $cs) {
        $contractSalaryMap[$cs['Id_contract']][] = $cs['Id_salary'];
    }
    error_log("Contract Salary Map: " . print_r($contractSalaryMap, true));

    $salaries = $app->select("staff-salary", ["id", "name", "price", "type", "priceValue"], [
        "type IN" => [1, 2],
        "status" => 'A'
    ]) ?? [];
    error_log("Salaries in POST: " . print_r($salaries, true));

    // Lấy dữ liệu từ bảng staff-holiday
    $holidays = $app->select("staff-holiday", [
        "id", "departmentId", "name", "startDate", "endDate", "salaryCoefficient"
    ], [
        "AND" => [
            "startDate[<=]" => $monthEnd,
            "endDate[>=]" => $monthStart,
            "status" => 'A'
        ]
    ]) ?? [];
    error_log("Holiday Data for month $year-$month: " . print_r($holidays, true));

    // Tạo mảng ánh xạ ngày nghỉ lễ
    $holidayMap = [];
    foreach ($holidays as $holiday) {
        $start = max(strtotime($monthStart), strtotime($holiday['startDate']));
        $end = min(strtotime($monthEnd), strtotime($holiday['endDate']));
        $startDate = date('Y-m-d', $start);
        $endDate = date('Y-m-d', $end);
        $currentDate = $startDate;

        while (strtotime($currentDate) <= strtotime($endDate)) {
            $holidayMap[$currentDate][] = [
                'departmentId' => $holiday['departmentId'],
                'name' => $holiday['name'],
                'coefficient' => $holiday['salaryCoefficient']
            ];
            $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
        }
    }
    error_log("Holiday Map: " . print_r($holidayMap, true));

    foreach ($employees as $index => $employee) {
        $sn = $employee['sn'];
        $department = $employee['personName'] ?? 'Không xác định'; // Lấy trực tiếp từ kết quả join
        $departmentId = $employee['departmentId'] ?? 0; // Lấy departmentId của nhân viên

        $salaryData = [];
        $totalSalary = 0;
        $basicSalary = 0;
        $allowanceSalary = 0;
        $dailySalary = 0;
        $totalWorkingDays = 0;
        $actualWorkingDays = 0;
        $totalWorkingHours = 0; // Tổng số giờ làm việc thực tế
        $holidayWorkingDays = []; // Lưu số ngày làm việc trong các ngày nghỉ lễ
        $overtimeHours = 0;
        $unauthorizedLeaveFromRecords = 0; // Nghỉ không phép từ chấm công
        $assignments = $assignmentMap[$sn] ?? [];
        $processedDays = [];
        $totalLateMinutes = 0; // Tổng số phút đi trễ
        $totalEarlyMinutes = 0; // Tổng số phút về sớm
        $totalLatePenalty = 0; // Tổng tiền phạt đi trễ
        $totalEarlyPenalty = 0; // Tổng tiền phạt về sớm

        // Duyệt qua từng ngày trong tháng để tính ngày công, giờ làm việc, nghỉ không phép, và kiểm tra đi trễ/về sớm
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = "$year-$month-" . sprintf("%02d", $day);
            $dateTimestamp = strtotime($date);
            $dayOfWeek = date('N', $dateTimestamp);
            $offKey = $dayMap[$dayOfWeek]['off'];
            $creditKey = $dayMap[$dayOfWeek]['credit'];
            $startKey = $dayMap[$dayOfWeek]['start'];
            $endKey = $dayMap[$dayOfWeek]['end'];

            // Xác định timeperiod_id dựa trên ngày áp dụng gần nhất
            $applicableTimePeriod = null;
            if (!empty($assignments)) {
                $latestApplyDate = null;
                $timeperiodId = '1'; // Giá trị mặc định nếu không có bản ghi phù hợp
                foreach ($assignments as $assignment) {
                    $applyDateTimestamp = strtotime($assignment['apply_date']);
                    if ($applyDateTimestamp <= $dateTimestamp && ($latestApplyDate === null || $applyDateTimestamp > $latestApplyDate)) {
                        $latestApplyDate = $applyDateTimestamp;
                        $timeperiodId = $assignment['timeperiod_id'];
                    }
                }
                $applicableTimePeriod = $timePeriodMap[$timeperiodId] ?? [];
            } else {
                $applicableTimePeriod = $timePeriodMap['1'] ?? [];
            }

            // Kiểm tra xem ngày đó có phải ngày nghỉ theo lịch làm việc không
            $isOffDay = $applicableTimePeriod && isset($applicableTimePeriod[$offKey]) && $applicableTimePeriod[$offKey];
            // Lấy giá trị work_credit cho ngày này, mặc định là 1 nếu không có
            $workCredit = $applicableTimePeriod && isset($applicableTimePeriod[$creditKey]) ? (float)$applicableTimePeriod[$creditKey] : 1.0;
            $records = $attendanceData[$sn][$date] ?? [];

            // Tính số giờ làm việc tiêu chuẩn trong ngày và lấy thời gian bắt đầu/kết thúc
            $hoursForDay = 0;
            $startTime = null;
            $endTime = null;
            if ($applicableTimePeriod && isset($applicableTimePeriod[$startKey], $applicableTimePeriod[$endKey])) {
                $startTime = strtotime("$date " . $applicableTimePeriod[$startKey]);
                $endTime = strtotime("$date " . $applicableTimePeriod[$endKey]);
                if ($endTime > $startTime) {
                    $hoursForDay = ($endTime - $startTime) / 3600;
                }
            }

            // Kiểm tra xem ngày này có phải ngày nghỉ lễ không
            $holidaysOnDate = $holidayMap[$date] ?? [];
            $applicableHolidays = [];
            foreach ($holidaysOnDate as $holiday) {
                // Kiểm tra xem ngày nghỉ lễ áp dụng cho phòng ban của nhân viên không
                if ($holiday['departmentId'] == 0 || $holiday['departmentId'] == $departmentId) {
                    $applicableHolidays[] = $holiday;
                }
            }

            if (!$isOffDay) {
                // Ngày làm việc
                $totalWorkingDays += $workCredit; // Tăng tổng số ngày làm việc theo work_credit

                // Kiểm tra chấm công trực tiếp
                if (!empty($records)) {
                    if (!isset($processedDays[$date])) {
                        // Sắp xếp records theo thời gian để lấy lần chấm công đầu tiên và cuối cùng
                        usort($records, fn($a, $b) => strtotime($a) <=> strtotime($b));
                        $firstRecord = $records[0]; // Lần chấm công đầu tiên (check-in)
                        $lastRecord = end($records); // Lần chấm công cuối cùng (check-out)

                        // Tính đi trễ với delay 15 phút
                        if ($startTime && $firstRecord) {
                            $checkInTime = strtotime($firstRecord);
                            $lateThreshold = $startTime + (15 * 60); // Thêm 15 phút vào startTime
                            if ($checkInTime > $lateThreshold) {
                                $lateMinutes = ($checkInTime - $startTime) / 60; // Số phút đi trễ tính từ startTime
                                $totalLateMinutes += $lateMinutes;
                                // Kiểm tra luật phạt đi trễ từ bảng latetime
                                $lateRule = $latetimeRules[$sn]['Đi trễ'] ?? null;
                                if ($lateRule && $lateMinutes >= $lateRule['threshold']) {
                                    $totalLatePenalty += $lateRule['penalty'];
                                }
                            }
                        }

                        // Tính về sớm với delay 15 phút
                        if ($endTime && $lastRecord) {
                            $checkOutTime = strtotime($lastRecord);
                            $earlyThreshold = $endTime - (15 * 60); // Trừ 15 phút từ endTime
                            if ($checkOutTime < $earlyThreshold) {
                                $earlyMinutes = ($endTime - $checkOutTime) / 60; // Số phút về sớm tính từ endTime
                                $totalEarlyMinutes += $earlyMinutes;
                                // Kiểm tra luật phạt về sớm từ bảng latetime
                                $earlyRule = $latetimeRules[$sn]['Về sớm'] ?? null;
                                if ($earlyRule && $earlyMinutes >= $earlyRule['threshold']) {
                                    $totalEarlyPenalty += $earlyRule['penalty'];
                                }
                            }
                        }

                        // Kiểm tra xem ngày này có trong leaveDaysMap không
                        $leaveInfo = $leaveDaysMap[$sn][$date] ?? null;
                        if ($leaveInfo) {
                            if ($leaveInfo['type'] === 'Nghỉ có lương') {
                                // Trường hợp 1: Nghỉ có lương 0.5 ngày và có đi làm -> Tính full lương
                                $actualWorkingDays += $workCredit; // Full ngày công
                                $totalWorkingHours += $hoursForDay; // Full giờ làm việc
                            } elseif ($leaveInfo['type'] === 'Nghỉ không lương') {
                                // Trường hợp 2: Nghỉ không lương 0.5 ngày và có đi làm -> Tính 0.5 ngày
                                $actualWorkingDays += ($workCredit * (1 - $leaveInfo['days'])); // Chỉ tính 0.5 ngày
                                $totalWorkingHours += $hoursForDay * (1 - $leaveInfo['days']); // Chỉ tính 0.5 giờ
                            }
                        } else {
                            // Không có nghỉ phép, tính giờ làm việc tiêu chuẩn
                            $dayWorkCredit = $workCredit;
                            if (!empty($applicableHolidays)) {
                                $maxCoefficient = max(array_map(fn($h) => $h['coefficient'], $applicableHolidays));
                                $dayWorkCredit *= $maxCoefficient;
                                $holidayWorkingDays[$date] = $dayWorkCredit;
                                $totalWorkingHours += $hoursForDay * $maxCoefficient; // Adjust hours for holiday coefficient
                            } else {
                                $totalWorkingHours += $hoursForDay;
                            }
                            $actualWorkingDays += $dayWorkCredit;
                        }
                        $processedDays[$date] = true;
                    }
                } else {
                    // Không có chấm công, kiểm tra nhảy ca
                    $shiftRecords = $shiftData[$sn][$date] ?? [];
                    $hasShiftCompensation = false;

                    foreach ($shiftRecords as $shift) {
                        $day2 = $shift['day2'];
                        $day2Records = $attendanceData[$sn][$day2] ?? [];
                        if (!empty($day2Records)) {
                            // Có chấm công vào ngày bù (day2)
                            if (!isset($processedDays[$date])) {
                                // Sắp xếp records của ngày bù để tính đi trễ/về sớm
                                usort($day2Records, fn($a, $b) => strtotime($a) <=> strtotime($b));
                                $firstRecord = $day2Records[0];
                                $lastRecord = end($day2Records);

                                // Lấy thời gian bắt đầu/kết thúc của ngày bù
                                $day2OfWeek = date('N', strtotime($day2));
                                $day2StartKey = $dayMap[$day2OfWeek]['start'];
                                $day2EndKey = $dayMap[$day2OfWeek]['end'];
                                $day2StartTime = null;
                                $day2EndTime = null;
                                if ($applicableTimePeriod && isset($applicableTimePeriod[$day2StartKey], $applicableTimePeriod[$day2EndKey])) {
                                    $day2StartTime = strtotime("$day2 " . $applicableTimePeriod[$day2StartKey]);
                                    $day2EndTime = strtotime("$day2 " . $applicableTimePeriod[$day2EndKey]);
                                }

                                // Tính đi trễ cho ngày bù với delay 15 phút
                                if ($day2StartTime && $firstRecord) {
                                    $checkInTime = strtotime($firstRecord);
                                    $lateThreshold = $day2StartTime + (15 * 60); // Thêm 15 phút
                                    if ($checkInTime > $lateThreshold) {
                                        $lateMinutes = ($checkInTime - $day2StartTime) / 60;
                                        $totalLateMinutes += $lateMinutes;
                                        $lateRule = $latetimeRules[$sn]['Đi trễ'] ?? null;
                                        if ($lateRule && $lateMinutes >= $lateRule['threshold']) {
                                            $totalLatePenalty += $lateRule['penalty'];
                                        }
                                    }
                                }

                                // Tính về sớm cho ngày bù với delay 15 phút
                                if ($day2EndTime && $lastRecord) {
                                    $checkOutTime = strtotime($lastRecord);
                                    $earlyThreshold = $day2EndTime - (15 * 60); // Trừ 15 phút
                                    if ($checkOutTime < $earlyThreshold) {
                                        $earlyMinutes = ($day2EndTime - $checkOutTime) / 60;
                                        $totalEarlyMinutes += $earlyMinutes;
                                        $earlyRule = $latetimeRules[$sn]['Về sớm'] ?? null;
                                        if ($earlyRule && $earlyMinutes >= $earlyRule['threshold']) {
                                            $totalEarlyPenalty += $earlyRule['penalty'];
                                        }
                                    }
                                }

                                $leaveInfo = $leaveDaysMap[$sn][$date] ?? null;
                                if ($leaveInfo) {
                                    if ($leaveInfo['type'] === 'Nghỉ có lương') {
                                        $actualWorkingDays += $workCredit; // Full ngày công
                                        $day2OfWeek = date('N', strtotime($day2));
                                        $day2StartKey = $dayMap[$day2OfWeek]['start'];
                                        $day2EndKey = $dayMap[$day2OfWeek]['end'];
                                        $day2HoursForDay = 0;
                                        if ($applicableTimePeriod && isset($applicableTimePeriod[$day2StartKey], $applicableTimePeriod[$day2EndKey])) {
                                            $startTimeDay2 = strtotime("$day2 " . $applicableTimePeriod[$day2StartKey]);
                                            $endTimeDay2 = strtotime("$day2 " . $applicableTimePeriod[$day2EndKey]);
                                            if ($endTimeDay2 > $startTimeDay2) {
                                                $day2HoursForDay = ($endTimeDay2 - $startTimeDay2) / 3600;
                                            }
                                        }
                                        $totalWorkingHours += $day2HoursForDay; // Full giờ
                                    } elseif ($leaveInfo['type'] === 'Nghỉ không lương') {
                                        $actualWorkingDays += ($workCredit * (1 - $leaveInfo['days'])); // 0.5 ngày
                                        $day2OfWeek = date('N', strtotime($day2));
                                        $day2StartKey = $dayMap[$day2OfWeek]['start'];
                                        $day2EndKey = $dayMap[$day2OfWeek]['end'];
                                        $day2HoursForDay = 0;
                                        if ($applicableTimePeriod && isset($applicableTimePeriod[$day2StartKey], $applicableTimePeriod[$day2EndKey])) {
                                            $startTimeDay2 = strtotime("$day2 " . $applicableTimePeriod[$day2StartKey]);
                                            $endTimeDay2 = strtotime("$day2 " . $applicableTimePeriod[$day2EndKey]);
                                            if ($endTimeDay2 > $startTimeDay2) {
                                                $day2HoursForDay = ($endTimeDay2 - $startTimeDay2) / 3600;
                                            }
                                        }
                                        $totalWorkingHours += $day2HoursForDay * (1 - $leaveInfo['days']); // 0.5 giờ
                                    }
                                } else {
                                    $dayWorkCredit = $workCredit;
                                    $day2Holidays = $holidayMap[$day2] ?? [];
                                    $day2ApplicableHolidays = [];
                                    foreach ($day2Holidays as $holiday) {
                                        if ($holiday['departmentId'] == 0 || $holiday['departmentId'] == $departmentId) {
                                            $day2ApplicableHolidays[] = $holiday;
                                        }
                                    }
                                    if (!empty($day2ApplicableHolidays)) {
                                        $maxCoefficient = max(array_map(fn($h) => $h['coefficient'], $day2ApplicableHolidays));
                                        $dayWorkCredit *= $maxCoefficient;
                                        $holidayWorkingDays[$date] = $dayWorkCredit;
                                        $day2OfWeek = date('N', strtotime($day2));
                                        $day2StartKey = $dayMap[$day2OfWeek]['start'];
                                        $day2EndKey = $dayMap[$day2OfWeek]['end'];
                                        $day2HoursForDay = 0;
                                        if ($applicableTimePeriod && isset($applicableTimePeriod[$day2StartKey], $applicableTimePeriod[$day2EndKey])) {
                                            $startTimeDay2 = strtotime("$day2 " . $applicableTimePeriod[$day2StartKey]);
                                            $endTimeDay2 = strtotime("$day2 " . $applicableTimePeriod[$day2EndKey]);
                                            if ($endTimeDay2 > $startTimeDay2) {
                                                $day2HoursForDay = ($endTimeDay2 - $startTimeDay2) / 3600;
                                            }
                                        }
                                        $totalWorkingHours += $day2HoursForDay * $maxCoefficient; // Adjust hours for holiday coefficient
                                    } else {
                                        $day2OfWeek = date('N', strtotime($day2));
                                        $day2StartKey = $dayMap[$day2OfWeek]['start'];
                                        $day2EndKey = $dayMap[$day2OfWeek]['end'];
                                        $day2HoursForDay = 0;
                                        if ($applicableTimePeriod && isset($applicableTimePeriod[$day2StartKey], $applicableTimePeriod[$day2EndKey])) {
                                            $startTimeDay2 = strtotime("$day2 " . $applicableTimePeriod[$day2StartKey]);
                                            $endTimeDay2 = strtotime("$day2 " . $applicableTimePeriod[$day2EndKey]);
                                            if ($endTimeDay2 > $startTimeDay2) {
                                                $day2HoursForDay = ($endTimeDay2 - $startTimeDay2) / 3600;
                                            }
                                        }
                                        $totalWorkingHours += $day2HoursForDay;
                                    }
                                    $actualWorkingDays += $dayWorkCredit;
                                }
                                $processedDays[$date] = true;
                            }
                            $hasShiftCompensation = true;
                            break;
                        }
                    }

                    if (!$hasShiftCompensation && !isset($leaveDaysMap[$sn][$date])) {
                        if (!empty($applicableHolidays)) {
                            $actualWorkingDays += $workCredit;
                            $totalWorkingHours += $hoursForDay;
                            $processedDays[$date] = true;
                        } else {
                            $unauthorizedLeaveFromRecords += $workCredit;
                        }
                    } elseif (!isset($processedDays[$date]) && isset($leaveDaysMap[$sn][$date])) {
                        $leaveInfo = $leaveDaysMap[$sn][$date];
                        if ($leaveInfo['type'] === 'Nghỉ có lương') {
                            $totalWorkingHours += $hoursForDay * $leaveInfo['days'];
                            $processedDays[$date] = true;
                        }
                    }
                }
            } else {
                $isCompensatedDay = false;
                foreach ($shiftData[$sn] ?? [] as $originalDay => $shiftRecords) {
                    foreach ($shiftRecords as $shift) {
                        if ($shift['day2'] === $date && !empty($records)) {
                            if (!isset($processedDays[$originalDay])) {
                                // Sắp xếp records của ngày bù để tính đi trễ/về sớm
                                usort($records, fn($a, $b) => strtotime($a) <=> strtotime($b));
                                $firstRecord = $records[0];
                                $lastRecord = end($records);

                                // Tính đi trễ/về sớm cho ngày bù với delay 15 phút
                                if ($startTime && $firstRecord) {
                                    $checkInTime = strtotime($firstRecord);
                                    $lateThreshold = $startTime + (15 * 60); // Thêm 15 phút
                                    if ($checkInTime > $lateThreshold) {
                                        $lateMinutes = ($checkInTime - $startTime) / 60;
                                        $totalLateMinutes += $lateMinutes;
                                        $lateRule = $latetimeRules[$sn]['Đi trễ'] ?? null;
                                        if ($lateRule && $lateMinutes >= $lateRule['threshold']) {
                                            $totalLatePenalty += $lateRule['penalty'];
                                        }
                                    }
                                }
                                if ($endTime && $lastRecord) {
                                    $checkOutTime = strtotime($lastRecord);
                                    $earlyThreshold = $endTime - (15 * 60); // Trừ 15 phút
                                    if ($checkOutTime < $earlyThreshold) {
                                        $earlyMinutes = ($endTime - $checkOutTime) / 60;
                                        $totalEarlyMinutes += $earlyMinutes;
                                        $earlyRule = $latetimeRules[$sn]['Về sớm'] ?? null;
                                        if ($earlyRule && $earlyMinutes >= $earlyRule['threshold']) {
                                            $totalEarlyPenalty += $earlyRule['penalty'];
                                        }
                                    }
                                }

                                $leaveInfo = $leaveDaysMap[$sn][$date] ?? null;
                                if ($leaveInfo) {
                                    if ($leaveInfo['type'] === 'Nghỉ có lương') {
                                        $actualWorkingDays += $workCredit; // Full ngày công
                                        $totalWorkingHours += $hoursForDay; // Full giờ
                                    } elseif ($leaveInfo['type'] === 'Nghỉ không lương') {
                                        $actualWorkingDays += ($workCredit * (1 - $leaveInfo['days'])); // 0.5 ngày
                                        $totalWorkingHours += $hoursForDay * (1 - $leaveInfo['days']); // 0.5 giờ
                                    }
                                } else {
                                    $dayWorkCredit = $workCredit;
                                    if (!empty($applicableHolidays)) {
                                        $maxCoefficient = max(array_map(fn($h) => $h['coefficient'], $applicableHolidays));
                                        $dayWorkCredit *= $maxCoefficient;
                                        $holidayWorkingDays[$originalDay] = $dayWorkCredit;
                                        $totalWorkingHours += $hoursForDay * $maxCoefficient; // Adjust hours for holiday coefficient
                                    } else {
                                        $totalWorkingHours += $hoursForDay;
                                    }
                                    $actualWorkingDays += $dayWorkCredit;
                                }
                                $processedDays[$originalDay] = true;
                            }
                            $isCompensatedDay = true;
                            break 2;
                        }
                    }
                }
            }
        }

        // Tìm hợp đồng phù hợp nhất cho tháng tính lương
        $contract = null;
        $contractsForEmployee = $contractMap[$sn] ?? [];
        foreach ($contractsForEmployee as $c) {
            $workingDate = strtotime($c['working_date']);
            $contractDuration = (int)$c['contract_duration'];
            $endDate = strtotime("+$contractDuration months", $workingDate);

            $isValidContract = $c['contract_type'] === 'Không thời hạn' ||
                ($workingDate <= $monthEndTimestamp && $endDate >= $monthStartTimestamp);

            if ($isValidContract) {
                if ($contract === null || $workingDate > strtotime($contract['working_date'])) {
                    $contract = $c;
                }
            }
        }

        $contractId = $contract['id'] ?? null;
        $hasContractSalary = $contractId && isset($contractSalaryMap[$contractId]) && !empty($contractSalaryMap[$contractId]);

        if ($hasContractSalary) {
            $salaryIds = $contractSalaryMap[$contractId];
            $employeeSalaries = array_filter($salaries, fn($s) => in_array($s['id'], $salaryIds));

            $hasHourlySalary = false;

            // Tách biệt lương cứng (type = 1) và phụ cấp (type = 2)
            $basicSalaryData = null; // Lương cứng
            $allowanceData = null;   // Phụ cấp

            foreach ($employeeSalaries as $es) {
                if ($es['type'] == 1) {
                    $basicSalaryData = $es;
                } elseif ($es['type'] == 2) {
                    $allowanceData = $es;
                }
            }

            // Xử lý hiển thị dữ liệu lương
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
            }

            // Tính lương cứng (type = 1)
            if ($basicSalaryData) {
                $price = is_numeric($basicSalaryData['price']) ? (int)$basicSalaryData['price'] : 0;
                $priceValue = $basicSalaryData['priceValue'];

                if ($priceValue == 1) { // Theo giờ
                    $hasHourlySalary = true;
                    $basicSalary = $price * $totalWorkingHours;
                } elseif ($priceValue == 2) { // Theo ngày
                    $basicSalary = $price * $actualWorkingDays;
                } else { // Theo tháng
                    if ($actualWorkingDays > 0) {
                        $basicSalary = $price * ($actualWorkingDays / $totalWorkingDays);
                    } else {
                        $basicSalary = 0;
                    }
                }
            }

            // Tính phụ cấp (type = 2)
            if ($allowanceData) {
                $price = is_numeric($allowanceData['price']) ? (int)$allowanceData['price'] : 0;
                $priceValue = $allowanceData['priceValue'];

                if ($priceValue == 1) { // Theo giờ
                    $hasHourlySalary = true;
                    $allowanceSalary = $price * $totalWorkingHours;
                } elseif ($priceValue == 2) { // Theo ngày
                    $allowanceSalary = $price * $actualWorkingDays;
                } else { // Theo tháng
                    if ($actualWorkingDays > 0) {
                        $allowanceSalary = $price * ($actualWorkingDays / $totalWorkingDays);
                    } else {
                        $allowanceSalary = 0;
                    }
                }
            }

            // Tính tổng lương
            $totalSalary = $basicSalary + $allowanceSalary;

            // Tính dailySalary
            if ($hasHourlySalary) {
                $dailySalary = 0; // Nếu có lương theo giờ, đặt dailySalary = 0
            } else {
                if ($actualWorkingDays > 0) {
                    $dailySalary = $totalSalary / $actualWorkingDays;
                } else {
                    $dailySalary = 0;
                }
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
            $insurance = (float)str_replace(',', '', $insuranceInfo['money']);
        }

        // Tính tăng ca
        $overtimePay = $app->select("staff-salary", ["price"], ["type" => 3, "status" => 'A'])[0]['price'] ?? 0;
        $overtimeHoursFromTable = 0;
        $overtimeMoneyFromTable = 0;
        $overtimeDetails = [];

        if (isset($overtimeData[$sn])) {
            foreach ($overtimeData[$sn] as $ot) {
                $hours = (strtotime($ot['end']) - strtotime($ot['start'])) / 3600;
                $overtimeHoursFromTable += $hours;
                $money = (float)$ot['money'];
                $overtimeMoneyFromTable += $money;
                $startTime = date('Y-m-d H:i', strtotime($ot['start']));
                $endTime = date('Y-m-d H:i', strtotime($ot['end']));
                $moneyFormatted = number_format($money, 0, ',', '.');
                $overtimeDetails[] = "$startTime-$endTime ($moneyFormatted)";
            }
        }

        $finalOvertimeHours = $overtimeHoursFromTable;
        $finalOvertimeMoney = $overtimeMoneyFromTable;

        $countOvertime = count($overtimeDetails);
        $detailsString = $countOvertime > 0 ? '[' . implode(', ', $overtimeDetails) . ']' : '';
        $overtimeDisplay = "$countOvertime lần: $detailsString. Tổng tiền: " . number_format($finalOvertimeMoney, 0, ',', '.');

        // Tổng tiền phạt đi trễ/về sớm
        $totalPenalty = $totalLatePenalty + $totalEarlyPenalty;
        $totalPenaltyFormatted = number_format($totalPenalty, 0, ',', '.');

        $unpaidLeave = 0;
        $paidLeave = 0;
        $workedPaidLeaveDays = 0; // Theo dõi số ngày nghỉ có lương nhưng vẫn đi làm
        foreach ($leaveData[$sn] ?? [] as $leave) {
            $start = max(strtotime("$year-$month-01 00:00:00"), strtotime($leave['start_date']));
            $end = min(strtotime("$year-$month-$daysInMonth 23:59:59"), strtotime($leave['end_date']));
            $startDate = date('Y-m-d', $start);
            $endDate = date('Y-m-d', $end);
            $currentDate = $startDate;

            while (strtotime($currentDate) <= strtotime($endDate)) {
                $hasAttendance = !empty($attendanceData[$sn][$currentDate]);
                $hasShiftCompensation = false;
                foreach ($shiftData[$sn][$currentDate] ?? [] as $shift) {
                    $day2 = $shift['day2'];
                    if (!empty($attendanceData[$sn][$day2])) {
                        $hasShiftCompensation = true;
                        break;
                    }
                }

                $leaveInfo = $leaveDaysMap[$sn][$currentDate] ?? null;
                if ($leaveInfo) {
                    $days = $leaveInfo['days'];
                    if ($leaveInfo['type'] === 'Nghỉ có lương') {
                        if ($hasAttendance || $hasShiftCompensation) {
                            $workedPaidLeaveDays += $days; // Ghi nhận số ngày nghỉ có lương nhưng vẫn đi làm
                        } else {
                            $paidLeave += $days; // Chỉ cộng vào paidLeave nếu không đi làm
                        }
                    } elseif ($leaveInfo['type'] === 'Nghỉ không lương') {
                        $unpaidLeave += $days;
                    }
                }
                $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
            }
        }

        $unauthorizedLeave = $unauthorizedLeaveFromRecords;

        // Tính totalAttendance, không cộng workedPaidLeaveDays vào
        $totalAttendance = $actualWorkingDays + $paidLeave;

        $reward = 0;
        $discipline = 0;
        foreach ($rewardData[$sn] ?? [] as $rd) {
            if ($rd['type'] === 'reward') {
                $reward += $rd['amount'];
            } elseif ($rd['type'] === 'discipline') {
                $discipline += abs($rd['amount']);
            }
        }

        $salaryAdvance = array_sum(array_map(fn($ad) => $ad['TypeID'] == 1 ? (float)str_replace(',', '', $ad['Amount']) : 0, $advanceData[$sn] ?? []));
        error_log("Salary Advance for $sn: " . $salaryAdvance);

        $salaryRepayment = array_sum(array_map(fn($ad) => $ad['TypeID'] == 2 ? (float)str_replace(',', '', $ad['Amount']) : 0, $advanceData[$sn] ?? []));
        error_log("Salary Repayment for $sn: " . $salaryRepayment);

        $netAdvance = $salaryAdvance - $salaryRepayment;
        error_log("Net Advance for $sn: " . $netAdvance);

        if ($hasHourlySalary) {
            $totalReceived = $totalSalary - $insurance + $reward - $discipline - $netAdvance - $totalPenalty + $finalOvertimeMoney;
        } else {
            $totalReceived = ($dailySalary * $totalAttendance) - $insurance + $reward - $discipline - $netAdvance - $totalPenalty + $finalOvertimeMoney;
        }

        $entry = [
            "personSn" => $employee['name'] ?? '',
            "departmentId" => $department ?? 'Không xác định',
        ];

        foreach ($salaries as $s) {
            $salaryKey = "salaryData_" . $s['id'];
            $salaryValue = $salaryData[$salaryKey] ?? '/0';
            $entry[$salaryKey] = is_string($salaryValue) ? $salaryValue : '/0';
        }

        $entry["dailySalary"] = is_numeric($dailySalary) ? number_format($dailySalary, 0, ',', '.') : '0';
        $entry["insurance"] = is_numeric($insurance) ? number_format($insurance, 0, ',', '.') : '0';
        $entry["workingDays"] = round($totalWorkingDays, 2) . "/" . round($actualWorkingDays, 2);
        $entry["overtime"] = $overtimeDisplay ?? '';
        $entry["lateArrival/earlyLeave"] = "Đi trễ: " . round($totalLateMinutes) . " phút / Về sớm: " . round($totalEarlyMinutes) . " phút. Tổng: $totalPenaltyFormatted";
        $entry["unpaidLeave"] = $unpaidLeave ?? 0;
        $entry["paidLeave"] = $paidLeave ?? 0;
        $entry["unauthorizedLeave"] = round($unauthorizedLeave, 2) ?? 0;
        $entry["totalAttendance"] = round($totalAttendance, 2) ?? 0;
        $entry["discipline"] = number_format($discipline, 0, ',', '.') ?? '0';
        $entry["reward"] = number_format($reward, 0, ',', '.') ?? '0';
        $entry["salaryAdvance"] = number_format($netAdvance, 0, ',', '.') ?? '0';
        $entry["totalReceived"] = number_format($totalReceived, 0, ',', '.') ?? '0';

        error_log("Entry for employee $sn: " . print_r($entry, true));
        $datas[] = $entry;
    }

    $totalReceived = array_sum(array_map(fn($d) => (int)str_replace(',', '', $d['totalReceived']), $datas));

    $response = [
        "draw" => $draw,
        "recordsTotal" => $totalEmployees,
        "recordsFiltered" => $filteredCount,
        "data" => $datas,
    ];

    error_log("Response: " . print_r($response, true));
    echo json_encode($response);
})->setPermissions(['salaryCalculation']);