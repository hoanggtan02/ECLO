<?php
    if (!defined('ECLO')) die("Hacking attempt");
    $jatbi = new Jatbi($app);
    $setting = $app->getValueData('setting');

// Khung thời gian
    $app->router("/overtime", 'GET', function($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Tăng ca");
        $vars['add'] = '/overtime-add';
        $vars['deleted'] = '/overtime-deleted';
        $vars['edit'] = '/overtime-edit';
        $vars['approved'] = '/overtime-approved';
        $data = $app->select("overtime", ["ids","type","employee","money","dayStart","dayEnd","note","statu","day"]);
        $vars['data'] = $data;
        echo $app->render('templates/employee/overtime.html', $vars);
    })->setPermissions(['overtime']);

    $app->router("/overtime", 'POST', function($vars) use ($app, $jatbi) {
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
        $orderColumnIndex = $_POST['order'][0]['column'] ?? 1; // Mặc định cột acTzNumber
        $orderDir = strtoupper($_POST['order'][0]['dir'] ?? 'DESC');
    
        // Danh sách cột hợp lệ
        $validColumns = ["checkbox","type","employee","money","dayStart","dayEnd","statu","day","note"];
        $orderColumn = $validColumns[$orderColumnIndex] ?? "type";
    
        // Điều kiện lọc dữ liệu
        $where = [
            "AND" => [
                "OR" => [
                    "overtime.employee[~]" => $searchValue,
                    "overtime.money[~]" => $searchValue,
                    "overtime.note[~]" => $searchValue,
                    "overtime.statu[~]" => $searchValue,                ]
            ],
            "LIMIT" => [$start, $length],
            "ORDER" => [$orderColumn => $orderDir]
        ];
    
        if (!empty($type)) {
            $where["AND"]["overtime.employee"] = $type;
        }
    
        // Đếm số bản ghi
        $count = $app->count("overtime", ["AND" => $where["AND"]]);
    
        // Truy vấn danh sách Khung thời gian
        $datas = $app->select("overtime", [
            'ids','type','employee','money','dayStart','dayEnd','note','statu','day'
        ], $where) ?? [];
    
        // Log dữ liệu truy vấn để kiểm tra
        error_log("Fetched overtimes Data: " . print_r($datas, true));
    
        // Xử lý dữ liệu đầu ra
        $formattedData = array_map(function($data) use ($app, $jatbi) {
            $typeLabels = [
                "1" => $jatbi->lang("Tăng ca ngày tết"),
                "2" => $jatbi->lang("Tăng ca 2 giờ"),
                "3" => $jatbi->lang("Tăng ca thứ 7"),
            ];

            if ($data['statu'] === 'Approved') {
                $temp = '<a href="#" class="status-link" " data-url="/overtime-approved?ids=' . $data['ids'] . '&statu=' . $data['statu'] . '" data-action="modal">' . $data['statu'] . '</a>';
            } elseif ($data['statu'] === 'Pending') {                   
                $temp = '<a href="#" class="status-link" style="color: green;" data-url="/overtime-approved?ids=' . $data['ids'] . '&statu=' . $data['statu'] . '" data-action="modal">' . $data['statu'] . '</a>';
            }

            return [
                "checkbox" => $app->component("box", ["data" => $data['ids']]),
                "ids" => $data['ids'],
                "type" => $typeLabels[$data['type']] ?? $jatbi->lang("Không xác định"),
                "employee" => $data['employee'],
                "money" => $data['money'],
                "dayStart" => $data['dayStart'],
                "dayEnd" => $data['dayEnd'],
                "note" => $data['note'],
                "statu" => $temp,
                "day" => $data['day'],
                "action" => $app->component("action", [
                    "button" => [          
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Sửa"),
                            'permission' => ['overtime.edit'],
                            'action' => ['data-url' => '/overtime-edit?ids=' . $data['ids'], 'data-action' => 'modal']
                        ],
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Xóa"),
                            'permission' => ['overtime.deleted'],
                            'action' => ['data-url' => '/overtime-deleted?ids=' . $data['ids'], 'data-action' => 'modal']
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
    })->setPermissions(['overtime']);

    //Thêm overtime
    $app->router("/overtime-add", 'GET', function($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Thêm Tăng ca");
        $vars['nv1'] = array_map(function($employee) {
            return implode(' - ', $employee);
        }, $app->select("employee", ["name"]));

        echo $app->render('templates/employee/overtime-post.html', $vars, 'global');
    })->setPermissions(['overtime.add']);
    
    $app->router("/overtime-add", 'POST', function($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
    
        // Lấy dữ liệu từ form và kiểm tra XSS
        $ids = $app->count("overtime") + 1;
        $employee = isset($_POST['employee']) ? $app->xss($_POST['employee']) : '';
        $type = isset($_POST['type']) ? $app->xss($_POST['type']) : '';
        $money = isset($_POST['money']) ? $app->xss($_POST['money']) : '';
        $dayStart = isset($_POST['dayStart']) ? $app->xss($_POST['dayStart']) : '';
        $dayEnd = isset($_POST['dayEnd']) ? $app->xss($_POST['dayEnd']) : '';
        $note = isset($_POST['note']) ? $app->xss($_POST['note']) : '';
        $statu = "Pending";
        date_default_timezone_set('Asia/Ho_Chi_Minh'); // Set timezone to Vietnam
        $day = date('Y-m-d H:i:s');
        
        // Kiểm tra dữ liệu đầu vào
        if (empty($ids) || empty($employee) || empty($type) || empty($money) || empty($dayStart) || empty($dayEnd)) {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
            return;
        }
        // Kiểm tra ngày bắt đầu không lớn hơn ngày kết thúc
        if (strtotime($dayStart) > strtotime($dayEnd)) {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Ngày bắt đầu không được sau ngày kết thúc")]);
            return;
        }
        try {
            // Dữ liệu để lưu vào database
            $insert = [
                "ids" => $app->xss($_POST['ids'] ?? ''),
                "employee" => $app->xss($_POST['employee'] ?? ''),
                "type" => $app->xss($_POST['type'] ?? ''),
                "money" => $app->xss($_POST['money'] ?? ''),
                "dayStart" => $app->xss($_POST['dayStart'] ?? ''),
                "dayEnd" => $app->xss($_POST['dayEnd'] ?? ''),
                "note" => $note,
                "statu" => $statu,
                "day" => $day
            ];
              
            // Ghi log
            $jatbi->logs('overtime', 'overtime-add', $insert);
   
            // Thêm dữ liệu vào database
            $app->insert("overtime", $insert);

            echo json_encode(["status" => "success", "content" => $jatbi->lang("Cập nhật thành công")]);
    
        } catch (Exception $e) {
            // Xử lý lỗi ngoại lệ
            echo json_encode(["status" => "error", "content" => "Lỗi: " . $e->getMessage()]);
        }
    })->setPermissions(['overtime.add']);

    //Xóa overtime
    $app->router("/overtime-deleted", 'GET', function($vars) use ($app, $jatbi) {
        $vars['title'] = $jatbi->lang("Xóa Khung thời gian");
        echo $app->render('templates/common/deleted.html', $vars, 'global');
    })->setPermissions(['overtime.deleted']);
    
    $app->router("/overtime-deleted", 'POST', function($vars) use ($app, $jatbi) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        if (!empty($_GET['ids'])) {
            $ids = $app->xss($_GET['ids']);
        } elseif (!empty($_GET['box'])) {
            $ids = $app->xss($_GET['box']);
        }
        
        try {              
            // Xóa dữ liệu trong database
            if (is_string($ids)) {
                $ids = explode(',', $ids); // Split by comma
                foreach ($ids as $number) {
                    $app->delete("overtime", ["ids" => trim($number)]); // Trim to remove extra spaces
                }
            } else {
                $app->delete("overtime", ["ids" => $ids]);
            }
            echo json_encode(["status" => "success", "content" => $jatbi->lang("Cập nhật thành công")]);
        } catch (Exception $e) {
            // Xử lý lỗi ngoại lệ
            echo json_encode(["status" => "error", "content" => "Lỗi: " . $e->getMessage()]);
        }
    })->setPermissions(['overtime.deleted']);

    //Sửa overtime
    $app->router("/overtime-edit", 'GET', function($vars) use ($app, $jatbi) {
        $vars['title'] = $jatbi->lang("Sửa Overtime");
        $vars['nv1'] = array_map(function($employee) {
            return implode(' - ', $employee);
        }, $app->select("employee", ["name"]));
        $ids = isset($_GET['ids']) ? $app->xss($_GET['ids']) : null;
        if (!$ids) {
            echo $app->render('templates/common/error-modal.html', $vars, 'global');
            return;
        }
        $vars['data'] = $app->get("overtime", "*", ["ids" => $ids]);
        $vars ['data']['edit'] = true;
        if ($vars['data']) {
            echo $app->render('templates/employee/overtime-post.html', $vars, 'global');
        } else {
            echo $app->render('templates/common/error-modal.html', $vars, 'global');
        }
    })->setPermissions(['overtime.edit']);

    $app->router("/overtime-edit", 'POST', function($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);

        // Lấy mã nhân viên từ request
        $ids = isset($_POST['ids']) ? $app->xss($_POST['ids']) : null;
        if (!$ids) {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Mã tăng ca không hợp lệ")]);
            return;
        }
    
        // Lấy thông tin nhân viên từ DB
        $data = $app->get("overtime", "*", ["ids" => $ids]);
        if (!$data) {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy Tăng ca")]);
            return;
        }
    
        // Kiểm tra dữ liệu đầu vào
        $employee = isset($_POST['employee']) ? $app->xss($_POST['employee']) : '';
        $type = isset($_POST['type']) ? $app->xss($_POST['type']) : '';
        $money = isset($_POST['money']) ? $app->xss($_POST['money']) : '';
        $dayStart = isset($_POST['dayStart']) ? $app->xss($_POST['dayStart']) : '';
        $dayEnd = isset($_POST['dayEnd']) ? $app->xss($_POST['dayEnd']) : '';
        $note = isset($_POST['note']) ? $app->xss($_POST['note']) : '';
        date_default_timezone_set('Asia/Ho_Chi_Minh'); // Set timezone to Vietnam
        $day = date('Y-m-d H:i:s');
    
        if ($employee === '' || $type === '' || $money === '' || $dayStart === '' || $dayEnd === '') {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống") . ": " . $type . ", " . $employee . ", " . $money . ", " . $dayStart . ", " . $dayEnd]);    
            return;
        }
        // Kiểm tra ngày bắt đầu không lớn hơn ngày kết thúc
        if (strtotime($dayStart) > strtotime($dayEnd)) {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Ngày bắt đầu không sau ngày kết thúc")]);
            return;
        }
    
        // Cập nhật dữ liệu trong database
        $update = [
            "employee"  => $employee,
            "type"    => $type,
            "money" => $money,
            "dayStart"  => $dayStart,
            "dayEnd"    => $dayEnd,
            "note" => $note,
            "day"  => $day,
        ];
    
        $app->update("overtime", $update, ["ids" => $ids]);
    
        // Ghi log cập nhật
        $jatbi->logs('overtime', 'overtime-edit', $update);
    
        echo json_encode(["status" => "success", "content" => $jatbi->lang("Cập nhật tăng ca thành công")]);

    })->setPermissions(['overtime.edit']);

    //Cấp phép overtime
    $app->router("/overtime-approved", 'GET', function($vars) use ($app, $jatbi) {
        if (isset($_GET['ids']) && isset($_GET['statu']) && $_GET['statu'] == "Pending") {
            $update = [
                "statu"  => "Approved"
            ];      
            $app->update("overtime", $update, ["ids" => $_GET['ids']]);
            echo $app->render('templates/common/restore.html', $vars, 'global');
        }

    })->setPermissions(['overtime.approved']);

    $app->router("/overtime-approved", 'POST', function($vars) use ($app, $jatbi) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        echo json_encode(["status" => "success", "content" => $jatbi->lang("Cấp phép thành công")]);
    })->setPermissions(['overtime.deleted']);
?>
