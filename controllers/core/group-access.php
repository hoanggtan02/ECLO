<?php
    if (!defined('ECLO')) die("Hacking attempt");
    $jatbi = new Jatbi($app);
    $setting = $app->getValueData('setting');

    //Nhóm kiểm soát
    $app->router("/control/group-access", 'GET', function($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Nhóm kiểm soát");
        echo $app->render('templates/group-access/group-access.html', $vars);
    })->setPermissions(['group-access']);

    $app->router("/control/group-access", 'POST', function($vars) use ($app, $jatbi) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
    
        // Nhận dữ liệu từ DataTable
        $draw = $_POST['draw'] ?? 0;
        $start = $_POST['start'] ?? 0;
        $length = $_POST['length'] ?? 10;
        $searchValue = $_POST['search']['value'] ?? '';
    
        // Fix lỗi ORDER cột
        $orderColumnIndex = $_POST['order'][0]['column'] ?? 1; // Mặc định cột SN
        $orderDir = strtoupper($_POST['order'][0]['dir'] ?? 'DESC');
    
        // Danh sách cột hợp lệ
        $validColumns = ["acGroupNumber", "name"];
        $orderColumn = $validColumns[$orderColumnIndex] ?? "acGroupNumber";
    
        // Điều kiện lọc dữ liệu
        $where = [
            "AND" => [
                "OR" => [
                    "group-access.acGroupNumber[~]" => $searchValue,
                    "group-access.name[~]" => $searchValue,
                ]
            ],
            "LIMIT" => [$start, $length],
            "ORDER" => [$orderColumn => $orderDir]
        ];
    
        // Đếm số bản ghi
        $count = $app->count("group-access", ["AND" => $where["AND"]]);
    
        // Truy vấn danh sách nhóm kiểm soát
        $datas = $app->select("group-access", ['acGroupNumber', 'name'], $where) ?? [];
    
        // Xử lý dữ liệu đầu ra
        $formattedData = array_map(function($data) use ($app, $jatbi) {
            return [
                "acGroupNumber" => $data['acGroupNumber'],
                "name" => $data['name'],
                "action" => $app->component("action", [
                    "button" => [
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Sửa"),
                            'permission' => ['group-access.edit'],
                            'action' => ['data-url' => '/control/group-access-edit?id='.$data['acGroupNumber'], 'data-action' => 'modal']
                        ],
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Xóa"),
                            'permission' => ['group-access.deleted'],
                            'action' => ['data-url' => '/control/group-access-deleted?id='.$data['acGroupNumber'], 'data-action' => 'modal']
                        ],
                    ]
                ]),            
            ];
        }, $datas);
    
        // Xuất JSON
        echo json_encode([
            "draw" => $draw,
            "recordsTotal" => $count,
            "recordsFiltered" => $count,
            "data" => $formattedData
        ]);
    })->setPermissions(['group-access']);

    // THêm nhóm kiểm soát

    $app->router("/control/group-access-add", 'GET', function($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Nhóm kiểm soát");
        echo $app->render('templates/group-access/group-access-post.html', $vars, 'global');
    })->setPermissions(['group-access.add']);


    
    $app->router("/control/group-access-add", 'POST', function($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
    
        // Lấy dữ liệu từ form và kiểm tra XSS
        $acGroupNumber = $app->xss($_POST['acGroupNumber'] ?? '');
        $name = $app->xss($_POST['name'] ?? '');
    
        // Kiểm tra dữ liệu đầu vào
        if (empty($acGroupNumber) || empty($name)) {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
            return;
        }
    
        try {
            // Dữ liệu để lưu vào database
            $insert = [
                "acGroupNumber" => $acGroupNumber,
                "name" => $name,
            ];
    
            // Thêm dữ liệu vào database
            $app->insert("group-access", $insert);
    
            // Ghi log
            $jatbi->logs('group-access', 'group-access-add', $insert);
    
            // Dữ liệu gửi lên API
            $apiData = [
                'deviceKey'     => '77ed8738f236e8df86',  
                'secret'        => '123456',              
                'acGroupNumber' => $acGroupNumber,
                'name'          => $name,
            ];
    
            $headers = [
                'Authorization: Bearer your_token',
                'Content-Type: application/x-www-form-urlencoded'
            ];
    
            // Gửi request đến API
            $response = $app->apiPost(
                'http://camera.ellm.io:8190/api/ac_group/merge',
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
    })->setPermissions(['group-access.add']);
    
    //Xóa nhóm kiểm soát

    $app->router("/control/group-access-deleted", 'GET', function($vars) use ($app, $jatbi) {
        $vars['title'] = $jatbi->lang("Xóa Nhóm Kiểm Soát");
        echo $app->render('templates/common/deleted.html', $vars, 'global');
    })->setPermissions(['group-access.deleted']);
    
    $app->router("/control/group-access-deleted", 'POST', function($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
    
        $acGroupNumber = $app->xss($_GET['id'] ?? '');
    
        if (!$acGroupNumber) {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Mã nhóm không hợp lệ")]);
            return;
        }
    
        try {
            $app->delete("group-access", ["acGroupNumber" => $acGroupNumber]);
    
            $headers = [
                'Authorization: Bearer your_token',
                'Content-Type: application/x-www-form-urlencoded'
            ];
    
            $apiData = [
                'deviceKey'     => '77ed8738f236e8df86',
                'secret'        => '123456',
                'acGroupNumber' => $acGroupNumber,
            ];
    
            $response = $app->apiPost(
                'http://camera.ellm.io:8190/api/ac_group/delete',
                $apiData,
                $headers
            );
    
            $apiResponse = json_decode($response, true);
    
            if (!empty($apiResponse['success']) && $apiResponse['success'] === true) {
                echo json_encode(["status" => "success", "content" => $jatbi->lang("Xóa thành công")]);
            } else {
                $errorMessage = $apiResponse['msg'] ?? "Không rõ lỗi";
                echo json_encode(["status" => "error", "content" => "Xóa trong database thành công, nhưng API gặp lỗi: " . $errorMessage]);
            }
        } catch (Exception $e) {
            echo json_encode(["status" => "error", "content" => "Lỗi: " . $e->getMessage()]);
        }
    })->setPermissions(['group-access.deleted']);

    //Cập nhật nhóm kiểm soát
    $app->router("/control/group-access-edit", 'GET', function($vars) use ($app, $jatbi) {
        $vars['title'] = $jatbi->lang("Sửa Nhóm Kiểm Soát");
    
        $acGroupNumber = isset($_GET['id']) ? $app->xss($_GET['id']) : null;
        if (!$acGroupNumber) {
            echo $app->render('templates/common/error-modal.html', $vars, 'global');
            return;
        }
    
        $vars['data'] = $app->get("group-access", "*", ["acGroupNumber" => $acGroupNumber]);
        if ($vars['data']) {
            echo $app->render('templates/group-access/group-access-post.html', $vars, 'global');
        } else {
            echo $app->render('templates/common/error-modal.html', $vars, 'global');
        }
    })->setPermissions(['group-access.edit']);
    
    $app->router("/control/group-access-edit", 'POST', function($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
    
        $acGroupNumber = isset($_POST['acGroupNumber']) ? $app->xss($_POST['acGroupNumber']) : null;
        if (!$acGroupNumber) {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Mã nhóm không hợp lệ")]);
            return;
        }
    
        $data = $app->get("group-access", "*", ["acGroupNumber" => $acGroupNumber]);
        if (!$data) {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy nhóm")]);
            return;
        }
    
        $name = isset($_POST['name']) ? $app->xss($_POST['name']) : '';
    
        if ($name === '') {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
            return;
        }
    
        $update = [
            "name" => $name,
        ];
    
        $app->update("group-access", $update, ["acGroupNumber" => $acGroupNumber]);
    
        $jatbi->logs('group-access', 'group-access-edit', $update);
    
        $headers = [
            'Authorization: Bearer your_token',
            'Content-Type: application/x-www-form-urlencoded'
        ];
    
        $apiData = [
            'deviceKey'     => '77ed8738f236e8df86',
            'secret'        => '123456',
            'acGroupNumber' => $acGroupNumber,
            'name'          => $name,
        ];
    
        $response = $app->apiPost(
            'http://camera.ellm.io:8190/api/ac_group/merge',
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
    })->setPermissions(['group-access.edit']);
    
?>