<?php
    if (!defined('ECLO')) die("Hacking attempt");
    $jatbi = new Jatbi($app);
    $setting = $app->getValueData('setting');

    // Khung thời gian
    $app->router("/staffConfiguration/timeperiod", 'GET', function($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Thời gian làm việc");
        $vars['add'] = '/manager/timeperiod-add';
        $vars['deleted'] = '/manager/timeperiod-deleted';
        $vars['sync'] = '/manager/timeperiod-sync';
        $vars['active']= "timeperiod";


        // Lấy dữ liệu từ bảng timeperiod, bao gồm các cột mới
        $data = $app->select("timeperiod", [
            "acTzNumber", "name",
            "monStart", "monEnd", "tueStart", "tueEnd", "wedStart", "wedEnd",
            "thursStart", "thursEnd", "friStart", "friEnd", "satStart", "satEnd",
            "sunStart", "sunEnd",
            "mon_off", "tue_off", "wed_off", "thu_off", "fri_off", "sat_off", "sun_off",
            "mon_work_credit", "tue_work_credit", "wed_work_credit", "thu_work_credit",
            "fri_work_credit", "sat_work_credit", "sun_work_credit",
            "breakTime", "note", "status"
        ]);

        $vars['data'] = $data;
        echo $app->render('templates/employee/timeperiod.html', $vars);
    })->setPermissions(['timeperiod']);

    $app->router("/staffConfiguration/timeperiod", 'POST', function($vars) use ($app, $jatbi) {
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
        $validColumns = [
            "checkbox", "acTzNumber", "name",
            "timeperiod", // Cột thời gian gộp
            "breakTime", "note", "status", "action"
        ];
        $orderColumn = $validColumns[$orderColumnIndex] ?? "acTzNumber";
    
        // Điều kiện lọc dữ liệu
        $where = [
            "AND" => [
                "OR" => [
                    "timeperiod.acTzNumber[~]" => $searchValue,
                    "timeperiod.name[~]" => $searchValue,
                ]
            ],
            "LIMIT" => [$start, $length],
            "ORDER" => [$orderColumn => $orderDir]
        ];
    
        if (!empty($type)) {
            $where["AND"]["timeperiod.monStart"] = $type;
        }
    
        // Đếm số bản ghi
        $count = $app->count("timeperiod", ["AND" => $where["AND"]]);
    
        // Truy vấn danh sách Khung thời gian
        $datas = $app->select("timeperiod", [
            'acTzNumber', 'name',
            'monStart', 'monEnd', 'tueStart', 'tueEnd', 'wedStart', 'wedEnd',
            'thursStart', 'thursEnd', 'friStart', 'friEnd', 'satStart', 'satEnd',
            'sunStart', 'sunEnd',
            'mon_off', 'tue_off', 'wed_off', 'thu_off', 'fri_off', 'sat_off', 'sun_off',
            'mon_work_credit', 'tue_work_credit', 'wed_work_credit', 'thu_work_credit',
            'fri_work_credit', 'sat_work_credit', 'sun_work_credit',
            'breakTime', 'note', 'status'
        ], $where) ?? [];
    
        // Log dữ liệu truy vấn để kiểm tra
        error_log("Fetched Timeperiods Data: " . print_r($datas, true));
    
        // Xử lý dữ liệu đầu ra
        $formattedData = array_map(function($data) use ($app, $jatbi) {
            // Gộp các ngày trong tuần thành một cột "Thời gian"
            $timeperiod = [
                (isset($data['mon_off']) && $data['mon_off'] ? "<span class='day-label'>T2:</span> " : "<span class='day-label'>T2:</span> " . ($data['monStart'] ?? '00:00') . " - " . ($data['monEnd'] ?? '23:59')),
                (isset($data['tue_off']) && $data['tue_off'] ? "<span class='day-label'>T3:</span> " : "<span class='day-label'>T3:</span> " . ($data['tueStart'] ?? '00:00') . " - " . ($data['tueEnd'] ?? '23:59')),
                (isset($data['wed_off']) && $data['wed_off'] ? "<span class='day-label'>T4:</span> " : "<span class='day-label'>T4:</span> " . ($data['wedStart'] ?? '00:00') . " - " . ($data['wedEnd'] ?? '23:59')),
                (isset($data['thu_off']) && $data['thu_off'] ? "<span class='day-label'>T5:</span> " : "<span class='day-label'>T5:</span> " . ($data['thursStart'] ?? '00:00') . " - " . ($data['thursEnd'] ?? '23:59')),
                (isset($data['fri_off']) && $data['fri_off'] ? "<span class='day-label'>T6:</span> " : "<span class='day-label'>T6:</span> " . ($data['friStart'] ?? '00:00') . " - " . ($data['friEnd'] ?? '23:59')),
                (isset($data['sat_off']) && $data['sat_off'] ? "<span class='day-label'>T7:</span> " : "<span class='day-label'>T7:</span> " . ($data['satStart'] ?? '00:00') . " - " . ($data['satEnd'] ?? '23:59')),
                (isset($data['sun_off']) && $data['sun_off'] ? "<span class='day-label'>CN:</span> " : "<span class='day-label'>CN:</span> " . ($data['sunStart'] ?? '00:00') . " - " . ($data['sunEnd'] ?? '23:59'))
            ];
            $timeperiodHtml = implode("<br>", $timeperiod);
    
            return [
                "checkbox" => $app->component("box", ["data" => $data['acTzNumber']]),
                "acTzNumber" => $data['acTzNumber'] ?? '',
                "name" => $data['name'] ?? '',
                "timeperiod" => $timeperiodHtml, // Cột thời gian gộp
                "breakTime" => $data['breakTime'] ?? '',
                "note" => $data['note'] ?? '',
                "status" => $app->component("status",["url"=>"/timeperiod-status/".$data['acTzNumber'],"data"=>$data['status'],"permission"=>['timeperiod.edit']]),
                "action" => $app->component("action", [
                    "button" => [          
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Sửa"),
                            'permission' => ['employee.edit'],
                            'action' => ['data-url' => '/manager/timeperiod-edit?box=' . ($data['acTzNumber'] ?? ''), 'data-action' => 'modal']
                        ],
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Xóa"),
                            'permission' => ['employee.deleted'],
                            'action' => ['data-url' => '/manager/timeperiod-deleted?box=' . ($data['acTzNumber'] ?? ''), 'data-action' => 'modal']
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
    })->setPermissions(['timeperiod']);

   // Thêm timeperiod
    $app->router("/manager/timeperiod-add", 'GET', function($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Thời gian làm việc");
        echo $app->render('templates/employee/timeperiod-post.html', $vars, 'global');
    })->setPermissions(['timeperiod.add']);

    $app->router("/manager/timeperiod-add", 'POST', function($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);

        // Lấy dữ liệu từ form và kiểm tra XSS
        $acTzNumber = $app->xss($_POST['acTzNumber'] ?? '');
        $acTzname = $app->xss($_POST['acTzname'] ?? '');
        $monStart = $app->xss($_POST['monStart'] ?? '');
        $monEnd = $app->xss($_POST['monEnd'] ?? '');
        $tueStart = $app->xss($_POST['tueStart'] ?? '');
        $tueEnd = $app->xss($_POST['tueEnd'] ?? '');
        $wedStart = $app->xss($_POST['wedStart'] ?? '');
        $wedEnd = $app->xss($_POST['wedEnd'] ?? '');
        $thursStart = $app->xss($_POST['thursStart'] ?? '');
        $thursEnd = $app->xss($_POST['thursEnd'] ?? '');
        $friStart = $app->xss($_POST['friStart'] ?? '');
        $friEnd = $app->xss($_POST['friEnd'] ?? '');
        $satStart = $app->xss($_POST['satStart'] ?? '');
        $satEnd = $app->xss($_POST['satEnd'] ?? '');
        $sunStart = $app->xss($_POST['sunStart'] ?? '');
        $sunEnd = $app->xss($_POST['sunEnd'] ?? '');
        // Các trường mới chỉ lưu vào database
        $mon_off = isset($_POST['mon_off']) && $_POST['mon_off'] == '1' ? 1 : 0;
        $tue_off = isset($_POST['tue_off']) && $_POST['tue_off'] == '1' ? 1 : 0;
        $wed_off = isset($_POST['wed_off']) && $_POST['wed_off'] == '1' ? 1 : 0;
        $thu_off = isset($_POST['thu_off']) && $_POST['thu_off'] == '1' ? 1 : 0;
        $fri_off = isset($_POST['fri_off']) && $_POST['fri_off'] == '1' ? 1 : 0;
        $sat_off = isset($_POST['sat_off']) && $_POST['sat_off'] == '1' ? 1 : 0;
        $sun_off = isset($_POST['sun_off']) && $_POST['sun_off'] == '1' ? 1 : 0;
        $mon_work_credit = $app->xss($_POST['mon_work_credit'] ?? '0');
        $tue_work_credit = $app->xss($_POST['tue_work_credit'] ?? '0');
        $wed_work_credit = $app->xss($_POST['wed_work_credit'] ?? '0');
        $thu_work_credit = $app->xss($_POST['thu_work_credit'] ?? '0');
        $fri_work_credit = $app->xss($_POST['fri_work_credit'] ?? '0');
        $sat_work_credit = $app->xss($_POST['sat_work_credit'] ?? '0');
        $sun_work_credit = $app->xss($_POST['sun_work_credit'] ?? '0');
        $breakTime = $app->xss($_POST['breakTime'] ?? '');
        $note = $app->xss($_POST['note'] ?? '');
        $status = $app->xss($_POST['status'] ?? '1');

        // Kiểm tra dữ liệu đầu vào
        if (empty($acTzNumber) || empty($acTzname) || empty($monStart) || empty($monEnd) || empty($tueStart) || empty($tueEnd) || empty($wedStart) || empty($wedEnd) || empty($thursStart) || empty($thursEnd) || empty($friStart) || empty($friEnd) || empty($satStart) || empty($satEnd) || empty($sunStart) || empty($sunEnd)) {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống: Mã Khung thời gian, Tên Khung thời gian, và các trường thời gian")]);
            return;
        }

        try {
            // Dữ liệu để lưu vào database (bao gồm cả các trường mới)
            $insert = [
                "acTzNumber" => $acTzNumber,
                "name" => $acTzname,
                "monStart" => $monStart,
                "monEnd" => $monEnd,
                "tueStart" => $tueStart,
                "tueEnd" => $tueEnd,
                "wedStart" => $wedStart,
                "wedEnd" => $wedEnd,
                "thursStart" => $thursStart,
                "thursEnd" => $thursEnd,
                "friStart" => $friStart,
                "friEnd" => $friEnd,
                "satStart" => $satStart,
                "satEnd" => $satEnd,
                "sunStart" => $sunStart,
                "sunEnd" => $sunEnd,
                "mon_off" => $mon_off,
                "tue_off" => $tue_off,
                "wed_off" => $wed_off,
                "thu_off" => $thu_off,
                "fri_off" => $fri_off,
                "sat_off" => $sat_off,
                "sun_off" => $sun_off,
                "mon_work_credit" => $mon_work_credit,
                "tue_work_credit" => $tue_work_credit,
                "wed_work_credit" => $wed_work_credit,
                "thu_work_credit" => $thu_work_credit,
                "fri_work_credit" => $fri_work_credit,
                "sat_work_credit" => $sat_work_credit,
                "sun_work_credit" => $sun_work_credit,
                "breakTime" => $breakTime,
                "note" => $note,
                "status" => $status
            ];

            // Ghi log
            $jatbi->logs('timeperiod', 'timeperiod-add', $insert);

            // Dữ liệu gửi lên API (chỉ bao gồm các trường cũ)
            $apiData = [
                'deviceKey' => '77ed8738f236e8df86',
                'secret'    => '123456',
                'acTzNumber' => $acTzNumber,
                'acTzName' => $acTzname,
                'monStart' => $monStart,
                'monEnd' => $monEnd,
                'tueStart' => $tueStart,
                'tueEnd' => $tueEnd,
                'wedStart' => $wedStart,
                'wedEnd' => $wedEnd,
                'thursStart' => $thursStart,
                'thursEnd' => $thursEnd,
                'friStart' => $friStart,
                'friEnd' => $friEnd,
                'satStart' => $satStart,
                'satEnd' => $satEnd,
                'sunStart' => $sunStart,
                'sunEnd' => $sunEnd
            ];

            $headers = [
                'Authorization: Bearer your_token',
                'Content-Type: application/x-www-form-urlencoded'
            ];

            $response = $app->apiPost(
                'http://camera.ellm.io:8190/api/ac_timezone/merge', 
                $apiData, 
                $headers
            );

            // Giải mã phản hồi từ API
            $apiResponse = json_decode($response, true);   
            // Kiểm tra phản hồi từ API
            if (!empty($apiResponse['success']) && $apiResponse['success'] === true) {
                // Thêm dữ liệu vào database
                $app->insert("timeperiod", $insert);
                echo json_encode(["status" => "success", "content" => $jatbi->lang("Thêm khung thời gian thành công")]);
            } else {
                $errorMessage = $apiResponse['msg'] ?? "Không rõ lỗi";
                echo json_encode(["status" => "error", "content" => $errorMessage]);
            }

        } catch (Exception $e) {
            // Xử lý lỗi ngoại lệ
            echo json_encode(["status" => "error", "content" => "Lỗi: " . $e->getMessage()]);
        }
    })->setPermissions(['timeperiod.add']);

    // Sửa timeperiod
    $app->router("/manager/timeperiod-edit", 'GET', function($vars) use ($app, $jatbi) {
        $vars['title'] = $jatbi->lang("Chỉnh sửa thời gian làm việc");
        
        $acTzNumber = $app->xss($_GET['box'] ?? '');
        if (empty($acTzNumber)) {
            echo $app->render('templates/common/error-modal.html', $vars, 'global');
            return;
        }

        $vars['data'] = $app->get("timeperiod", "*", ["acTzNumber" => $acTzNumber]);
        if ($vars['data']) {
            // Đảm bảo dữ liệu trả về có acTzname (tương ứng với name trong database)
            $vars['data']['acTzname'] = $vars['data']['name'];
            echo $app->render('templates/employee/timeperiod-post.html', $vars, 'global');
        } else {
            echo $app->render('templates/common/error-modal.html', $vars, 'global');
        }
    })->setPermissions(['timeperiod.edit']);

    $app->router("/manager/timeperiod-edit", 'POST', function($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        
        $acTzNumber = $app->xss($_POST['acTzNumber'] ?? '');
        $acTzname = $app->xss($_POST['acTzname'] ?? '');
        $monStart = $app->xss($_POST['monStart'] ?? '');
        $monEnd = $app->xss($_POST['monEnd'] ?? '');
        $tueStart = $app->xss($_POST['tueStart'] ?? '');
        $tueEnd = $app->xss($_POST['tueEnd'] ?? '');
        $wedStart = $app->xss($_POST['wedStart'] ?? '');
        $wedEnd = $app->xss($_POST['wedEnd'] ?? '');
        $thursStart = $app->xss($_POST['thursStart'] ?? '');
        $thursEnd = $app->xss($_POST['thursEnd'] ?? '');
        $friStart = $app->xss($_POST['friStart'] ?? '');
        $friEnd = $app->xss($_POST['friEnd'] ?? '');
        $satStart = $app->xss($_POST['satStart'] ?? '');
        $satEnd = $app->xss($_POST['satEnd'] ?? '');
        $sunStart = $app->xss($_POST['sunStart'] ?? '');
        $sunEnd = $app->xss($_POST['sunEnd'] ?? '');
        // Các trường mới chỉ lưu vào database
        $mon_off = isset($_POST['mon_off']) && $_POST['mon_off'] == '1' ? 1 : 0;
        $tue_off = isset($_POST['tue_off']) && $_POST['tue_off'] == '1' ? 1 : 0;
        $wed_off = isset($_POST['wed_off']) && $_POST['wed_off'] == '1' ? 1 : 0;
        $thu_off = isset($_POST['thu_off']) && $_POST['thu_off'] == '1' ? 1 : 0;
        $fri_off = isset($_POST['fri_off']) && $_POST['fri_off'] == '1' ? 1 : 0;
        $sat_off = isset($_POST['sat_off']) && $_POST['sat_off'] == '1' ? 1 : 0;
        $sun_off = isset($_POST['sun_off']) && $_POST['sun_off'] == '1' ? 1 : 0;
        $mon_work_credit = $app->xss($_POST['mon_work_credit'] ?? '0');
        $tue_work_credit = $app->xss($_POST['tue_work_credit'] ?? '0');
        $wed_work_credit = $app->xss($_POST['wed_work_credit'] ?? '0');
        $thu_work_credit = $app->xss($_POST['thu_work_credit'] ?? '0');
        $fri_work_credit = $app->xss($_POST['fri_work_credit'] ?? '0');
        $sat_work_credit = $app->xss($_POST['sat_work_credit'] ?? '0');
        $sun_work_credit = $app->xss($_POST['sun_work_credit'] ?? '0');
        $breakTime = $app->xss($_POST['breakTime'] ?? '');
        $note = $app->xss($_POST['note'] ?? '');
        $status = $app->xss($_POST['status'] ?? '1');

        // Kiểm tra dữ liệu đầu vào
        if (empty($acTzNumber) || empty($acTzname)) {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống: Mã Khung thời gian và Tên Khung thời gian")]);
            return;
        }

        try {
            // Dữ liệu để cập nhật vào database (bao gồm cả các trường mới)
            $updateData = [
                "acTzNumber" => $acTzNumber,
                "name" => $acTzname,
                "monStart" => $monStart,
                "monEnd" => $monEnd,
                "tueStart" => $tueStart,
                "tueEnd" => $tueEnd,
                "wedStart" => $wedStart,
                "wedEnd" => $wedEnd,
                "thursStart" => $thursStart,
                "thursEnd" => $thursEnd,
                "friStart" => $friStart,
                "friEnd" => $friEnd,
                "satStart" => $satStart,
                "satEnd" => $satEnd,
                "sunStart" => $sunStart,
                "sunEnd" => $sunEnd,
                "mon_off" => $mon_off,
                "tue_off" => $tue_off,
                "wed_off" => $wed_off,
                "thu_off" => $thu_off,
                "fri_off" => $fri_off,
                "sat_off" => $sat_off,
                "sun_off" => $sun_off,
                "mon_work_credit" => $mon_work_credit,
                "tue_work_credit" => $tue_work_credit,
                "wed_work_credit" => $wed_work_credit,
                "thu_work_credit" => $thu_work_credit,
                "fri_work_credit" => $fri_work_credit,
                "sat_work_credit" => $sat_work_credit,
                "sun_work_credit" => $sun_work_credit,
                "breakTime" => $breakTime,
                "note" => $note,
                "status" => $status
            ];

            // Dữ liệu gửi lên API (chỉ bao gồm các trường cũ)
            $updateDataAPI = [
                "acTzNumber" => $acTzNumber,
                "acTzName" => $acTzname,
                "monStart" => $monStart,
                "monEnd" => $monEnd,
                "tueStart" => $tueStart,
                "tueEnd" => $tueEnd,
                "wedStart" => $wedStart,
                "wedEnd" => $wedEnd,
                "thursStart" => $thursStart,
                "thursEnd" => $thursEnd,
                "friStart" => $friStart,
                "friEnd" => $friEnd,
                "satStart" => $satStart,
                "satEnd" => $satEnd,
                "sunStart" => $sunStart,
                "sunEnd" => $sunEnd
            ];

            $jatbi->logs('timeperiod', 'timeperiod-edit', $updateData);

            $apiData = array_merge(['deviceKey' => '77ed8738f236e8df86', 'secret' => '123456'], $updateDataAPI);
            $headers = ['Authorization: Bearer your_token', 'Content-Type: application/x-www-form-urlencoded'];

            $response = $app->apiPost('http://camera.ellm.io:8190/api/ac_timezone/merge', $apiData, $headers);
            $apiResponse = json_decode($response, true);

            if (!empty($apiResponse['success']) && $apiResponse['success'] === true) {
                $app->update("timeperiod", $updateData, ["acTzNumber" => $acTzNumber]);
                echo json_encode(["status" => "success", "content" => $jatbi->lang("Cập nhật khung thời gian thành công")]);
            } else {
                echo json_encode(["status" => "error", "content" => $apiResponse['msg'] ?? "Không rõ lỗi"]);
            }
        } catch (Exception $e) {
            echo json_encode(["status" => "error", "content" => "Lỗi: " . $e->getMessage()]);
        }
    })->setPermissions(['timeperiod.edit']);

    // Xóa timeperiod
    $app->router("/manager/timeperiod-deleted", 'GET', function($vars) use ($app, $jatbi) {
        $vars['title'] = $jatbi->lang("Xóa Khung thời gian");
        echo $app->render('templates/common/deleted.html', $vars, 'global');
    })->setPermissions(['timeperiod.deleted']);

    $app->router("/manager/timeperiod-deleted", 'POST', function($vars) use ($app, $jatbi) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);

        $acTzNumber = $app->xss($_GET['box']);
        try {            
            $headers = [
                'Authorization: Bearer your_token',
                'Content-Type: application/x-www-form-urlencoded'
            ];
            $apiData = [
                'deviceKey' => '77ed8738f236e8df86',
                'secret'    => '123456',
                'acTzNumber' => $acTzNumber,
            ];
            $response = $app->apiPost(
                'http://camera.ellm.io:8190/api/ac_timezone/delete', 
                $apiData, 
                $headers
            );
            $apiResponse = json_decode($response, true);

            // Kiểm tra phản hồi từ API
            if (!empty($apiResponse['success']) && $apiResponse['success'] === true) {
                // Xóa dữ liệu trong database
                if (is_string($acTzNumber)) {
                    $acTzNumbers = explode(',', $acTzNumber); // Split by comma
                    foreach ($acTzNumbers as $number) {
                        $app->delete("timeperiod", ["acTzNumber" => trim($number)]); // Trim to remove extra spaces
                    }
                } else {
                    $app->delete("timeperiod", ["acTzNumber" => $acTzNumber]);
                }
                echo json_encode(["status" => "success", "content" => $jatbi->lang("Xóa khung thời gian thành công")]);
            } else {
                $errorMessage = $apiResponse['msg'] ?? "Không rõ lỗi";
                echo json_encode(["status" => "error", "content" => $errorMessage]);
            }

        } catch (Exception $e) {
            // Xử lý lỗi ngoại lệ
            echo json_encode(["status" => "error", "content" => "Lỗi: " . $e->getMessage()]);
        }
    })->setPermissions(['timeperiod.deleted']);


    //Đồng bộ dữ liệu từ server
    $app->router("/manager/timeperiod-sync", 'GET', function($vars) use ($app, $jatbi) {
        $vars['title'] = $jatbi->lang("Xóa Khung thời gian");
        echo $app->render('templates/common/restore.html', $vars, 'global');
    })->setPermissions(['timeperiod.sync']);

    $app->router("/manager/timeperiod-sync", 'POST', function($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);

        try {
            $headers = [
                'Authorization: Bearer your_token',
                'Content-Type: application/x-www-form-urlencoded'
            ];
            $apiData = [
                'deviceKey' => '77ed8738f236e8df86',
                'secret'    => '123456'
            ];
            
            // Gửi yêu cầu đến API để lấy dữ liệu
            $response = $app->apiPost(
                'http://camera.ellm.io:8190/api/ac_timezone/findList', 
                $apiData, 
                $headers
            );
            
            $apiResponse = json_decode($response, true);
            
            // Kiểm tra phản hồi từ API
            if (!empty($apiResponse['success']) && $apiResponse['success'] === true) {
                $data = $apiResponse['data'] ?? [];

                 // Lấy danh sách tất cả acTzNumber từ cơ sở dữ liệu
                $existingRecords = $app->select("timeperiod", ["acTzNumber"]);
                $existingAcTzNumbers = array_column($existingRecords, 'acTzNumber'); // Chuyển thành mảng đơn giản

                // Lấy danh sách acTzNumber từ $data
                $newAcTzNumbers = array_column($data, 'acTzNumber');

                // Tìm các acTzNumber cần xóa (có trong database nhưng không có trong $data)
                $acTzNumbersToDelete = array_diff($existingAcTzNumbers, $newAcTzNumbers);

                // Xóa các acTzNumber không có trong $data
                foreach ($acTzNumbersToDelete as $acTzNumber) {
                    $app->delete("timeperiod", ["acTzNumber" => $acTzNumber]);
                }

                //Đồng bộ dữ liệu vào database
                foreach ($data as $item) {
                    $acTzNumber = $item['acTzNumber'];
                    $insert2 = [
                        "acTzNumber" => $item['acTzNumber'] ?? null,
                        "monStart" => $item['monStart'] ?? '',
                        "monEnd" => $item['monEnd'] ?? '',
                        "tueStart" => $item['tueStart'] ?? '',
                        "tueEnd" => $item['tueEnd'] ?? '',
                        "wedStart" => $item['wedStart'] ?? '',
                        "wedEnd" => $item['wedEnd'] ?? '',
                        "thursStart" => $item['thursStart'] ?? '',
                        "thursEnd" => $item['thursEnd'] ?? '',
                        "friStart" => $item['friStart'] ?? '',
                        "friEnd" => $item['friEnd'] ?? '',
                        "satStart" => $item['satStart'] ?? '',
                        "satEnd" => $item['satEnd'] ?? '',
                        "sunStart" => $item['sunStart'] ?? '',
                        "sunEnd" => $item['sunEnd'] ?? ''
                    ];
                    $app->insert("timeperiod", $insert2);
                    $app->update("timeperiod", $insert2, ["acTzNumber" => $acTzNumber]);
                }
                
                echo json_encode(["status" => "success", "content" => $jatbi->lang("Đồng bộ thành công công")]);
            } else {
                $errorMessage = $apiResponse['msg'] ?? "Không rõ lỗi";
                echo json_encode(["status" => "error", "content" => $errorMessage]);
            }
        } catch (Exception $e) {
            // Xử lý lỗi ngoại lệ
            echo json_encode(["status" => "error", "content" => "Lỗi: " . $e->getMessage()]);
        }
    })->setPermissions(['timeperiod.sync']);
    
    //Cấp phép stattus
    $app->router("/timeperiod-status/{acTzNumber}", 'POST', function($vars) use ($app, $jatbi) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);

        $data = $app->get("timeperiod","*",["acTzNumber"=>$vars['acTzNumber']]);
        if($data>1){
            if($data>1){
                if($data['status']==='A'){
                    $status = "D";
                } 
                elseif($data['status']==='D'){
                    $status = "A";
                }
                $app->update("timeperiod",["status"=>$status],["acTzNumber"=>$data['acTzNumber']]);
                $jatbi->logs('timeperiod','timeperiod-status',$data);
                echo json_encode(value: ['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
            }
            else {
                echo json_encode(['status'=>'error','content'=>$jatbi->lang("Cập nhật thất bại"),]);
            }
        }
        else {
            echo json_encode(["status"=>"error","content"=>$jatbi->lang("Không tìm thấy dữ liệu")]);
        }
    })->setPermissions(['timeperiod.edit']);

    // $app->router("/manager/timeperiod-update-status", 'POST', function($vars) use ($app, $jatbi) {
    //     $app->header([
    //         'Content-Type' => 'application/json',
    //     ]);
    
    //     // Lấy dữ liệu từ yêu cầu AJAX
    //     $input = json_decode(file_get_contents('php://input'), true);
    //     $acTzNumber = $input['acTzNumber'] ?? '';
    //     $status = $input['status'] ?? 0;
    
    //     // Kiểm tra dữ liệu đầu vào
    //     if (empty($acTzNumber)) {
    //         $app->responseJson([
    //             "status" => "error",
    //             "message" => $jatbi->lang("Không tìm thấy mã khung thời gian")
    //         ]);
    //         return;
    //     }
    
    //     // Cập nhật trạng thái
    //     $app->update("timeperiod", [
    //         "status" => $status
    //     ], ["acTzNumber" => $acTzNumber]);
    
    //     $app->responseJson([
    //         "status" => "success",
    //         "message" => $jatbi->lang("Cập nhật trạng thái thành công")
    //     ]);
    // })->setPermissions(['timeperiod.edit']);

?>
