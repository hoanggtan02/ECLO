<?php

if (!defined('ECLO')) die("Hacking attempt");
$jatbi = new Jatbi($app);
$setting = $app->getValueData('setting');

$app->router("/salary", 'GET', function($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Tính lương");
    $vars['department'] = $app->select("department",["personName (text)","departmentId (value)"],[]);
    $vars['employee'] = $app->select("employee",["name (text)","sn (value)"],[]);
    $vars['month'] = date('m');
    $vars['year'] = date('Y');
    $vars['monthYear'] = 2024;
    // $monthYear = $app->xss($_POST['my'] ?? "");
    // $month = $app->xss($_POST['month'] ?? date('m'));
    // $year = $app->xss($_POST['year'] ?? date('Y'));
    // $monthYear = $month . "/" . $year;
    // if($monthYear == "") {
    //     $vars['title'] = $jatbi->lang("Hôm nay");
    // } else {
    //     $vars['title'] = $jatbi->lang("Tính lương");
    // }
    // $salary_list = $app->get("salary_list","list",["salary_list.monthYear" => $monthYear]);
    // $vars['salary_list'] = json_decode($salary_list, true);

    echo $app->render('templates/salary/salary.html', $vars);
})->setPermissions(['salary']);

$app->router("/salary", 'POST', function($vars) use ($app, $jatbi) {
    $app->header([
        'Content-Type' => 'application/json',
    ]);
    $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
    $start = $_POST['start'] ?? 0;
    $length = $_POST['length'] ?? 10;
    $searchValue = $_POST['search']['value'] ?? '';
    $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'employee.name';
    $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';

    $month = $app->xss($_POST['month'] ?? date('m'));
    $year = $app->xss($_POST['yesr'] ?? date('Y'));
    $month = $year . "-" . $month;
    $department = isset($_POST['department']) ? $_POST['department'] : '';
    $employee = isset($_POST['employee']) ? $_POST['employee'] : '';

    $where = [
        "AND" => [
            "OR" => [
                // "salary.id[~]" => $searchValue,
                // "salary.departmentId[~]" => $searchValue,
                "salary.personSn[~]" => $searchValue,
                "employee.name[~]"   => $searchValue,
            ],
            "salary.month" => $month,
        ],

        "LIMIT" => [$start, $length],
        "ORDER" => [$orderName => strtoupper($orderDir)]
    ];

    if(!empty($department)) {
        $where["AND"]["salary.departmentId"] = $department;
    }
    if(!empty($employee)) {
        $where["AND"]["salary.personSn"] = $employee;
    }

    // $count = $app->count("salary", [
    //     "[>]employee" => ["personSn" => "sn"],
    //     "AND" => $where['AND'],
    // ]);

    if($month == date("Y-m")) {
        checkStaff($app, $month);
        attendanceTracking($app);
    }

    $app->select("salary", [
        "[>]employee" => ["personSn" => "sn"],
    ],[
        'salary.personSn',
        'salary.departmentId',
        'salary.dailySalary',
        'salary.workingDays',
        'salary.totalWorkingDays',
        'salary.overtime',
        'salary.lateArrival',
        'salary.earlyLeave',
        'salary.unpaidLeave',
        'salary.paidLeave',
        'salary.unauthorizedLeave',
        'salary.reward',
        'salary.discipline',
        'salary.salaryAdvance',
        'salary.month',
        'employee.name (personName)',
        ], $where, function ($data) use (&$datas,$jatbi,$app) {
            $insurance = (float)$app->sum("insurance", "money", [// bảo hiểm
                "employee"          => $data['personSn'],
                "statu"             => "A",
            ]);

            $overtime = $app->select("overtime", ["money"], [// tăng ca
                "employee"          => $data['personSn'],
                "dayEnd[>=]"        => $data['month'] . "-01 00:00:00",
                "dayEnd[<]"         => (new DateTime($data['month'] . '-01'))->format('Y-m-t') . " 23:59:59",
                "statu"             => "Approved",
            ]);
            $overtimeMoney = 0;
            foreach ($overtime as $item) {
                $overtimeMoney += $item['money'];
            }

            $reward = $app->select("reward_discipline", ["amount"], [// nhận thưởng
                "personSN"          => $data['personSn'],
                "apply_date[<>]"    => [$data['month'] . "-01", (new DateTime($data['month'] . '-01'))->format('Y-m-t')],
                "type"              => "reward",
            ]);
            $rewardMoney = 0;
            foreach ($reward as $item) {
                $rewardMoney += $item['amount'];
            }

            $discipline = $app->select("reward_discipline", ["amount"], [// phạt
                "personSN"          => $data['personSn'],
                "apply_date[<>]"    => [$data['month'] . "-01", (new DateTime($data['month'] . '-01'))->format('Y-m-t')],
                "type"              => "discipline",
            ]);
            $disciplineMoney = 0;
            foreach ($discipline as $item) {
                $disciplineMoney += $item['amount'];
            }

            $total = $data['workingDays'] + $data['paidLeave'];

            $penalty = penalty ($app, $data['personSn'], $data['lateArrival'], $data['earlyLeave']);

            $provisionalSalary = $total * $data['dailySalary'] -$insurance + $overtimeMoney + $rewardMoney + $disciplineMoney -$penalty;// tạm tính
            $datas[] = [
                "personSn"                  => $data['personSn'] . " - " . $data['personName'],
                "departmentId"              => $data['departmentId'],
                "workingDays"               => $data['workingDays'] . ' / ' . $data['totalWorkingDays'],
                "insurance"                 => number_format($insurance, 0, '.', ','),
                "dailySalary"               => number_format($data['dailySalary'], 0, '.', ','),
                "overtime"                  => number_format($overtimeMoney, 0, '.', ',') . " / " . count($overtime),
                "lateArrival/earlyLeave"    => $data['lateArrival'] . ' / ' . $data['earlyLeave'],
                "unpaidLeave"               => $data['unpaidLeave'],
                "paidLeave"                 => $data['paidLeave'],
                "unauthorizedLeave"         => $data['unauthorizedLeave'],
                "total"                     => $total,
                "reward"                    => number_format($rewardMoney, 0, '.', ',') . " / " . count($reward),
                "discipline"                => number_format($disciplineMoney, 0, '.', ',') . " / " . count($discipline),
                "penalty"                   => $penalty,
                "provisionalSalary"         => number_format($provisionalSalary, 0, '.', ','),
                "salaryAdvance"             => number_format($data['salaryAdvance'], 0, '.', ','),
                "salaryReceived"            => number_format($provisionalSalary - $data['salaryAdvance'], 0, '.', ','),
            ];
    }); 

    if($datas) {
        $index = 1;
        foreach ($datas as &$item) {
            $item = array_merge($item, [
                "numericalOrder"            => $index,
            ]);
            $index++;
        }
    }

    $count = count($datas);

    echo json_encode([
        "draw" => $draw,
        "recordsTotal" => $count,
        "recordsFiltered" => $count,
        "data" => $datas ?? [],
    ]);

})->setPermissions(['salary']);

