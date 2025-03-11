<?php
    if (!defined('ECLO')) die("Hacking attempt");
    $jatbi = new Jatbi($app);
    $setting = $app->getValueData('setting');

    $app->router("/manager/employee", 'GET', function($vars) use ($app, $jatbi, $setting) {
        // Lấy danh sách nhân viên từ bảng `employee`
        $data = $app->select("employee", [
            "sn",
            "name",
            "type"
        ]);
    
        $vars['data'] = $data;
        echo $app->render('templates/employee/employee.html', $vars);
    });

    $app->router("/manager/employee-add", 'POST', function($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);

        // Khởi tạo biến lỗi
        $error = [];
    
        // Lấy dữ liệu từ form và kiểm tra
        $sn   = $app->xss($_POST['sn'] ?? '');
        $name = $app->xss($_POST['name'] ?? '');
        $type = $app->xss($_POST['type'] ?? '');
    
        if (empty($sn) || empty($name) || empty($type)) {
            $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")];
        }
    
        // Nếu có lỗi, trả về JSON ngay lập tức
        if (!empty($error)) {
            echo json_encode($error);
            return;
        }
    
        try {
            $insert = [
                "type" => 1,
                "name" => $name,
                "sn"   => $sn,
                //"type" => $type, // Nếu cần thêm type, bỏ comment dòng này
            ];
            
            // Thêm dữ liệu vào database
            $app->insert("employee", $insert);
    
            // Ghi log
            $jatbi->logs('accounts', 'accounts-add', $insert);
    
            // Trả về kết quả JSON thành công
            echo json_encode(["status" => "success", "content" => $jatbi->lang("Cập nhật thành công")]);
    
        } catch (Exception $e) {
            // Xử lý lỗi nếu có
            echo json_encode(["status" => "error", "content" => "Lỗi: " . $e->getMessage()]);
        }
    })->setPermissions(['employee.add']);
    $app->router("/manager/employee-edit", 'POST', function($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        
        $error = [];
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $sn = $app->xss($input['sn'] ?? '');
        $name = $app->xss($input['name'] ?? '');
        $type = $app->xss($input['type'] ?? '');
        
        if (empty($sn)) $error['sn'] = $jatbi->lang("Vui lòng nhập mã nhân viên");
        if (empty($name)) $error['name'] = $jatbi->lang("Vui lòng nhập tên nhân viên");
        if (empty($type)) $error['type'] = $jatbi->lang("Vui lòng chọn loại nhân viên");
        elseif (!in_array($type, ['1', '2', '3'])) $error['type'] = $jatbi->lang("Loại nhân viên không hợp lệ");
    
        if (!empty($error)) {
            echo json_encode([
                "status" => "error",
                "content" => $jatbi->lang("Dữ liệu không hợp lệ"),
                "errors" => $error
            ]);
            return;
        }
        
        try {
            $existing = $app->select("employee", "*", ["sn" => $sn]);
            if (empty($existing)) {
                echo json_encode([
                    "status" => "error",
                    "content" => $jatbi->lang("Nhân viên không tồn tại")
                ]);
                return;
            }
            
            $update = ["name" => $name, "type" => $type];
            $affected = $app->update("employee", $update, ["sn" => $sn]);
            
            if ($affected) {
                $apiData = [
                    "deviceKey" => "77ed8738f236e8df86",
                    "secret" => "123456",
                    "type" => $type, // Chuyển type thành số nguyên
                    "sn" => $sn,     // Chuyển sn thành số nguyên (giả định API yêu cầu số)
                    "name" => $name // Xóa khoảng trắng thừa
                ];
            
                error_log("API Data Sent: " . json_encode($apiData)); // Log dữ liệu gửi đi
            
                // Gửi yêu cầu POST đến API
                $response = $app->apiPost('http://camera.ellm.io:8190/api/person/update', $apiData, [
                    'Authorization: Bearer your_token',
                    'Content-Type: application/x-www-form-urlencoded'
                ]);
            
                // Kiểm tra lỗi từ $app->apiPost (giả định hàm trả về mảng hoặc JSON)
                if ($response === false || is_null($response)) {
                    error_log("API Error: " . $error);
                    echo json_encode([
                        "status" => "warning",
                        "content" => $jatbi->lang("Cập nhật nhân viên thành công nhưng không thể kết nối API camera"),
                        "error" => $error
                    ]);
                } else {
                    $responseData = json_decode($response, true);
            
                    if (isset($responseData['success']) && $responseData['success'] === true) {
                        echo json_encode([
                            "status" => "success",
                            "content" => $jatbi->lang("Cập nhật nhân viên và API camera thành công"),
                            "data" => array_merge(["sn" => $sn], $update),
                            "api_response" => $response
                        ]);
                    } else {
                        echo json_encode([
                            "status" => "warning",
                            "content" => $jatbi->lang("Cập nhật nhân viên thành công nhưng API camera không cập nhật"),
                            "api_response" => $response,
                            "data_sent" => $apiData
                        ]);
                    }
                }
            } else {
                echo json_encode([
                    "status" => "error",
                    "content" => $jatbi->lang("Không có thay đổi nào được thực hiện")
                ]);
            }
        } catch (Exception $e) {
            echo json_encode([
                "status" => "error",
                "content" => $jatbi->lang("Lỗi hệ thống: ") . $e->getMessage()
            ]);
        }
    })->setPermissions(['employee.edit']);

 ?>

   

