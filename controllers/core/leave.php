<?php
    if (!defined('ECLO')) die("Hacking attempt");
    $jatbi = new Jatbi($app);
    $setting = $app->getValueData('setting');

    $app->router("/leave", 'GET', function($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Nghỉ Phép");
        // $vars['add'] = '/leave-add';
        // $vars['deleted'] = '/leave-deleted';
        echo $app->render('templates/leave/leave.html', $vars);
    })->setPermissions(['leave']);

    $app->router("/leave", 'POST', function($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);

        // Nhận dữ liệu từ DataTable
        $draw = $_POST['draw'] ?? 0;
        $start = $_POST['start'] ?? 0;
        $length = $_POST['length'] ?? 10;
        $searchValue = $_POST['search']['value'] ?? '';
    
        // Fix lỗi ORDER cột
        $orderColumnIndex = $_POST['order'][0]['column'] ?? 1; // Mặc định cột đầu tiên
        $orderDir = strtoupper($_POST['order'][0]['dir'] ?? 'DESC');
    
        // Danh sách cột hợp lệ
        $validColumns = [
            "checkbox",
            "employee.name",
            "leave_requests.leave_days",
            "leave_requests.start_date",
            "leave_requests.end_date",
            "leave_requests.note",
            "leavetype.Name",
            "leave_requests.created_at"
        ];
        $orderColumn = $validColumns[$orderColumnIndex] ?? "leave_requests.created_at";
    
        // Điều kiện lọc dữ liệu
        $where = ["LIMIT" => [$start, $length], "ORDER" => [$orderColumn => $orderDir]];
    
        if (!empty($searchValue)) {
            $where["AND"]["OR"] = [
                "employee.name[~]" => $searchValue,
                "leave_requests.note[~]" => $searchValue
            ];
        }
    
        // Đếm số bản ghi
        $count = $app->count("leave_requests", [
            "[>]employee" => ["personSN" => "sn"]
        ], "leave_requests.id");
    

    
        $datas = $app->select("leave_requests", [
            "[>]employee" => ["personSN" => "sn"],
            "[>]leavetype" => ["LeaveId" => "LeaveTypeID"]
        ], [
            "leave_requests.id",
            "leave_requests.personSN",
            "employee.name",
            "leave_requests.leave_days",
            "leave_requests.start_date",
            "leave_requests.end_date",
            "leave_requests.note",
            "leavetype.Name",
            "leave_requests.created_at"
        ], $where) ?? [];

    
            
        // Xử lý dữ liệu đầu ra
        $formattedData = array_map(function($data) use ($app, $jatbi) {
            return [
                "checkbox" => $app->component("box", ["data" => $data['id']]),
                "employee_name" => $data['name'] ?? $jatbi->lang("Không xác định"),
                "leave_days" => $data['leave_days'],
                "start_date" => date("H:i d/m/Y", strtotime($data['start_date'])),
                "end_date" => date("H:i d/m/Y", strtotime($data['end_date'])),
                "note" => $data['note'],
                "leaveType" => $data['Name'] ?? $jatbi->lang("Không xác định"),
                "created_at" => date("d/m/Y H:i:s", strtotime($data['created_at'])),
                "action" => $app->component("action", [
                    "button" => [
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Sửa"),
                            'permission' => ['leave.edit'],
                            'action' => ['data-url' => '/leave-edit?id=' . $data['id'], 'data-action' => 'modal']
                        ],
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Xóa"),
                            'permission' => ['leave.deleted'],
                            'action' => ['data-url' => '/leave-deleted?id=' . $data['id'], 'data-action' => 'modal']
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
            "data" => $formattedData,
        ]);
    })->setPermissions(['leave']);
    
    $app->router("/leave-add", 'GET', function($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Thêm đơn nghỉ phép");
        echo $app->render('templates/leave/leave-post.html', $vars, 'global');
    })->setPermissions(['leave.add']);
    
    $app->router("/leave-add", 'POST', function($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        
        // Lấy và làm sạch dữ liệu đầu vào
        $personSN = $app->xss($_POST['personSN'] ?? '');
        $leaveId = $app->xss($_POST['LeaveId'] ?? '');
        $startDateTimeStr = $app->xss($_POST['start_date'] ?? '');
        $endDateTimeStr = $app->xss($_POST['end_date'] ?? '');
        $note = $app->xss($_POST['note'] ?? '');

        // Kiểm tra dữ liệu đầu vào bắt buộc
        if (!$personSN || !$leaveId || !$startDateTimeStr || !$endDateTimeStr) {
            echo json_encode(["status" => "error", "content" => "Vui lòng điền đầy đủ thông tin"]);
            return;
        }
    
        try {
            $startDateTime = new DateTime($startDateTimeStr);
            $endDateTime = new DateTime($endDateTimeStr);
            $currentDateTime = new DateTime();
    
            if ($startDateTime > $endDateTime) {
                echo json_encode(["status" => "error", "content" => "Ngày bắt đầu không được lớn hơn ngày kết thúc"]);
                return;
            }
            if ($startDateTime < $currentDateTime) {
                echo json_encode(["status" => "error", "content" => "Ngày bắt đầu không được ở quá khứ"]);
                return;
            }
    
            // Tính toán số ngày nghỉ
            $interval = $startDateTime->diff($endDateTime);
            $leaveDays = ($interval->days == 0) ? (($interval->h + ($interval->i / 60) <= 6) ? 0.5 : 1) : $interval->days + 1;
          
            // Lưu vào database
            $result = $app->insert("leave_requests", [
                "personSN" => $personSN,
                "leave_days" => $leaveDays,
                "start_date" => $startDateTime->format("Y-m-d H:i:s"),
                "end_date" => $endDateTime->format("Y-m-d H:i:s"),
                "note" => $note ?: NULL,
                "created_at" => $currentDateTime->format("Y-m-d H:i:s"),
                "LeaveId" => $leaveId
            ]);

            if (!$result) {
                echo json_encode(["status" => "error", "content" => "Lỗi SQL: " . $app->getLastError()]);
                die();
            }
            
            echo json_encode(["status" => $result ? "success" : "error", "content" => $result ? "Thêm đơn nghỉ phép thành công" : "Không thể thêm đơn nghỉ phép, vui lòng thử lại", "leave_days" => $leaveDays]);
        } catch (Exception $e) {
            echo json_encode(["status" => "error", "content" => "Lỗi hệ thống: " . $e->getMessage()]);
        }
    })->setPermissions(['leave.add']);

    // Xóa đơn nghỉ phép
    $app->router("/leave-deleted", 'GET', function($vars) use ($app, $jatbi) {
        $vars['title'] = $jatbi->lang("Xóa Đơn Nghỉ Phép");
        echo $app->render('templates/common/deleted.html', $vars, 'global');
    })->setPermissions(['leave.deleted']);

    $app->router("/leave-deleted", 'POST', function($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        
        // Lấy danh sách ID cần xóa
        $leaveIds = [];
        if (!empty($_GET['id'])) {
            $leaveIds[] = $app->xss($_GET['id']);
        } elseif (!empty($_GET['box'])) {
            $leaveIds = array_map('trim', explode(',', $app->xss($_GET['box'])));
        }
        
        if (empty($leaveIds)) {
            echo json_encode(["status" => "error", "content" => "Thiếu ID đơn nghỉ phép để xóa"]);
            return;
        }
        
        try {
            $deletedCount = 0;
            $errors = [];
        
            foreach ($leaveIds as $leaveId) {
                if (empty($leaveId)) continue; // Bỏ qua nếu giá trị rỗng
        
                // Xóa khỏi database
                $deleteResult = $app->delete("leave_requests", ["id" => $leaveId]);
        
                if ($deleteResult->rowCount() > 0) {
                    $deletedCount++;
                } else {
                    $errors[] = "ID $leaveId: Không tìm thấy hoặc không thể xóa";
                }
            }
        
            if (!empty($errors)) {
                echo json_encode([
                    "status" => "error",
                    "content" => "Một số đơn nghỉ phép xóa thất bại",
                    "errors" => $errors
                ]);
            } else {
                echo json_encode([
                    "status" => "success",
                    "content" => "Đã xóa thành công $deletedCount đơn nghỉ phép"
                ]);
            }
        } catch (Exception $e) {
            echo json_encode(["status" => "error", "content" => "Lỗi: " . $e->getMessage()]);
        }
    })->setPermissions(['leave.deleted']);

    //Sửa Xin nghỉ

    $app->router("/leave-edit", 'GET', function($vars) use ($app, $jatbi) {
        $vars['title'] = $jatbi->lang("Sửa Đơn Nghỉ");
    
        $id = isset($_GET['id']) ? $app->xss($_GET['id']) : null;
        if (!$id) {
            echo $app->render('templates/common/error-modal.html', $vars, 'global');
            return;
        }
    
        $vars['data'] = $app->get("leave_requests", "*", ["id" => $id]);
        $vars['data']['edit'] = true;
        if ($vars['data']) {
            echo $app->render('templates/leave/leave-post.html', $vars, 'global');
        } else {
            echo $app->render('templates/common/error-modal.html', $vars, 'global');
        }
    })->setPermissions(['leave.edit']);
    
    $app->router("/leave-edit", 'POST', function($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
    
        // Lấy ID đơn nghỉ phép
        $id = isset($_POST['id']) ? $app->xss($_POST['id']) : null;
    
        if (!$id || !is_numeric($id)) {
            echo json_encode(["status" => "error", "content" => "ID đơn nghỉ không hợp lệ"]);
            return;
        }
    
        // Kiểm tra đơn nghỉ có tồn tại không
        $data = $app->get("leave_requests", "*", ["id" => $id]);
        if (!$data) {
            echo json_encode(["status" => "error", "content" => "Không tìm thấy đơn nghỉ"]);
            return;
        }
    
        // Lấy dữ liệu từ request
        $employee_sn = $app->xss($_POST['personSN'] ?? '');
        $leaveId     = $app->xss($_POST['LeaveId'] ?? '');
        $start_date  = $app->xss($_POST['start_date'] ?? '');
        $end_date    = $app->xss($_POST['end_date'] ?? '');
        $note        = $app->xss($_POST['note'] ?? '');
    
        // Kiểm tra dữ liệu đầu vào
        if (!$employee_sn || !$start_date || !$end_date) {
            echo json_encode(["status" => "error", "content" => "Vui lòng không để trống các trường bắt buộc"]);
            return;
        }
        
        // Chuyển đổi ngày tháng
        try {
            $startDateTime = new DateTime($start_date);
            $endDateTime   = new DateTime($end_date);
        } catch (Exception $e) {
            echo json_encode(["status" => "error", "content" => "Ngày không hợp lệ"]);
            return;
        }
    
        // Tính toán số ngày nghỉ
        $interval = $startDateTime->diff($endDateTime);
        $leaveDays = ($interval->days == 0) ? (($interval->h + ($interval->i / 60) <= 6) ? 0.5 : 1) : $interval->days + 1;
    
        // Mảng dữ liệu cập nhật
        $update = [
            "personSN"    => $employee_sn,
            "LeaveId"     => $leaveId, 
            "leave_days"  => $leaveDays,
            "start_date"  => $startDateTime->format('Y-m-d H:i:s'),
            "end_date"    => $endDateTime->format('Y-m-d H:i:s'),
            "note"        => $note,
            "created_at"  => date("Y-m-d H:i:s"),
        ];
    
        // Thực hiện cập nhật
        $result = $app->update("leave_requests", $update, ["id" => $id]);
    
        if (!$result) {
            echo json_encode(["status" => "error", "content" => "Lỗi cập nhật dữ liệu"]);
            return;
        }
    
        // Phản hồi thành công
        echo json_encode(["status" => "success", "content" => "Cập nhật thành công", "leave_days" => $leaveDays]);
    })->setPermissions(['leave.edit']);
    
    
?>
