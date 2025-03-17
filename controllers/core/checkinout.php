<?php
if (!defined('ECLO')) die("Hacking attempt");
$jatbi = new Jatbi($app);
$setting = $app->getValueData('setting');

$app->router("/manager/checkinout", 'GET', function($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Thời gian ra vào");
        $vars['add'] = '/manager/checkinout-add';
        $vars['deleted'] = '/manager/checkinout-deleted';
        // Lấy danh sách từ bảng checkinout
        $data = $app->select("checkinout", ["id", "sn", "checkinout_list", "updated_at"]);

        // Truy vấn bảng employee để lấy tên tương ứng với sn
    $employeeData = $app->select("employee", ["sn", "name"]);
    $employeeMap = [];
    foreach ($employeeData as $employee) {
        $employeeMap[$employee['sn']] = $employee['name'];
    }
        
        // Gọi API để lấy dữ liệu thời gian cho từng sn
        $apiData = [];
        foreach ($data as &$item) {
            $headers = [
                'Authorization: Bearer your_token',
                'Content-Type: application/x-www-form-urlencoded'
            ];
            $params = [
                'deviceKey' => '77ed8738f236e8df86',
                'secret' => '123456',
                'sn' => $item['sn'],
                'passtimeList' => $item['checkinout_list']
            ];
    
            // $response = $app->apiPost(
            //     'http://camera.ellm.io:8190/api/person/passtime/merge',
            //     $params,
            //     $headers
            // );
    
            // $apiResponse = json_decode($response, true);
            // if (!empty($apiResponse['data']['acT21'])) {
            //     $item['api_time_list'] = $apiResponse['data']['acT21'];
            // } else {
            //     $item['api_time_list'] = [];
            // }
        }
    
        $vars['data'] = $data;
        echo $app->render('templates/employee/checkinout.html', $vars);
    })->setPermissions(['checkinout']);
    $app->router("/manager/checkinout", 'POST', function($vars) use ($app, $jatbi) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
    
        // Nhận dữ liệu từ DataTable
        $draw = $_POST['draw'] ?? 0;
        $start = $_POST['start'] ?? 0;
        $length = $_POST['length'] ?? 10;
        $searchValue = $_POST['search']['value'] ?? '';
        $sn = $_POST['sn'] ?? '';
    
        // Fix lỗi ORDER cột
        $orderColumnIndex = $_POST['order'][0]['column'] ?? 0;
        $orderDir = strtoupper($_POST['order'][0]['dir'] ?? 'DESC');
    
        // Danh sách cột hợp lệ
        $validColumns = ["sn", "name", "checkinout_list", "updated_at"];
        $orderColumn = $validColumns[$orderColumnIndex] ?? "sn";
    
        // Điều kiện lọc dữ liệu
        $where = [
            "AND" => [
                "OR" => [
                    "checkinout.sn[~]" => $searchValue,
                    "checkinout.checkinout_list[~]" => $searchValue,
                    "employee.name[~]" => $searchValue,  
                ]
            ],
            "LIMIT" => [$start, $length],
            "ORDER" => [$orderColumn => $orderDir]
        ];
    
        if (!empty($sn)) {
            $where["AND"]["checkinout.sn"] = $sn;
        }
    
        // Đếm số bản ghi
        
        $count = $app->count("checkinout");
    
        // Truy vấn danh sách checkinout
        $datas = $app->select("checkinout", [
            "[>]employee" => ["sn" => "sn"]

        ], [
            "checkinout.id",
            "checkinout.sn",
            "checkinout.checkinout_list",
            "checkinout.updated_at",
            "employee.name"  
        ], $where) ?? [];
    
        // Truy vấn bảng employee để lấy tên tương ứng với sn
        $employeeData = $app->select("employee", ["sn", "name"]);
        $employeeMap = [];
        foreach ($employeeData as $employee) {
            $employeeMap[$employee['sn']] = $employee['name'];
        }
    
        // Gọi API và xử lý dữ liệu đầu ra
        $formattedData = array_map(function($data) use ($app, $jatbi, $employeeMap) {
            // Gọi API để lấy dữ liệu thời gian
            $headers = [
                'Authorization: Bearer your_token',
                'Content-Type: application/x-www-form-urlencoded'
            ];
            $params = [
                'deviceKey' => '77ed8738f236e8df86',
                'secret' => '123456',
                'sn' => $data['sn'],
                'passtimeList' => $data['checkinout_list'] ?? ''
            ];
    
            $response = $app->apiPost(
                'http://camera.ellm.io:8190/api/person/passtime/merge',
                $params,
                $headers
            );
    
            $apiResponse = json_decode($response, true);
    
            // Lấy dữ liệu từ passtimeList
            $passTimeList = !empty($apiResponse['data']['passtimeList'][0]) ? $apiResponse['data']['passtimeList'][0] : [];
    
            // Định dạng dữ liệu thời gian để hiển thị
            $formattedTimeList = [];
            $daysMapping = [
                'mon' => 'Thứ Hai',
                'tue' => 'Thứ Ba',
                'wed' => 'Thứ Tư',
                'thurs' => 'Thứ Năm',
                'fri' => 'Thứ Sáu',
                'sat' => 'Thứ Bảy',
                'sun' => 'Chủ Nhật'
            ];
    
            foreach ($passTimeList as $day => $time) {
                if (isset($daysMapping[$day]) && !empty($time)) {
                    $formattedTimeList[] = "{$daysMapping[$day]}: {$time}";
                }
            }
    
            $timeListString = implode("<br>", $formattedTimeList);
    
            return [
                // "checkbox" => "<input type='checkbox' class= 'form-check-input checker'  value='{$data['id']}'>",
                "checkbox" => $app->component("box",["data"=>$data['id']]),
                "sn"=> $data['sn'],
                "name" => isset($employeeMap[$data['sn']]) ? $employeeMap[$data['sn']] : $data['sn'], // Hiển thị tên thay vì sn
                "checkinout_list" => $timeListString, // Hiển thị dữ liệu từ API
                "updated_at" => $data['updated_at'],
                "action" => $app->component("action", [
                    "button" => [
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Sửa"),
                            'permission' => ['checkinout.edit'],
                            'action' => ['data-url' => '/manager/checkinout-edit?id='.$data['id'], 'data-action' => 'modal']
                        ],
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Xóa"),
                            'permission' => ['checkinout.deleted'],
                            'action' => ['data-url' => '/manager/checkinout-deleted?box='.$data['id'], 'data-action' => 'modal']
                        ],
                    ]
                ]),
            ];
        }, $datas);
    
        // Trả về dữ liệu cho DataTable
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
    })->setPermissions(['checkinout']);


   // Thêm thời gian ra vào GET
    $app->router("/manager/checkinout-add", 'GET', function($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Thêm thời gian ra vào");
    
        // Truy vấn danh sách nhân viên từ bảng employee
        $employees = $app->select("employee", ["sn", "name"]);
        $vars['employees'] = $employees;
    
        $vars['data'] = [
            "sn" => '',
            "checkinout_list" => '[]',
        ];
        echo $app->render('templates/employee/checkinout-post.html', $vars, 'global');
    })->setPermissions(['checkinout.add']);

