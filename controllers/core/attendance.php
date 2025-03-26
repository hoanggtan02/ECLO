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
            "record.createTime[<=]" => "$year-12-31 23:59:59"
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

    // Join bảng record, employee, department và assignments
    $recordEmployeeData = $app->select("record", [
        "[><]employee" => ["personSn" => "sn"],
        "[>]department" => ["employee.departmentId" => "departmentId"],
        "[>]assignments" => ["employee.sn" => "employee_id"]
    ], [
        "record.personSn(employee_sn)",
        "employee.name",
        "employee.departmentId",
        "department.personName(department_name)",
        "record.createTime",
        "assignments.timeperiod_id"
    ], $where) ?? [];

    // Debug: Ghi log dữ liệu sau khi join
    if (empty($recordEmployeeData)) {
        error_log("No data found after joining record with employee, department, and assignments for year: $year");
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
    ]);
    $timePeriodMap = array_column($timePeriods, null, 'acTzNumber'); // Map theo acTzNumber

    // Debug: Ghi log dữ liệu timeperiod
    error_log("Time period data: " . json_encode($timePeriodMap));

    // Lấy dữ liệu từ bảng leave_requests
    $leaveRequests = $app->select("leave_requests", [
        "personSN",
        "start_date",
        "end_date"
    ], [
        "AND" => [
            "start_date[<=]" => "$year-$month-31 23:59:59",
            "end_date[>=]" => "$year-$month-01 00:00:00"
        ]
    ]) ?? [];

    // Nhóm leave_requests theo personSN
    $leaveRequestsByEmployee = [];
    foreach ($leaveRequests as $request) {
        $personSN = $request['personSN'];
        if (!isset($leaveRequestsByEmployee[$personSN])) {
            $leaveRequestsByEmployee[$personSN] = [];
        }
        $leaveRequestsByEmployee[$personSN][] = [
            'start_date' => $request['start_date'],
            'end_date' => $request['end_date']
        ];
    }

    // Debug: Ghi log dữ liệu leave_requests
    error_log("Leave requests data: " . json_encode($leaveRequestsByEmployee));

    // Kết hợp dữ liệu
    $datas = [];
    foreach ($recordEmployeeData as $record) {
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

            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

            // Lấy timeperiod_id của nhân viên
            $timeperiodId = $employeeInfo['timeperiod_id'] ?? null;
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
                error_log("No timeperiod found for employee $employeeSn, using default");
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

            // Khởi tạo dữ liệu cho tất cả các ngày trong tháng
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $dateKey = "$year-$month-$day";
                $dayOfWeek = date('N', strtotime($dateKey));
                $isDayOff = (bool)$timePeriod[$dayMap[$dayOfWeek]['off']];

                // Kiểm tra xem ngày này có nằm trong khoảng thời gian xin nghỉ phép không
                $isOffPermitted = false;
                if (isset($leaveRequestsByEmployee[$employeeSn])) {
                    foreach ($leaveRequestsByEmployee[$employeeSn] as $request) {
                        // Chuyển đổi ngày thành timestamp và bỏ qua giờ phút giây
                        $startDate = strtotime(date('Y-m-d', strtotime($request['start_date'])));
                        $endDate = strtotime(date('Y-m-d', strtotime($request['end_date'])));
                        $currentDate = strtotime(date('Y-m-d', strtotime($dateKey)));

                        // Debug: Ghi log các giá trị ngày để kiểm tra
                        error_log("Employee $employeeSn, Date $dateKey: StartDate = " . date('Y-m-d', $startDate) . ", EndDate = " . date('Y-m-d', $endDate) . ", CurrentDate = " . date('Y-m-d', $currentDate));

                        if ($currentDate >= $startDate && $currentDate <= $endDate) {
                            $isOffPermitted = true;
                            error_log("Employee $employeeSn, Date $dateKey: Marked as off-permitted");
                            break;
                        }
                    }
                }

                // Gán trạng thái mặc định cho ngày
                if ($isOffPermitted) {
                    $status = ['off-permitted'];
                } elseif ($isDayOff) {
                    $status = ['day-off'];
                } else {
                    $status = ['no-record'];
                }

                $employeeData["day_$day"] = [
                    "check_in" => null,
                    "check_out" => null,
                    "status" => $status
                ];
            }

            // Xử lý các ngày có bản ghi chấm công
            foreach ($employeeInfo['days'] as $dateKey => $checkTimes) {
                $day = (int)date('d', strtotime($dateKey));
                $checkTimesSorted = array_column($checkTimes, 'time');
                sort($checkTimesSorted);

                $checkIn = null;
                $checkOut = null;
                $status = [];

                // Xác định ngày trong tuần từ createTime
                $dayOfWeek = date('N', strtotime($dateKey));
                $isDayOff = (bool)$timePeriod[$dayMap[$dayOfWeek]['off']];

                // Kiểm tra xem ngày này có nằm trong khoảng thời gian xin nghỉ phép không
                $isOffPermitted = false;
                if (isset($leaveRequestsByEmployee[$employeeSn])) {
                    foreach ($leaveRequestsByEmployee[$employeeSn] as $request) {
                        // Chuyển đổi ngày thành timestamp và bỏ qua giờ phút giây
                        $startDate = strtotime(date('Y-m-d', strtotime($request['start_date'])));
                        $endDate = strtotime(date('Y-m-d', strtotime($request['end_date'])));
                        $currentDate = strtotime(date('Y-m-d', strtotime($dateKey)));

                        // Debug: Ghi log các giá trị ngày để kiểm tra
                        error_log("Employee $employeeSn, Date $dateKey (with check-in): StartDate = " . date('Y-m-d', $startDate) . ", EndDate = " . date('Y-m-d', $endDate) . ", CurrentDate = " . date('Y-m-d', $currentDate));

                        if ($currentDate >= $startDate && $currentDate <= $endDate) {
                            $isOffPermitted = true;
                            error_log("Employee $employeeSn, Date $dateKey (with check-in): Marked as off-permitted");
                            break;
                        }
                    }
                }

                // Debug: Ghi log ngày và trạng thái
                error_log("Employee $employeeSn on $dateKey (Day $dayOfWeek): IsDayOff = " . ($isDayOff ? 'yes' : 'no') . ", IsOffPermitted = " . ($isOffPermitted ? 'yes' : 'no'));

                if ($isOffPermitted) {
                    // Nếu ngày này có xin nghỉ phép, ưu tiên trạng thái off-permitted
                    $status[] = 'off-permitted';
                    if (count($checkTimesSorted) >= 2) {
                        $checkIn = date('H:i', strtotime($checkTimesSorted[0]));
                        $checkOut = date('H:i', strtotime($checkTimesSorted[count($checkTimesSorted) - 1]));
                    } elseif (count($checkTimesSorted) == 1) {
                        $checkIn = date('H:i', strtotime($checkTimesSorted[0]));
                    }
                } elseif ($isDayOff) {
                    // Nếu là ngày nghỉ theo ca, gán trạng thái day-off
                    $status[] = 'day-off';
                    if (count($checkTimesSorted) >= 2) {
                        $checkIn = date('H:i', strtotime($checkTimesSorted[0]));
                        $checkOut = date('H:i', strtotime($checkTimesSorted[count($checkTimesSorted) - 1]));
                    } elseif (count($checkTimesSorted) == 1) {
                        $checkIn = date('H:i', strtotime($checkTimesSorted[0]));
                    }
                } else {
                    // Nếu không phải ngày nghỉ, kiểm tra trạng thái chấm công
                    $checkInStandard = $timePeriod[$dayMap[$dayOfWeek]['start']] ?? '09:00';
                    $checkOutStandard = $timePeriod[$dayMap[$dayOfWeek]['end']] ?? '16:30';

                    // Debug: Ghi log khung giờ áp dụng
                    error_log("Employee $employeeSn on $dateKey (Day $dayOfWeek): CheckInStandard = $checkInStandard, CheckOutStandard = $checkOutStandard");

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

                        // Tính sai số thời gian (phút)
                        $checkInMinutes = $checkInHour * 60 + $checkInMinute;
                        $checkInStandardMinutes = $checkInStandardHour * 60 + $checkInStandardMinute;
                        $checkOutMinutes = $checkOutHour * 60 + $checkOutMinute;
                        $checkOutStandardMinutes = $checkOutStandardHour * 60 + $checkOutStandardMinute;

                        $lateMinutes = $checkInMinutes - $checkInStandardMinutes;
                        $earlyMinutes = $checkOutStandardMinutes - $checkOutMinutes;

                        // Cho phép sai số 30 phút
                        $isCheckInOnTime = ($lateMinutes <= 30); // Muộn không quá 30 phút
                        $isCheckOutOnTime = ($earlyMinutes <= 30); // Sớm không quá 30 phút

                        // Debug: Ghi log sai số
                        error_log("Employee $employeeSn on $dateKey: Late = $lateMinutes minutes, Early = $earlyMinutes minutes");

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
                }

                $employeeData["day_$day"] = [
                    "check_in" => $checkIn,
                    "check_out" => $checkOut,
                    "status" => $status
                ];
            }

            $departmentData[$departmentId]['employees'][] = $employeeData;
        }
    }

    $vars['data'] = $departmentData;

    echo $app->render('templates/employee/attendance.html', $vars);
})->setPermissions(['attendance']);

