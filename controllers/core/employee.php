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
        $app->header(['Content-Type' => 'application/json']);
    
        // Lấy danh sách nhóm từ bảng group-access
        $groups = $app->select("group-access", ['acGroupNumber', 'name']);
        $groupMap = [];
        foreach ($groups as $group) {
            $groupMap[$group['acGroupNumber']] = $group['name']; // Lưu tên nhóm theo mã
        }
    
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
        $validColumns = ["checkbox", "sn", "name", "type", "acGroupNumber"];
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
        $datas = $app->select("employee", ['sn', 'name', 'type', 'acGroupNumber'], $where) ?? [];
    
        // Xử lý dữ liệu đầu ra
        $formattedData = array_map(function($data) use ($app, $jatbi, $groupMap) {
            // Chuyển đổi giá trị type thành văn bản
            $typeLabels = [
                "1" => $jatbi->lang("Nhân viên nội bộ"),
                "2" => $jatbi->lang("Khách"),
                "3" => $jatbi->lang("Danh sách đen"),
            ];
    
            // Lấy tên nhóm từ bảng group-access (nếu có)
            $groupName = $groupMap[$data['acGroupNumber']] ?? $jatbi->lang("Không xác định");
    
            return [
                "checkbox" => $app->component("box", ["data" => $data['sn']]),
                "sn" => $data['sn'],
                "name" => $data['name'],
                "type" => $typeLabels[$data['type']] ?? $jatbi->lang("Không xác định"),
                "acGroupNumber" => $groupName,
                "action" => $app->component("action", [
                    "button" => [
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Xem ảnh"),
                            'permission' => ['face_employee'],
                            'action' => ['data-url' => '/manager/face-viewimage?box='.$data['sn'], 'data-action' => 'modal']
                        ],
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Xem chấm công"), // Nút mới
                            'permission' => [''], // Quyền mới, cần định nghĩa trong hệ thống
                            'action' => ['data-url' => '/manager/timekeeping-view?box='.$data['sn'], 'data-action' => 'modal']
                        ],
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Sửa"),
                            'permission' => ['employee.edit'],
                            'action' => ['data-url' => '/manager/employee-edit?id=' . $data['sn'], 'data-action' => 'modal']
                        ],
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Xóa"),
                            'permission' => ['employee.deleted'],
                            'action' => ['data-url' => '/manager/employee-deleted?id=' . $data['sn'], 'data-action' => 'modal']
                        ],
                    ]
                ]),
            ];
        }, $datas);
    
        // Trả về dữ liệu JSON
        echo json_encode([
            "draw" => $draw,
            "recordsTotal" => $count,
            "recordsFiltered" => $count,
            "data" => $formattedData
        ]);
    })->setPermissions(['employee']);
    
    
    $app->router("/manager/employee-add", 'GET', function($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Nhân viên");
        echo $app->render('templates/employee/employee-post.html', $vars,'global');
    })->setPermissions(['employee.add']);
    

    $app->router("/manager/employee-add", 'POST', function($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
    
        // Lấy dữ liệu từ form và kiểm tra XSS
        $sn            = $app->xss($_POST['sn'] ?? '');
        $name          = $app->xss($_POST['name'] ?? '');
        $type          = (int) ($app->xss($_POST['type'] ?? ''));
        $acGroupNumber = (int) $app->xss($_POST['acGroupNumber'] ?? '');    
    
        // Kiểm tra dữ liệu đầu vào
        if (empty($sn) || empty($name) || empty($type)) {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
            return;
        }
    
        try {
            // Dữ liệu để lưu vào database
            $insert = [
                "sn"            => $sn,
                "name"          => $name,
                "type"          => $type,
                "acGroupNumber" => $acGroupNumber,
            ];
            
            // Thêm dữ liệu vào database
            $app->insert("employee", $insert);
    
            // Ghi log
            $jatbi->logs('employee', 'employee-add', $insert);
    
            // Dữ liệu gửi lên API
            $apiData = [
                'deviceKey'     => '77ed8738f236e8df86',
                'secret'        => '123456',
                'sn'            => $sn,
                'name'          => $name,
                'type'          => $type,
                'acGroupNumber' => $acGroupNumber,
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
    
    $app->router("/manager/employee-deleted", 'POST', function($vars) use ($app, $jatbi) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
    
        // Kiểm tra xem có 'id' hay 'box' trong request không
        $snList = [];
    
        if (!empty($_GET['id'])) {
            $snList[] = $app->xss($_GET['id']);
        } elseif (!empty($_GET['box'])) {
            $snList = array_map('trim', explode(',', $app->xss($_GET['box'])));
        }
    
        if (empty($snList)) {
            echo json_encode(["status" => "error", "content" => "Thiếu ID nhân viên để xóa"]);
            return;
        }
    
        try {
            $headers = [
                'Authorization: Bearer your_token',
                'Content-Type: application/x-www-form-urlencoded'
            ];
    
            $deletedCount = 0;
            $errors = [];
    
            foreach ($snList as $sn) {
                if (empty($sn)) continue; // Bỏ qua nếu có giá trị rỗng
    
                // Xóa khỏi database
                $app->delete("employee", ["sn" => $sn]);
    
                // Gửi yêu cầu xóa từ API
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
    
                if (!empty($apiResponse['success']) && $apiResponse['success'] === true) {
                    $deletedCount++;
                } else {
                    $errorMessage = $apiResponse['msg'] ?? "Không rõ lỗi";
                    $errors[] = "SN $sn: " . $errorMessage;
                }
            }
    
            if (!empty($errors)) {
                echo json_encode([
                    "status" => "error",
                    "content" => "Một số nhân viên xóa thất bại",
                    "errors" => $errors
                ]);
            } else {
                echo json_encode([
                    "status" => "success",
                    "content" => "Đã xóa thành công $deletedCount nhân viên"
                ]);
            }
        } catch (Exception $e) {
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
        $name          = isset($_POST['name']) ? $app->xss($_POST['name']) : '';
        $type          = isset($_POST['type']) ? $app->xss($_POST['type']) : '';
        $acGroupNumber = isset($_POST['acGroupNumber']) ? $app->xss($_POST['acGroupNumber']) : '';
    
        if ($name === '' || $type === '' || $acGroupNumber === '') {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
            return;
        }
    
        // Cập nhật dữ liệu trong database
        $update = [
            "name"          => $name,
            "type"          => $type,
            "acGroupNumber" => $acGroupNumber,
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
            'deviceKey'     => '77ed8738f236e8df86',
            'secret'        => '123456',
            'sn'            => $sn,
            'name'          => $name,
            'type'          => $type,
            'acGroupNumber' => $acGroupNumber,
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
    
    
     
    $app->router("/manager/employee-edit", 'POST', function($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
    
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
    
        // Nhận dữ liệu từ request
        $name = isset($_POST['name']) ? $app->xss($_POST['name']) : '';
        $type = isset($_POST['type']) ? $app->xss($_POST['type']) : '';
        $acGroupNumber = isset($_POST['acGroupNumber']) ? $app->xss($_POST['acGroupNumber']) : '';
    
        if ($name === '' || $type === '' || $acGroupNumber === '') {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
            return;
        }
    
        // Cập nhật dữ liệu trong database
        $update = [
            "name" => $name,
            "type" => $type,
            "acGroupNumber" => $acGroupNumber
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
            'acGroupNumber' => $acGroupNumber 
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
    

    //Động bộ hóa API
    $app->router("/manager/employee-reload", 'GET', function($vars) use ($app, $jatbi) {
        $vars['title'] = $jatbi->lang("Đồng bộ nhân viên");

        echo $app->render('templates/common/reloadd.html', $vars, 'global');
    })->setPermissions(['employee']);

    $app->router("/manager/employee-reload", 'POST', function($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
    
        // Tham số truyền vào API
        $params = [
            "deviceKey" => "77ed8738f236e8df86",
            "secret" => "123456",
            "index" => 1,
            "length" => 20
        ];
    
        $apiUrl = "http://camera.ellm.io:8190/api/person/findList";
        $headers = [
            'Authorization: Bearer your_token',
            'Content-Type: application/x-www-form-urlencoded'
        ];
    
        // Gọi API lấy danh sách nhân viên
        $response = $app->apiPost($apiUrl, $params, $headers);
        $employeesFromAPI = json_decode($response, true);
    
        if (!$employeesFromAPI || empty($employeesFromAPI['data'])) {
            echo json_encode(["status" => "error", "content" => "Không lấy được dữ liệu từ API"]);
            return;
        }
    
        $employeesFromAPI = $employeesFromAPI['data'];
    
        // Lấy danh sách nhân viên hiện có trong database (thêm cột acGroupNumber)
        $employeesFromDB = $app->select("employee", ["sn", "name", "type", "acGroupNumber"]);
        $dbEmployeeMap = [];
        foreach ($employeesFromDB as $employee) {
            $dbEmployeeMap[$employee['sn']] = $employee;
        }
    
        $added = 0;
        $updated = 0;
        $skipped = 0;
    
        $insertData = [];
        $updateData = [];
    
        foreach ($employeesFromAPI as $employee) {
            $sn = $employee['sn'];
            $name = $employee['name'];
            $type = $employee['type'];
            $acGroupNumber = $employee['acGroupNumber'] ?? 0;
            if (isset($dbEmployeeMap[$sn])) {
                // Nếu đã tồn tại, kiểm tra xem có thay đổi không
                if ($dbEmployeeMap[$sn]['name'] !== $name || 
                    $dbEmployeeMap[$sn]['type'] != $type || 
                    $dbEmployeeMap[$sn]['acGroupNumber'] != $acGroupNumber) {
                    
                    $updateData[] = [
                        "sn" => $sn,
                        "name" => $name,
                        "type" => $type,
                        "acGroupNumber" => $acGroupNumber
                    ];
                    $updated++;
                } else {
                    $skipped++;
                }
            } else {
                // Nếu chưa có, thêm mới vào database
                $insertData[] = [
                    "sn" => $sn,
                    "name" => $name,
                    "type" => $type,
                    "acGroupNumber" => $acGroupNumber
                ];
                $added++;
            }
        }
    
        // Cập nhật dữ liệu nếu có thay đổi
        foreach ($updateData as $update) {
            $app->update("employee", [
                "name" => $update['name'],
                "type" => $update['type'],
                "acGroupNumber" => $update['acGroupNumber']
            ], ["sn" => $update['sn']]);
        }
    
        // Chèn dữ liệu mới nếu có
        if (!empty($insertData)) {
            $app->insert("employee", $insertData);
        }
    
        echo json_encode(["status" => "success", "content" => "Đồng bộ thành công",
            "added" => $added,
            "updated" => $updated,
            "skipped" => $skipped
        ]);
    })->setPermissions(['employee']);
    
    $app->router("/manager/timekeeping-view", 'GET', function($vars) use ($app, $jatbi) {
        $vars['title'] = $jatbi->lang("Xem chấm công");

        // Lấy personSn từ query string
        $personSn = $app->xss($_GET['box'] ?? '');
        if (empty($personSn)) {
            $vars['error'] = $jatbi->lang("Không tìm thấy mã nhân viên");
            echo $app->render('templates/common/error-modal.html', $vars, 'global');
            return;
        }

        // Lấy thông tin nhân viên từ bảng face_employee (để hiển thị tên)
        $faceEmployee = $app->get("face_employee", ["employee_sn", "img_base64"], ["employee_sn" => $personSn]);
        if (!$faceEmployee) {
            $vars['error'] = $jatbi->lang("Nhân viên không tồn tại");
            echo $app->render('templates/common/error-modal.html', $vars, 'global');
            return;
        }

        // Lấy thông tin từ bảng employee để lấy tên (nếu cần)
        $employee = $app->get("employee", ["sn", "name"], ["sn" => $personSn]);
        $vars['employee_name'] = $employee['name'] ?? 'Không rõ';
        $vars['employee_sn'] = $personSn;

        // Lấy danh sách chấm công từ bảng record
        $timekeepingRecords = $app->select("record", [
            "id",
            "createTime"
        ], [
            "personSn" => $personSn,
            "ORDER" => ["createTime" => "DESC"]
        ]);

        if (empty($timekeepingRecords)) {
            $vars['error'] = $jatbi->lang("Không có dữ liệu chấm công");
        } else {
            // Nhóm dữ liệu theo ngày
            $timekeepingByDate = [];
            $datesWithRecords = [];

            foreach ($timekeepingRecords as $record) {
                // Tách createTime thành ngày và giờ
                $dateTime = explode(" ", $record['createTime']);
                $date = $dateTime[0]; // Ngày: 2025-03-19
                $time = $dateTime[1]; // Giờ: 14:43:31

                // Lưu danh sách ngày có chấm công
                $datesWithRecords[$date] = true;

                // Nhóm dữ liệu theo ngày để hiển thị chi tiết
                if (!isset($timekeepingByDate[$date])) {
                    $timekeepingByDate[$date] = [];
                }
                $timekeepingByDate[$date][] = [
                    'id' => $record['id'],
                    'time' => $time
                ];
            }

            $vars['dates_with_records'] = array_keys($datesWithRecords); // Danh sách ngày có chấm công
            $vars['timekeeping_by_date'] = $timekeepingByDate; // Dữ liệu chi tiết theo ngày
        }
    
        // Render template HTML
        echo $app->render('templates/common/view-record.html', $vars, 'global');
    })->setPermissions([]);
    

?>