// Thêm thời gian ra vào (POST)
$app->router("/manager/checkinout-add", 'POST', function($vars) use ($app, $jatbi) {
    $app->header(['Content-Type' => 'application/json']);
    
    $sn = $app->xss($_POST['sn'] ?? '');
    $checkinout_list = $app->xss($_POST['checkinout_list'] ?? '[]');

    if (empty($sn) || empty($checkinout_list)) {
        echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
        return;
    }

    try {
        // Kiểm tra xem sn đã tồn tại trong bảng checkinout 
        $existingRecord = $app->select("checkinout", ["id"], ["sn" => $sn]);
        if (!empty($existingRecord)) {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Nhân viên đã được thêm vào danh sách")]);
            return;
        }
        $apiData = [
            'deviceKey' => '77ed8738f236e8df86',
            'secret' => '123456',
            'sn' => $sn,
            'passtimeList' => $checkinout_list, 
        ];
        
        $headers = [
            'Authorization: Bearer your_token',
            'Content-Type: application/x-www-form-urlencoded'
        ];

        $response = $app->apiPost(
            'http://camera.ellm.io:8190/api/person/passtime/merge', 
            $apiData, 
            $headers
        );

        $apiResponse = json_decode($response, true);

        if (!empty($apiResponse['code']) && $apiResponse['code'] == "000") {
            $insert = [
                "sn" => $sn,
                "checkinout_list" => $checkinout_list,
                "updated_at" => date('Y-m-d H:i:s'),
            ];
            
            $app->insert("checkinout", $insert);
            $jatbi->logs('checkinout', 'checkinout-add', $insert);

            echo json_encode(["status" => "success", "content" => $jatbi->lang("Cập nhật thành công")]);
        } else {
            $errorMessage = $apiResponse['msg'] ?? "Không rõ lỗi";
            echo json_encode(["status" => "error", "content" => "API gặp lỗi: " . $errorMessage]);
        }

    } catch (Exception $e) {
        echo json_encode(["status" => "error", "content" => "Lỗi: " . $e->getMessage()]);
    }
})->setPermissions(['checkinout.add']);


