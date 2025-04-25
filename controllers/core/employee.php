<?php
if (!defined('ECLO')) die("Hacking attempt");
$jatbi = new Jatbi($app);
$setting = $app->getValueData('setting');

$app->router("/manager/employee", 'GET', function($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Nhân viên");
    $vars['add'] = '/manager/employee-add';
    $vars['deleted'] = '/manager/employee-deleted';
    echo $app->render('templates/employee/employee.html', $vars);
})->setPermissions(['employee']);

$app->router("/manager/employee", 'POST', function($vars) use ($app, $jatbi) {
    $app->header(['Content-Type' => 'application/json']);

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
    $validColumns = ["checkbox", "sn", "name", "type", "department"];
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
    $datas = $app->select("employee", [
        "[>]department" => ["departmentId" => "departmentId"]
    ], [
        "department.personName",
        "employee.sn",
        "employee.name",
        "employee.type",
        "employee.status",
    ], $where) ?? [];

    // Xử lý dữ liệu đầu ra
    $formattedData = array_map(function($data) use ($app, $jatbi) {
        // Chuyển đổi giá trị type thành văn bản
        $typeLabels = [
            "1" => $jatbi->lang("Nhân viên nội bộ"),
            "2" => $jatbi->lang("Khách"),
            "3" => $jatbi->lang("Danh sách đen"),
        ];

        return [
            "checkbox" => $app->component("box", ["data" => $data['sn']]),
            "sn" => $data['sn'],
            "name" => $data['name'],
            "type" => $typeLabels[$data['type']] ?? $jatbi->lang("Không xác định"),
            "department" => $data['personName'],
            "status" => $app->component("status", ["url" => "/employee-status/" . $data['sn'], "data" => $data['status'], "permission" => ['employee.edit']]),
            "action" => $app->component("action", [
                "button" => [
                    [
                        'type' => 'button',
                        'name' => $jatbi->lang("Xem ảnh"),
                        'permission' => ['face_employee'],
                        'action' => ['data-url' => '/manager/face-viewimage?box=' . $data['sn'], 'data-action' => 'modal']
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
                    [
                        'type' => 'button',
                        'name' => $jatbi->lang("Xem Thời Gian Ra Vào"),
                        'permission' => ['checkinout.edit'],
                        'action' => ['data-url' => '/manager/checkinout-edit?box=' . $data['sn'], 'data-action' => 'modal']
                    ]
                ] 
            ]),
            "view" => '<a href="/manager/employee-detail?box=' . $data['sn'] . '" title="' . $jatbi->lang("Xem Chi Tiết") . '"><i class="ti ti-eye"></i></a>',
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

$app->router("/manager/employee-detail", 'GET', function($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Chi tiết nhân viên");
    $vars['title_labor'] = $jatbi->lang("Hợp đồng lao động");
    $vars['title_isurance'] = $jatbi->lang("Bảo hiểm");


    // Lấy giá trị box (sn) từ query parameter
    $sn = $_GET['box'] ?? null;

    if (!$sn) {
        // Nếu không có sn, trả về thông báo lỗi
        $vars['error'] = $jatbi->lang("Không tìm thấy nhân viên");
        echo $app->render('templates/employee/employee-detail.html', $vars);
        return;
    }

    // Truy vấn thông tin nhân viên từ bảng employees

    $employee = $app->select("employee", [
        "sn",
        "name",
        "type",
        "acGroupNumber",
        "departmentId",
        "status"
    ], ["sn" => $sn])[0] ?? null;
    
    // Truy vấn khuôn mặt 
    $face_employee = $app->select("face_employee", [
        "img_base64"
    ], ["employee_sn" => $sn]);

    // Truy vấn hợp đồng lao động từ bảng contracts
    $contracts = $app->select("employee_contracts","*", ["person_sn" => $sn]);

    // Truy vấn thông tin bảo hiểm từ bảng insurances
    $insurances = $app->select("insurance","*", ["employee" => $sn]);

    // Debug dữ liệu (tạm thời, có thể xóa sau khi kiểm tra)
    
    // echo "<pre>";
    // var_dump("Employee:", $employee);
    // var_dump("Contracts:", $contracts);
    //var_dump("Insurances:", $insurances);
    // var_dump($face_employee);
    // echo "</pre>";
    //die();
    

    // Truyền dữ liệu vào template
    $vars['employee'] = $employee;
    $vars['contracts'] = $contracts;
    $vars['insurances'] = $insurances;
    $vars['face'] = $face_employee[0]['img_base64'] ?? '';

    

    // Render template
    echo $app->render('templates/employee/employee-detail.html', $vars);

})->setPermissions(['employee']);

$app->router("/employee-detail", 'POST', function($vars) use ($app, $jatbi) {
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
    $statu = $_POST['statu'] ?? '';

    // Fix lỗi ORDER cột
    $orderColumnIndex = $_POST['order'][0]['column'] ?? 1; // Mặc định cột 1
    $orderDir = strtoupper($_POST['order'][0]['dir'] ?? 'DESC');

    // Danh sách cột hợp lệ
    $validColumns = ["idbh", "employee", "money", "moneybhxh", "numberbhxh", "daybhxh", "placebhxh", "numberyte", "dayyte", "placeyte", "statu"];
    $orderColumn = $validColumns[$orderColumnIndex] ?? "type";

    // Điều kiện lọc dữ liệu
    $conditions = ["AND" => []];

    if (!empty($searchValue)) {
        $conditions["AND"]["OR"] = [
            "employee.name[~]" => $searchValue,
            "insurance.placebhxh[~]" => $searchValue,
            "insurance.placeyte[~]" => $searchValue,
            "insurance.numberyte[~]" => $searchValue,
            "insurance.numberbhxh[~]" => $searchValue,
        ];
    }

    if (!empty($statu)) {
        $conditions["AND"]["insurance.statu"] = $statu;
    }

    // Kiểm tra nếu conditions bị trống, tránh lỗi SQL
    if (empty($conditions["AND"])) {
        unset($conditions["AND"]);
    }

    // Đếm số bản ghi
    $count = $app->count("insurance", [
        "[>]employee" => ["employee" => "sn"]
    ], "insurance.idbh", $conditions);

    // Truy vấn danh sách Khung thời gian
    $datas = $app->select("insurance", [
        "[>]employee" => ["employee" => "sn"] 
    ], [
        'insurance.idbh',
        'employee.name(employee_name)', // Lấy tên nhân viên từ bảng employee
        'insurance.money',
        'insurance.moneybhxh',
        'insurance.numberbhxh',
        'insurance.daybhxh',
        'insurance.placebhxh',
        'insurance.numberyte',
        'insurance.dayyte',
        'insurance.placeyte',
        'insurance.statu',
        'insurance.note'
    ], array_merge($conditions, [
        "LIMIT" => [$start, $length],
        "ORDER" => [$orderColumn => $orderDir]
    ])) ?? [];

    // Log dữ liệu truy vấn để kiểm tra
    error_log("Fetched insurances Data: " . print_r($datas, true));

    // Xử lý dữ liệu đầu ra
    $formattedData = array_map(function($data) use ($app, $jatbi) {
        $moneylabel = number_format($data['money'], 0, '.', ',');
        $moneylabel2 = number_format($data['moneybhxh'], 0, '.', ',');

        return [
            "checkbox" => $app->component("box", ["data" => $data['idbh']]),
            //"idbh" => $data['idbh'],
            "employee" => $data['employee_name'] ?? $jatbi->lang("Không xác định"), // Hiển thị tên nhân viên
            "money" => $moneylabel,
            "moneybhxh" => $moneylabel2,
            "numberbhxh" => $data['numberbhxh'],
            "daybhxh" => $data['daybhxh'],
            "placebhxh" => $data['placebhxh'],
            "numberyte" => $data['numberyte'],
            "dayyte" => $data['dayyte'],
            "placeyte" => $data['placeyte'],
            "statu" => $app->component("status",["url"=>"/insurance-status/".$data['idbh'],"data"=>$data['statu'],"permission"=>['insurance.edit']]),
            "action" => $app->component("action", [
                "button" => [          
                    [
                        'type' => 'button',
                        'name' => $jatbi->lang("Sửa"),
                        'permission' => ['insurance.edit'],
                        'action' => ['data-url' => '/insurance-edit?idbh=' . $data['idbh'], 'data-action' => 'modal']
                    ],
                    [
                        'type' => 'button',
                        'name' => $jatbi->lang("Xóa"),
                        'permission' => ['insurance.deleted'],
                        'action' => ['data-url' => '/insurance-deleted?idbh=' . $data['idbh'], 'data-action' => 'modal']
                    ],
                    [
                        'type' => 'button',
                        'name' => $jatbi->lang("Chi tiết"),
                        'permission' => ['insurance'],
                        'action' => ['data-url' => '/insurance-detail?idbh=' . $data['idbh'], 'data-action' => 'modal']
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
})->setPermissions(['insurance']);

// $app->router("/labor_contract", 'POST', function($vars) use ($app, $jatbi) {
//     $app->header(['Content-Type' => 'application/json']);

//     // Nhận dữ liệu từ DataTable
//     $draw = $_POST['draw'] ?? 0;
//     $start = $_POST['start'] ?? 0;
//     $length = $_POST['length'] ?? 10;
//     $searchValue = $_POST['search']['value'] ?? '';

//     // Fix lỗi ORDER cột
//     $orderColumnIndex = $_POST['order'][0]['column'] ?? 1; // Mặc định cột đầu tiên
//     $orderDir = strtoupper($_POST['order'][0]['dir'] ?? 'DESC');

//     // Danh sách cột hợp lệ
//     $validColumns = [
//         "checkbox",
//         "employee.name",
//         "department.personName",
//         "employee_contracts.contract_type",
//         "employee_contracts.contract_number",
//         "employee_contracts.contract_duration",
//         "employee_contracts.remaining_days",
//         "employee_contracts.working_date",
//     ];
//     $orderColumn = $validColumns[$orderColumnIndex] ?? "contract.created_at";

//     // Điều kiện lọc dữ liệu
//     $where = ["LIMIT" => [$start, $length], "ORDER" => [$orderColumn => $orderDir]];

//     if (!empty($searchValue)) {
//         $where["AND"]["OR"] = [
//             "employee.name[~]" => $searchValue,
//             "employee_contracts.contract_number[~]" => $searchValue
//         ];
//     }

//     // Đếm số bản ghi
//     $count = $app->count("employee_contracts", [
//         "[>]employee" => ["person_sn" => "sn"]
//     ], "employee_contracts.id");

//     // Lấy danh sách hợp đồng
//     $datas = $app->select("employee_contracts", [
//         "[>]employee" => ["employee_contracts.person_sn" => "sn"],  
//         "[>]department" => ["employee.departmentId" => "departmentId"],        
//         ], [
//         "employee_contracts.id",
//         "employee.name",
//         "department.personName",
//         "employee_contracts.contract_type",
//         "employee_contracts.contract_number",
//         "employee_contracts.contract_duration",
//         "employee_contracts.working_date",
//     ], $where) ?? [];
    
//     // Xử lý dữ liệu đầu ra
//     $formattedData = array_map(function($data) use ($app, $jatbi) {
//         // Chuyển đổi ngày làm việc sang timestamp
//         $workingDate = strtotime($data['working_date']); 
    
//         // Nếu không có ngày làm việc hoặc thời hạn hợp đồng, trả về "Không xác định"
//         if (!$workingDate || !isset($data['contract_duration'])) {
//             $remainingDays = $jatbi->lang("Không xác định");
//         } else {
//             // Thời hạn hợp đồng tính theo tháng → Chuyển thành ngày
//             $contractMonths = (int) $data['contract_duration'];
//             $contractEndDate = strtotime("+{$contractMonths} months", $workingDate);
            
//             // Ngày hiện tại
//             $currentDate = time();
    
//             // Tính số ngày còn lại
//             $remainingDays = round(($contractEndDate - $currentDate) / (60 * 60 * 24));
    
//             // Nếu số ngày nhỏ hơn 0, nghĩa là hợp đồng đã hết hạn
//             if ($remainingDays < 0) {
//                 $remainingDays = $jatbi->lang("Hết hạn");
//             } else {
//                 // Chuyển thành số tháng
//                 $remainingMonths = floor($remainingDays / 30);
//                 $remainingText = ($remainingMonths > 0) ? "$remainingMonths " . $jatbi->lang("tháng") : "$remainingDays " . $jatbi->lang("ngày");
//                 $remainingDays = $remainingText;
//             }
//         }
    
//         return [
//             "checkbox" => $app->component("box", ["data" => $data['id']]),
    
//             // TÊN NHÂN VIÊN
//             "employee_name" => $data['name'] ?? $jatbi->lang("Không xác định"),
    
//             // PHÒNG BAN 
//             "department" => $data['personName'] ?? $jatbi->lang("Không xác định"),
    
//             // LOẠI HỢP ĐỒNG
//             "contract_type" => $jatbi->lang($data['contract_type']),
    
//             // SỐ HỢP ĐỒNG
//             "contract_number" => $data['contract_number'],
    
//             // THỜI HẠN HỢP ĐỒNG
//             "contract_duration" => isset($data['contract_duration']) 
//                 ? $data['contract_duration'] . " " . $jatbi->lang("tháng") 
//                 : $jatbi->lang("Không xác định"),
    
//             // CÒN LẠI (hiển thị dưới dạng số tháng/ngày)
//             "remaining_days" => $remainingDays,
    
//             // NGÀY LÀM VIỆC
//             "working_date" => date("d/m/Y", strtotime($data['working_date'])),
    
//             "action" => $app->component("action", [
//                 "button" => [
//                     [
//                         'type' => 'button',
//                         'name' => $jatbi->lang("Sửa"),
//                         'permission' => ['labor_contract.edit'],
//                         'action' => ['data-url' => '/labor_contract-edit?id='.$data['id'], 'data-action' => 'modal']
//                     ],
//                     [
//                         'type' => 'button',
//                         'name' => $jatbi->lang("Xóa"),
//                         'permission' => ['labor_contract.deleted'],
//                         'action' => ['data-url' => '/labor_contract-deleted?id='.$data['id'], 'data-action' => 'modal']
//                     ],
//                     [
//                         'type' => 'button',
//                         'name' => $jatbi->lang("Chi tiết"),
//                         'permission' => ['labor_contract'],
//                         'action' => ['data-url' => '/labor_contract-view/'.$data['id'], 'data-action' => 'modal']
//                     ],
//                 ]
//             ]),

//             "detail" => $app->component("action", [
//                 "button" => []]),
//         ];
//     }, $datas);
    
//     // Trả về dữ liệu JSON
//     echo json_encode([
//         "draw" => $draw,
//         "recordsTotal" => $count,
//         "recordsFiltered" => $count,
//         "data" => $formattedData,
//     ]);
    
// })->setPermissions(['labor_contract']);



$app->router("/manager/employee-add", 'GET', function($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Nhân viên");
    $vars['departments'] = $app->select("department", ['departmentId', 'personName'], ["ORDER" => ["personName" => "ASC"]]);
    echo $app->render('templates/employee/employee-post.html', $vars, 'global');
})->setPermissions(['employee.add']);

$app->router("/manager/employee-add", 'POST', function($vars) use ($app, $jatbi) {
    $app->header(['Content-Type' => 'application/json']);

    // Lấy dữ liệu từ form và kiểm tra XSS
    $sn = $app->xss($_POST['sn'] ?? '');
    $name = $app->xss($_POST['name'] ?? '');
    $type = (int) ($app->xss($_POST['type'] ?? ''));
    $departmentId = $app->xss($_POST['departmentId'] ?? '');

    // Kiểm tra dữ liệu đầu vào
    if (empty($sn) || empty($name) || empty($type)) {
        echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
        return;
    }

    try {
        // Dữ liệu để lưu vào database
        $insert = [
            "sn" => $sn,
            "name" => $name,
            "type" => $type,
            "departmentId" => $departmentId,
        ];
        
        // Thêm dữ liệu vào database
        $app->insert("employee", $insert);

        // Ghi log
        $jatbi->logs('employee', 'employee-add', $insert);

        // Dữ liệu gửi lên API
        $apiData = [
            'deviceKey' => '77ed8738f236e8df86',
            'secret' => '123456',
            'sn' => $sn,
            'name' => $name,
            'type' => $type,
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

// Xóa employee
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
                'secret' => '123456',
                'sn' => $sn,
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

// Cập nhật employee
$app->router("/manager/employee-edit", 'GET', function($vars) use ($app, $jatbi) {
    $vars['title'] = $jatbi->lang("Sửa Nhân Viên");

    $sn = isset($_GET['id']) ? $app->xss($_GET['id']) : null;
    if (!$sn) {
        echo $app->render('templates/common/error-modal.html', $vars, 'global');
        return;
    }

    $vars['data'] = $app->get("employee", "*", ["sn" => $sn]);
    $vars['departments'] = $app->select("department", ['departmentId', 'personName'], [
        "ORDER" => ["personName" => "ASC"]
    ]);   
        
    $vars['data']['edit'] = true;
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
    $departmentId = isset($_POST['departmentId']) ? $app->xss($_POST['departmentId']) : '';

    if ($name === '' || $type === '') {
        echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
        return;
    }

    // Cập nhật dữ liệu trong database
    $update = [
        "name" => $name,
        "type" => $type,
        "departmentId" => $departmentId,
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
        'secret' => '123456',
        'sn' => $sn,
        'name' => $name,
        'type' => $type,
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

// Đồng bộ hóa API
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

    // Lấy danh sách nhân viên hiện có trong database
    $employeesFromDB = $app->select("employee", ["sn", "name", "type"]);
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

        if (isset($dbEmployeeMap[$sn])) {
            // Nếu đã tồn tại, kiểm tra xem có thay đổi không
            if ($dbEmployeeMap[$sn]['name'] !== $name || 
                $dbEmployeeMap[$sn]['type'] != $type) {
                
                $updateData[] = [
                    "sn" => $sn,
                    "name" => $name,
                    "type" => $type
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
                "type" => $type
            ];
            $added++;
        }
    }

    // Cập nhật dữ liệu nếu có thay đổi
    foreach ($updateData as $update) {
        $app->update("employee", [
            "name" => $update['name'],
            "type" => $update['type']
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

    // Lấy tháng và năm từ query string (mặc định là tháng hiện tại)
    $currentMonth = isset($_GET['month']) ? (int)$_GET['month'] : date('n'); // 1-12
    $currentYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
    $vars['current_month'] = $currentMonth;
    $vars['current_year'] = $currentYear;

    // Tính tháng trước và tháng sau để điều hướng
    $prevMonth = $currentMonth - 1;
    $prevYear = $currentYear;
    if ($prevMonth < 1) {
        $prevMonth = 12;
        $prevYear--;
    }
    $nextMonth = $currentMonth + 1;
    $nextYear = $currentYear;
    if ($nextMonth > 12) {
        $nextMonth = 1;
        $nextYear++;
    }
    $vars['prev_month'] = $prevMonth;
    $vars['prev_year'] = $prevYear;
    $vars['next_month'] = $nextMonth;
    $vars['next_year'] = $nextYear;

    // Lấy ngày đầu tiên và ngày cuối cùng của tháng
    $firstDayOfMonth = new DateTime("$currentYear-$currentMonth-01");
    $lastDayOfMonth = new DateTime("$currentYear-$currentMonth-" . $firstDayOfMonth->format('t'));
    $firstDayOfWeek = (int)$firstDayOfMonth->format('w'); // 0 (Chủ nhật) đến 6 (Thứ 7)
    $totalDays = (int)$lastDayOfMonth->format('d');

    // Tạo mảng để lưu các ngày trong tháng
    $calendar = [];
    $week = array_fill(0, 7, null);
    $dayCount = 1;

    // Điền các ô trống trước ngày đầu tiên của tháng
    for ($i = 0; $i < $firstDayOfWeek; $i++) {
        $week[$i] = null;
    }

    // Điền các ngày trong tháng
    for ($i = $firstDayOfWeek; $i < 7; $i++) {
        if ($dayCount > $totalDays) break;
        $week[$i] = $dayCount;
        $dayCount++;
    }
    $calendar[] = $week;

    // Điền các hàng còn lại
    while ($dayCount <= $totalDays) {
        $week = array_fill(0, 7, null);
        for ($i = 0; $i < 7; $i++) {
            if ($dayCount > $totalDays) break;
            $week[$i] = $dayCount;
            $dayCount++;
        }
        $calendar[] = $week;
    }
    $vars['calendar'] = $calendar;

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
            $dateTime = explode(" ", $record['createTime']);
            $date = $dateTime[0]; // Ngày: 2025-03-19
            $time = $dateTime[1]; // Giờ: 14:43:31

            $datesWithRecords[$date] = true;
            if (!isset($timekeepingByDate[$date])) {
                $timekeepingByDate[$date] = [];
            }
            $timekeepingByDate[$date][] = [
                'id' => $record['id'],
                'time' => $time
            ];
        }

        $vars['dates_with_records'] = array_keys($datesWithRecords);
        $vars['timekeeping_by_date'] = $timekeepingByDate;

        // Lấy ngày được chọn từ query string
        $selectedDate = isset($_GET['selected_date']) ? $app->xss($_GET['selected_date']) : null;
        $vars['selected_date'] = $selectedDate;
        if ($selectedDate && isset($timekeepingByDate[$selectedDate])) {
            $vars['selected_records'] = $timekeepingByDate[$selectedDate];
        } else {
            $vars['selected_records'] = [];
        }
    }

    // Render template HTML
    echo $app->render('templates/common/view-record.html', $vars, 'global');
})->setPermissions([]);

// Cấp phép employee
$app->router("/employee-status/{sn}", 'POST', function($vars) use ($app, $jatbi) {
    $app->header([
        'Content-Type' => 'application/json',
    ]);

    $data = $app->get("employee", "*", ["sn" => $vars['sn']]);
    if ($data) {
        $status = $data['status'] === 'A' ? 'D' : 'A';
        $app->update("employee", ["status" => $status], ["sn" => $data['sn']]);
        $jatbi->logs('employee', 'employee-status', $data);
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
    } else {
        echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
    }
})->setPermissions(['employee.edit']);
?>