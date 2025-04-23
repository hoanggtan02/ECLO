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
            ], [
            "employee_contracts.id",
            "employee.name",
            "department.personName",
            "employee_contracts.contract_type",
            "employee_contracts.contract_number",
            "employee_contracts.contract_duration",
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
                            'action' => ['data-url' => '/labor_contract-edit?id='.$data['id'], 'data-action' => 'modal']
                        ],
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Xóa"),
                            'permission' => ['labor_contract.deleted'],
                            'action' => ['data-url' => '/labor_contract-deleted?id='.$data['id'], 'data-action' => 'modal']
                        ],
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Chi tiết"),
                            'permission' => ['labor_contract'],
                            'action' => ['data-url' => '/labor_contract-view/'.$data['id'], 'data-action' => 'modal']
                        ],
                    ]
                ]),

                "detail" => $app->component("action", [
                    "button" => []]),
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

        // Lấy danh sách chức vụ từ bảng `staff-position`
        $positions = $app->select("staff-position", ["id", "name"]);
        $vars['position'] = array_column($positions, 'name', 'id');
        //
        $department = $app->select("department", ["departmentId", "personName"]);
        $vars['department'] = array_column($department, 'personName', 'departmentId');
    
        // Lấy danh sách nội dung lương từ bảng `staff-salary`
        $salaries = $app->select("staff-salary", ["id", "name", "price", "type"]);

        // Chia thành hai nhóm: Lương (type = 1) và Phụ cấp (type = 2, 3)
        $vars['salaries'] = array_filter($salaries, fn($s) => $s['type'] == 1);
        $vars['allowances'] = array_filter($salaries, fn($s) => in_array($s['type'], [2, 3]));

    
        echo $app->render('templates/labor_contract/labor_contract-post.html', $vars, 'global');
    })->setPermissions(['labor_contract.add']); 
    
    $app->router("/labor_contract-add", 'POST', function($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
    
        // Lấy và làm sạch dữ liệu đầu vào
        $personSN = $app->xss($_POST['person_sn'] ?? '');
        $position = $app->xss($_POST['position_id'] ?? '');  
        $contractType = $app->xss($_POST['contract_type'] ?? '');
        $contractDuration = $app->xss($_POST['contract_duration'] ?? '');
        $workingDate = $app->xss($_POST['working_date'] ?? '');
        $degree = $app->xss($_POST['degree'] ?? '');
        $educationLevel = $app->xss($_POST['education_level'] ?? ''); 
        $note = $app->xss($_POST['note'] ?? ''); 
        $interviewDateStr = $app->xss($_POST['interview_date'] ?? '');
        $contractNumber = $app->xss($_POST['contract_number'] ?? '');
        $job = $app->xss($_POST['department'] ?? '');
    
        // Lấy dữ liệu lương và trợ cấp (chỉ có 1 giá trị, không phải mảng)
        $salaryID = $_POST['salary_content'] ?? ''; 
        $allowanceID = $_POST['allowance_content'] ?? '';
    
        //Kiểm tra dữ liệu bắt buộc
        if (!$personSN || !$position || !$contractDuration || !$contractNumber) {
            echo json_encode(["status" => "error", "content" => "Vui lòng điền đầy đủ thông tin bắt buộc"]);
            return;
        }
    
        try {
            // Thêm vào bảng employee_contracts
            $result = $app->insert("employee_contracts", [
                "person_sn" => $personSN,
                "position_id" => $position,
                "contract_type" => $contractType,
                "contract_duration" => $contractDuration,
                "working_date" => date('Y-m-d', strtotime($workingDate)),
                "degree" => $degree ?: NULL,
                "education_level" => $educationLevel ?: NULL,
                "note" => $note ?: NULL,
                "interview_date" => date('Y-m-d', strtotime($interviewDateStr)) ?: NULL,
                "contract_number" => $contractNumber ?: NULL,
                "department" => $job ?: NULL,
            ]);
    
            // Kiểm tra xem việc chèn có thành công không
            if (!$result) {
                echo json_encode(["status" => "error", "content" => "Lỗi SQL khi thêm vào employee_contracts: " . $app->getLastError()]);
                return;
            }

            // Lấy ID của hợp đồng vừa thêm
            $query = $app->query("SELECT LAST_INSERT_ID() AS last_id");
            $contractID = $query->fetchColumn();
         
            // Xử lý tiền lương nếu có
            if (!empty($salaryID)) {
                $app->insert("contract_salary", [
                    "Id_contract" => $contractID,
                    "Id_salary" => $salaryID,
                ]);
            }
    
            // Xử lý tiền trợ cấp nếu có
            if (!empty($allowanceID)) {
                $app->insert("contract_salary", [
                    "Id_contract" => $contractID,
                    "Id_salary" => $allowanceID,
                ]);
            }
    
            echo json_encode(["status" => "success", "content" => "Thêm hợp đồng lao động, tiền lương và trợ cấp thành công"]);
        } catch (Exception $e) {
            echo json_encode(["status" => "error", "content" => "Lỗi hệ thống: " . $e->getMessage()]);
        }
    })->setPermissions(['labor_contract.add']);
    
    // Xóa hợp đồng lao động
    $app->router("/labor_contract-deleted", 'GET', function($vars) use ($app, $jatbi) {
        $vars['title'] = $jatbi->lang("Xóa Hợp Đồng Lao Động");
        echo $app->render('templates/common/deleted.html', $vars, 'global');
    })->setPermissions(['labor_contract.deleted']);

    $app->router("/labor_contract-deleted", 'POST', function($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        
        // Lấy danh sách ID cần xóa
        $leaveIds = [];
        if (!empty($_GET['id'])) {
            $leaveIds[] = $app->xss($_GET['id']);
        } elseif (!empty($_GET['box'])) {
            $leaveIds = array_map('trim', explode(',', $app->xss($_GET['box'])));
        }
        
        if (empty($leaveIds)) { 
            echo json_encode(["status" => "error", "content" => "Thiếu ID hợp đồng để xóa"]);
            return;
        }
        
        try {
            $deletedCount = 0;
            $errors = [];
        
            foreach ($leaveIds as $leaveId) {
                if (empty($leaveId)) continue; // Bỏ qua nếu giá trị rỗng
        
                // Xóa khỏi database
                $deleteResult = $app->delete("employee_contracts", ["id" => $leaveId]);
        
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
    })->setPermissions(['labor_contract.deleted']);
    
    //Sửa hợp đồng lao động

    $app->router("/labor_contract-edit", 'GET', function($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Sửa Hợp Đồng");
    
        $id = isset($_GET['id']) ? $app->xss($_GET['id']) : null;
        if (!$id) {
            error_log("No ID provided for labor_contract-edit");
            echo $app->render('templates/common/error-modal.html', $vars, 'global');
            return;
        }
    
        // Lấy dữ liệu cụ thể của hợp đồng
        $vars['data'] = $app->select("employee_contracts", "*", ["id" => $id]);
        if (!$vars['data']) {
            error_log("No data found for ID: " . $id);
            echo $app->render('templates/common/error-modal.html', $vars, 'global');
            return;
        }
    
        // Đảm bảo dữ liệu là mảng, nếu chỉ có một bản ghi
        if (isset($vars['data']['id'])) {
            $vars['data'] = [$vars['data']]; // Chuyển thành mảng nếu chỉ có một bản ghi
        }
    
        $vars['data'][0]['edit'] = true;
    
        // Lấy danh sách giá trị ENUM từ database (Loại hợp đồng)
        $query = $app->query("SHOW COLUMNS FROM employee_contracts LIKE 'contract_type'");
        $row = $query->fetch(PDO::FETCH_ASSOC);
    
        if ($row && isset($row['Type'])) {
            $enum_values = str_replace(["enum('", "')"], "", $row['Type']);
            $contract_types = explode("','", $enum_values);
    
            if (!empty($contract_types)) {
                $vars['contract_type'] = array_combine(range(1, count($contract_types)), $contract_types);
            } else {
                $vars['contract_type'] = [];
                error_log("No contract types found in employee_contracts.contract_type");
            }
        } else {
            $vars['contract_type'] = [];
            error_log("Failed to query contract_type ENUM from employee_contracts");
        }
    
        // Lấy danh sách chức vụ từ bảng `staff-position`
        $positions = $app->select("staff-position", ["id", "name"]);
        if (empty($positions)) {
            error_log("No positions found in staff-position");
        }
        $vars['position'] = array_column($positions, 'name', 'id');
    
        // Lấy danh sách phòng ban (Công việc) từ bảng `department`
        $department = $app->select("department", ["departmentId", "personName"]);
        if (empty($department)) {
            error_log("No departments found in department");
        }
        $vars['department'] = array_column($department, 'personName', 'departmentId');
    
        // Lấy danh sách nội dung lương từ bảng `staff-salary`
        $salaries = $app->select("staff-salary", ["id", "name", "price", "type"]);
        if (empty($salaries)) {
            error_log("No salaries found in staff-salary");
        }
    
        // Chia thành hai nhóm: Lương (type = 1) và Phụ cấp (type = 2)
        $vars['salaries'] = array_filter($salaries, fn($s) => $s['type'] == 1);
        $vars['allowances'] = array_filter($salaries, fn($s) => $s['type'] == 2);
    
        // Lấy thông tin lương và trợ cấp từ bảng contract_salary với join
        $salaryData = $app->select("contract_salary", [
            "[>]staff-salary" => ["Id_salary" => "id"]
        ], [
            "contract_salary.Id_salary",
            "staff-salary.type"
        ], ["contract_salary.id_contract" => $id]);
    
        if ($salaryData) {
            $salaryContent = null;
            $allowanceContent = null;
    
            foreach ($salaryData as $dataRow) {
                if ($dataRow['type'] == 1) {
                    $salaryContent = $dataRow['Id_salary']; // Lấy lương (type = 1)
                } elseif ($dataRow['type'] == 2) {
                    $allowanceContent = $dataRow['Id_salary']; // Lấy phụ cấp (type = 2)
                }
            }
    
            // Gán vào data[0]
            $vars['data'][0]['salary_content'] = $salaryContent;
            $vars['data'][0]['allowance_content'] = $allowanceContent;
        } else {
            $vars['data'][0]['salary_content'] = null;
            $vars['data'][0]['allowance_content'] = null;
        }
        
        // Render template với tất cả các biến
        echo $app->render('templates/labor_contract/labor_contract-post.html', $vars, 'global');
    })->setPermissions(['labor_contract.edit']);

    $app->router("/labor_contract-edit", 'POST', function($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
    
        $id = isset($_POST['id']) ? $app->xss($_POST['id']) : null;
        if (!$id || !is_numeric($id)) {
            echo json_encode(["status" => "error", "content" => "ID hợp đồng không hợp lệ"]);
            return;
        }
    
        $data = $app->get("employee_contracts", "*", ["id" => $id]);
        if (!$data) {
            echo json_encode(["status" => "error", "content" => "Không tìm thấy hợp đồng"]);
            return;
        }
    
        $personSN         = $app->xss($_POST['person_sn'] ?? '');
        $positionId       = $app->xss($_POST['position_id'] ?? '');
        $contractType     = $app->xss($_POST['contract_type'] ?? '');
        $contractDuration = $app->xss($_POST['contract_duration'] ?? '');
        $workingDate      = $app->xss($_POST['working_date'] ?? '');
        $degree           = $app->xss($_POST['degree'] ?? '');
        $educationLevel   = $app->xss($_POST['education_level'] ?? '');
        $note             = $app->xss($_POST['note'] ?? '');
        $contractNumber   = $app->xss($_POST['contract_number'] ?? '');
        $department       = $app->xss($_POST['department'] ?? '');
        $interviewDate    = $app->xss($_POST['interview_date'] ?? '');
    
        $salaryContent    = $_POST['salary_content'] ?? '';
        $allowanceContent = $_POST['allowance_content'] ?? '';
    
        // Kiểm tra từng trường bắt buộc
        if (empty($personSN)) {
            echo json_encode(["status" => "error", "content" => "Vui lòng điền Mã nhân viên"]);
            return;
        }
        if (empty($positionId)) {
            echo json_encode(["status" => "error", "content" => "Vui lòng điền Chức vụ"]);
            return;
        }
        if (empty($contractType)) {
            echo json_encode(["status" => "error", "content" => "Vui lòng điền Loại hợp đồng"]);
            return;
        }
        if (empty($contractDuration)) {
            echo json_encode(["status" => "error", "content" => "Vui lòng điền Thời hạn hợp đồng"]);
            return;
        }
        if (empty($workingDate)) {
            echo json_encode(["status" => "error", "content" => "Vui lòng điền Ngày làm việc"]);
            return;
        }
    
        try {
            $workingDateFormatted = date('Y-m-d', strtotime($workingDate));
            $interviewDateFormatted = $interviewDate ? date('Y-m-d', strtotime($interviewDate)) : null;
            if (!$workingDateFormatted || ($interviewDate && !$interviewDateFormatted)) {
                throw new Exception("Định dạng ngày không hợp lệ");
            }
    
            $update = [
                "person_sn"        => $personSN,
                "position_id"      => $positionId,
                "contract_type"    => $contractType,
                "contract_duration" => $contractDuration,
                "working_date"     => $workingDateFormatted,
                "degree"           => $degree ?: NULL,
                "education_level"  => $educationLevel ?: NULL,
                "note"             => $note ?: NULL,
                "contract_number"  => $contractNumber ?: NULL,
                "department"       => $department ?: NULL,
                "interview_date"   => $interviewDateFormatted,
            ];
    
            $result = $app->update("employee_contracts", $update, ["id" => $id]);
            if (!$result) {
                $error = $app->getLastError() ?: "Không xác định";
                echo json_encode(["status" => "error", "content" => "Lỗi cập nhật dữ liệu employee_contracts: " . $error]);
                return;
            }
    
            // Xóa tất cả bản ghi cũ trong contract_salary cho hợp đồng này
            $app->delete("contract_salary", ["Id_contract" => $id]);
    
            // Thêm lại lương nếu có
            if (!empty($salaryContent)) {
                $salaryExists = $app->select("staff-salary", ["id"], ["id" => $salaryContent, "type" => 1]);
                if (!empty($salaryExists)) {
                    $app->insert("contract_salary", [
                        "Id_contract" => $id,
                        "Id_salary" => $salaryContent,
                    ]);
                } else {
                    echo json_encode(["status" => "error", "content" => "ID lương không hợp lệ"]);
                    return;
                }
            }
    
            // Thêm lại phụ cấp nếu có
            if (!empty($allowanceContent)) {
                $allowanceExists = $app->select("staff-salary", ["id"], ["id" => $allowanceContent, "type" => 2]);
                if (!empty($allowanceExists)) {
                    $app->insert("contract_salary", [
                        "Id_contract" => $id,
                        "Id_salary" => $allowanceContent,
                    ]);
                } else {
                    echo json_encode(["status" => "error", "content" => "ID phụ cấp không hợp lệ"]);
                    return;
                }
            }
    
            echo json_encode(["status" => "success", "content" => "Cập nhật hợp đồng, tiền lương và trợ cấp thành công"]);
        } catch (Exception $e) {
            echo json_encode(["status" => "error", "content" => "Lỗi hệ thống: " . $e->getMessage()]);
        }
    })->setPermissions(['labor_contract.edit']);


// Route để hiển thị chi tiết hợp đồng
$app->router("/labor_contract-view/{id}", 'GET', function($vars) use ($app, $jatbi) {
    // Lấy dữ liệu hợp đồng từ bảng employee_contracts và các bảng liên quan
    $vars['data'] = $app->get("employee_contracts", [
        "[>]employee" => ["person_sn" => "sn"],
        "[>]department" => ["employee.departmentId" => "departmentId"],
        "[>]staff-position" => ["employee_contracts.position_id" => "id"],
    ], [
        "employee_contracts.id",
        "employee_contracts.person_sn",
        "employee.name(employee_name)",
        "department.personName(department_name)",
        "staff-position.name(position_name)",
        "employee_contracts.contract_type",
        "employee_contracts.contract_number",
        "employee_contracts.contract_duration",
        "employee_contracts.working_date",
        "employee_contracts.interview_date",
        "employee_contracts.degree",
        "employee_contracts.education_level",
        "employee_contracts.note",
    ], [
        "employee_contracts.id" => $vars['id'],
    ]);

    // Lấy thông tin lương và phụ cấp từ bảng contract_salary
    $salary_data = $app->select("contract_salary", [
        "[>]staff-salary" => ["Id_salary" => "id"],
    ], [
        "staff-salary.name(salary_name)",
        "staff-salary.price",
        "staff-salary.type",
    ], [
        "contract_salary.Id_contract" => $vars['id'],
    ]);

    // Thêm lương và phụ cấp vào $vars['data']
    $vars['data']['salaries'] = array_filter($salary_data, fn($s) => $s['type'] == 1);
    $vars['data']['allowances'] = array_filter($salary_data, fn($s) => $s['type'] == 2);

    // Kiểm tra xem hợp đồng có tồn tại không
    if ($vars['data']) {
        // Render modal hiển thị hợp đồng
        echo $app->render('templates/labor_contract/contracts-view.html', $vars, 'global');
    } else {
        // Nếu không tìm thấy hợp đồng, hiển thị modal lỗi
        echo $app->render('templates/common/error-modal.html', $vars, 'global');
    }
})->setPermissions(['labor_contract']);
?>
