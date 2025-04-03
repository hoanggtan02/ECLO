<?php
if (!defined('ECLO')) die("Hacking attempt");
$jatbi = new Jatbi($app);
$setting = $app->getValueData('setting');

$app->router("/salary", 'GET', function($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Tính lương");
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
    $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'numericalOrder';
    $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';

    // global $monthYear;

    $month = $app->xss($_POST['month'] ?? date('m'));
    $year = $app->xss($_POST['yesr'] ?? date('Y'));
    $month = $year . "-" . $month;

    $where = [
        "AND" => [
            "OR" => [
                // "salary.id[~]" => $searchValue,
                // "salary.personName[~]" => $searchValue,
                "salary.personSn[~]" => $searchValue,
            ],
            "salary.month" => $month,
        ],

        "LIMIT" => [$start, $length],
        // "ORDER" => [$orderName => strtoupper($orderDir)]
    ];
    
    // if(!empty($MY)) {
    //     $where["AND"]["salary.MY"] = $MY;
    // }
    // if(!empty($endTime)) {
    //     $endTime = date("Y-m-d", strtotime($endTime . " +1 day"));
    //     $where["AND"]["record.createTime[<=]"] = $endTime;
    // }
    // if(!empty($personSn)) {
    //     $where["AND"]["record.personSn"] = $personSn;
    // }
    // if($personType > -1) {
    //     $where["AND"]["record.personType"] = $personType;
    // }

    $count = $app->count("salary",[
        "AND" => $where['AND'],
    ]);
    
    if($month == date("Y-m")) {
        checkStaff($app, $month);

        $staff = $app->select("salary", [
            "[>]assignments"        => ["personSn" => "employee_id"],
            "[>]timeperiod"         => ["assignments.timeperiod_id" => "acTzNumber"],
            "[>]salaryadvances"     => ["personSn" => "sn"],
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
            'salaryadvances.Amount',
            'salaryadvances.AppliedDate',
        ], ["salary.status" => 'A']);
        
        foreach ($staff as $s) {// duyệt qua từng staff 

            $workingDays = 0;// ngày công
            $workingDaysTotal = 0;// ngày công
            $lateArrival = 0;// tới trễ
            $earlyLeave = 0;// về sớm
            
            $date = DateTime::createFromFormat('Y-m-d', "{$s['month']}-01");

            while ($date->format('Y-m') == $month) {// duyệt từng ngày trong tháng

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
                $timeMax = new DateTime($app->max("record", "createTime", [
                    "createTime[>=]" => $date->format('Y-m-d') . " 00:00:00",
                    "createTime[<=]" => $date->format('Y-m-d') . " 23:59:59",
                ]));
                
                switch ($d) {
                    case 'Monday':
                        if($s['mon_off'] == '1') {// bỏ qua ngày nghỉ
                            break;
                        } else $workingDaysTotal++;
                        if($timeMin) {// nếu đi làm + 1 ngày công
                            $workingDays++;
                            // list($h1, $m1) = explode(":", $timeMin->format('H:i'));
                            list($h2, $m2) = explode(":", $s["monStart"]);
    
                            // // // Chuyển thành tổng số phút
                            // $totalMinutes1 = $h1 * 60 + $m1;
                            $totalMinutes2 = 8 * 60 + 30;
    
                            // // Trừ hai thời gian
                            // $resultMinutes = $totalMinutes2 - $totalMinutes1;
    
                            // if($resultMinutes>0) {
                                $lateArrival = 150;
                            // }
                        }
                        // $abc++;
                        // $lateArrival = 150;
                        // $lateArrival = 10;
                        break;
                    case 'Tuesday':
                        if($s['tue_off'] == '1') {
                            break;
                        } else $workingDaysTotal++;
                        if($timeMin) {
                            $workingDays++;

                            list($h1, $m1) = explode(":", $timeMin->format('H:i'));
                            list($h2, $m2) = explode(":", $s["tueStart"]);

                            $totalMinutes1 = $h1 * 60 + $m1;// Chuyển thành tổng số phút
                            $totalMinutes2 = $h2 * 60 + $m2;

                            $resultMinutes = $totalMinutes1 - $totalMinutes2;// Trừ hai thời gian

                            if($resultMinutes>0) {
                                $lateArrival += $resultMinutes;
                            }
                        }
                        break;
                    case 'Wednesday':
                        if($s['wed_off'] == '1') {
                            break;
                        } else $workingDaysTotal++;
                        if(!empty($timeMin)) {
                            $workingDays++;
                            break;
                        }
                        break;
                    case 'Thursday':
                        if($s['thu_off'] == '1') {
                            break;
                        } else $workingDaysTotal++;
                        if(!empty($timeMin)) {
                            $workingDays++;
                            break;
                        }
                        break;
                    case 'Friday':
                        if($s['fri_off'] == '1') {
                            break;
                        } else $workingDaysTotal++;
                        if(!empty($timeMin)) {
                            $workingDays++;
                            break;
                        }
                        break;
                    case 'Saturday':
                        if($s['sat_off'] == '1') {
                            break;
                        } else $workingDaysTotal++;
                        if(!empty($timeMin)) {
                            $workingDays++;
                            break;
                        }
                        break;
                    case 'Sunday':
                        if($s['sun_off'] == '1') {
                            break;
                        } else $workingDaysTotal++;
                        if(!empty($timeMin)) {
                            $workingDays++;
                            break;
                        } else $workingDaysTotal++;
                        break;
                    default:
                        break;
                        // echo "Hôm nay không có gì đặc biệt.";
                    }
                }
                $date->modify('+1 day');  
            }

            $insert = [
                "workingDays"       => $workingDays . " / ". $workingDaysTotal,
                "lateArrival"       => $lateArrival,
            ];

            if($s["AppliedDate"] >= ($month . "-01") && $s["AppliedDate"] <= date('Y-m-d')) {
                $insert = array_merge($insert, [
                    "salaryAdvance"     => $s["Amount"],
                ]);
            }

            
            $app->update("salary",$insert,["id"=>$s['id']]);
        }


        $app->select("salary", [
            "[>]employee" => ["personSn" => "sn"],
            "[>]employee_contracts" => ["personSn" => "person_sn"],
            "[>]staff-salary" => ["employee_contracts.salaryId" => "id"],
            ],
            [
            'salary.personSn',
            'salary.departmentId',
            'salary.dailySalary',
            'salary.workingDays',
            'salary.overtime',
            'salary.lateArrival',
            'salary.earlyLeave',
            'salary.unpaidLeave',
            'salary.paidLeave',
            'salary.unauthorizedLeave',
            'salary.reward',
            'salary.discipline',
            'salary.salaryAdvance',

            'employee.name (employeeName)',


            'employee_contracts.salaryId ',
            'staff-salary.price',
            'staff-salary.priceValue',
            // "`staff-salary'.'priceValue`",
            
            // "`staff-salary`.`id`",
            // 'staff-salary.priceValue',
            // 'reward',
            // 'discipline',
            // 'salaryAdvance',
            // 'salaryReceived',
            // 'salary.attendanceTracking',

            // 'salary.salaryAdvance',
            // 'salary.salaryReceived',
            // 'salary.month',
            ], $where, function ($data) use (&$datas,$jatbi,$app) {
                // $salary = json_decode($data['salary'], true);
                $workingdays = explode(" / ", $data['workingDays']);
                $datas[] = [
                    "numericalOrder"            => 0,
                    "personSn"                  => $data['personSn'] . " - " . $data['employeeName'],
                    "departmentId"              => $data['departmentId'],
                    "workingDays"               => $data['workingDays'],
                    "insurance"                 => 0,
                    "dailySalary"               => $data['price'],
                    "overtime"                  => $data['overtime'],
                    "lateArrival/earlyLeave"    => $data['lateArrival'] . ' / ' . $data['earlyLeave'],
                    "unpaidLeave"               => $data['unpaidLeave'],
                    "paidLeave"                 => $data['paidLeave'],
                    "unauthorizedLeave"         => $data['unauthorizedLeave'],
                    "total"                     => 0,
                    "reward"                    => $data['reward'],
                    "discipline"                => $data['discipline'],
                    "provisionalSalary"         => number_format($workingdays[0] * $data['price'] + $data['reward'] - $data['discipline'], 0, '.', ','),
                    "salaryAdvance"             => number_format($data['salaryAdvance'], 0, '.', ','),
                    "salaryReceived"            => number_format($workingdays[0] * $data['price'] - $data['salaryAdvance'], 0, '.', ','),
                ];
        }); 
     } else {
        $app->select("salary",
            [
            'salary.personSn',
            'salary.departmentId',
            'salary.dailySalary',
            'salary.workingDays',
            'salary.overtime',
            'salary.lateArrival',
            'salary.earlyLeave', 
            'salary.unpaidLeave',
            'salary.paidLeave',
            'salary.unauthorizedLeave',
            'reward',
            'discipline',
            'salaryAdvance',
            'salaryReceived',
            ], $where, function ($data) use (&$datas,$jatbi,$app) {
                $workday = explode("/", $data['workingDays']);
                $datas[] = [
                    "numericalOrder"            => 0,
                    "personSn"                  => $data['personSn'],
                    "departmentId"              => $data['departmentId'],
                    "dailySalary"               => number_format($data['dailySalary'], 0, '.', ','), // thêm dấu , vào tiền
                    "workingDays"               => $data['workingDays'],
                    "overtime"                  => $data['overtime'],
                    "lateArrival/earlyLeave"    => $data['lateArrival'] . ' / ' . $data['earlyLeave'],
                    "unpaidLeave"               => $data['unpaidLeave'],
                    "paidLeave"                 => $data['paidLeave'],
                    "unauthorizedLeave"         => $data['unauthorizedLeave'],
                    "reward"                    => $data['reward'],
                    "discipline"                => $data['discipline'],
                    "provisionalSalary"         => number_format($workday[0] * $data['dailySalary'] + $data['reward'] - $data['discipline'], 0, '.', ','),
                    "salaryAdvance"             => number_format($data['salaryAdvance'], 0, '.', ','),
                    "salaryReceived"            => number_format($workday[0] * $data['dailySalary'] - $data['salaryAdvance'], 0, '.', ','),
                ];
        });
    }

    echo json_encode([
        "draw" => $draw,
        "recordsTotal" => $count,
        "recordsFiltered" => $count,
        "data" => $datas ?? [],
    ]);

})->setPermissions(['salary']);

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

