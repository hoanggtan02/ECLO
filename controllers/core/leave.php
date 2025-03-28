<?php
    if (!defined('ECLO')) die("Hacking attempt");
    $jatbi = new Jatbi($app);
    $setting = $app->getValueData('setting');

    $app->router("/leave", 'GET', function($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Nghỉ Phép");
        $vars['add'] = '/leave-add';
        $vars['deleted'] = '/leave-deleted';
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
                            'action' => ['data-url' => '/manager/leave-edit?id=' . $data['id'], 'data-action' => 'modal']
                        ],
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Xóa"),
                            'permission' => ['leave.deleted'],
                            'action' => ['data-url' => '/manager/leave-deleted?id=' . $data['id'], 'data-action' => 'modal']
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
    
        $personSN  = $app->xss($_POST['personSN'] ?? '');
        $startDateTimeStr = $app->xss($_POST['start_date'] ?? '');
        $endDateTimeStr = $app->xss($_POST['end_date'] ?? '');
        $note      = $app->xss($_POST['note'] ?? '');
    
        // Kiểm tra dữ liệu đầu vào
        if (empty($personSN) || empty($startDateTimeStr) || empty($endDateTimeStr)) {
            echo json_encode(["status" => "error", "content" => "Dữ liệu ngày nghỉ bị thiếu"]);
            return;
        }
    
        try {
            // Chuyển đổi thành đối tượng DateTime
            $startDateTime = new DateTime($startDateTimeStr);
            $endDateTime = new DateTime($endDateTimeStr);
            $currentDateTime = new DateTime(); // Lấy thời gian hiện tại
    
            // Kiểm tra ngày bắt đầu không được lớn hơn ngày kết thúc
            if ($startDateTime > $endDateTime) {
                echo json_encode(["status" => "error", "content" => "Ngày bắt đầu không được lớn hơn ngày kết thúc"]);
                return;
            }
    
            // Kiểm tra ngày bắt đầu không được trong quá khứ (tùy vào yêu cầu hệ thống)
            if ($startDateTime < $currentDateTime) {
                echo json_encode(["status" => "error", "content" => "Ngày bắt đầu không được ở quá khứ"]);
                return;
            }
    
            // Tính toán số ngày nghỉ
            $interval = $startDateTime->diff($endDateTime);
    
            if ($interval->days == 0) {
                // Nghỉ trong cùng 1 ngày
                $hours = $interval->h + ($interval->i / 60);
                $leaveDays = ($hours <= 6) ? 0.5 : 1;
            } else {
                // Nghỉ nhiều ngày
                $leaveDays = $interval->days + 1;
            }
    
            // Chuẩn bị dữ liệu để lưu vào database
            $insert = [
                "personSN" => $personSN,
                "leave_days" => $leaveDays,
                "start_date" => $startDateTimeStr,
                "end_date" => $endDateTimeStr,
                "note" => $note,
                "created_at" => date("Y-m-d H:i:s")
            ];
    
            $app->insert("leave_requests", $insert);
            echo json_encode(["status" => "success", "content" => "Thêm đơn nghỉ phép thành công", "leave_days" => $leaveDays]);
        } catch (Exception $e) {
            echo json_encode(["status" => "error", "content" => "Lỗi: " . $e->getMessage()]);
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
        $app->header([
            'Content-Type' => 'application/json',
        ]);
    
        $id = isset($_POST['id']) ? $app->xss($_POST['id']) : null;
    
        if (!$id) {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("ID đơn nghỉ không hợp lệ")]);
            return;
        }
    
        $data = $app->get("leave_requests", "*", ["id" => $id]);
    
        if (!$data) {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy đơn nghỉ")]);
            return;
        }
    
        // Lấy dữ liệu từ request
        $employee_sn = isset($_POST['personSN']) ? $app->xss($_POST['personSN']) : '';
        $leave_type  = isset($_POST['leave_type']) ? $app->xss($_POST['leave_type']) : '';
        $start_date  = isset($_POST['start_date']) ? $app->xss($_POST['start_date']) : '';
        $end_date    = isset($_POST['end_date']) ? $app->xss($_POST['end_date']) : '';
        $note        = isset($_POST['note']) ? $app->xss($_POST['note']) : '';


    
        // Kiểm tra dữ liệu đầu vào
        if ($employee_sn === '' || $start_date === '' || $end_date === '') {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
            return;
        }
    
        // Chuyển đổi ngày tháng
        try {
            $startDateTime = new DateTime($start_date);
            $endDateTime   = new DateTime($end_date);
        } catch (Exception $e) {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Ngày không hợp lệ")]);
            return;
        }
    
        // Tính toán số ngày nghỉ
        $interval = $startDateTime->diff($endDateTime);
    
        if ($interval->days == 0) {
            // Nghỉ trong cùng 1 ngày
            $hours = $interval->h + ($interval->i / 60);
            $leaveDays = ($hours <= 6) ? 0.5 : 1;
        } else {
            // Nghỉ nhiều ngày
            $leaveDays = $interval->days + 1;
        }
    
        // Mảng dữ liệu cập nhật
        $update = [
            "personSN"    => $employee_sn,
            "leave_days"  => $leaveDays,
            "start_date"  => $startDateTime->format('Y-m-d'),
            "end_date"    => $endDateTime->format('Y-m-d'),
            "note"        => $note,
            "created_at"  => date("Y-m-d H:i:s"), // Cập nhật thời gian chỉnh sửa
        ];
    
        // Debug: Log dữ liệu cập nhật
        error_log("Update Data: " . json_encode($update));
    
        // Thực hiện cập nhật
        $result = $app->update("leave_requests", $update, ["id" => $id]);
    
        if (!$result) {
            error_log("SQL Update Error: " . json_encode($app->error()));
            echo json_encode(["status" => "error", "content" => "Lỗi cập nhật dữ liệu"]);
            return;
        }
    
        // Ghi log thay đổi
        $jatbi->logs('leave_requests', 'leave-edit', $update);
    
        // Phản hồi thành công
        echo json_encode(["status" => "success", "content" => $jatbi->lang("Cập nhật thành công")]);
    })->setPermissions(['leave.edit']);
    
?>
