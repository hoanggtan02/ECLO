<?php
    if (!defined('ECLO')) die("Hacking attempt");
    $jatbi = new Jatbi($app);
    $setting = $app->getValueData('setting');

    $app->router("/record", 'GET', function($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Hồ sơ");
        $dayMin = $app->min("record", "createTime");
        $dayMax = $app->max("record", "createTime");
        if (!empty($dayMin )) {
            $vars['day'] = ("Dữ liệu ").date("d-m-Y", strtotime($dayMin)).(" - ").date("d-m-Y", strtotime($dayMax)).(".");
        } else {
            $vars['day'] = "Chưa tải dữ liệu.";
        }
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
        if($personType > -1) {
            $where["AND"]["record.personType"] = $personType;
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


    //Tải dữ liệu
    $app->router("/record-find", 'GET', function($vars) use ($app, $jatbi) {
        $vars['title'] = $jatbi->lang("Tải dữ liệu");
        echo $app->render('templates/record/record-find.html', $vars, 'global');
    })->setPermissions(['record']);
    
    $app->router("/record-find", 'POST', function($vars) use ($app,$jatbi) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);

        $startTime = $app->xss($_POST['startTime'] ?? "");
        $endTime = $app->xss($_POST['endTime'] ?? "");

        if(empty($startTime)) {
            echo json_encode(['status'=>'error','content'=>$jatbi->lang("Vui lòng nhập ngày bắt đầu.")]);
            exit;
        }
        if(empty($endTime)) {
            echo json_encode(['status'=>'error','content'=>$jatbi->lang("Vui lòng nhập ngày kết thúc.")]);
            exit;
        }
        if($startTime>$endTime) {
            echo json_encode(['status'=>'error','content'=>$jatbi->lang("Ngày kết thúc không được bé hơn ngày bắt đầu.")]);
            exit;
        }
        $startTime = strtotime($startTime. " 00:00:00") * 1000;
        $endTime = strtotime($endTime. " 23:59:59") * 1000;
  
        $app->delete("record", []);

        $apiResponse = postToAPI($app, $startTime, $endTime);


        // Kiểm tra dữ liệu API có hợp lệ không
        if (!$apiResponse || !isset($apiResponse['success']) || !$apiResponse['success'] || !isset($apiResponse['data'])) {
            echo json_encode(['status'=>'error','content'=>$jatbi->lang("Có lỗi xẩy ra.")]);
        } else {
            foreach ($apiResponse['data'] as $data) {
                $check = $app->select("record","*",["id"=>$data['id']]);
                if(count($check) == 0){
                     // Điều chỉnh thời gian: trừ 6 tiếng từ timestamp của API
                    $adjustedCreateTime = $data['createTime'] + (7 * 3600 * 1000);
                    $insert = [
                        "id" => $data['id'],
                        "personName" => $data['personName'] ?? "Không rõ",
                        "personSn" => $data['personSn'] ?? "",
                        "personType" => $data['personType'] ?? "không xác định",
                        "createTime" => date("Y-m-d H:i:s", $adjustedCreateTime / 1000), // Sử dụng thời gian đã điều chỉnh // Chuyển timestamp thành thời gian đọc được
                    ];
                    $app->insert("record",$insert);
                } 
            }
            $jatbi->logs('record','record-find',$apiResponse);
            echo json_encode(['status'=>'success',"content"=>$jatbi->lang("Dữ liệu được cập nhật.").$startTime.(' ').$endTime]);
            exit;
        } 
      
    })->setPermissions(['record']);

    function postToAPI($app, $startTime, $endTime) {
        $headers = [
            'Authorization: Bearer your_token', // Thay your_token bằng token thực tế
            'Content-Type: application/x-www-form-urlencoded'
        ];
        
        $personSn = $app->xss($_POST['personSn'] ?? "");
        $personType = $app->xss($_POST['personType'] ?? "");
        $recordType = $app->xss($_POST['recordType'] ?? "");
        $index = $app->xss($_POST['index'] ?? "");
        $length = $app->xss($_POST['length'] ?? "");

        $apiData = [
            'deviceKey' => '77ed8738f236e8df86',
            'secret'    => '123456',
            'startTime' => $startTime,
            "endTime"   => $endTime,
        ];

        if(!empty($personSn)) {
            $apiData['personSn'] = $personSn;
        }
        if(!empty($personType)) {
            $apiData['personType'] = $personType;
        }
        if(!empty($recordType)) {
            $apiData['recordType'] = $recordType;
        }
        if(!empty($index)) {
            $apiData['index'] = $index;
        }
        if(!empty($length)) {
            $apiData['length'] = $length;
        }
        
        $response = $app->apiPost('http://camera.ellm.io:8190/api/record/findList', $apiData, $headers);
        return json_decode($response, true);
    }

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