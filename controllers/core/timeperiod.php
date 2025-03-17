<?php
    if (!defined('ECLO')) die("Hacking attempt");
    $jatbi = new Jatbi($app);
    $setting = $app->getValueData('setting');

// Khung thời gian
    $app->router("/manager/timeperiod", 'GET', function($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Khung thời gian");
        $vars['add'] = '/manager/timeperiod-add';
        $vars['deleted'] = '/manager/timeperiod-deleted';
        $vars['sync'] = '/manager/timeperiod-sync';
        $data = $app->select("timeperiod", ["acTzNumber","name","monStart","monEnd","tueStart","tueEnd","wedStart","wedEnd","thursStart","thursEnd","friStart","friEnd","satStart","satEnd","sunStart","sunEnd"]);
        $vars['data'] = $data;
        echo $app->render('templates/employee/timeperiod.html', $vars);
    })->setPermissions(['timeperiod']);

    $app->router("/manager/timeperiod", 'POST', function($vars) use ($app, $jatbi) {
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
        $validColumns = ["checkbox","acTzNumber","name","monStart","monEnd","tueStart","tueEnd","wedStart","wedEnd","thursStart","thursEnd","friStart","friEnd","satStart","satEnd","sunStart","sunEnd"];
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
            'acTzNumber', 
            'name', 
            'monStart', 
            'monEnd', 
            'tueStart', 
            'tueEnd', 
            'wedStart', 
            'wedEnd', 
            'thursStart', 
            'thursEnd', 
            'friStart', 
            'friEnd', 
            'satStart', 
            'satEnd', 
            'sunStart', 
            'sunEnd'
        ], $where) ?? [];
    
        // Log dữ liệu truy vấn để kiểm tra
        error_log("Fetched Timeperiods Data: " . print_r($datas, true));
    
        // Xử lý dữ liệu đầu ra
        $formattedData = array_map(function($data) use ($app, $jatbi) {
            return [
                "checkbox" => "<input type='checkbox' class='checker' id=box value='{$data['acTzNumber']}'>",
                "acTzNumber" => $data['acTzNumber'],
                "name" => $data['name'],
                "mon" => "{$data['monStart']} : {$data['monEnd']}",
                "tue" => "{$data['tueStart']} : {$data['tueEnd']}",
                "wed" => "{$data['wedStart']} : {$data['wedEnd']}",
                "thurs" => "{$data['thursStart']} : {$data['thursEnd']}",
                "fri" => "{$data['friStart']} : {$data['friEnd']}",
                "sat" => "{$data['satStart']} : {$data['satEnd']}",
                "sun" => "{$data['sunStart']} : {$data['sunEnd']}",                         
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

    //Thêm hoặc Sửa timeperiod
    $app->router("/manager/timeperiod-add", 'GET', function($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Khung thời gian");
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
    
        // Kiểm tra dữ liệu đầu vào
        if (empty($acTzNumber) || empty($acTzname) || empty($monStart) || empty($monEnd) || empty($tueStart) || empty($tueEnd) || empty($wedStart) || empty($wedEnd) || empty($thursStart) || empty($thursEnd) || empty($friStart) || empty($friEnd) || empty($satStart) || empty($satEnd) || empty($sunStart) || empty($sunEnd)) {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống: $acTzname, $acTzNumber")]);
            return;
        }
    
        try {
            // Dữ liệu để lưu vào database
            $insert = [
                "acTzNumber" => $acTzNumber,
                "name" => $acTzname,
                "monStart" => $app->xss($_POST['monStart'] ?? ''),
                "monEnd" => $app->xss($_POST['monEnd'] ?? ''),
                "tueStart" => $app->xss($_POST['tueStart'] ?? ''),
                "tueEnd" => $app->xss($_POST['tueEnd'] ?? ''),
                "wedStart" => $app->xss($_POST['wedStart'] ?? ''),
                "wedEnd" => $app->xss($_POST['wedEnd'] ?? ''),
                "thursStart" => $app->xss($_POST['thursStart'] ?? ''),
                "thursEnd" => $app->xss($_POST['thursEnd'] ?? ''),
                "friStart" => $app->xss($_POST['friStart'] ?? ''),
                "friEnd" => $app->xss($_POST['friEnd'] ?? ''),
                "satStart" => $app->xss($_POST['satStart'] ?? ''),
                "satEnd" => $app->xss($_POST['satEnd'] ?? ''),
                "sunStart" => $app->xss($_POST['sunStart'] ?? ''),
                "sunEnd" => $app->xss($_POST['sunEnd'] ?? '')
            ];
              
            // Ghi log
            $jatbi->logs('timeperiod', 'timeperiod-add', $insert);
    
            // Dữ liệu gửi lên API
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
                echo json_encode(["status" => "success", "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                $errorMessage = $apiResponse['msg'] ?? "Không rõ lỗi";
                echo json_encode(["status" => "error", "content" => $errorMessage]);
            }
    
        } catch (Exception $e) {
            // Xử lý lỗi ngoại lệ
            echo json_encode(["status" => "error", "content" => "Lỗi: " . $e->getMessage()]);
        }
    })->setPermissions(['timeperiod.add']);

    //Xóa timeperiod
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
                echo json_encode(["status" => "success", "content" => $jatbi->lang("Cập nhật thành công")]);
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
                $app->delete("timeperiod", []);
                //Đồng bộ dữ liệu vào database
                foreach ($data as $item) {
                    $app->insert("timeperiod", [
                        "acTzNumber" => $item['acTzNumber'] ?? null,
                        "name" => $item['name'] ?? '', // Nếu có trường 'name' trong dữ liệu trả về
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
                    ]);
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

?>
