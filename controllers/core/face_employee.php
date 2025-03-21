<?php
    if (!defined('ECLO')) die("Hacking attempt");
    $jatbi = new Jatbi($app);
    $setting = $app->getValueData('setting');
    
    $app->router("/manager/face_employee", 'GET', function($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Khuôn mặt"); // Đồng bộ với $requests
        $vars['add'] = '/manager/face_employee-add';
        $vars['deleted'] = '/manager/face_employee-deleted';

        // Lấy dữ liệu từ bảng face_employee, có thể join với employee để lấy name và type
        $data = $app->select("face_employee", ["employee_sn", "img_base64", "easy"], [
            "ORDER" => ["employee_sn" => "ASC"]
        ]);
        // Nếu cần join với employee
        if (!empty($data)) {
            $snList = array_column($data, 'employee_sn');
            $employeeData = $app->select("employee", ["sn", "name", "type"], [
                "sn" => $snList
            ]);
            $employeeMap = [];
            foreach ($employeeData as $emp) {
                $employeeMap[$emp['sn']] = $emp;
            }
            foreach ($data as &$row) {
                $row['name'] = $employeeMap[$row['employee_sn']]['name'] ?? 'N/A';
                $row['type'] = $employeeMap[$row['employee_sn']]['type'] ?? 'N/A';
            }
        }
        $vars['data'] = $data;

        echo $app->render('templates/employee/face-employee.html', $vars);
    })->setPermissions(['face_employee']);

    $app->router("/manager/face_employee", 'POST', function($vars) use ($app, $jatbi) {
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
        $orderColumnIndex = $_POST['order'][0]['column'] ?? 0; // Mặc định cột employee_sn
        $orderDir = strtoupper($_POST['order'][0]['dir'] ?? 'DESC');

        // Danh sách cột hợp lệ
        $validColumns = [
            1 => "employee_sn", // Chỉ số 1 trong <thead>
            2 => "name",        // Chỉ số 2 trong <thead>
            3 => "type",        // Chỉ số 3 trong <thead>
            4 => "img_base64",  // Chỉ số 4 trong <thead>
            5 => "easy"         // Chỉ số 5 trong <thead>
        ];
        $orderColumn = $validColumns[$orderColumnIndex] ?? "employee_sn";

        // Điều kiện lọc dữ liệu
        $where = [
            "AND" => [
                "OR" => [
                    "face_employee.employee_sn[~]" => $searchValue,
                    "employee.name[~]" => $searchValue, 
                ]
            ],
            "LIMIT" => [$start, $length],
            "ORDER" => [$orderColumn => $orderDir]
        ];

        if (!empty($type)) {
            $where["AND"]["employee.type"] = $type;
        }

        // Join với employee để lấy name và type
        $datas = $app->select("face_employee", [
            "[>]employee" => ["employee_sn" => "sn"]
        ], [
            "face_employee.employee_sn",
            "employee.name",
            "employee.type",
            "face_employee.img_base64",
            "face_employee.easy"
        ], $where) ?? [];

        // Đếm số bản ghi
        // $count = $app->count("face_employee", ["AND" => $where["AND"]]);
        $count = $app->count("face_employee");

        // Log dữ liệu truy vấn để kiểm tra
        error_log("Fetched Face Employees Data: " . print_r($datas, true));

        // Xử lý dữ liệu đầu ra
        $formattedData = array_map(function($data) use ($app, $jatbi) {
            // Chuyển đổi giá trị type thành văn bản
            $typeLabels = [
                "1" => $jatbi->lang("Nhân viên nội bộ"),
                "2" => $jatbi->lang("Khách"),
                "3" => $jatbi->lang("Danh sách đen"),
            ];
            return [
                "checkbox" => "<input class= 'form-check-input checker' type='checkbox' value='{$data['employee_sn']}'>",
                "employee_sn" => $data['employee_sn'],
                "name" => $data['name'] ?? 'N/A',
                "type" => $typeLabels[$data['type']] ?? $jatbi->lang("Không xác định"), // Hiển thị nhãn văn bản
                "img_base64" => "<img src='data:image/jpeg;base64,{$data['img_base64']}' style='max-width: 100px;'>", // Hiển thị ảnh
                "easy" => $data['easy'] == '1' ? $jatbi->lang("Chất lượng cao") : $jatbi->lang("Chất lượng thấp"),
                "action" => $app->component("action", [
                    "button" => [
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Xem ảnh"),
                            'permission' => ['face_employee'],
                            'action' => ['data-url' => '/manager/face-viewimage?box='.$data['employee_sn'], 'data-action' => 'modal']
                        ],
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Sửa"),
                            'permission' => ['face_employee.edit'],
                            'action' => ['data-url' => '/manager/face_employee-edit?id='.$data['employee_sn'], 'data-action' => 'modal']
                        ],
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Xóa"),
                            'permission' => ['face_employee.deleted'],
                            'action' => ['data-url' => '/manager/face_employee-deleted?id='.$data['employee_sn'], 'data-action' => 'modal']
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
    })->setPermissions(['face_employee']);



    $app->router("/manager/face_employee-add", 'GET', function($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Thêm khuôn mặt nhân viên");
        $sn = isset($_GET['id']) ? $app->xss($_GET['id']) : '';
        $vars['data'] = [
            "employee_sn" => $sn,
            "img_base64" => '',
            "easy" => '1', // Mặc định là chất lượng cao
        ];
        
        echo $app->render('templates/employee/face_employee-post.html', $vars, 'global');
    })->setPermissions(['face_employee.add']);


    $app->router("/manager/face_employee-add", 'POST', function($vars) use ($app, $jatbi, $setting) {
        $app->header(['Content-Type' => 'application/json']);

        // Lấy dữ liệu từ form
        $employee_sn = $app->xss($_POST['employee_sn'] ?? '');
        $easy = $app->xss($_POST['easy'] ?? '');
        $img_file = $_FILES['img_file'] ?? null;

        // Kiểm tra dữ liệu đầu vào
        if (empty($employee_sn) || !isset($_POST['easy']) || !in_array($easy, ['0', '1']) || empty($img_file)) {
            echo json_encode(["status" => "error", "content" => "Vui lòng không để trống"]);
            return;
        }

        // Kiểm tra lỗi upload file
        if ($img_file['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(["status" => "error", "content" => "Lỗi khi upload file"]);
            return;
        }
        if (strlen($employee_sn) > 300) {
            echo json_encode(["status" => "error", "content" => "Mã nhân viên không được vượt quá 300 ký tự"]);
            return;
        }

        // Chuyển file thành chuỗi Base64
        $img_content = file_get_contents($img_file['tmp_name']);
        $img_base64 = base64_encode($img_content);

        try {

            // Dữ liệu gửi qua API
            $apiData = [
                'deviceKey' => '77ed8738f236e8df86',
                'secret'    => '123456',
                'personSn'        => $employee_sn,
                'imgBase64' => $img_base64, // Chuỗi Base64
                'easy'      => $easy,
            ];

            $headers = [
                'Authorization: Bearer your_token',
                'Content-Type: application/x-www-form-urlencoded'
            ];

            // Gửi yêu cầu đến API
            $response = $app->apiPost(
                'http://camera.ellm.io:8190/api/face/merge',
                $apiData,
                $headers
            );

            // Kiểm tra phản hồi từ API
            $apiResponse = json_decode($response, true);
            if (!empty($apiResponse['success']) && $apiResponse['success'] === true) {
                if($app->count("face_employee", ["employee_sn" => $employee_sn])){
                    $update = [
                        "img_base64" => $img_base64,
                        "easy" => $easy,
                    ];
                    $app->update("face_employee", $update, ["employee_sn" => $employee_sn]);
                }else{
                    // Dữ liệu để lưu vào database
                    $insert = [
                        "employee_sn" => $employee_sn,
                        "img_base64"  => $img_base64,
                        "easy"        => $easy,
                    ];
                    $app->insert("face_employee", $insert);
                }
                
                echo json_encode(["status" => "success", "content" => "Thêm thành công"]);
            } else {
                $errorMessage = $apiResponse['msg'] ?? "Không rõ lỗi";
                echo json_encode(["status" => "error", "content" => "Lưu thất bại, Lỗi: " . $errorMessage]);
            }

        } catch (Exception $e) {
            echo json_encode(["status" => "error", "content" => "Lỗi: " . $e->getMessage()]);
        }
    })->setPermissions(['face_employee.add']);

    $app->router("/manager/face_employee-edit", 'GET', function($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Sửa Khuôn Mặt Nhân Viên");
    
        $sn = isset($_GET['id']) ? $app->xss($_GET['id']) : null;
        if (!$sn) {
            echo $app->render('templates/common/error-modal.html', $vars, 'global');
            return;
        }
    
        $vars['data'] = $app->get("face_employee", "*", ["employee_sn" => $sn]);
        $vars ['data']['edit'] = true;
        if ($vars['data']) {
            echo $app->render('templates/employee/face_employee-post.html', $vars, 'global');
        } else {
            echo $app->render('templates/common/error-modal.html', $vars, 'global');
        }
    })->setPermissions(['face_employee.edit']);


    $app->router("/manager/face_employee-edit", 'POST', function($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);

        // Lấy dữ liệu từ form
        $employee_sn = $app->xss($_POST['employee_sn'] ?? '');
        $easy = $app->xss($_POST['easy'] ?? '0');
        $img_file = $_FILES['img_file'] ?? null;

        // Kiểm tra dữ liệu đầu vào
        if (empty($employee_sn) || !isset($_POST['easy']) || !in_array($easy, ['0', '1']) ) {
            echo json_encode(["status" => "error", "content" => "Vui lòng không để trống"]);
            return;
        }

        // Lấy dữ liệu hiện tại (chỉ cần trong chế độ chỉnh sửa)
        $currentData = [];
        if (!empty($employee_sn)) {
            $currentData = $app->select("face_employee", ["img_base64"], ["employee_sn" => $employee_sn])[0] ?? [];
        }
        $img_base64 = $currentData['img_base64'] ?? '';
        
        // Nếu có file mới, chuyển thành Base64
        if ($img_file && $img_file['error'] === UPLOAD_ERR_OK) {
            $img_content = file_get_contents($img_file['tmp_name']);
            $img_base64 = base64_encode($img_content);
        }

        try {

            // Dữ liệu gửi qua API
            $apiData = [
                'deviceKey' => '77ed8738f236e8df86',
                'secret'    => '123456',
                'personSn'        => $employee_sn,
                'imgBase64' => $img_base64, // Chuỗi Base64
                'easy'      => $easy,
            ];

            $headers = [
                'Authorization: Bearer your_token',
                'Content-Type: application/x-www-form-urlencoded'
            ];

            // Gửi yêu cầu đến API
            $response = $app->apiPost(
                'http://camera.ellm.io:8190/api/face/merge',
                $apiData,
                $headers
            );

            // Kiểm tra phản hồi từ API
            $apiResponse = json_decode($response, true);
            if (!empty($apiResponse['success']) && $apiResponse['success'] === true) {
                $update = [
                    "img_base64" => $img_base64,
                    "easy" => $easy,
                ];
                $app->update("face_employee", $update, ["employee_sn" => $employee_sn]);
                echo json_encode(["status" => "success", "content" => "Sửa thành công"]);
            } else {
                $errorMessage = $apiResponse['msg'] ?? "Không rõ lỗi";
                echo json_encode(["status" => "error", "content" => "Sửa thất bại! Lỗi: " . $errorMessage]);
            }

        } catch (Exception $e) {
            echo json_encode(["status" => "error", "content" => "Lỗi: " . $e->getMessage()]);
        }
    })->setPermissions(['face_employee.edit']);

    $app->router("/manager/face_employee-deleted", 'GET', function($vars) use ($app, $jatbi) {
        $vars['title'] = $jatbi->lang("Xóa Khuôn Mặt Nhân Viên");

        echo $app->render('templates/common/deleted.html', $vars, 'global');
    })->setPermissions(['face_employee.deleted']);
    
    $app->router("/manager/face_employee-deleted", 'POST', function($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        $idString = $_GET['id'] ?? '';
        $employeeSnList = explode(",", $idString);
    
        try {
            if (empty($employeeSnList) || $idString === '') {
                echo json_encode(["status" => "error", "content" => "Vui lòng chọn ít nhất một nhân viên để xóa!"]);
                return;
            }
    
            $successCount = 0;
            $errorMessages = [];
    
            foreach ($employeeSnList as $sn) {
                $sn = trim($app->xss($sn)); // Loại bỏ khoảng trắng và bảo mật chống XSS
                if (empty($sn)) continue; // Bỏ qua nếu ID rỗng
    
                $headers = [
                    'Authorization: Bearer your_token',
                    'Content-Type: application/x-www-form-urlencoded'
                ];
    
                $apiData = [
                    'deviceKey' => '77ed8738f236e8df86',
                    'secret' => '123456',
                    'personSn' => $sn,
                ];
    
                $response = $app->apiPost('http://camera.ellm.io:8190/api/face/delete', $apiData, $headers);
                $apiResponse = json_decode($response, true);
    
                if (!empty($apiResponse['success']) && $apiResponse['success'] === true) {
                    $app->delete("face_employee", ["employee_sn" => $sn]);
                    $successCount++;
                } else {
                    $errorMessages[] = $apiResponse['msg'] ?? "Lỗi không xác định cho ID: $sn";
                }
            }
    
            if ($successCount > 0) {
                $message = $successCount === count($employeeSnList)
                    ? $jatbi->lang("Xóa thành công tất cả các bản ghi!")
                    : $jatbi->lang("Đã xóa $successCount bản ghi, nhưng có lỗi: ") . implode(", ", $errorMessages);
                echo json_encode(["status" => "success", "content" => $message]);
            } else {
                echo json_encode(["status" => "error", "content" => "Không xóa được bản ghi nào! Lỗi: " . implode(", ", $errorMessages)]);
            }
        } catch (Exception $e) {
            echo json_encode(["status" => "error", "content" => "Lỗi hệ thống: " . $e->getMessage()]);
        }
    })->setPermissions(['face_employee.deleted']);


    $app->router("/manager/reload-api", 'GET', function($vars) use ($app, $jatbi) {
        $vars['title'] = $jatbi->lang("Đồng bộ nhân viên");

        echo $app->render('templates/common/reload-api.html', $vars, 'global');
    })->setPermissions(['face_employee']);

    $app->router("/manager/reload-api", 'POST', function($vars) use ($app, $jatbi) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        try {
            // Bước 1: Xóa toàn bộ dữ liệu trong bảng face_employee
            $app->delete("face_employee", []); // Không truyền điều kiện để xóa toàn bộ
    
            // Bước 2: Chuẩn bị dữ liệu gửi đi cho API findList
            $apiData = [
                'deviceKey' => '77ed8738f236e8df86',
                'secret' => '123456',
                'index' => 1,
                'length' => 1000
            ];
    
            $headers = [
                'Content-Type: application/x-www-form-urlencoded'
                // Nếu API yêu cầu token, thêm header Authorization
                // 'Authorization: Bearer your_token'
            ];
    
            // Gửi yêu cầu POST đến API findList để lấy danh sách nhân viên
            $response = $app->apiPost(
                'http://camera.ellm.io:8190/api/person/findList',
                $apiData,
                $headers
            );
    
            // Kiểm tra phản hồi từ API findList
            $apiResponse = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo json_encode(["status" => "error", "content" => "Đồng bộ thất bại! Lỗi: Dữ liệu JSON không hợp lệ từ API findList."]);
                return;
            }
    
            if (empty($apiResponse)) {
                echo json_encode(["status" => "error", "content" => "Đồng bộ thất bại! Lỗi: Không có dữ liệu từ API findList."]);
                return;
            }
    
            // Đếm số bản ghi thành công và lỗi (chỉ dùng nội bộ, không hiển thị)
            $successCount = 0;
            $errorCount = 0;
    
            // Kiểm tra nếu API trả về dữ liệu dạng {"data": [...]} thì lấy mảng "data"
            $data = isset($apiResponse['data']) ? $apiResponse['data'] : $apiResponse;
    
            // Bước 3: Duyệt dữ liệu từ API và chỉ thêm mới vào bảng face_employee
            foreach ($data as $item) {
                try {
    
                    // Chuẩn bị dữ liệu gửi đi cho API face/find để lấy img_base64 và easy
                    $faceApiData = [
                        'deviceKey' => '77ed8738f236e8df86',
                        'secret' => '123456',
                        'personSn' => $item['sn']
                    ];
    
                    // Gửi yêu cầu POST đến API face/find
                    $faceResponse = $app->apiPost(
                        'http://camera.ellm.io:8190/api/face/find',
                        $faceApiData,
                        $headers
                    );
                    
                    //check api
                    $apifaceResponse = json_decode($faceResponse, true);
                    // Kiểm tra img_base64 và easy trong phản hồi
                    if (!empty($apifaceResponse['success']) && $apifaceResponse['success'] === true) {
                        // Kiểm tra nếu API trả về dữ liệu dạng {"data": {...}} thì lấy mảng "data"
                        $faceData = isset($apifaceResponse['data']) ? $apifaceResponse['data'] : $apifaceResponse;

                        // Thêm mới vào bảng face_employee
                        $app->insert("face_employee", [
                            "employee_sn" => $item['sn'],
                            "img_base64" => $faceData['imgBase64']
                        ]);

                        $successCount++;
                    }

                    
                    
                } catch (Exception $e) {
                    $errorCount++;
                    // Tiếp tục xử lý các bản ghi khác, không trả về ngay
                }
            }
    
            // Bước 4: Xác định thành công hoặc thất bại
            if ($errorCount === 0) {
                echo json_encode(["status" => "success", "content" => "Đồng bộ thành công"]);
            } else {
                echo json_encode(["status" => "error", "content" => "Đồng bộ thất bại! Lỗi: Có $errorCount bản ghi không xử lý được."]);
            
            }
    
        } catch (Exception $e) {
            echo json_encode(["status" => "error", "content" => "Đồng bộ thất bại! Lỗi: " . $e->getMessage()]);
        }
    })->setPermissions(['face_employee']);

    $app->router("/manager/face-viewimage", 'GET', function($vars) use ($app, $jatbi) {
        $vars['title'] = $jatbi->lang("Xem ảnh hồ sơ");
    
        // Lấy ID từ query string
        $recordId = $app->xss($_GET['box'] ?? '');
        if (empty($recordId)) {
            echo json_encode(['status'=>'error',"content"=>$jatbi->lang("Không tìm thấy ID hồ sơ.")]);
            return;
        }
    
    
        // Lấy thông tin record từ cơ sở dữ liệu
        $face_employee = $app->select("face_employee", ["employee_sn", "img_base64", "easy"], ["employee_sn" => $recordId]);
        $employee = $app->select("employee", ["sn"], ["sn" => $recordId]);


        $vars['image'] = $face_employee[0]['img_base64'];
        $vars['sn'] = $employee[0]['sn'];

        // Render template HTML (không cần header JSON)
        echo $app->render('templates/common/view-image-post.html', $vars, 'global');
    })->setPermissions(['face_employee']);
?>