// Sửa thời gian ra vào (GET)
$app->router("/manager/checkinout-edit", 'GET', function($vars) use ($app, $jatbi, $setting) {
    $id = $app->xss($_GET['id'] ?? '');
    $vars['title'] = $jatbi->lang("Sửa thời gian ra vào");

    if (empty($id)) {
        $app->error(404, $jatbi->lang("Không tìm thấy ID"));
        return;
    }

    // Lấy dữ liệu từ bảng checkinout
    $data = $app->select("checkinout", ["id", "sn", "checkinout_list", "updated_at"], ["id" => $id]);
    if (empty($data)) {
        $app->error(404, $jatbi->lang("Không tìm thấy dữ liệu"));
        return;
    }

    // Lấy danh sách nhân viên từ bảng employee
    $employees = $app->select("employee", ["sn", "name"]);
    $employeeMap = [];
    foreach ($employees as $employee) {
        $employeeMap[$employee['sn']] = $employee['name'];
    }

    // Thêm tên nhân viên vào dữ liệu, với giá trị mặc định nếu không tìm thấy
    $data[0]['employee_name'] = isset($employeeMap[$data[0]['sn']]) ? $employeeMap[$data[0]['sn']] : "Không xác định (" . ($data[0]['sn'] ?? 'N/A') . ")";

    $vars['employees'] = $employees;
    $vars['data'] = $data[0];
    echo $app->render('templates/employee/checkinout-post.html', $vars, 'global');
})->setPermissions(['checkinout.edit']);


//sửa thời gian ra vào POST
$app->router("/manager/checkinout-edit", 'POST', function($vars) use ($app, $jatbi) {
    $app->header(['Content-Type' => 'application/json']);
    
    // Lấy id từ query string hoặc form
    $id = $app->xss($_POST['id'] ?? $_GET['id'] ?? '');
    $sn = $app->xss($_POST['sn'] ?? '');
    $checkinout_list = $app->xss($_POST['checkinout_list'] ?? '[]');

    if (empty($id) || empty($sn) || empty($checkinout_list)) {
        echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
        return;
    }

    try {
        $apiData = [
            'deviceKey' => '77ed8738f236e8df86',
            'secret' => '123456',
            'sn' => $sn,
            'passtimeList' => $checkinout_list, 
        ];
        
        $headers = [
            'Authorization: Bearer your_token',
            'Content-Type: application/x-www-form-urlencoded'
        ];

        $response = $app->apiPost(
            'http://camera.ellm.io:8190/api/person/passtime/merge', 
            $apiData, 
            $headers
        );

        $apiResponse = json_decode($response, true);

        if (!empty($apiResponse['code']) && $apiResponse['code'] == "000") {
            $update = [
                "sn" => $sn,
                "checkinout_list" => $checkinout_list,
                "updated_at" => date('Y-m-d H:i:s'),
            ];
            
            $app->update("checkinout", $update, ["id" => $id]);
            $jatbi->logs('checkinout', 'checkinout-edit', $update);

            echo json_encode(["status" => "success", "content" => $jatbi->lang("Cập nhật thành công")]);
        } else {
            $errorMessage = $apiResponse['msg'] ?? "Không rõ lỗi";
            echo json_encode(["status" => "error", "content" => "API gặp lỗi: " . $errorMessage]);
        }

    } catch (Exception $e) {
        echo json_encode(["status" => "error", "content" => "Lỗi: " . $e->getMessage()]);
    }
})->setPermissions(['checkinout.edit']);



// Xóa thời gian ra vào (GET)
$app->router("/manager/checkinout-deleted", 'GET', function($vars) use ($app, $jatbi) {
    $vars['title'] = $jatbi->lang("Xóa thời gian ra vào");
    echo $app->render('templates/common/deleted.html', $vars, 'global');
})->setPermissions(['checkinout.deleted']);

// Xóa thời gian ra vào (POST)
$app->router("/manager/checkinout-deleted", 'POST', function($vars) use ($app, $jatbi) {
    $app->header([
        'Content-Type' => 'application/json',
    ]);

    try {
        // Lấy danh sách id từ query string 'box' (dạng chuỗi phân tách bằng dấu phẩy)
        $boxid = explode(',', $app->xss($_GET['box'] ?? ''));

        // Loại bỏ các giá trị rỗng và không phải số trong mảng
        $boxid = array_filter($boxid, function($value) {
            return !empty($value) && is_numeric($value);
        });

        if (empty($boxid)) {
            error_log("No valid IDs found in box");
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Không tìm thấy ID để xóa")]);
            return;
        }

        // Lấy danh sách các bản ghi từ bảng checkinout dựa trên id
        $datas = $app->select("checkinout", "*", [
            "id" => $boxid
        ]);


        if (count($datas) > 0) {
            // Xóa tất cả các bản ghi
            foreach ($datas as $data) {
                $app->delete("checkinout", ["id" => $data['id']]);
            }
            // Ghi log với tất cả dữ liệu đã xóa
            $jatbi->logs('checkinout', 'checkinout-deleted', $datas);
            echo json_encode(['status' => 'success', "content" => $jatbi->lang("Xóa thành công")]);
        } else {
            error_log("No matching records found for deletion");
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Không tìm thấy dữ liệu để xóa")]);
        }
    } catch (Exception $e) {
        error_log("Error in /manager/checkinout-deleted: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'content' => "Lỗi: " . $e->getMessage()]);
    }
})->setPermissions(['checkinout.deleted']);
    
   