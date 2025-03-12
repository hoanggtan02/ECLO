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
    
        // Kiểm tra dữ liệu nhận từ DataTables
        error_log("Received POST Data: " . print_r($_POST, true));
    
        // Nhận dữ liệu từ DataTable
        $draw = $_POST['draw'] ?? 0;
        $start = $_POST['start'] ?? 0;
        $length = $_POST['length'] ?? 10;
        $searchValue = $_POST['search']['value'] ?? '';
        $type = $_POST['type'] ?? '';
    
        // Fix lỗi ORDER cột
        $orderColumnIndex = $_POST['order'][0]['column'] ?? 1; // Mặc định cột SN
        $orderDir = strtoupper($_POST['order'][0]['dir'] ?? 'DESC');
    
        // Danh sách cột hợp lệ
        $validColumns = ["sn", "name", "type"];
        $orderColumn = $validColumns[$orderColumnIndex] ?? "sn";
    
        // Điều kiện lọc dữ liệu
        $where = [
            "AND" => [
                "OR" => [
                    "employee.sn[~]" => $searchValue,
                    "employee.name[~]" => $searchValue,
                ]
            ],
            "LIMIT" => [$start, $length],
            "ORDER" => [$orderColumn => $orderDir]
        ];
    
        if (!empty($type)) {
            $where["AND"]["employee.type"] = $type;
        }
    
        // Đếm số bản ghi
        $count = $app->count("employee", ["AND" => $where["AND"]]);
    
        // Truy vấn danh sách nhân viên
        $datas = $app->select("employee", ['sn', 'name', 'type'], $where) ?? [];
    
        // Log dữ liệu truy vấn để kiểm tra
        error_log("Fetched Employees Data: " . print_r($datas, true));
    
        // Xử lý dữ liệu đầu ra
        $formattedData = array_map(function($data) use ($app, $jatbi) {
            // Chuyển đổi giá trị type thành văn bản
            $typeLabels = [
                "1" => $jatbi->lang("Nhân viên nội bộ"),
                "2" => $jatbi->lang("Khách"),
                "3" => $jatbi->lang("Danh sách đen"),
            ];
            
            return [
                "checkbox" => "<input type='checkbox' value='{$data['sn']}'>",
                "sn" => $data['sn'],
                "name" => $data['name'],
                "type" => $typeLabels[$data['type']] ?? $jatbi->lang("Không xác định"), // Hiển thị nhãn văn bản
                "action" => $app->component("action", [
                    "button" => [
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Sửa"),
                            'permission' => ['employee.edit'],
                            'action' => ['data-url' => '/manager/employee-edit?id='.$data['sn'], 'data-action' => 'modal']
                        ],
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Xóa"),
                            'permission' => ['employee.deleted'],
                            'action' => ['data-url' => '/manager/employee-deleted?id='.$data['sn'], 'data-action' => 'modal']
                        ],
                    ]
                ]),            
            ];
        }, $datas);

    
        // Log dữ liệu đã format trước khi JSON encode
        error_log("Formatted Data: " . print_r($formattedData, true));
    
        // Kiểm tra lỗi JSON
        $response = json_encode([
            "draw" => $draw,
            "recordsTotal" => $count,
            "recordsFiltered" => $count,
            "data" => $formattedData
        ]);
    
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON Encode Error: " . json_last_error_msg());
        }
    
        echo $response;
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
                echo json_encode(["status" => "error", "content" => "Lưu vào database thành công, nhưng API gặp lỗi: " . $errorMessage]);
            }
    
        } catch (Exception $e) {
            // Xử lý lỗi ngoại lệ
            echo json_encode(["status" => "error", "content" => "Lỗi: " . $e->getMessage()]);
        }
    })->setPermissions(['employee.add']);
    

    //Xóa employee
    $app->router("/manager/employee-deleted", 'GET', function($vars) use ($app, $jatbi) {
        $vars['title'] = $jatbi->lang("Xóa Nhân Viên");

        echo $app->render('templates/common/deleted.html', $vars, 'global');
    })->setPermissions(['employee.deleted']);
    
    $app->router("/manager/employee-deleted", 'POST', function($vars) use ($app,$jatbi) {
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
                echo json_encode(["status" => "error", "content" => "Lưu vào database thành công, nhưng API gặp lỗi: " . $errorMessage]);
            }

        }catch (Exception $e) {
            // Xử lý lỗi ngoại lệ
            echo json_encode(["status" => "error", "content" => "Lỗi: " . $e->getMessage()]);
        }
    })->setPermissions(['employee.deleted']);

    //Cập nhật employee
    $app->router("/manager/employee-edit", 'GET', function($vars) use ($app, $jatbi) {
        $vars['title'] = $jatbi->lang("Sửa Nhân Viên");
    
        $sn = isset($_GET['id']) ? $app->xss($_GET['id']) : null;
        if (!$sn) {
            echo $app->render('templates/common/error-modal.html', $vars, 'global');
            return;
        }
    
        $vars['data'] = $app->get("employee", "*", ["sn" => $sn]);
        if ($vars['data']) {
            echo $app->render('templates/employee/employee-post.html', $vars, 'global');
        } else {
            echo $app->render('templates/common/error-modal.html', $vars, 'global');
        }
    })->setPermissions(['employee.edit']);
        
    $app->router("/manager/employee-edit", 'POST', function($vars) use ($app, $jatbi) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
    
        // Lấy mã nhân viên từ request
        $sn = isset($_POST['sn']) ? $app->xss($_POST['sn']) : null;
    
        if (!$sn) {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Mã nhân viên không hợp lệ")]);
            return;
        }
    
        // Lấy thông tin nhân viên từ DB
        $data = $app->get("employee", "*", ["sn" => $sn]);
        if (!$data) {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy nhân viên")]);
            return;
        }
    
        // Kiểm tra dữ liệu đầu vào
        $name = isset($_POST['name']) ? $app->xss($_POST['name']) : '';
        $type = isset($_POST['type']) ? $app->xss($_POST['type']) : '';
    
        if ($name === '' || $type === '') {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
            return;
        }
    
        // Cập nhật dữ liệu trong database
        $update = [
            "name" => $name,
            "type" => $type,
        ];
    
        $app->update("employee", $update, ["sn" => $sn]);
    
        // Ghi log cập nhật
        $jatbi->logs('employee', 'employee-edit', $update);
    
        // Gọi API cập nhật thông tin trên hệ thống camera
        $headers = [
            'Authorization: Bearer your_token',
            'Content-Type: application/x-www-form-urlencoded'
        ];
    
        $apiData = [
            'deviceKey' => '77ed8738f236e8df86',
            'secret'    => '123456',
            'sn'        => $sn,
            'name'      => $name,
            'type'      => $type,
        ];
    
        $response = $app->apiPost(
            'http://camera.ellm.io:8190/api/person/update', 
            $apiData, 
            $headers
        );
    
        $apiResponse = json_decode($response, true);
    
        if (!empty($apiResponse['success']) && $apiResponse['success'] === true) {
            echo json_encode(["status" => "success", "content" => $jatbi->lang("Cập nhật thành công")]);
        } else {
            $errorMessage = $apiResponse['msg'] ?? "Không rõ lỗi từ API";
            echo json_encode([
                "status" => "error",
                "content" => "Cập nhật trong database thành công, nhưng API gặp lỗi: " . $errorMessage
            ]);
        }
    })->setPermissions(['employee.edit']);
    
    
    
    
?>