// Route để thêm chấm công
$app->router("/manager/attendance-add", 'GET', function($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Thêm chấm công");
    $vars['data'] = [
        "employee_sn" => '',
        "date" => '',
        "status" => ''
    ];
    $vars['employees'] = $app->select("employee", ["sn", "name"], [
        "ORDER" => ["name" => "ASC"]
    ]);
    echo $app->render('templates/employee/attendance-post.html', $vars, 'global');
})->setPermissions(['attendance.add']);

$app->router("/manager/attendance-add", 'POST', function($vars) use ($app, $jatbi) {
    $app->header(['Content-Type' => 'application/json']);

    $employee_sn = $app->xss($_POST['employee_sn'] ?? '');
    $date = $app->xss($_POST['date'] ?? '');
    $status = $app->xss($_POST['status'] ?? '');

    if (empty($employee_sn) || empty($date) || empty($status)) {
        echo json_encode(["status" => "error", "content" => "Vui lòng không để trống"]);
        return;
    }

    if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $date) || !strtotime($date)) {
        echo json_encode(["status" => "error", "content" => "Ngày không hợp lệ"]);
        return;
    }

    // Chuyển đổi trạng thái thành thời gian chấm công giả lập
    $createTime = $date . ' ';
    if ($status == 'checked') {
        $createTime .= '08:00:00'; // Giả lập chấm công đúng giờ
    } elseif ($status == 'late') {
        $createTime .= '18:00:00'; // Giả lập chấm công trễ
    } elseif ($status == 'not-checked') {
        $createTime .= '07:00:00'; // Giả lập chưa chấm công về
    } else {
        $createTime .= '00:00:00'; // OFF
    }

    $app->insert("record", [
        "personSn" => $employee_sn,
        "personName" => $app->select("employee", ["name"], ["sn" => $employee_sn])[0]['name'] ?? 'N/A',
        "personType" => $app->select("employee", ["type"], ["sn" => $employee_sn])[0]['type'] ?? 1,
        "createTime" => $createTime,
        "checkImg" => null
    ]);

    echo json_encode(["status" => "success", "content" => "Thêm thành công"]);
})->setPermissions(['attendance.add']);