function penalty ($app, $personSn, $lateArrival, $earlyLeave){
    $penalty = 0;
    $a = $app->get("latetime", [
        "value",
        "amount",
    ], [
        "type"              => 'Đi trễ',
        "sn"                => $personSn,
        "status"            => 'A',
        "apply_date[<=]"    => date("Y-m-d H:i:s"),
        "ORDER"             => ["apply_date" => "DESC"],
        "LIMIT"             => 1
    ]);

    $b = $app->get("latetime", [
        "value",
        "amount",
    ], [
        "type"              => 'Về sớm',
        "sn"                => $personSn,
        "status"            => 'A',
        "apply_date[<=]"    => date("Y-m-d"),
        "ORDER"             => ["apply_date" => "DESC"],
        "LIMIT"             => 1
    ]);

    if(!empty($a)) {
        $penalty += ($lateArrival / $a["value"]) * $a["amount"];
    }

    if(!empty($b)) {
        $penalty += ($earlyLeave / $b["value"]) * $b["amount"];
    }
    return $penalty;
}

function addAllStaff($app, $month) { // thêm toàn bộ nhân viên vào bảng tính lương trong tháng mới
    $month = DateTime::createFromFormat('Y-m-d', "$month-01");
    $check = $app->has("logs", ["date[>=]" => $month->format('Y-m-d') . " 00:00:00"]); // kiểm tra logs xem đã được thêm chưa trong tháng mới
    if(!empty($check)) {
        $staff = $app->select("assignments", [
            "[>]employee" => ["employee_id" => "sn"],
        ], [// lấy tất cả nhân viên từ bảng assignments (phân công) với apply_date bé hơn hoặc bằng ngày hiện tại 
            'assignments.employee_id ',
            'employee.departmentId',
        ], [
            "apply_date[<=]" => $month->format('Y-m-d'),
            "apply_date[>=]" => $month->format("Y-m-" . $month->format('t')), // lấy ngày cuối của tháng
        ]);
        foreach ($staff as $s) {// duyệt qua từng phần tử lấy được và kiểm tra đã có trong bảng salary với tháng hiện tại
            $check = $app->has("salary",[
                "personSn"      => $s["employee_id"],
                "month"         => $month,
            ]);
    
            if (empty($check)) {// tạo các nhân viên chưa có trong bảng lương tháng này
                $insert = [
                    "personSn"      => $s["employee_id"],
                    "departmentId"  => $s["departmentId"],
                    "month"         => $month,
                    "status"        => 'A',
                ];
                $app->insert("salary",$insert);
            }
        }
    }
}

