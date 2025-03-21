<?php
    if (!defined('ECLO')) die("Hacking attempt");
    $jatbi = new Jatbi($app);
    $setting = $app->getValueData('setting');

    $app->router("/record", 'GET', function($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Hồ sơ");
        $vars['deleted'] = '/record-delete';
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
            ],
            "LIMIT" => [$start, $length],
            "ORDER" => [$orderName => strtoupper($orderDir)]
        ];
        
        if(!empty($startTime)) {
            $where["AND"]["record.createTime[>=]"] = $startTime;
        }
        if(!empty($endTime)) {
            $endTime = date("Y-m-d", strtotime($endTime . " +1 day"));
            $where["AND"]["record.createTime[<=]"] = $endTime;
        }
        if(!empty($personSn)) {
            $where["AND"]["record.personSn"] = $personSn;
        }
        if($personType > -1) {
            $where["AND"]["record.personType"] = $personType;
        }
        
        $count = $app->count("record",[
            "AND" => $where['AND'],
        ]);
        $app->select("record", 
            [
            'record.id',
            'record.personName',
            'record.personSn',
            'record.personType',
            'record.createTime',
            ], $where, function ($data) use (&$datas,$jatbi,$app) {
            $datas[] = [
                "checkbox" => $app->component("box",["data"=>$data['id']]),
                "id" => $data['id'],
                "personName" => $data['personName'],
                "personSn" => $data['personSn'],
                "personType" => $data['personType'] == 1 ? "Nhân viên" : 
                                ($data['personType'] == 2 ? "Khách" : 
                                ($data['personType'] == 3 ? "Danh sách đen" : "Không xác định")),
                "createTime" => $data['createTime'],
                "action" => $app->component("action",[
                    "button" => [
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Xem ảnh"),
                            'permission' => ['record'],
                            'action' => ['data-url' => '/record-viewimage?box='.$data['id'], 'data-action' => 'modal']
                        ],
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

    $app->router("/record-update", 'GET', function($vars) use ($app, $jatbi) {
        $vars['title'] = $jatbi->lang("Xóa hồ sơ");
        echo $app->render('templates/common/reload-api.html', $vars, 'global');
    })->setPermissions(['record']);
    
    $app->router("/record-update", 'POST', function($vars) use ($app,$jatbi) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        $headers = [
            'Authorization: Bearer your_token',
            'Content-Type: application/x-www-form-urlencoded'
        ];

        $startTime = $app->max("record", "createTime"); //Lấy createTime trong database 
        if (!empty($startTime )) {
            $startTime = strtotime($startTime) * 1000 - 25200000; //trừ 7 tiếng
            // $recordIdMax = $app->max("record2", "recordId");
        } else {
            $startTime = 1740762000000; //Lấy 1/3/2025 
        }
        // $today = round(microtime(true) * 1000000); //Lấy thời gian hiện tại và chuyển sang timestamp mili giây (13 chữ số)
        $today = strtotime("+1 day") * 1000;
        $endTime = $startTime + 86400000;

        while ($endTime < $today) {

            $apiData = [
                'deviceKey' => '77ed8738f236e8df86',
                'secret'    => '123456',
                'startTime' => $startTime,
                "endTime"   => $endTime,
                "length"    => 1000,
            ];

            $response = $app->apiPost('http://camera.ellm.io:8190/api/record/findList', $apiData, $headers);
            $apiResponse = json_decode($response, true);

            if (!$apiResponse || !isset($apiResponse['success']) || !$apiResponse['success'] || !isset($apiResponse['data'])) { //Kiểm tra dữ liệu API có hợp lệ không
                echo json_encode(['status'=>'error','content'=>$jatbi->lang("Không thể kết nối tới API")]);
                exit;
            } else {
                foreach ($apiResponse['data'] as $data) {
                    $check = $app->select("record","*",["id"=>$data['id']]);
                    if(count($check) == 0){
                        $adjustedCreateTime = $data['createTime'] + (7 * 3600 * 1000);//Điều chỉnh thời gian: trừ 6 tiếng từ timestamp của API
                        $insert = [
                            "id"   => $data['id'],
                            "personName" => $data['personName'] ?? "Không rõ",
                            "personSn"   => $data['personSn'] ?? "",
                            "personType" => $data['personType'],
                            "createTime" => date("Y-m-d H:i:s", $adjustedCreateTime / 1000), //Sử dụng thời gian đã điều chỉnh, chuyển timestamp thành thời gian đọc được
                        ];
                        $app->insert("record",$insert);
                    } 
                }
            } 
            $startTime = $startTime + 86400000;
            $endTime = $endTime + 86400000;
        }
        echo json_encode(['status'=>'success',"content"=>$jatbi->lang("Cập nhật thành công")]);
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

        $headers = [
            'Authorization: Bearer your_token',
            'Content-Type: application/x-www-form-urlencoded'
        ];

        $deletedCount = 0;

        if(count($datas)>0){
            foreach($datas as $data){
                $deletedCount++;
                $app->delete("record",["id"=>$data['id']]);
                // $name[] = $data['name'];

                $apiData = [
                    'deviceKey' => '77ed8738f236e8df86',
                    'secret'    => '123456',
                    'startTime' => strtotime($data['createTime'])*1000-10,
                    "endTime"   => strtotime($data['createTime'])*1000+10,
                    "recordId"  => $data['id'],
                ];
    
                // $response = $app->apiPost('http://camera.ellm.io:8190/api/record/delete', $apiData, $headers);
                // $apiResponse = json_decode($response, true);

                // if (!empty($apiResponse['success']) && $apiResponse['success'] === true) {
    
                // }
            }

            $jatbi->logs('record','record-deleted',$datas);
            // $jatbi->trash('/users/accounts-restore',"Tài khoản: ".implode(', ',$name),["database"=>'accounts',"data"=>$boxid]);
            echo json_encode(['status'=>'success',"content"=>$jatbi->lang("Đã xóa thành công $deletedCount hồ sơ")]);
        }
        else {
            echo json_encode(['status'=>'error','content'=>$jatbi->lang("Có lỗi xẩy ra")]);
        }
      
    })->setPermissions(['record.deleted']);

    $app->router("/record-viewimage", 'GET', function($vars) use ($app, $jatbi) {
        $vars['title'] = $jatbi->lang("Xem ảnh hồ sơ");
    
        // Lấy ID từ query string
        $recordId = $app->xss($_GET['box'] ?? '');
        if (empty($recordId)) {
            echo json_encode(['status'=>'error',"content"=>$jatbi->lang("Không tìm thấy ID hồ sơ.")]);
            return;
        }
    
    
        // Lấy thông tin record từ cơ sở dữ liệu
        $record = $app->select("record", ["id", "personName", "personSn", "checkImgUrl"], ["id" => $recordId]);
        if (!$record) {
            $vars['error'] = $jatbi->lang("Hồ sơ không tồn tại");
            echo $app->render('templates/common/error-modal.html', $vars, 'global');
            return;
        }

        // Nếu đã có checkImgUrl trong database, sử dụng nó
        if (!empty($record['checkImgUrl'])) {
            $vars['image'] = $record['checkImgUrl'];
        } else {
            // Gọi API để lấy ảnh nếu chưa có trong database
            $apiData = [
                'deviceKey' => '77ed8738f236e8df86',
                'secret' => '123456',
                'recordId' => $recordId,
            ];

            $headers = [
                'Authorization: Bearer your_token',
                'Content-Type: application/x-www-form-urlencoded'
            ];

            try {
                // Giả định dùng /api/record/find để lấy ảnh của record
                $response = $app->apiPost('http://camera.ellm.io:8190/api/record/find', $apiData, $headers);
                $apiResponse = json_decode($response, true);

                if (!empty($apiResponse['success']) && $apiResponse['success'] === true && isset($apiResponse['data']['checkImgBase64'])) {
                    $checkImgBase64 = $apiResponse['data']['checkImgBase64'];
                    $vars['image'] = $checkImgBase64;

                    // Cập nhật ảnh vào database
                    $app->update("record", ["checkImgUrl" => $checkImgBase64], ["id" => $recordId]);
                } else {
                    $vars['error'] = $jatbi->lang("Không thể tải ảnh từ API");
                }
            } catch (Exception $e) {
                $vars['error'] = $jatbi->lang("Lỗi hệ thống: ") . $e->getMessage();
            }
        }

        // Render template HTML (không cần header JSON)
        echo $app->render('templates/common/view-image.html', $vars, 'global');
    })->setPermissions(['record']);