<?php

// use Jose\Component\Signature\Algorithm\None;

    if (!defined('ECLO')) die("Hacking attempt");
    $jatbi = new Jatbi($app);
    $setting = $app->getValueData('setting');

    $app->router("/salary", 'GET', function($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Tính lương");
        $vars['employee'] = $app->select("employee",["name (text)","sn (value)"],[]);
        $vars['month'] = date('m');
        $vars['year'] = (int) date('y');
        $vars['salary'] = $app->select("staff-salary",["id","name","price","priceValue"],["type[<]" => 3,"status" => 'A',]);
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

        $month = $app->xss($_POST['month'] ?? "");
        $year = $app->xss($_POST['year'] ?? "");
        // $MY = sprintf("%02d/%s", $month, $year);

        $where = [
            "AND" => [
                "OR" => [
                    // "salary.id[~]" => $searchValue,
                    // "salary.personName[~]" => $searchValue,
                    "salary.personSn[~]" => $searchValue,
                ],
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

        $app->select("salary",  
            [
            'personSn',
            'departmentId',
            'salary',
            'workingDays',
            'overtime',
            'lateArrival',
            'earlyLeave',
            'unpaidLeave',
            'paidLeave',
            'unauthorizedLeave',
            // 'reward',
            // 'discipline',
            // 'salaryAdvance',
            // 'salaryReceived',
            // 'salary.attendanceTracking',
            // 'salary.reward',
            // 'salary.discipline',
            // 'salary.salaryAdvance',
            // 'salary.salaryReceived',
            // 'salary.month',
            ], $where, function ($data) use (&$datas,$jatbi,$app) {
                $salary = json_decode($data['salary'], true);
                $datas[] = array_merge([
                    "numericalOrder"            => 0,
                    "personSn"                  => $data['personSn'],
                    "departmentId"              => $data['departmentId'],
                    "workingDays"               => $data['workingDays'],
                    "overtime"                  => $data['overtime'],
                    "lateArrival/earlyLeave"    => $data['lateArrival'] . ' / ' . $data['earlyLeave'],
                    "unpaidLeave"               => $data['unpaidLeave'],
                    "paidLeave"                 => $data['paidLeave'],
                    "unauthorizedLeave"         => $data['unauthorizedLeave'],
                    // "dailySalary"     => $data['dailySalary'],
                    // "insurance"       => $data['insurance'],
                    // "workday"         => $data['workday'], 
                    // "overtime"        => $data['overtime'],
                    // "leaveWithoutPay" => $data['leaveWithoutPay'],
                    // "paidLeave"       => $data['paidLeave'],
                    // 'total'           => 0,
                ],$salary);
          
            
            // foreach ($salary as $key => $value) {
            //     $entry[$key] = $value;
            // }
      
        }); 
    
        echo json_encode([
            "draw" => $draw,
            "recordsTotal" => $count,
            "recordsFiltered" => $count,
            "data" => $datas ?? [],
        ]);

    })->setPermissions(['salary']);