function checkArrive($staffArrive, $timeArrive) {
    list($h1, $m1) = explode(":", $staffArrive);
    list($h2, $m2) = explode(":", $timeArrive);

    $totalMinutes1 = $h1 * 60 + $m1;// Chuyển thành tổng số phút
    $totalMinutes2 = $h2 * 60 + $m2;

    $resultMinutes = $totalMinutes1 - $totalMinutes2;// Trừ hai thời gian

    if($resultMinutes>0) {
        return $resultMinutes;
    }
    return 0;
}

function checkLeave($staffLeave, $timeLeave) {
    list($h1, $m1) = explode(":", $staffLeave);
    list($h2, $m2) = explode(":", $timeLeave);

    $totalMinutes1 = $h1 * 60 + $m1;// Chuyển thành tổng số phút
    $totalMinutes2 = $h2 * 60 + $m2;

    $resultMinutes = $totalMinutes2 - $totalMinutes1;// Trừ hai thời gian

    if($resultMinutes>0) {
        return $resultMinutes;
    }
    return 0;
}

function checkStaff($app, $month) {
    $staff = $app->select("assignments", [
        "[>]employee" => ["employee_id" => "sn"],
    ], [// lấy tất cả nhân viên từ bảng assignments (phân công) với apply_date bé hơn hoặc bằng ngày hiện tại 
        'assignments.employee_id ',
        'employee.departmentId',
    ], ["apply_date[<=]" => date("Y-m-d")]);
    
    foreach ($staff as $s) {// duyệt qua từng phần tử lấy được và kiểm tra đã có trong bảng salary với tháng hiện tại
        $check = $app->has("salary",[
            "personSn"      => $s["employee_id"],
            "month"         => $month,
        ]);

        if (empty($check)) {// tạo các nhân viên chưa có trong bảng lương tháng này
            $insert = [
                "personSn"      => $s["employee_id"],
                "departmentId"  => $s["departmentId"],
                "month"         => $month,
                "status"        => 'A',
            ];
            $app->insert("salary",$insert);
        } else {
            $insert = [
                "departmentId"  => $s["departmentId"],
                "status"        => 'A',
            ];
            $app->update("salary",$insert,[
                "personSn"      => $s["employee_id"],
                "month"         => $month,
            ]);
        }
    }
}

function checkDayOff ($app, $personSn, $date, &$paidLeave, &$unpaidLeave) {
    $dayOff = $app->get("leave_requests", [
        "[>]leavetype"          => ["LeaveId" => "LeaveTypeID"],
    ],[
        'leave_requests.leave_days',
        'leave_requests.start_date',
        'leave_requests.end_date',
        'leavetype.SalaryType',
    ],[
        'leave_requests.personSN'       => $personSn,
        'leave_requests.start_date[<=]' => $date . " 23:59:59",
        'leave_requests.end_date[>=]'   => $date . " 00:00:00",
        "ORDER"             => ["leave_requests.created_at" => "DESC"],
        "LIMIT"             => 1
    ]);
               

    if($dayOff) {
        if($dayOff["SalaryType"] == "Nghỉ có lương") $paidLeave++;
        else $unpaidLeave++;
        return 1;
    }
    return "";
}