// Route để xóa chấm công
$app->router("/manager/attendance-deleted", 'GET', function($vars) use ($app, $jatbi) {
    $vars['title'] = $jatbi->lang("Xóa chấm công");
    echo $app->render('templates/common/deleted.html', $vars, 'global');
})->setPermissions(['attendance.deleted']);

$app->router("/manager/attendance-deleted", 'POST', function($vars) use ($app, $jatbi) {
    $app->header(['Content-Type' => 'application/json']);
    $idString = $_GET['id'] ?? '';
    $ids = explode(",", $idString);

    if (empty($ids) || $idString === '') {
        echo json_encode(["status" => "error", "content" => "Vui lòng chọn ít nhất một bản ghi để xóa!"]);
        return;
    }

    $successCount = 0;
    foreach ($ids as $id) {
        $id = trim($app->xss($id));
        if (empty($id)) continue;
        $app->delete("record", ["id" => $id]);
        $successCount++;
    }

    echo json_encode(["status" => "success", "content" => "Đã xóa $successCount bản ghi"]);
})->setPermissions(['attendance.deleted']);

// Route để xuất Excel (giả lập)
$app->router("/manager/attendance/excel", 'GET', function($vars) use ($app, $jatbi) {
    $app->header(['Content-Type' => 'application/json']);
    echo json_encode(["status" => "success", "content" => "Đang xuất file Excel..."]);
})->setPermissions(['attendance']);

$app->router("/manager/attendance/excel_pro", 'GET', function($vars) use ($app, $jatbi) {
    $app->header(['Content-Type' => 'application/json']);
    echo json_encode(["status" => "success", "content" => "Đang xuất file Excel theo công..."]);
})->setPermissions(['attendance']);