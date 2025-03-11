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
    
?>

