<?php
    if (!defined('ECLO')) die("Hacking attempt");
    $jatbi = new Jatbi($app);
    $setting = $app->getValueData('setting');

    // Nhảy Ca
    $app->router("/shift", 'GET', function($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Nhảy Ca");
        $vars['tangca'] = array_map(function($employee) {
            return $employee['acTzNumber'] . ' - ' . $employee['name'];
        }, $app->select("timeperiod", ["name", "acTzNumber"], ["status" => "A"]));
        echo $app->render('templates/employee/shift.html', $vars);
    })->setPermissions(['shift']);

    $app->router("/shift", 'POST', function($vars) use ($app, $jatbi) {
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
        $statu = $_POST['statu'] ?? ''; // Lọc theo trạng thái
        $shift = $_POST['shift'] ?? ''; // Lọc theo ca 1
        $shift2 = $_POST['shift2'] ?? ''; // Lọc theo ca 2

        // Fix lỗi ORDER cột
        $orderColumnIndex = $_POST['order'][0]['column'] ?? 1; // Mặc định cột employee
        $orderDir = strtoupper($_POST['order'][0]['dir'] ?? 'DESC');

        // Danh sách cột hợp lệ
        $validColumns = ["checkbox", "employee", "shift", "shift2", "dayCreat", "note"];
        $orderColumn = $validColumns[$orderColumnIndex] ?? "employee";

        // Điều kiện lọc dữ liệu
        $conditions = ["AND" => []];

        // Tìm kiếm toàn cục (searchValue)
        if (!empty($searchValue)) {
            $conditions["AND"]["OR"] = [
                "employee.name[~]" => $searchValue, // Tìm kiếm theo tên nhân viên
                "shift.note[~]" => $searchValue,
                "shift.shift[~]" => $searchValue,
                "shift.shift2[~]" => $searchValue,
                "shift.dayCreat[~]" => $searchValue,
            ];
        }

        // Lọc theo trạng thái (statu)
        if (!empty($statu)) {
            $conditions["AND"]["shift.statu"] = $statu;
        }

        // Lọc theo ca 1 (shift)
        if (!empty($shift)) {
            $conditions["AND"]["shift.shift"] = $shift;
        }

        // Lọc theo ca 2 (shift2)
        if (!empty($shift2)) {
            $conditions["AND"]["shift.shift2"] = $shift2;
        }

        // Kiểm tra nếu conditions bị trống, tránh lỗi SQL
        if (empty($conditions["AND"])) {
            unset($conditions["AND"]);
        }

        // Đếm tổng số bản ghi (không dùng LIMIT)
        $count = $app->count("shift", [
            "[>]employee" => ["employee" => "sn"]
        ], "shift.idshift", $conditions);

        // Truy vấn danh sách Nhảy ca
        $datas = $app->select("shift", [
            "[>]employee" => ["employee" => "sn"] // Thực hiện JOIN: shift.employee -> employee.sn
        ], [
            'shift.idshift',
            'employee.name(employee_name)', // Lấy tên nhân viên từ bảng employee
            'shift.shift',
            'shift.day',
            'shift.timeStart',
            'shift.timeEnd',
            'shift.shift2',
            'shift.day2',
            'shift.timeStart2',
            'shift.timeEnd2',
            'shift.statu',
            'shift.dayCreat',
            'shift.note'
        ], array_merge($conditions, [
            "LIMIT" => [$start, $length],
            "ORDER" => [$orderColumn => $orderDir]
        ])) ?? [];

        // Log dữ liệu truy vấn để kiểm tra
        error_log("Fetched shifts Data: " . print_r($datas, true));

        // Xử lý dữ liệu đầu ra
        $formattedData = array_map(function($data) use ($app, $jatbi) {
            $shiftLabels = array_column($app->select("timeperiod", ["acTzNumber", "name"]), "name", "acTzNumber");

            $temp = $shiftLabels[$data['shift']] ?? $jatbi->lang("Không xác định");
            $temp2 = $shiftLabels[$data['shift2']] ?? $jatbi->lang("Không xác định");

            // Tùy chỉnh hiển thị trạng thái (statu) với màu sắc
            $statuDisplay = $data['statu'];
            if ($data['statu'] === 'Approved') {
                $statuDisplay = '<a href="#" class="status-link" data-url="/shift-status/' . $data['idshift'] . '" data-action="modal">' . $data['statu'] . '</a>';
            } elseif ($data['statu'] === 'Pending') {
                $statuDisplay = '<a href="#" class="status-link" style="color: green;" data-url="/shift-status/' . $data['idshift'] . '" data-action="modal">' . $data['statu'] . '</a>';
            }

            return [
                "checkbox" => $app->component("box", ["data" => $data['idshift']]),
                "idshift" => $data['idshift'],
                "employee" => $data['employee_name'] ?? $jatbi->lang("Không xác định"), // Hiển thị tên nhân viên
                "shift" => "{$temp} : {$data['day']} || {$data['timeStart']} : {$data['timeEnd']}",
                "shift2" => "{$temp2} : {$data['day2']} || {$data['timeStart2']} : {$data['timeEnd2']}",
                "note" => $data['note'],
                "statu" => $statuDisplay,
                "dayCreat" => $data['dayCreat'],
                "action" => $app->component("action", [
                    "button" => [          
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Sửa"),
                            'permission' => ['shift.edit'],
                            'action' => ['data-url' => '/shift-edit?idshift=' . $data['idshift'], 'data-action' => 'modal']
                        ],
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Xóa"),
                            'permission' => ['shift.deleted'],
                            'action' => ['data-url' => '/shift-deleted?idshift=' . $data['idshift'], 'data-action' => 'modal']
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
    })->setPermissions(['shift']);

    //Thêm shift
    $app->router("/shift-add", 'GET', function($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Thêm Nhảy Ca");
        $vars['nv1'] = array_map(function($employee) {
            return $employee['sn'] . ' - ' . $employee['name'];
        }, $app->select("employee", ["name", "sn"], ["status" => "A"]));
        $vars['ca'] = array_map(function($employee) {
            return $employee['acTzNumber'] . ' - ' . $employee['name'];
        }, $app->select("timeperiod", ["name", "acTzNumber"], ["status" => "A"]));

        echo $app->render('templates/employee/shift-post.html', $vars, 'global');
    })->setPermissions(['shift.add']);
    
    $app->router("/shift-add", 'POST', function($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
    
        // Lấy dữ liệu từ form và kiểm tra XSS
        $idshift = $app->count("shift") + 1;
        $employee = isset($_POST['employee']) ? $app->xss($_POST['employee']) : '';
        $shift = isset($_POST['shift']) ? $app->xss($_POST['shift']) : '';
        $day = isset($_POST['day']) ? $app->xss($_POST['day']) : '';
        $timeStart = isset($_POST['timeStart']) ? $app->xss($_POST['timeStart']) : '';
        $timeEnd = isset($_POST['timeEnd']) ? $app->xss($_POST['timeEnd']) : '';
        $shift2 = isset($_POST['shift2']) ? $app->xss($_POST['shift2']) : '';
        $day2 = isset($_POST['day2']) ? $app->xss($_POST['day2']) : '';
        $timeStart2 = isset($_POST['timeStart2']) ? $app->xss($_POST['timeStart2']) : '';
        $timeEnd2 = isset($_POST['timeEnd2']) ? $app->xss($_POST['timeEnd2']) : '';
        $statu = isset($_POST['statu']) ? $app->xss($_POST['statu']) : '';
        $note = isset($_POST['note']) ? $app->xss($_POST['note']) : '';
        date_default_timezone_set('Asia/Ho_Chi_Minh'); // Set timezone to Vietnam
        $dayCreat = date('Y-m-d H:i:s');
        
        // Kiểm tra dữ liệu đầu vào
        if (empty($employee) || empty($shift) || empty($day) || empty($timeStart) || empty($timeEnd) || empty($shift2) || empty($day2) || empty($timeStart2) || empty($timeEnd2)) {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống!")]);
            return;
        }
        // Kiểm tra giờ bắt đầu không lớn hơn giờ kết thúc
        if (strtotime($timeStart) > strtotime($timeEnd) || (!empty($timeStart2) && strtotime($timeStart2) > strtotime($timeEnd2))) {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Giờ bắt đầu không được sau giờ kết thúc")]);
            return;
        }
        try {
            $temp = substr($shift, 0, strpos($shift, " -"));
            $temp2 = substr($shift2, 0, strpos($shift2, " -"));
            $temp3 = substr($employee, 0, strpos($employee, " -"));

            // Dữ liệu để lưu vào database
            $insert = ["idshift" => $idshift, "employee" => $temp3, "shift" => $temp, "day" => $day, "timeStart" => $timeStart, "timeEnd" => $timeEnd, "shift2" => $temp2, "day2" => $day2, "timeStart2" => $timeStart2, "timeEnd2" => $timeEnd2, "statu" => $statu, "note" => $note, "dayCreat" => $dayCreat];
              
            // Ghi log
            $jatbi->logs('shift', 'shift-add', $insert);
   
            // Thêm dữ liệu vào database
            $app->insert("shift", $insert);

            echo json_encode(["status" => "success", "content" => $jatbi->lang("Thêm thành công")]);
    
        } catch (Exception $e) {
            // Xử lý lỗi ngoại lệ
            echo json_encode(["status" => "error", "content" => "Lỗi: " . $e->getMessage()]);
        }
    })->setPermissions(['shift.add']);

    //Xóa shift
    $app->router("/shift-deleted", 'GET', function($vars) use ($app, $jatbi) {
        $vars['title'] = $jatbi->lang("Xóa Nhảy ca");

        echo $app->render('templates/common/deleted.html', $vars, 'global');
    })->setPermissions(['shift.deleted']);
    
    $app->router("/shift-deleted", 'POST', function($vars) use ($app, $jatbi) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);

        if (!empty($_GET['idshift'])) {
            $idshift = $app->xss($_GET['idshift']);
        } elseif (!empty($_GET['box'])) {
            $idshift = $app->xss($_GET['box']);
        }
        
        try {              
            // Xóa dữ liệu trong database
            if (is_string($idshift)) {
                $idshift = explode(',', $idshift); // Split by comma
                foreach ($idshift as $number) {
                    $app->delete("shift", ["idshift" => trim($number)]); // Trim to remove extra spaces
                }
            } else {
                $app->delete("shift", ["idshift" => $idshift]);
            }
            echo json_encode(["status" => "success", "content" => $jatbi->lang("Xóa nhảy ca thành công")]);
        } catch (Exception $e) {
            // Xử lý lỗi ngoại lệ
            echo json_encode(["status" => "error", "content" => "Lỗi: " . $e->getMessage()]);
        }
    })->setPermissions(['shift.deleted']);

    //Sửa shift
    $app->router("/shift-edit", 'GET', function($vars) use ($app, $jatbi) {
        $vars['title'] = $jatbi->lang("Sửa Nhảy ca");
        $vars['nv1'] = array_map(function($employee) {
            return $employee['sn'] . ' - ' . $employee['name'];
        }, $app->select("employee", ["name", "sn"], ["status" => "A"]));
        $vars['ca'] = array_map(function($employee) {
            return $employee['acTzNumber'] . ' - ' . $employee['name'];
        }, $app->select("timeperiod", ["name", "acTzNumber"], ["status" => "A"]));

        $idshift = isset($_GET['idshift']) ? $app->xss($_GET['idshift']) : null;

        if (!$idshift) {
            echo $app->render('templates/common/error-modal.html', $vars, 'global');
            return;
        }
        $vars['data'] = $app->get("shift", "*", ["idshift" => $idshift]);
        $vars ['data']['edit'] = true;
        if ($vars['data']) {
            echo $app->render('templates/employee/shift-post.html', $vars, 'global');
        } else {
            echo $app->render('templates/common/error-modal.html', $vars, 'global');
        }
    })->setPermissions(['shift.edit']);

    $app->router("/shift-edit", 'POST', function($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);

        // Lấy mã nhân viên từ request
        $idshift = isset($_POST['idshift']) ? $app->xss($_POST['idshift']) : null;
        if (!$idshift) {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Mã nhảy ca không hợp lệ")]);
            return;
        }    
    
        // Lấy thông tin nhân viên từ DB
        $data = $app->get("shift", "*", ["idshift" => $idshift]);
        if (!$data) {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy Nhảy ca")]);
            return;
        }
    
        // Kiểm tra dữ liệu đầu vào
        $employee = isset($_POST['employee']) ? $app->xss($_POST['employee']) : '';
        $shift = isset($_POST['shift']) ? $app->xss($_POST['shift']) : '';
        $day = isset($_POST['day']) ? $app->xss($_POST['day']) : '';
        $timeStart = isset($_POST['timeStart']) ? $app->xss($_POST['timeStart']) : '';
        $timeEnd = isset($_POST['timeEnd']) ? $app->xss($_POST['timeEnd']) : '';
        $shift2 = isset($_POST['shift2']) ? $app->xss($_POST['shift2']) : '';
        $day2 = isset($_POST['day2']) ? $app->xss($_POST['day2']) : '';
        $timeStart2 = isset($_POST['timeStart2']) ? $app->xss($_POST['timeStart2']) : '';
        $timeEnd2 = isset($_POST['timeEnd2']) ? $app->xss($_POST['timeEnd2']) : '';
        $statu = isset($_POST['statu']) ? $app->xss($_POST['statu']) : '';
        $note = isset($_POST['note']) ? $app->xss($_POST['note']) : '';
        date_default_timezone_set('Asia/Ho_Chi_Minh'); // Set timezone to Vietnam
        $dayCreat = date('Y-m-d H:i:s');
        
        if (empty($employee) || empty($shift) || empty($day) || empty($timeStart) || empty($timeEnd) || empty($shift2) || empty($day2) || empty($timeStart2) || empty($timeEnd2)) {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống!")]);
            return;
        }
        // Kiểm tra giờ bắt đầu không lớn hơn giờ kết thúc
        if (strtotime($timeStart) > strtotime($timeEnd) || (!empty($timeStart2) && strtotime($timeStart2) > strtotime($timeEnd2))) {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Giờ bắt đầu không được sau giờ kết thúc")]);
            return;
        }
        $temp = substr($shift, 0, strpos($shift, " -"));
        $temp2 = substr($shift2, 0, strpos($shift2, " -"));
        $temp3 = substr($employee, 0, strpos($employee, " -"));

        // Cập nhật dữ liệu trong database
        $update = [
            "employee"  => $temp3,
            "shift"     => $temp,
            "day"       => $day,
            "timeStart" => $timeStart,
            "timeEnd"   => $timeEnd,
            "shift2"    => $temp2,
            "day2"      => $day2,
            "timeStart2"=> $timeStart2,
            "timeEnd2"  => $timeEnd2,
            "statu"     => $statu,
            "note"      => $note,
            "dayCreat"  => $dayCreat,
        ];
    
        $app->update("shift", $update, ["idshift" => $idshift]);
    
        // Ghi log cập nhật
        $jatbi->logs('shift', 'shift-edit', $update);
    
        echo json_encode(["status" => "success", "content" => $jatbi->lang("Cập nhật nhảy ca thành công")]);

    })->setPermissions(['shift.edit']);

    //Cấp phép shift
    $app->router("/shift-status/{idshift}", 'POST', function($vars) use ($app, $jatbi) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);

        $data = $app->get("shift","*",["idshift"=>$vars['idshift']]);
        if($data>1){
            if($data>1){
                if($data['statu']==='A'){
                    $status = "D";
                } 
                elseif($data['statu']==='D'){
                    $status = "A";
                }
                $app->update("shift",["statu"=>$status],["idshift"=>$data['idshift']]);
                $jatbi->logs('shift','shift-status',$data);
                echo json_encode(value: ['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
            }
            else {
                echo json_encode(['status'=>'error','content'=>$jatbi->lang("Cập nhật thất bại"),]);
            }
        }
        else {
            echo json_encode(["status"=>"error","content"=>$jatbi->lang("Không tìm thấy dữ liệu")]);
        }
    })->setPermissions(['shift.edit']);
?>
