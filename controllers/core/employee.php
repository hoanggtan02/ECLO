<?php
    if (!defined('ECLO')) die("Hacking attempt");
    $jatbi = new Jatbi($app);
    $setting = $app->getValueData('setting');

    $app->router("/manager/employee", 'GET', function($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Nhân viên");
        $vars['add'] = '/manager/employee-add';
        $vars['deleted'] = '/manager/employee-deleted';
        $data = $app->select("employee", ["sn","name","type"]);
        $vars['data'] = $data;
        echo $app->render('templates/employee/employee.html', $vars);
    })->setPermissions(['employee']);

    $app->router("/manager/employee", 'POST', function($vars) use ($app, $jatbi) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        
        $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
        $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
        $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
        $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
        $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'sn';
        $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
        $type = isset($_POST['type']) ? $_POST['type'] : '';
    
        $where = [
            "AND" => [
                "OR" => [
                    "employee.sn[~]" => $searchValue,
                    "employee.name[~]" => $searchValue,
                ],
            ],
            "LIMIT" => [$start, $length],
            "ORDER" => [$orderName => strtoupper($orderDir)]
        ];
    
        if (!empty($type)) {
            $where["AND"]["employee.type"] = $type;
        }
    
        $count = $app->count("employee", [
            "AND" => $where['AND'],
        ]);
        
        $datas = [];
        $app->select("employee", [], [
            'employee.sn',
            'employee.name',
            'employee.type',
        ], $where, function ($data) use (&$datas, $app) {
            $datas[] = [
                "sn" => $data['sn'],
                "name" => $data['name'],
                "type" => $data['type'],
            ];
        });
        
        echo json_encode([
            "draw" => $draw,
            "recordsTotal" => $count,
            "recordsFiltered" => $count,
            "data" => $datas
        ]);
    })->setPermissions(['employee']);

    $app->router("/manager/employee-add", 'GET', function($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Nhân viên");
        $vars['data'] = [
            "type" => '1',
        ];
        echo $app->render('templates/employee/employee-post.html', $vars,'global');
    })->setPermissions(['employee.add']);
    

    $app->router("/manager/employee-add", 'POST', function($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
    
        // Lấy dữ liệu từ form và kiểm tra XSS
        $sn   = $app->xss($_POST['sn'] ?? '');
        $name = $app->xss($_POST['name'] ?? '');
        $type = (int) ($app->xss($_POST['type'] ?? ''));
    
        // Kiểm tra dữ liệu đầu vào
        if (empty($sn) || empty($name) || empty($type)) {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
            return;
        }
    
        try {
            // Dữ liệu để lưu vào database
            $insert = [
                "sn"   => $sn,
                "name" => $name,
                "type" => $type,
            ];
            
            // Thêm dữ liệu vào database
            $app->insert("employee", $insert);
    
            // Ghi log
            $jatbi->logs('employee', 'employee-add', $insert);
    
            // Dữ liệu gửi lên API
            $apiData = [
                'deviceKey' => '77ed8738f236e8df86',
                'secret'    => '123456',
                'sn'        => $sn,
                'name'      => $name,
                'type'      => $type,
            ];
            
            $headers = [
                'Authorization: Bearer your_token',
                'Content-Type: application/x-www-form-urlencoded'
            ];
    
            $response = $app->apiPost(
                'http://camera.ellm.io:8190/api/person/create', 
                $apiData, 
                $headers
            );
    
            // Giải mã phản hồi từ API
            $apiResponse = json_decode($response, true);
    
            // Kiểm tra phản hồi từ API
            if (!empty($apiResponse['success']) && $apiResponse['success'] === true) {
                echo json_encode(["status" => "success", "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                $errorMessage = $apiResponse['msg'] ?? "Không rõ lỗi";
                echo json_encode(["status" => "warning", "content" => "Lưu vào database thành công, nhưng API gặp lỗi: " . $errorMessage]);
            }
    
        } catch (Exception $e) {
            // Xử lý lỗi ngoại lệ
            echo json_encode(["status" => "error", "content" => "Lỗi: " . $e->getMessage()]);
        }
    })->setPermissions(['employee.add']);
    
    //xóa employee
    $app->router("/employee/employee-deleted", 'GET', function($vars) use ($app, $jatbi) {
        $vars['title'] = $jatbi->lang("Xóa Nhân Viên");
        echo $app->render('templates/common/deleted.html', $vars, 'global');
    })->setPermissions(['employee.deleted']);
    
    $app->router("/employee/employee-deleted", 'POST', function($vars) use ($app,$jatbi) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        $sn = explode(',', $app->xss($_GET['id']));
        $datas = $app->select("employee","*",["sn"=>$sn]);
        if(count($datas)>0){
            try {
                foreach($datas as $data){
                    $app->delete("employee", ["sn" => $data['sn']]);
                    $name[] = $data['name'];

                    $headers = [
                        'Authorization: Bearer your_token',
                        'Content-Type: application/x-www-form-urlencoded'
                    ];    
                    $apiData = [
                        'deviceKey' => '77ed8738f236e8df86',
                        'secret'    => '123456',
                        'sn'        => $data['sn'],
                    ];
                    $app->apiPost(
                        'http://camera.ellm.io:8190/api/person/delete', 
                        $apiData, 
                        $headers
                    );
                }
                // $jatbi->logs('employee','employee-deleted',$datas);
                // $jatbi->trash('/users/accounts-restore',"Tài khoản: ".implode(', ',$name),["database"=>'accounts',"data"=>$boxid]);
                echo json_encode(['status'=>'success',"content"=>$jatbi->lang("Cập nhật thành công")]);
            }catch (Exception $e) {
                echo json_encode(["status" => "error", "content" => "Lỗi: " . $e->getMessage()]);
            }
        }
        else {
            echo json_encode(['status'=>'error','content'=>$jatbi->lang("Có lỗi xẩy ra")]);
        }
    })->setPermissions(['employee.deleted']);
?>

