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
        $sn = $app->xss($_GET['id']);
        try {
            $app->delete("employee", ["sn" => $sn]);
            
            $headers = [
                'Authorization: Bearer your_token',
                'Content-Type: application/x-www-form-urlencoded'
            ];

            $apiData = [
                'deviceKey' => '77ed8738f236e8df86',
                'secret'    => '123456',
                'sn'        => $sn,
            ];

            $response = $app->apiPost(
                'http://camera.ellm.io:8190/api/person/delete', 
                $apiData, 
                $headers
            );

            $apiResponse = json_decode($response, true);
    
            // Kiểm tra phản hồi từ API
            if (!empty($apiResponse['success']) && $apiResponse['success'] === true) {
                echo json_encode(["status" => "success", "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                $errorMessage = $apiResponse['msg'] ?? "Không rõ lỗi";
                echo json_encode(["status" => "warning", "content" => "Lưu vào database thành công, nhưng API gặp lỗi: " . $errorMessage]);
            }

        }catch (Exception $e) {
            // Xử lý lỗi ngoại lệ
            echo json_encode(["status" => "error", "content" => "Lỗi: " . $e->getMessage()]);
        }
    })->setPermissions(['employee.deleted']);

    $app->router("/manager/employee-edit", 'POST', function($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        
        $error = [];
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $sn = $app->xss($input['sn'] ?? '');
        $name = $app->xss($input['name'] ?? '');
        $type = $app->xss($input['type'] ?? '');
        
        if (empty($sn)) $error['sn'] = $jatbi->lang("Vui lòng nhập mã nhân viên");
        if (empty($name)) $error['name'] = $jatbi->lang("Vui lòng nhập tên nhân viên");
        if (empty($type)) $error['type'] = $jatbi->lang("Vui lòng chọn loại nhân viên");
        elseif (!in_array($type, ['1', '2', '3'])) $error['type'] = $jatbi->lang("Loại nhân viên không hợp lệ");
        if (!empty($error)) {
            echo json_encode([
                "status" => "error",
                "content" => $jatbi->lang("Dữ liệu không hợp lệ"),
                "errors" => $error
            ]);
            return;
        }
        
        try {
            $existing = $app->select("employee", "*", ["sn" => $sn]);
            if (empty($existing)) {
                echo json_encode([
                    "status" => "error",
                    "content" => $jatbi->lang("Nhân viên không tồn tại")
                ]);
                return;
            }
            
            $update = ["name" => $name, "type" => $type];
            $affected = $app->update("employee", $update, ["sn" => $sn]);
            
            if ($affected) {
                $apiData = [
                    "deviceKey" => "77ed8738f236e8df86",
                    "secret" => "123456",
                    "type" => $type,
                    "sn" => $sn,    
                    "name" => $name 
                ];
            
                error_log("API Data Sent: " . json_encode($apiData)); // Log dữ liệu gửi đi
            
                // Gửi yêu cầu POST đến API
                $response = $app->apiPost('http://camera.ellm.io:8190/api/person/update', $apiData, [
                    'Authorization: Bearer your_token',
                    'Content-Type: application/x-www-form-urlencoded'
                ]);
            
                // Kiểm tra lỗi từ $app->apiPost (giả định hàm trả về mảng hoặc JSON)
                if ($response === false || is_null($response)) {
                    error_log("API Error: " . $error);
                    echo json_encode([
                        "status" => "warning",
                        "content" => $jatbi->lang("Cập nhật nhân viên thành công nhưng không thể kết nối API camera"),
                        "error" => $error
                    ]);
                } else {
                    $responseData = json_decode($response, true);
            
                    if (isset($responseData['success']) && $responseData['success'] === true) {
                        echo json_encode([
                            "status" => "success",
                            "content" => $jatbi->lang("Cập nhật nhân viên và API camera thành công"),
                            "data" => array_merge(["sn" => $sn], $update),
                            "api_response" => $response
                        ]);
                    } else {
                        echo json_encode([
                            "status" => "warning",
                            "content" => $jatbi->lang("Cập nhật nhân viên thành công nhưng API camera không cập nhật"),
                            "api_response" => $response,
                            "data_sent" => $apiData
                        ]);
                    }
                }
            } else {
                echo json_encode([
                    "status" => "error",
                    "content" => $jatbi->lang("Không có thay đổi nào được thực hiện")
                ]);
            }
        } catch (Exception $e) {
            echo json_encode([
                "status" => "error",
                "content" => $jatbi->lang("Lỗi hệ thống: ") . $e->getMessage()
            ]);
        }
    })->setPermissions(['employee.edit']);
?>

