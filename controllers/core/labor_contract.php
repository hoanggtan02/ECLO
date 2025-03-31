<?php
    if (!defined('ECLO')) die("Hacking attempt");
    $jatbi = new Jatbi($app);
    $setting = $app->getValueData('setting');

    $app->router("/labor_contract", 'GET', function($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Hợp đồng lao động");
        echo $app->render('templates/labor_contract/labor_contract.html', $vars);
    })->setPermissions(['leave']);

    function formatRemainingTime($days, $jatbi) {
        if ($days <= 0) {
            return $jatbi->lang("Hết hạn");
        }
    
        $years = floor($days / 365);
        $months = floor(($days % 365) / 30);
        $remainingDays = ($days % 365) % 30;
    
        if ($years > 0) {
            return $jatbi->lang("$years năm " . ($months > 0 ? "$months tháng" : ""));
        } elseif ($months > 0) {
            return $jatbi->lang("$months tháng " . ($remainingDays > 0 ? "$remainingDays ngày" : ""));
        } else {
            return $jatbi->lang("$remainingDays ngày");
        }
    }
    

    $app->router("/labor_contract", 'POST', function($vars) use ($app, $jatbi) {
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
            "department.personName",
            "employee_contracts.contract_type",
            "employee_contracts.contract_number",
            "employee_contracts.contract_duration",
            "employee_contracts.remaining_days",
            "employee_contracts.working_date",
        ];
        $orderColumn = $validColumns[$orderColumnIndex] ?? "contract.created_at";
    
        // Điều kiện lọc dữ liệu
        $where = ["LIMIT" => [$start, $length], "ORDER" => [$orderColumn => $orderDir]];
    
        if (!empty($searchValue)) {
            $where["AND"]["OR"] = [
                "employee.name[~]" => $searchValue,
                "employee_contracts.contract_number[~]" => $searchValue
            ];
        }
    
        // Đếm số bản ghi
        $count = $app->count("employee_contracts", [
            "[>]employee" => ["person_sn" => "sn"]
        ], "employee_contracts.id");
    
        // Lấy danh sách hợp đồng
        $datas = $app->select("employee_contracts", [
            "[>]employee" => ["employee_contracts.person_sn" => "sn"],  
            "[>]department" => ["employee.departmentId" => "departmentId"],        
            "[>]staff-salary" => ["salaryId" => "id"],         
            "[>]staff-salary" => ["AdvanceID" => "id"]
        ], [
            "employee_contracts.id",
            "employee.name",
            "department.personName",
            "employee_contracts.contract_type",
            "employee_contracts.contract_number",
            "employee_contracts.contract_duration",
            "employee_contracts.remaining_days",
            "employee_contracts.working_date",
        ], $where) ?? [];
        
        // Xử lý dữ liệu đầu ra
        $formattedData = array_map(function($data) use ($app, $jatbi) {
            // Chuyển đổi ngày làm việc sang timestamp
            $workingDate = strtotime($data['working_date']); 
        
            // Nếu không có ngày làm việc hoặc thời hạn hợp đồng, trả về "Không xác định"
            if (!$workingDate || !isset($data['contract_duration'])) {
                $remainingDays = $jatbi->lang("Không xác định");
            } else {
                // Thời hạn hợp đồng tính theo tháng → Chuyển thành ngày
                $contractMonths = (int) $data['contract_duration'];
                $contractEndDate = strtotime("+{$contractMonths} months", $workingDate);
                
                // Ngày hiện tại
                $currentDate = time();
        
                // Tính số ngày còn lại
                $remainingDays = round(($contractEndDate - $currentDate) / (60 * 60 * 24));
        
                // Nếu số ngày nhỏ hơn 0, nghĩa là hợp đồng đã hết hạn
                if ($remainingDays < 0) {
                    $remainingDays = $jatbi->lang("Hết hạn");
                } else {
                    // Chuyển thành số tháng
                    $remainingMonths = floor($remainingDays / 30);
                    $remainingText = ($remainingMonths > 0) ? "$remainingMonths " . $jatbi->lang("tháng") : "$remainingDays " . $jatbi->lang("ngày");
                    $remainingDays = $remainingText;
                }
            }
        
            return [
                "checkbox" => $app->component("box", ["data" => $data['id']]),
        
                // TÊN NHÂN VIÊN
                "employee_name" => $data['name'] ?? $jatbi->lang("Không xác định"),
        
                // PHÒNG BAN 
                "department" => $data['personName'] ?? $jatbi->lang("Không xác định"),
        
                // LOẠI HỢP ĐỒNG
                "contract_type" => $jatbi->lang($data['contract_type']),
        
                // SỐ HỢP ĐỒNG
                "contract_number" => $data['contract_number'],
        
                // THỜI HẠN HỢP ĐỒNG
                "contract_duration" => isset($data['contract_duration']) 
                    ? $data['contract_duration'] . " " . $jatbi->lang("tháng") 
                    : $jatbi->lang("Không xác định"),
        
                // CÒN LẠI (hiển thị dưới dạng số tháng/ngày)
                "remaining_days" => $remainingDays,
        
                // NGÀY LÀM VIỆC
                "working_date" => date("d/m/Y", strtotime($data['working_date'])),
        
                "action" => $app->component("action", [
                    "button" => [
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Sửa"),
                            'permission' => ['labor_contract.edit'],
                            'action' => ['data-url' => '/labor_contract?id='.$data['id'], 'data-action' => 'modal']
                        ],
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Xóa"),
                            'permission' => ['labor_contract.deleted'],
                            'action' => ['data-url' => '/labor_contract?id='.$data['id'], 'data-action' => 'modal']
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
    })->setPermissions(['labor_contract']);

    
    $app->router("/labor_contract-add", 'GET', function($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Thêm hợp đồng lao động");
        // Lấy danh sách giá trị ENUM từ database
        $query = $app->query("SHOW COLUMNS FROM employee_contracts LIKE 'contract_type'");
        $row = $query->fetch(PDO::FETCH_ASSOC);

        // Xử lý để lấy các giá trị ENUM
        $enum_values = str_replace(["enum('", "')"], "", $row['Type']);
        $contract_types = explode("','", $enum_values);

        // Chuyển danh sách về dạng key-value
        $vars['contract_type'] = array_combine(range(1, count($contract_types)), $contract_types);
        echo $app->render('templates/labor_contract/labor_contract-post.html', $vars, 'global');
    })->setPermissions(['labor_contract.add']);
    
    $app->router("/labor_contract-add", 'POST', function($vars) use ($app, $jatbi) {
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
    })->setPermissions(['labor_contract.add']);

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
