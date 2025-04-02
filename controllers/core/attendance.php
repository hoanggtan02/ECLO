
<?php
if (!defined('ECLO')) die("Hacking attempt");
$jatbi = new Jatbi($app);
$setting = $app->getValueData('setting');

$app->router("/manager/attendance", 'GET', function($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Chấm công");
    $vars['add'] = '/manager/attendance-add';
    $vars['deleted'] = '/manager/attendance-deleted';

    // Lấy danh sách nhân viên để hiển thị trong bộ lọc
    $vars['employees'] = $app->select("employee", ["sn", "name"], [
        "ORDER" => ["name" => "ASC"]
    ]);

    // Lấy tháng và năm từ query string (mặc định là tháng hiện tại)
    $month = $_GET['month'] ?? date('n');
    $year = $_GET['year'] ?? date('Y');
    $vars['month'] = $month;
    $vars['year'] = $year;
    $personnels = $_GET['personnels'] ?? '';
    $searchValue = $_GET['name'] ?? '';

    // Điều kiện lọc dữ liệu
    $where = [
        "AND" => [
            "record.createTime[>=]" => "$year-01-01 00:00:00",
            "record.createTime[<=]" => "$year-12-31 23:59:59",
            "department.status" => "A",
        ],
        "ORDER" => ["employee.name" => "ASC", "record.createTime" => "ASC"]
    ];

    if (!empty($searchValue)) {
        $where["AND"]["OR"] = [
            "record.personSn[~]" => $searchValue,
            "employee.name[~]" => $searchValue,
        ];
    }

    if (!empty($personnels)) {
        $where["AND"]["record.personSn"] = $personnels;
    }

    // Join bảng record, employee, department
    $recordEmployeeData = $app->select("record", [
        "[><]employee" => ["personSn" => "sn"],
        "[>]department" => ["employee.departmentId" => "departmentId"]
    ], [
        "record.personSn(employee_sn)",
        "employee.name",
        "employee.departmentId",
        "department.personName(department_name)",
        "record.createTime"
    ], $where) ?? [];

    // Debug: Ghi log dữ liệu sau khi join
    if (empty($recordEmployeeData)) {
        error_log("No data found after joining record with employee and department for year: $year");
    } else {
        error_log("Found " . count($recordEmployeeData) . " records after joining: " . json_encode($recordEmployeeData));
    }

    // Lấy tất cả dữ liệu từ timeperiod để sử dụng sau
    $timePeriods = $app->select("timeperiod", [
        "acTzNumber",
        "monStart", "monEnd", "tueStart", "tueEnd", "wedStart", "wedEnd",
        "thursStart", "thursEnd", "friStart", "friEnd", "satStart", "satEnd",
        "sunStart", "sunEnd",
        "mon_off", "tue_off", "wed_off", "thu_off", "fri_off", "sat_off", "sun_off"
    ],[
        "status" => "A",
    ]);
    $timePeriodMap = array_column($timePeriods, null, 'acTzNumber'); // Map theo acTzNumber

    // Debug: Ghi log dữ liệu timeperiod
    error_log("Time period data: " . json_encode($timePeriodMap));

    // Lấy dữ liệu từ bảng leave_requests và join với leavetype
    $leaveRequests = $app->select("leave_requests", [
        "[>]leavetype" => ["LeaveId" => "LeaveTypeID"]
    ], [
        "leave_requests.personSN",
        "leave_requests.start_date",
        "leave_requests.end_date",
        "leave_requests.LeaveId",
        "leavetype.Code",
        "leavetype.Name"
    ], [
        "AND" => [
            "leave_requests.start_date[<=]" => "$year-$month-31 23:59:59",
            "leave_requests.end_date[>=]" => "$year-$month-01 00:00:00",
            "Status" => 1 // Chỉ lấy những yêu cầu đã được phê duyệt
        ]
    ]) ?? [];

    // Nhóm leave_requests theo personSN và lưu thông tin loại nghỉ phép
    $leaveRequestsByEmployee = [];
    foreach ($leaveRequests as $request) {
        $personSN = $request['personSN'];
        if (!isset($leaveRequestsByEmployee[$personSN])) {
            $leaveRequestsByEmployee[$personSN] = [];
        }
        $leaveRequestsByEmployee[$personSN][] = [
            'start_date' => $request['start_date'],
            'end_date' => $request['end_date'],
            'leave_type_id' => $request['LeaveId'],
            'leave_code' => $request['Code'],
            'leave_name' => $request['Name']
        ];
    }

    // Debug: Ghi log dữ liệu leave_requests
    error_log("Leave requests data: " . json_encode($leaveRequestsByEmployee));

    // Lấy tất cả loại nghỉ phép để tạo màu sắc động
    $leaveTypes = $app->select("leavetype", [
        "LeaveTypeID",
        "Code",
        "Name"
    ],[
        "Status" => "A",
    ]);
    $vars['leave_types'] = $leaveTypes;

    // Tạo mảng màu sắc cho từng loại nghỉ phép
    $leaveTypeColors = [];
    $colors = [
        '#f4a261', '#e76f51', '#2a9d8f', '#264653', '#e9c46a', '#f4e4bc', '#d4a5a5', '#a3bffa'
    ]; // Danh sách màu sắc
    foreach ($leaveTypes as $index => $leaveType) {
        $leaveTypeColors[$leaveType['LeaveTypeID']] = $colors[$index % count($colors)];
    }
    $vars['leave_type_colors'] = $leaveTypeColors;

    // Thêm timeperiod_id vào dữ liệu chấm công
    $datas = [];
    foreach ($recordEmployeeData as $record) {
        $employeeSn = $record['employee_sn'];
        $createTime = $record['createTime'];

        // Truy vấn bảng assignments để lấy timeperiod_id dựa trên apply_date
        $assignment = $app->select("assignments", [
            "timeperiod_id"
        ], [
            "employee_id" => $employeeSn,
            "apply_date[<=]" => $createTime,
            "ORDER" => ["apply_date" => "DESC"],
            "LIMIT" => 1,
            
        ]);

        // Nếu không tìm thấy assignment, để timeperiod_id là null
        $record['timeperiod_id'] = !empty($assignment) ? $assignment[0]['timeperiod_id'] : null;

        // Debug: Ghi log timeperiod_id được chọn
        error_log("Employee $employeeSn on $createTime: Selected timeperiod_id = " . ($record['timeperiod_id'] ?? 'null'));

        $record['department_name'] = $record['department_name'] ?? 'Không xác định (departmentId: ' . ($record['departmentId'] ?? 'null') . ')';
        $datas[] = $record;
    }

    // Lọc và nhóm dữ liệu theo tháng, năm
    $groupedData = [];
    if (empty($datas)) {
        error_log("No data in \$datas to group for month: $month, year: $year");
    } else {
        foreach ($datas as $data) {
            $createTime = $data['createTime'];
            $recordMonth = (int)date('n', strtotime($createTime));
            $recordYear = (int)date('Y', strtotime($createTime));
            $recordDay = (int)date('d', strtotime($createTime));

            if ($recordMonth == $month && $recordYear == $year) {
                $employeeSn = $data['employee_sn'];
                $departmentId = $data['departmentId'] ?? 'unknown';
                $departmentName = $data['department_name'];
                $employeeName = $data['name'] ?? 'Nhân viên không xác định (employee_sn: ' . $employeeSn . ')';
                $dateKey = "$year-$month-$recordDay";

                if (!isset($groupedData[$departmentId])) {
                    $groupedData[$departmentId] = [
                        "department_name" => $departmentName,
                        "employees" => []
                    ];
                }

                if (!isset($groupedData[$departmentId]['employees'][$employeeSn])) {
                    $groupedData[$departmentId]['employees'][$employeeSn] = [
                        "name" => $employeeName,
                        "days" => [],
                        "timeperiod_id" => $data['timeperiod_id'] // Lưu timeperiod_id cho nhân viên
                    ];
                }

                if (!isset($groupedData[$departmentId]['employees'][$employeeSn]['days'][$dateKey])) {
                    $groupedData[$departmentId]['employees'][$employeeSn]['days'][$dateKey] = [];
                }

                $groupedData[$departmentId]['employees'][$employeeSn]['days'][$dateKey][] = [
                    'time' => $createTime,
                    'timeperiod_id' => $data['timeperiod_id']
                ];
            }
        }
    }

    // Xử lý dữ liệu để hiển thị trong bảng
    $departmentData = [];
    foreach ($groupedData as $departmentId => $departmentInfo) {
        $departmentData[$departmentId] = [
            "department_name" => $departmentInfo['department_name'] ?? 'Không xác định',
            "employees" => []
        ];

        foreach ($departmentInfo['employees'] as $employeeSn => $employeeInfo) {
            $employeeData = [
                "employee_sn" => $employeeSn,
                "name" => $employeeInfo['name'] ?? 'Nhân viên không xác định',
            ];

            // $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
            $daysInMonth = date('t', mktime(0, 0, 0, $month, 1, $year));

            // Lấy timeperiod_id của nhân viên cho từng ngày
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $dateKey = "$year-$month-" . sprintf("%02d", $day);
                $currentDate = $dateKey . " 00:00:00";

                // Truy vấn assignments để lấy timeperiod_id cho ngày hiện tại
                $assignment = $app->select("assignments", [
                    "timeperiod_id"
                ], [
                    "employee_id" => $employeeSn,
                    "apply_date[<=]" => $currentDate,
                    "ORDER" => ["apply_date" => "DESC"],
                    "LIMIT" => 1
                ]);

                $timeperiodId = !empty($assignment) ? $assignment[0]['timeperiod_id'] : null;
                $timePeriod = $timePeriodMap[$timeperiodId] ?? null;

                // Nếu không có timeperiod, dùng mặc định
                if (!$timePeriod) {
                    $timePeriod = [
                        "monStart" => "09:00", "monEnd" => "16:30", "tueStart" => "09:00", "tueEnd" => "16:30",
                        "wedStart" => "09:00", "wedEnd" => "16:30", "thursStart" => "09:00", "thursEnd" => "16:30",
                        "friStart" => "09:00", "friEnd" => "16:30", "satStart" => "09:00", "satEnd" => "16:30",
                        "sunStart" => "09:00", "sunEnd" => "16:30",
                        "mon_off" => 0, "tue_off" => 0, "wed_off" => 0, "thu_off" => 0, "fri_off" => 0, "sat_off" => 0, "sun_off" => 0
                    ];
                    error_log("No timeperiod found for employee $employeeSn on date $dateKey, using default");
                }

                // Ánh xạ ngày trong tuần
                $dayMap = [
                    1 => ['start' => 'monStart', 'end' => 'monEnd', 'off' => 'mon_off'],
                    2 => ['start' => 'tueStart', 'end' => 'tueEnd', 'off' => 'tue_off'],
                    3 => ['start' => 'wedStart', 'end' => 'wedEnd', 'off' => 'wed_off'],
                    4 => ['start' => 'thursStart', 'end' => 'thursEnd', 'off' => 'thu_off'],
                    5 => ['start' => 'friStart', 'end' => 'friEnd', 'off' => 'fri_off'],
                    6 => ['start' => 'satStart', 'end' => 'satEnd', 'off' => 'sat_off'],
                    7 => ['start' => 'sunStart', 'end' => 'sunEnd', 'off' => 'sun_off']
                ];

                $dayOfWeek = date('N', strtotime($dateKey));
                $isDayOff = (bool)$timePeriod[$dayMap[$dayOfWeek]['off']];

                // Kiểm tra xem ngày này có nằm trong khoảng thời gian xin nghỉ phép không
                $isOffPermitted = false;
                $leaveTypeId = null;
                $leaveCode = null;
                $leaveName = null;
                if (isset($leaveRequestsByEmployee[$employeeSn])) {
                    foreach ($leaveRequestsByEmployee[$employeeSn] as $request) {
                        $startDate = strtotime(date('Y-m-d', strtotime($request['start_date'])));
                        $endDate = strtotime(date('Y-m-d', strtotime($request['end_date'])));
                        $currentDateTimestamp = strtotime($dateKey);

                        if ($currentDateTimestamp >= $startDate && $currentDateTimestamp <= $endDate) {
                            $isOffPermitted = true;
                            $leaveTypeId = $request['leave_type_id'];
                            $leaveCode = $request['leave_code'];
                            $leaveName = $request['leave_name'];
                            error_log("Employee $employeeSn, Date $dateKey: Marked as off-permitted with LeaveTypeID $leaveTypeId");
                            break;
                        }
                    }
                }

                // Gán trạng thái mặc định cho ngày
                if ($isOffPermitted) {
                    $status = ['off-permitted'];
                    $employeeData["day_$day"] = [
                        "check_in" => null,
                        "check_out" => null,
                        "status" => $status,
                        "leave_type_id" => $leaveTypeId,
                        "leave_code" => $leaveCode,
                        "leave_name" => $leaveName
                    ];
                } elseif ($isDayOff) {
                    $status = ['day-off'];
                    $employeeData["day_$day"] = [
                        "check_in" => null,
                        "check_out" => null,
                        "status" => $status
                    ];
                } else {
                    $status = ['no-record'];
                    $employeeData["day_$day"] = [
                        "check_in" => null,
                        "check_out" => null,
                        "status" => $status
                    ];
                }
            }

            // Xử lý các ngày có bản ghi chấm công
            foreach ($employeeInfo['days'] as $dateKey => $checkTimes) {
                $day = (int)date('d', strtotime($dateKey));
                $checkTimesSorted = array_column($checkTimes, 'time');
                sort($checkTimesSorted);

                $checkIn = null;
                $checkOut = null;
                $status = [];

                // Truy vấn lại timeperiod_id cho ngày chấm công
                $assignment = $app->select("assignments", [
                    "timeperiod_id"
                ], [
                    "employee_id" => $employeeSn,
                    "apply_date[<=]" => $dateKey . " 00:00:00",
                    "ORDER" => ["apply_date" => "DESC"],
                    "LIMIT" => 1
                ]);

                $timeperiodId = !empty($assignment) ? $assignment[0]['timeperiod_id'] : null;
                $timePeriod = $timePeriodMap[$timeperiodId] ?? null;

                if (!$timePeriod) {
                    $timePeriod = [
                        "monStart" => "09:00", "monEnd" => "16:30", "tueStart" => "09:00", "tueEnd" => "16:30",
                        "wedStart" => "09:00", "wedEnd" => "16:30", "thursStart" => "09:00", "thursEnd" => "16:30",
                        "friStart" => "09:00", "friEnd" => "16:30", "satStart" => "09:00", "satEnd" => "16:30",
                        "sunStart" => "09:00", "sunEnd" => "16:30",
                        "mon_off" => 0, "tue_off" => 0, "wed_off" => 0, "thu_off" => 0, "fri_off" => 0, "sat_off" => 0, "sun_off" => 0
                    ];
                    error_log("No timeperiod found for employee $employeeSn on date $dateKey (with check-in), using default");
                }

                $dayOfWeek = date('N', strtotime($dateKey));
                $isDayOff = (bool)$timePeriod[$dayMap[$dayOfWeek]['off']];

                // Kiểm tra xem ngày này có nằm trong khoảng thời gian xin nghỉ phép không
                $isOffPermitted = false;
                $leaveTypeId = null;
                $leaveCode = null;
                $leaveName = null;
                if (isset($leaveRequestsByEmployee[$employeeSn])) {
                    foreach ($leaveRequestsByEmployee[$employeeSn] as $request) {
                        $startDate = strtotime(date('Y-m-d', strtotime($request['start_date'])));
                        $endDate = strtotime(date('Y-m-d', strtotime($request['end_date'])));
                        $currentDateTimestamp = strtotime($dateKey);

                        if ($currentDateTimestamp >= $startDate && $currentDateTimestamp <= $endDate) {
                            $isOffPermitted = true;
                            $leaveTypeId = $request['leave_type_id'];
                            $leaveCode = $request['leave_code'];
                            $leaveName = $request['leave_name'];
                            error_log("Employee $employeeSn, Date $dateKey (with check-in): Marked as off-permitted with LeaveTypeID $leaveTypeId");
                            break;
                        }
                    }
                }

                if ($isOffPermitted) {
                    $status[] = 'off-permitted';
                    if (count($checkTimesSorted) >= 2) {
                        $checkIn = date('H:i', strtotime($checkTimesSorted[0]));
                        $checkOut = date('H:i', strtotime($checkTimesSorted[count($checkTimesSorted) - 1]));
                    } elseif (count($checkTimesSorted) == 1) {
                        $checkIn = date('H:i', strtotime($checkTimesSorted[0]));
                    }
                    $employeeData["day_$day"] = [
                        "check_in" => $checkIn,
                        "check_out" => $checkOut,
                        "status" => $status,
                        "leave_type_id" => $leaveTypeId,
                        "leave_code" => $leaveCode,
                        "leave_name" => $leaveName
                    ];
                } elseif ($isDayOff) {
                    $status[] = 'day-off';
                    if (count($checkTimesSorted) >= 2) {
                        $checkIn = date('H:i', strtotime($checkTimesSorted[0]));
                        $checkOut = date('H:i', strtotime($checkTimesSorted[count($checkTimesSorted) - 1]));
                    } elseif (count($checkTimesSorted) == 1) {
                        $checkIn = date('H:i', strtotime($checkTimesSorted[0]));
                    }
                    $employeeData["day_$day"] = [
                        "check_in" => $checkIn,
                        "check_out" => $checkOut,
                        "status" => $status
                    ];
                } else {
                    $checkInStandard = $timePeriod[$dayMap[$dayOfWeek]['start']] ?? '09:00';
                    $checkOutStandard = $timePeriod[$dayMap[$dayOfWeek]['end']] ?? '16:30';

                    if (count($checkTimesSorted) >= 2) {
                        $checkIn = date('H:i', strtotime($checkTimesSorted[0]));
                        $checkOut = date('H:i', strtotime($checkTimesSorted[count($checkTimesSorted) - 1]));

                        $checkInTime = strtotime($checkTimesSorted[0]);
                        $checkOutTime = strtotime($checkTimesSorted[count($checkTimesSorted) - 1]);
                        $checkInHour = (int)date('H', $checkInTime);
                        $checkInMinute = (int)date('i', $checkInTime);
                        $checkOutHour = (int)date('H', $checkOutTime);
                        $checkOutMinute = (int)date('i', $checkOutTime);

                        $checkInStandardHour = (int)date('H', strtotime($checkInStandard));
                        $checkInStandardMinute = (int)date('i', strtotime($checkInStandard));
                        $checkOutStandardHour = (int)date('H', strtotime($checkOutStandard));
                        $checkOutStandardMinute = (int)date('i', strtotime($checkOutStandard));

                        $lateMinutes = ($checkInHour * 60 + $checkInMinute) - ($checkInStandardHour * 60 + $checkInStandardMinute);
                        $earlyMinutes = ($checkOutStandardHour * 60 + $checkOutStandardMinute) - ($checkOutHour * 60 + $checkOutMinute);

                        $isCheckInOnTime = ($lateMinutes <= 30);
                        $isCheckOutOnTime = ($earlyMinutes <= 30);

                        if ($isCheckInOnTime && $isCheckOutOnTime) {
                            $status[] = 'checked';
                        } else {
                            if (!$isCheckInOnTime) {
                                $status[] = 'late';
                            }
                            if (!$isCheckOutOnTime) {
                                $status[] = 'not-checked';
                            }
                        }
                    } elseif (count($checkTimesSorted) == 1) {
                        $checkIn = date('H:i', strtotime($checkTimesSorted[0]));
                        $status[] = 'not-checked';
                    }
                    $employeeData["day_$day"] = [
                        "check_in" => $checkIn,
                        "check_out" => $checkOut,
                        "status" => $status
                    ];
                }
            }

            $departmentData[$departmentId]['employees'][] = $employeeData;
        }
    }

    $vars['data'] = $departmentData;

    echo $app->render('templates/employee/attendance.html', $vars);
})->setPermissions(['attendance']);



// Route để xuất Excel (giả lập)
$app->router("/manager/attendance/excel", 'GET', function($vars) use ($app, $jatbi) {
    $app->header(['Content-Type' => 'application/json']);
    echo json_encode(["status" => "success", "content" => "Đang xuất file Excel..."]);
})->setPermissions(['attendance']);

$app->router("/manager/attendance/excel_pro", 'GET', function($vars) use ($app, $jatbi) {
    $app->header(['Content-Type' => 'application/json']);
    echo json_encode(["status" => "success", "content" => "Đang xuất file Excel theo công..."]);
})->setPermissions(['attendance']);

