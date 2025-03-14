<?php
    if (!defined('ECLO')) die("Hacking attempt");
    $jatbi = new Jatbi($app);
    $setting = $app->getValueData('setting');

    $app->router("/record", 'GET', function($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Hồ sơ");
        // $vars['add'] = '/manager/employee-add';
        // $vars['deleted'] = '/manager/employee-deleted';
        $data = $app->select("record", ["id","personName","personSn"]);
        $vars['data'] = $data; 
        echo $app->render('templates/record/record.html', $vars);
    })->setPermissions(['record']);

    $app->router("/record", 'POST', function($vars) use ($app, $jatbi) {
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

        $where = [
            "AND" => [
                "OR" => [
                    "record.id[~]" => $searchValue,
                    "record.personName[~]" => $searchValue,
                    "record.personSn[~]" => $searchValue,
                ],
                // "record.createTime[>]" => 2025-01-01
                // "accounts.status[<>]" => $status,
                // "accounts.deleted" => 0,
            ],
            "LIMIT" => [$start, $length],
            "ORDER" => [$orderName => strtoupper($orderDir)]
        ];
        if(!empty($startTime)) {
            $where["AND"]["record.createTime[>=]"] = $startTime;
        }
        if(!empty($endTime)) {
            $where["AND"]["record.createTime[<=]"] = $endTime;
        }
        if(!empty($personSn)) {
            $where["AND"]["record.personSn"] = $personSn;
        }
        if(!empty($personType > -1)) {
            $where["AND"]["record.personType"] = $personSn;
        }

        $count = $app->count("record",[
            "AND" => $where['AND'],
        ]);
        $app->select("record", 
        // [
        //     "[>]permissions" => ["permission" => "id"]
        //     ], 
            [
            'record.id',
            'record.personName',
            'record.personSn',
            'record.personType',
            'record.createTime',
            // 'permissions.name (permission)',
            ], $where, function ($data) use (&$datas,$jatbi,$app) {
            $datas[] = [
                "checkbox" => $app->component("box",["data"=>$data['id']]),
                "id" => $data['id'],
                "personName" => $data['personName'],
                "personSn" => $data['personSn'],
                "personType" => $data['personType'],
                "createTime" => $data['createTime'],
                "action" => $app->component("action",[
                    "button" => [
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Xóa"),
                            'permission' => ['record.deleted'],
                            'action' => ['data-url' => '/record-delete?box='.$data['id'], 'data-action' => 'modal']
                        ],
                    ]
                ]),
            ];
        });
    
        echo json_encode([
            "draw" => $draw,
            "recordsTotal" => $count,
            "recordsFiltered" => $count,
            "data" => $datas ?? [],
        ]);
    })->setPermissions(['record']);

    $app->router("/record-delete", 'GET', function($vars) use ($app, $jatbi) {
        $vars['title'] = $jatbi->lang("Xóa hồ sơ");
        echo $app->render('templates/common/deleted.html', $vars, 'global');
    })->setPermissions(['record.deleted']);
    
    $app->router("/record-delete", 'POST', function($vars) use ($app,$jatbi) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        $boxid = explode(',', $app->xss($_GET['box']));
        $datas = $app->select("record","*",["id"=>$boxid]);
        if(count($datas)>0){
            foreach($datas as $data){
                $app->delete("record",["id"=>$data['id']]);
                // $name[] = $data['name'];
            }
            // $jatbi->logs('accounts','accounts-deleted',$datas);
            // $jatbi->trash('/users/accounts-restore',"Tài khoản: ".implode(', ',$name),["database"=>'accounts',"data"=>$boxid]);
            echo json_encode(['status'=>'success',"content"=>$jatbi->lang("Cập nhật thành công")]);
        }
        else {
            echo json_encode(['status'=>'error','content'=>$jatbi->lang("Có lỗi xẩy ra")]);
        }
      
    })->setPermissions(['record.deleted']);