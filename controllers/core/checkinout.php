<?php
if (!defined('ECLO'))
    die("Hacking attempt");
$jatbi = new Jatbi($app);
$setting = $app->getValueData('setting');

$app->router("/manager/checkinout", 'GET', function ($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Thời gian ra vào");
    $vars['add'] = '/manager/checkinout-add';
    $vars['deleted'] = '/manager/checkinout-deleted';
    // Lấy danh sách từ bảng checkinout
    $data = $app->select("checkinout", ["sn", "checkinout_list", "updated_at"]);

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




$app->router("/manager/checkinout", 'POST', function ($vars) use ($app, $jatbi) {
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
        "checkinout.sn",
        "checkinout.checkinout_list",
        "checkinout.updated_at",
        // "employee.name"
    ], $where) ?? [];

    // Truy vấn bảng employee để lấy tên tương ứng với sn
    $employeeData = $app->select("employee", ["sn", "name"]);
    $employeeMap = [];
    foreach ($employeeData as $employee) {
        $employeeMap[$employee['sn']] = $employee['name'];
    }


    $formattedData = array_map(function ($data) use ($app, $jatbi, $employeeMap) {
        // Chuyển đổi chuỗi JSON thành mảng
        $passTimeListArray = json_decode($data['checkinout_list'], true);
        
        // Kiểm tra nếu JSON decode không thành công hoặc không đúng định dạng
        if (!is_array($passTimeListArray) || empty($passTimeListArray[0])) {
            $passTimeList = [];
        } else {
            // Lấy object đầu tiên trong mảng
            $passTimeList = $passTimeListArray[0];
        }
    
        // Định dạng dữ liệu thời gian để hiển thị
        $formattedTimeList = [];
        $daysMapping = [
            'mon'   => 'Thứ Hai',
            'tue'   => 'Thứ Ba',
            'wed'   => 'Thứ Tư',
            'thurs' => 'Thứ Năm',
            'fri'   => 'Thứ Sáu',
            'sat'   => 'Thứ Bảy',
            'sun'   => 'Chủ Nhật'
        ];
    
        foreach ($daysMapping as $key => $day) {
            if (isset($passTimeList[$key]) && !empty($passTimeList[$key])) {
                $formattedTimeList[] = "{$day}: {$passTimeList[$key]}";
            }
        }
    
        // Gộp danh sách thời gian thành chuỗi HTML để hiển thị
        $timeListString = implode("<br>", $formattedTimeList);
    
        return [
            "checkbox" => $app->component("box", ["data" => $data['sn']]),
            "sn" => $data['sn'],
            "name" => $employeeMap[$data['sn']] ?? $data['sn'],
            "checkinout_list" => $timeListString,
            "updated_at" => $data['updated_at'],
            "action" => $app->component("action", [
                "button" => [
                    [
                        'type' => 'button',
                        'name' => $jatbi->lang("Sửa"),
                        'permission' => ['checkinout.edit'],
                        'action' => ['data-url' => '/manager/checkinout-edit?box=' . $data['sn'], 'data-action' => 'modal']
                    ],
                    [
                        'type' => 'button',
                        'name' => $jatbi->lang("Xóa"),
                        'permission' => ['checkinout.deleted'],
                        'action' => ['data-url' => '/manager/checkinout-deleted?box=' . $data['sn'], 'data-action' => 'modal']
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
$app->router("/manager/checkinout-add", 'GET', function ($vars) use ($app, $jatbi, $setting) {
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
$app->router("/manager/checkinout-add", 'POST', function ($vars) use ($app, $jatbi) {
    $app->header(['Content-Type' => 'application/json']);

    $sn = $app->xss($_POST['sn'] ?? '');
    $checkinout_list = $_POST['checkinout'] ?? '{}';

    // Nếu là mảng, chuyển thành JSON
    if (is_array($checkinout_list)) {
        $checkinout_list = json_encode([$checkinout_list], JSON_UNESCAPED_UNICODE);
    }

    // Kiểm tra đầu vào hợp lệ
    if (empty(trim($sn)) || empty($checkinout_list) || json_decode($checkinout_list, true) === null) {
        echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng nhập đúng định dạng dữ liệu")]);
        return;
    }

    try {
        // Kiểm tra SN đã tồn tại chưa
        $existingRecord = $app->select("checkinout", ["sn"], ["sn" => $sn]);
        if (!empty($existingRecord)) {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Nhân viên đã có trong danh sách")]);
            return;
        }

        // Gửi dữ liệu lên API
        $apiData = [
            'deviceKey'    => '77ed8738f236e8df86',
            'secret'       => '123456',
            'sn'           => $sn,
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

        // Ghi log response API để debug
        error_log("API Response for SN: $sn: " . var_export($response, true));

        $apiResponse = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Lỗi JSON từ API: " . json_last_error_msg());
        }

        if (!empty($apiResponse['code']) && $apiResponse['code'] == "000") {
            // Chuẩn bị dữ liệu để thêm vào database
            $insert = [
                "sn"              => $sn,
                "checkinout_list" => $checkinout_list,
                "updated_at"      => date('Y-m-d H:i:s'),
            ];

            // Thêm vào database
            $result = $app->insert("checkinout", $insert);
            if (!$result) {
                $error = $app->error() ? var_export($app->error(), true) : "Không rõ lỗi";
                throw new Exception("Không thể thêm vào database: " . $error);
            }

            // Ghi log và phản hồi thành công
            $jatbi->logs('checkinout', 'checkinout-add', $insert);
            echo json_encode(["status" => "success", "content" => $jatbi->lang("Thêm thành công")]);
        } else {
            $errorMessage = $apiResponse['msg'] ?? "Không rõ lỗi";
            echo json_encode(["status" => "error", "content" => "API lỗi: " . $errorMessage]);
        }

    } catch (Exception $e) {
        error_log("Lỗi thêm checkinout cho SN: $sn: " . $e->getMessage());
        echo json_encode(["status" => "error", "content" => "Lỗi: " . $e->getMessage()]);
    }
})->setPermissions(['checkinout.add']);


$app->router("/manager/checkinout-edit", 'GET', function ($vars) use ($app, $jatbi, $setting) {
    $id = $app->xss($_GET['box'] ?? '');
    $vars['title'] = $jatbi->lang("Sửa thời gian ra vào");

    if (empty($id)) {
        $app->error(404, $jatbi->lang("Không tìm thấy ID"));
        return;
    }

    // Lấy dữ liệu từ bảng checkinout
    $data = $app->select("checkinout", ["sn", "checkinout_list", "updated_at"], ["sn" => $id]);
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

    // Lấy tên nhân viên theo `sn`, nếu không có thì đặt giá trị mặc định
    $data[0]['employee_name'] = $employeeMap[$data[0]['sn']] ?? "Không xác định ({$data[0]['sn']})";

    // Gửi dữ liệu ra giao diện
    $vars['data'] = $data[0];
    echo $app->render('templates/employee/checkinout-post.html', $vars, 'global');
})->setPermissions(['checkinout.edit']);



//Sửa thời gian ra vào (POST)
$app->router("/manager/checkinout-edit", 'POST', function ($vars) use ($app, $jatbi) {
    $app->header(['Content-Type' => 'application/json']);

    // Lấy dữ liệu từ request
    $sn = $app->xss($_POST['sn'] ?? $_GET['sn'] ?? '');
    $checkinout_list = $_POST['checkinout'] ?? '{}';

    // Nếu là mảng, chuyển thành JSON
    if (is_array($checkinout_list)) {
        $checkinout_list = json_encode([$checkinout_list], JSON_UNESCAPED_UNICODE);
    }

    // Kiểm tra dữ liệu có hợp lệ không
    if (empty(trim($sn)) || empty($checkinout_list) || json_decode($checkinout_list, true) === null) {
        echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng nhập đúng định dạng dữ liệu")]);
        return;
    }

    // Kiểm tra xem SN có tồn tại không
    $existingRecord = $app->select("checkinout", ["sn"], ["sn" => $sn]);
    if (empty($existingRecord)) {
        echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy bản ghi để cập nhật")]);
        return;
    }

    try {
        // Gửi dữ liệu lên API để cập nhật
        $apiData = [
            'deviceKey'   => '77ed8738f236e8df86',
            'secret'      => '123456',
            'sn'          => $sn,
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

        // Ghi log response API để kiểm tra
        error_log("API Response for SN: $sn: " . var_export($response, true));

        $apiResponse = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Lỗi JSON từ API: " . json_last_error_msg());
        }

        if (!empty($apiResponse['code']) && $apiResponse['code'] == "000") {
            // Cập nhật dữ liệu vào database
            $update = [
                "sn"              => $sn,
                "checkinout_list" => $checkinout_list,
                "updated_at"      => date('Y-m-d H:i:s'),
            ];

            $updateResult = $app->update("checkinout", $update, ["sn" => $sn]);

            if ($updateResult) {
                $jatbi->logs('checkinout', 'checkinout-edit', $update);
                echo json_encode(["status" => "success", "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                $error = $app->error() ? var_export($app->error(), true) : "Không rõ lỗi";
                echo json_encode(["status" => "error", "content" => "API thành công nhưng không thể cập nhật database: " . $error]);
            }
        } else {
            $errorMessage = $apiResponse['msg'] ?? "Không rõ lỗi";
            echo json_encode(["status" => "error", "content" => "API lỗi: " . $errorMessage]);
        }
    } catch (Exception $e) {
        error_log("Lỗi cập nhật checkinout cho SN: $sn: " . $e->getMessage());
        echo json_encode(["status" => "error", "content" => "Lỗi: " . $e->getMessage()]);
    }
})->setPermissions(['checkinout.edit']);







// Sửa thời gian ra vào (POST)
// $app->router("/manager/checkinout-edit", 'POST', function ($vars) use ($app, $jatbi) {
//     $app->header(['Content-Type' => 'application/json']);

//     // Lấy dữ liệu từ request
//     $sn = $app->xss($_POST['sn'] ?? $_GET['sn'] ?? '');
//     $checkinout_list = $app->xss($_POST['checkinout_list'] ?? '{}');

//     // Kiểm tra dữ liệu đầu vào
//     if (empty($sn)) {
//         echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống SN")]);
//         return;
//     }

//     // Giải mã checkinout_list
//     $timeList = json_decode($checkinout_list, true);
//     if (!is_array($timeList)) {
//         echo json_encode(["status" => "error", "content" => $jatbi->lang("Danh sách thời gian không hợp lệ")]);
//         return;
//     }

//     // Kiểm tra định dạng thời gian cho từng ngày
//     $days = ['mon', 'tue', 'wed', 'thurs', 'fri', 'sat', 'sun'];
//     foreach ($days as $day) {
//         if (isset($timeList[$day]) && !empty($timeList[$day])) {
//             if (!validateTimeFormat($timeList[$day])) {
//                 echo json_encode([
//                     "status" => "error",
//                     "content" => $jatbi->lang("Định dạng thời gian không hợp lệ, vui lòng nhập lại theo định dạng HH:MM-HH:MM (ví dụ: 00:00-23:59)")
//                 ]);
//                 return;
//             }
//         }
//     }

//     // Kiểm tra xem SN có tồn tại trong database không
//     $existingRecord = $app->select("checkinout", ["sn"], ["sn" => $sn]);
//     if (empty($existingRecord)) {
//         echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy bản ghi để cập nhật")]);
//         return;
//     }

//     try {
//         // Gửi dữ liệu lên API để cập nhật
//         $apiData = [
//             'deviceKey' => '77ed8738f236e8df86',
//             'secret' => '123456',
//             'sn' => $sn,
//             'passtimeList' => $checkinout_list,
//         ];

//         $headers = [
//             'Authorization: Bearer your_token',
//             'Content-Type: application/x-www-form-urlencoded'
//         ];

//         $response = $app->apiPost(
//             'http://camera.ellm.io:8190/api/person/passtime/merge',
//             $apiData,
//             $headers
//         );

//         // Debug: Ghi lại response từ API
//         error_log("API Response for SN: $sn: " . var_export($response, true));

//         $apiResponse = json_decode($response, true);
//         if (json_last_error() !== JSON_ERROR_NONE) {
//             throw new Exception("Dữ liệu trả về từ API không hợp lệ: " . json_last_error_msg());
//         }

//         if (!empty($apiResponse['code']) && $apiResponse['code'] == "000") {
//             // Cập nhật dữ liệu trong database
//             $update = [
//                 "sn" => $sn,
//                 "checkinout_list" => $checkinout_list,
//                 "updated_at" => date('Y-m-d H:i:s'),
//             ];

//             $updateResult = $app->update("checkinout", $update, ["sn" => $sn]);

//             if ($updateResult) {
//                 $jatbi->logs('checkinout', 'checkinout-edit', $update);
//                 echo json_encode(["status" => "success", "content" => $jatbi->lang("Cập nhật thành công")]);
//             } else {
//                 $error = $app->error() ? var_export($app->error(), true) : "Không rõ lỗi";
//                 echo json_encode(["status" => "error", "content" => "Cập nhật API thành công nhưng không thể cập nhật database: " . $error]);
//             }
//         } else {
//             $errorMessage = $apiResponse['msg'] ?? "Không rõ lỗi";
//             echo json_encode(["status" => "error", "content" => "API gặp lỗi: " . $errorMessage]);
//         }
//     } catch (Exception $e) {
//         error_log("Error updating checkinout for SN: $sn: " . $e->getMessage());
//         echo json_encode(["status" => "error", "content" => "Lỗi: " . $e->getMessage()]);
//     }
// })->setPermissions(['checkinout.edit']);







// Xóa thời gian ra vào (GET)
$app->router("/manager/checkinout-deleted", 'GET', function($vars) use ($app, $jatbi) {
    $vars['title'] = $jatbi->lang("Xóa thời gian ra vào");
    echo $app->render('templates/common/deleted.html', $vars, 'global');
})->setPermissions(['checkinout.deleted']);

$app->router("/manager/checkinout-deleted", 'POST', function ($vars) use ($app, $jatbi) {
    $app->header([
        'Content-Type' => 'application/json',
    ]);

    try {
        // Lấy danh sách ID từ query string 'box' (cho phép xóa nhiều bản ghi)
        $boxid = explode(',', $app->xss($_GET['box'] ?? ''));

        // Loại bỏ các giá trị rỗng và không phải số trong danh sách
        $boxid = array_filter($boxid, function ($value) {
            return !empty($value) && is_numeric($value);
        });

        if (empty($boxid)) {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Không tìm thấy ID để xóa")]);
            return;
        }

        // Lấy danh sách dữ liệu cần xóa từ database
        $datas = $app->select("checkinout", ["sn"], ["sn" => $boxid]);

        if (empty($datas)) {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Không tìm thấy dữ liệu để xóa")]);
            return;
        }

        // Thiết lập thông tin API
        $apiUrl = "http://camera.ellm.io:8190/api/person/passtime/delete";
        $headers = [
            'Authorization: Bearer your_token',
            'Content-Type: application/x-www-form-urlencoded'
        ];

        foreach ($datas as $data) {
            $apiData = [
                'deviceKey' => '77ed8738f236e8df86',
                'secret'    => '123456',
                'sn'        => $data['sn']
            ];

            // Gọi API để xóa dữ liệu
            $response = $app->apiPost($apiUrl, $apiData, $headers);
            $apiResponse = json_decode($response, true);

            // Kiểm tra phản hồi từ API
            if (empty($apiResponse['success']) || $apiResponse['success'] !== true) {
                echo json_encode([
                    "status" => "error",
                    "content" => "Xóa thất bại trên API cho SN: " . $data['sn']
                ]);
                return;
            }
        }

        // Nếu API xóa thành công, xóa dữ liệu trong database
        $app->delete("checkinout", ["sn" => $boxid]);

        // Ghi log quá trình xóa
        $jatbi->logs('checkinout', 'checkinout-deleted', $datas);

        echo json_encode(["status" => "success", "content" => $jatbi->lang("Xóa thành công")]);

    } catch (Exception $e) {
        echo json_encode(["status" => "error", "content" => "Lỗi: " . $e->getMessage()]);
    }
})->setPermissions(['checkinout.deleted']);



//Đồng bộ thời gian ra vào từ API

$app->router("/manager/checkinout-sync", 'GET', function($vars) use ($app, $jatbi) {
    $vars['title'] = $jatbi->lang("Đồng bộ nhân viên");

    echo $app->render('templates/common/restore.html', $vars, 'global');
})->setPermissions(['checkinout.sync']);

$app->router("/manager/checkinout-sync", 'POST', function($vars) use ($app, $jatbi) {
    $app->header(['Content-Type' => 'application/json']);

    // Gọi API lấy danh sách nhân viên
    $params = [
        "deviceKey" => "77ed8738f236e8df86",
        "secret" => "123456",
        "index" => 1,
        "length" => 50
    ];

    $apiUrl = "http://camera.ellm.io:8190/api/person/findList";
    $headers = [
        'Authorization: Bearer your_token',
        'Content-Type: application/x-www-form-urlencoded'
    ];

    $response = $app->apiPost($apiUrl, $params, $headers);
    $employeesFromAPI = json_decode($response, true);

    // Kiểm tra dữ liệu trả về từ API
    if (!$employeesFromAPI || empty($employeesFromAPI['data'])) {
        echo json_encode(["status" => "error", "content" => "Không lấy được dữ liệu từ API"]);
        return;
    }

    $employeesFromAPI = $employeesFromAPI['data'];

    // Lấy danh sách nhân viên hiện có trong database
    $employeesFromDB = $app->select("checkinout", ["sn", "checkinout_list"]);
    $dbEmployeeMap = [];
    foreach ($employeesFromDB as $employee) {
        $dbEmployeeMap[$employee['sn']] = $employee;
    }

    // So sánh dữ liệu API với database
    $added = 0;
    $updated = 0;
    $skipped = 0;

    $insertData = [];
    $updateData = [];

    foreach ($employeesFromAPI as $employee) {
        $sn = $employee['sn'];

        // Xử lý checkinout_list đảm bảo đúng định dạng [{"mon": "...", "tue": "...", ...}]
        $checkinoutRaw = $employee['acTz1'] ?? [];
        if (!is_array($checkinoutRaw)) {
            $checkinoutRaw = [];
        }
        $checkinout_list = json_encode([$checkinoutRaw], JSON_UNESCAPED_UNICODE);

        if (isset($dbEmployeeMap[$sn])) {
            // Nếu đã tồn tại, kiểm tra xem có thay đổi không
            if ($dbEmployeeMap[$sn]['checkinout_list'] !== $checkinout_list) {
                $updateData[] = [
                    "sn" => $sn,
                    "checkinout_list" => $checkinout_list
                ];
                $updated++;
            } else {
                $skipped++;
            }
        } else {
            // Nếu chưa có, thêm mới vào database
            $insertData[] = [
                "sn" => $sn,
                "checkinout_list" => $checkinout_list
            ];
            $added++;
        }
    }

    // Cập nhật dữ liệu nếu có thay đổi
    foreach ($updateData as $update) {
        $app->update("checkinout", [
            "checkinout_list" => $update['checkinout_list']
        ], ["sn" => $update['sn']]);
    }

    // Chèn dữ liệu mới nếu có
    if (!empty($insertData)) {
        $app->insert("checkinout", $insertData);
    }

    // Trả về kết quả
    echo json_encode([
        "status" => "success",
        "content" => "Đồng bộ nhân viên thành công",
        "added" => $added,
        "updated" => $updated,
        "skipped" => $skipped
    ]);
})->setPermissions(['checkinout.sync']);