function attendanceTracking($app) {
    $staff = $app->select("salary", [
        "[>]assignments"        => ["personSn" => "employee_id"],
        "[>]timeperiod"         => ["assignments.timeperiod_id" => "acTzNumber"],
    ], [
        'salary.id',
        'salary.personSn',
        'salary.month',
        'timeperiod.monStart',
        'timeperiod.monEnd',
        'timeperiod.tueStart',
        'timeperiod.tueEnd',
        'timeperiod.wedStart',
        'timeperiod.wedEnd',
        'timeperiod.thursStart',
        'timeperiod.thursEnd',
        'timeperiod.friStart',
        'timeperiod.friEnd',
        'timeperiod.satStart',
        'timeperiod.satEnd',
        'timeperiod.sunStart',
        'timeperiod.sunEnd',
        'timeperiod.mon_off',
        'timeperiod.tue_off',
        'timeperiod.wed_off',
        'timeperiod.thu_off',
        'timeperiod.fri_off',
        'timeperiod.sat_off',
        'timeperiod.sun_off',
    ], ["salary.status" => 'A']);
    
    foreach ($staff as $s) {// duyệt qua từng staff 

        $workingDays = 0;// ngày công
        $totalWorkingDays = 0;// ngày công
        $lateArrival = 0;// tới trễ
        $earlyLeave = 0;// về sớm
        $paidLeave = 0;
        $unpaidLeave = 0;
        $unauthorizedLeave = 0;
        
        $workingDate = $app->get("employee_contracts", [
            "working_date",
        ], [
            "person_sn"         => $s["personSn"],
            "ORDER"             => ["working_date" => "DESC"],
            "LIMIT"             => 1
        ]);

        $date = DateTime::createFromFormat('Y-m-d', "{$s['month']}-01");

        if(!empty($workingDate) && $workingDate["working_date"] > $date->format('Y-m-d')) {
            $date = DateTime::createFromFormat('Y-m-d', "{$workingDate["working_date"]}");
        }

        while ($date->format('Y-m') == $s["month"]) {// duyệt từng ngày trong tháng

            $holiday = $app->has("staff-holiday", [// kiểm tra ngày lễ
                "startDate[<=]" => $date->format('Y-m-d'),
                "endDate[>=]"   => $date->format('Y-m-d'),
                "status"        => 'A',
            ]); 
            if(empty($holiday)) {
                $d = $date->format('l');// lấy thứ hiện tại
            $timeMin = $app->min("record", "createTime", [// lấy thời gian ra vào lớn nhất và bé nhất của ngày hiện tại
                "createTime[>=]" => $date->format('Y-m-d') . " 00:00:00",
                "createTime[<=]" => $date->format('Y-m-d') . " 23:59:59",
                "personSn"       => $s["personSn"],
            ]);
            if(!empty($timeMin)) { $timeMin = new DateTime($timeMin); }
            $timeMax = $app->max("record", "createTime", [
                "createTime[>=]" => $date->format('Y-m-d') . " 00:00:00",
                "createTime[<=]" => $date->format('Y-m-d') . " 23:59:59",
                "personSn"       => $s["personSn"],
            ]);
            if(!empty($timeMax)) { $timeMax = new DateTime($timeMax); }
            
            switch ($d) {
                case 'Monday':
                    if($s['mon_off'] == '1') {// bỏ qua ngày nghỉ
                        break;
                    } else $totalWorkingDays++;
   
                    if($timeMin) {// nếu đi làm + 1 ngày công
                        $workingDays++;
                        $lateArrival += checkArrive($timeMin->format('H:i'), $s["monStart"]);
                        $earlyLeave += checkLeave($timeMax->format('H:i'), $s["monEnd"]);
                    }
                    if(checkDayOff ($app, $s["personSn"], $date->format('Y-m-d'), $paidLeave, $unpaidLeave)) break;
                    if ($date->format('Y-m-d') <= date("Y-m-d")) $unauthorizedLeave++;
                    break;
                case 'Tuesday':
                    if($s['tue_off'] == '1') {
                        break;
                    } else $totalWorkingDays++;
                    if($timeMin) {
                        $workingDays++;
                        $lateArrival += checkArrive($timeMin->format('H:i'), $s["tueStart"]);
                        $earlyLeave += checkLeave($timeMax->format('H:i'), $s["tueEnd"]);
                        break;
                    }
                    if(checkDayOff ($app, $s["personSn"], $date->format('Y-m-d'), $paidLeave, $unpaidLeave)) break;
                    if ($date->format('Y-m-d') <= date("Y-m-d")) $unauthorizedLeave++;
                    break;
                case 'Wednesday':
                    if($s['wed_off'] == '1') {
                        break;
                    } else $totalWorkingDays++;
                    if($timeMin) {
                        $workingDays++;
                        $lateArrival += checkArrive($timeMin->format('H:i'), $s["wedStart"]);
                        $earlyLeave += checkLeave($timeMax->format('H:i'), $s["wedEnd"]);
                        break;
                    }
                    if(checkDayOff ($app, $s["personSn"], $date->format('Y-m-d'), $paidLeave, $unpaidLeave)) break;
                    if ($date->format('Y-m-d') <= date("Y-m-d")) $unauthorizedLeave++;
                    break;
                case 'Thursday':
                    if($s['thu_off'] == '1') {
                        break;
                    } else $totalWorkingDays++;
                    if($timeMin) {
                        $workingDays++;
                        $lateArrival += checkArrive($timeMin->format('H:i'), $s["thursStart"]);
                        $earlyLeave += checkLeave($timeMax->format('H:i'), $s["thursEnd"]);
                        break;
                    }   
                    if(checkDayOff ($app, $s["personSn"], $date->format('Y-m-d'), $paidLeave, $unpaidLeave)) break;
                    if ($date->format('Y-m-d') <= date("Y-m-d")) $unauthorizedLeave++;
                    break;
                case 'Friday':
                    if($s['fri_off'] == '1') {
                        break;
                    } else $totalWorkingDays++;
                    if($timeMin) {
                        $workingDays++;
                        $lateArrival += checkArrive($timeMin->format('H:i'), $s["friStart"]);
                        $earlyLeave += checkLeave($timeMax->format('H:i'), $s["friEnd"]);
                        break;
                    }
                    if(checkDayOff ($app, $s["personSn"], $date->format('Y-m-d'), $paidLeave, $unpaidLeave)) break;
                    if ($date->format('Y-m-d') <= date("Y-m-d")) $unauthorizedLeave++;
                    break;
                case 'Saturday':
                    if($s['sat_off'] == '1') {
                        break;
                    } else $totalWorkingDays++;
                    if($timeMin) {
                        $workingDays++;
                        $lateArrival += checkArrive($timeMin->format('H:i'), $s["satStart"]);
                        $earlyLeave += checkLeave($timeMax->format('H:i'), $s["satEnd"]);
                        break;
                    }
                    if(checkDayOff ($app, $s["personSn"], $date->format('Y-m-d'), $paidLeave, $unpaidLeave)) break;
                    if ($date->format('Y-m-d') <= date("Y-m-d")) $unauthorizedLeave++;
                    break;
                case 'Sunday':
                    if($s['sun_off'] == '1') {
                        break;
                    } else $totalWorkingDays++;
                    if($timeMin) {
                        $workingDays++;
                        $lateArrival += checkArrive($timeMin->format('H:i'), $s["sunStart"]);
                        $earlyLeave += checkLeave($timeMax->format('H:i'), $s["sunEnd"]);
                        break;
                    }
                    if(checkDayOff ($app, $s["personSn"], $date->format('Y-m-d'), $paidLeave, $unpaidLeave)) break; 
                    if ($date->format('Y-m-d') <= date("Y-m-d")) $unauthorizedLeave++;
                    break;
                default:
                    break;
                }
            }
            $date->modify('+1 day');  
        }

        $dailySalary = $app->get("employee_contracts", [
            "[>]contract_salary" => ["id" => "Id_contract"],
            "[>]staff-salary" => ["contract_salary.Id_salary" => "id"], 
        ], [
            "staff-salary.price",
        ], [
            "employee_contracts.person_sn"          => $s["personSn"],
            "employee_contracts.working_date[<]"    => $date->format('Y-m-d'),
            "ORDER"             => ["employee_contracts.working_date" => "DESC"],
            "LIMIT"             => 1
        ]);

        $salaryAdvance = $app->sum("salaryadvances", "Amount", [
            "sn"                => $s["personSn"],
            "TypeID"            => '1',
            "AppliedDate[>=]"   => $s["month"] . "-01",
            "AppliedDate[<]"    => $date->format('Y-m-d'),
        ]);

        $leaveDay = 0;

        $insert = [
            "workingDays"       => $workingDays,
            "totalWorkingDays"  => $totalWorkingDays,
            "lateArrival"       => $lateArrival,
            "earlyLeave"        => $earlyLeave,
            "paidLeave"         => $paidLeave,
            "unpaidLeave"       => $unpaidLeave,
            "unauthorizedLeave" => $unauthorizedLeave,
            "dailySalary"       => $dailySalary["price"]??0,
            "salaryAdvance"     => $salaryAdvance,
        ];

        if($s["month"] != date("Y-m")) {
            $insert = array_merge($insert, [
                "status"     => 'D',
            ]);
        }
        
        $app->update("salary",$insert,["id"=>$s['id']]);
    }
}
