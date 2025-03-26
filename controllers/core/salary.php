<?php

// use Jose\Component\Signature\Algorithm\None;

    if (!defined('ECLO')) die("Hacking attempt");
    $jatbi = new Jatbi($app);
    $setting = $app->getValueData('setting');

    $app->router("/salary", 'GET', function($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Tính lương");
        $vars['employee'] = $app->select("employee",["name (text)","sn (value)"],[]);
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
        $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
        $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';

        $startTime = $app->xss($_POST['startTime'] ?? "");
        $endTime = $app->xss($_POST['endTime'] ?? "");
        $personSn = $app->xss($_POST['personSn'] ?? "");
        $personType = $app->xss($_POST['personType'] ?? "");

        // $where = [
        //     "AND" => [
        //         "OR" => [
        //             "record.id[~]" => $searchValue,
        //             "record.personName[~]" => $searchValue,
        //             "record.personSn[~]" => $searchValue,
        //         ],
        //     ],
        //     "LIMIT" => [$start, $length],
        //     "ORDER" => [$orderName => strtoupper($orderDir)]
        // ];
        
        // if(!empty($startTime)) {
        //     $where["AND"]["record.createTime[>=]"] = $startTime;
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
            // "AND" => $where['AND'],
        ]);

        $app->select("salary",  
            [
            'salary.personSn',
            'salary.department',
            'salary.dailySalary',
            'salary.insurance',
            'salary.workday',
            'salary.overtime',
            'salary.leaveWithoutPay',
            'salary.paidLeave',
            ], function ($data) use (&$datas,$jatbi,$app) {
            $datas[] = [
                "numericalOrder"  => 0,
                "personSn"        => $data['personSn'],
                "department"      => $data['department'],
                "dailySalary"     => $data['dailySalary'],
                "insurance"       => $data['insurance'],
                "workday"         => $data['workday'], 
                "overtime"        => $data['overtime'],
                "leaveWithoutPay" => $data['leaveWithoutPay'],
                "paidLeave"       => $data['paidLeave'],
                'total'           => 0,
            ];
        }); 
    
        echo json_encode([
            "draw" => $draw,
            "recordsTotal" => $count,
            "recordsFiltered" => $count,
            "data" => $datas ?? [],
        ]);

    })->setPermissions(['salary']);