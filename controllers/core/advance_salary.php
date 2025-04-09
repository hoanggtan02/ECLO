<?php
    if (!defined('ECLO')) die("Hacking attempt");
    $jatbi = new Jatbi($app);
    $setting = $app->getValueData('setting');
    $common = $app->getValueData('common');

    // Route để hiển thị giao diện quản lý ứng lương
    $app->router("/advance-salary", 'GET', function($vars) use ($app, $jatbi) {
        $vars['title'] = $jatbi->lang("Quản lý ứng lương");
        $vars['add'] = '/manager/advance-salary-add';
        $vars['deleted'] = '/manager/advance-salary-deleted';
        $data= $app->select("salaryadvances", ["AdvanceID", "sn", "TypeID", "Amount", "AppliedDate", "Note"]);
        // $vars['datatable'] = $app->component('datatable', ["datas" => [], "search" => []]);
        $var['data']= $data;
        echo $app->render('templates/employee/advance-salary.html', $vars);
    })->setPermissions(['advance-salary']);


    $app->router("/advance-salary", 'POST', function($vars) use ($app, $jatbi) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
    
        // Lấy các tham số từ DataTables
        $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
        $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
        $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
        $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
        $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'AdvanceID';
        $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
    
        // Điều kiện WHERE cho truy vấn
        $where = [
            "AND" => [
                "OR" => [
                   "salaryadvances.AdvanceID [~]" => $searchValue, 
                    "salaryadvances.sn[~]" => $searchValue, // Tìm kiếm theo mã nhân viên
                    "salaryadvances.Note[~]" => $searchValue, // Tìm kiếm theo ghi chú
                    "salaryadvances.AppliedDate[~]"=> $searchValue,
                    "employee.name[~]" => $searchValue, // Tìm kiếm theo tên nhân viên
                ],
                // "deleted" => 0, // Bỏ điều kiện deleted để kiểm tra dữ liệu
                
            ],
            "LIMIT" => [$start, $length],
            "ORDER" => [$orderName => strtoupper($orderDir)]
        ];
    
        // Debug: Kiểm tra điều kiện WHERE
        error_log("WHERE condition: " . json_encode($where));
    
        // Đếm tổng số bản ghi (không tính LIMIT)
        $count = $app->count("salaryadvances");
    
        // Lấy dữ liệu từ bảng salaryadvances và join với employee
        $datas = [];
        $app->select("salaryadvances", [
            "[>]employee" => ["sn" => "sn"],
            "[>]transactiontypes" => ["TypeID" => "TypeID"]
        ], [
            "salaryadvances.AdvanceID",
            "salaryadvances.sn",
            "salaryadvances.TypeID",
            "salaryadvances.Amount",
            "salaryadvances.AppliedDate",
            "salaryadvances.Note",
            "employee.name(employee_name)",
            "transactiontypes.TypeName(transaction_type)"
        ], $where, function ($data) use (&$datas, $jatbi, $app) {
    
            $note = $data['Note'] ?: $jatbi->lang("Không có ghi chú");
            // Thay thế ký tự xuống dòng \n bằng <br>
            $note = str_replace("\n", "<br>", $note);
            // Nếu chuỗi dài hơn 50 ký tự, tự động chèn <br> sau mỗi 50 ký tự
            $note = wordwrap($note, 20, "<br>", true);
            $datas[] = [
                "checkbox" => $app->component("box", ["data" => $data['AdvanceID']]),
                "AdvanceID" => $data['AdvanceID'],
                "sn" => $data['sn'],
                "employee_name" => $data['employee_name'] ?: $jatbi->lang("Không xác định"),
                "transaction_type" => $data['transaction_type'] ?: $jatbi->lang("Không xác định"),
                "Amount" => number_format($data['Amount'], 0, '.', ','), // Định dạng số tiền
                "AppliedDate" => $data['AppliedDate'],
                "Note" => $note,
                "action" => $app->component("action", [
                    "button" => [
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Sửa"),
                            'permission' => ['advance-salary.edit'],
                            'action' => ['data-url' => '/advance-salary/edit/' . $data['AdvanceID'], 'data-action' => 'modal']
                        ],
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Xóa"),
                            'permission' => ['advance-salary.deleted'],
                            'action' => ['data-url' => '/advance-salary/deleted?box=' . $data['AdvanceID'], 'data-action' => 'modal']
                        ],
                    ]
                ]),
            ];
        });
    
    
        // Trả về dữ liệu dưới dạng JSON cho DataTables
        $response = json_encode([
            "draw" => $draw,
            "recordsTotal" => $count,
            "recordsFiltered" => $count,
            "data" => $datas ?? []
        ], JSON_UNESCAPED_UNICODE);
    
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON Encode Error: " . json_last_error_msg());
            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => 0,
                "recordsFiltered" => 0,
                "data" => [],
                "error" => "JSON Encode Error: " . json_last_error_msg()
            ]);
            return;
        }
    
        echo $response;
    })->setPermissions(['advance-salary']);



 // Route để hiển thị form thêm ứng lương
$app->router("/advance-salary/add", 'GET', function($vars) use ($app, $jatbi) {
    $vars['title'] = $jatbi->lang("Thêm ứng lương");

     // Truy vấn danh sách nhân viên từ bảng employee
     $employees = $app->select("employee", ["sn", "name"], ["status" => 'A'], ["name" => "ASC"]);
     $vars['employees'] = $employees;
 
    $vars['data'] = []; // Dữ liệu rỗng vì đây là form thêm mới
    echo $app->render('templates/employee/advance-salary-post.html', $vars, 'global');
})->setPermissions(['advance-salary.add']);
$app->router("/advance-salary/add", 'POST', function($vars) use ($app, $jatbi) {
    $app->header([
        'Content-Type' => 'application/json',
    ]);

    // Kiểm tra dữ liệu đầu vào
    $sn = trim($_POST['sn'] ?? '');
    $TypeID = trim($_POST['TypeID'] ?? '');
    $Amount = trim($_POST['Amount'] ?? '');
    $AppliedDate = trim($_POST['AppliedDate'] ?? '');
    $Note = trim($_POST['Note'] ?? '');

    if ($sn == '' || $TypeID == '' || $Amount == '' || $AppliedDate == '') {
        echo json_encode([
            'status' => 'error',
            'content' => $jatbi->lang("Vui lòng điền đầy đủ thông tin bắt buộc"),
        ]);
        return;
    }

    // Kiểm tra mã nhân viên có tồn tại không
    $existingEmployee = $app->get("employee", ["sn"], ["sn" => $sn]);
    if (!$existingEmployee) {
        echo json_encode([
            'status' => 'error',
            'content' => $jatbi->lang("Mã nhân viên không tồn tại"),
        ]);
        return;
    }

    // Chuẩn bị dữ liệu để lưu vào DB
    $advanceData = [
        "sn" => $sn,
        "TypeID" => $TypeID,
        "Amount" => (float) $Amount, // Chuyển về kiểu số thực
        "AppliedDate" => date("Y-m-d", strtotime($AppliedDate)), // Định dạng ngày hợp lệ
        "Note" => $Note,
       
    ];

    // Thêm dữ liệu vào bảng salaryadvances
    $inserted = $app->insert("salaryadvances", $advanceData);

    if (!$inserted) {
        echo json_encode([
            'status' => 'error',
            'content' => $jatbi->lang("Lỗi khi thêm vào cơ sở dữ liệu"),
        ]);
        return;
    }

    // Ghi log nếu thêm thành công
    $jatbi->logs('advance-salary', 'advance-salary-add', $advanceData);

    // Trả về kết quả thành công
    echo json_encode([
        'status' => 'success',
        'content' => $jatbi->lang("Thêm ứng lương thành công"),
        'reload' => true,
    ]);
})->setPermissions(['advance-salary.add']);






$app->router("/advance-salary/edit/{id}", 'GET', function($vars) use ($app, $jatbi) {
    $advanceID = $vars['id']; // Lấy AdvanceID từ URL

    // Kiểm tra xem ứng lương có tồn tại không
    $advance = $app->select("salaryadvances", ["AdvanceID", "sn", "TypeID", "Amount", "AppliedDate", "Note"], ["AdvanceID" => $advanceID]);

    // Lấy danh sách nhân viên từ bảng employee
    $employees = $app->select("employee", ["sn", "name"], [], ["name" => "ASC"]); // Sắp xếp theo tên
    $employeeMap = [];
    foreach ($employees as $employee) {
        $employeeMap[$employee['sn']] = $employee['name'];
    }

    // Lấy tên nhân viên theo `sn`, nếu không có thì đặt giá trị mặc định
    $advance[0]['employee_name'] = $employeeMap[$advance[0]['sn']] ?? $jatbi->lang("Không xác định") . " ({$advance[0]['sn']})";

    // Truyền dữ liệu ứng lương vào template
    $vars['title'] = $jatbi->lang("Sửa ứng lương");
    $vars['employees'] = $employees ?: [];
    $vars['data'] = [
        'AdvanceID' => $advance[0]['AdvanceID'],
        'sn' => $advance[0]['sn'],
        'employee_name' => $advance[0]['employee_name'],
        'TypeID' => $advance[0]['TypeID'],
        'Amount' => $advance[0]['Amount'],
        'AppliedDate' => $advance[0]['AppliedDate'],
        'Note' => $advance[0]['Note'],
        'edit' => 1, 
        'id' => $advance[0]['AdvanceID'], 
    ];

    echo $app->render('templates/employee/advance-salary-post.html', $vars, 'global');
})->setPermissions(['advance-salary.edit']);

$app->router("/advance-salary/edit/{id}", 'POST', function($vars) use ($app, $jatbi) {
    $app->header([
        'Content-Type' => 'application/json',
    ]);

    $advanceID = $vars['id'];

    // Kiểm tra xem ứng lương có tồn tại không
    $existingAdvance = $app->get("salaryadvances", ["AdvanceID"], ["AdvanceID" => $advanceID]);
    if (!$existingAdvance) {
        echo json_encode([
            'status' => 'error',
            'content' => $jatbi->lang("Ứng lương không tồn tại"),
        ]);
        return;
    }

    // Kiểm tra dữ liệu đầu vào
    $sn = trim($_POST['sn'] ?? '');
    $TypeID = trim($_POST['TypeID'] ?? '');
    $Amount = trim($_POST['Amount'] ?? '');
    $AppliedDate = trim($_POST['AppliedDate'] ?? '');
    $Note = trim($_POST['Note'] ?? '');

    if ($sn == '' || $TypeID == '' || $Amount == '' || $AppliedDate == '') {
        echo json_encode([
            'status' => 'error',
            'content' => $jatbi->lang("Vui lòng điền đầy đủ thông tin bắt buộc"),
        ]);
        return;
    }

    // Kiểm tra sn có tồn tại và có status = 'A' trong bảng employee không
    $existingEmployee = $app->get("employee", ["sn", "status"], ["sn" => $sn]);
    if (!$existingEmployee) {
        echo json_encode([
            'status' => 'error',
            'content' => $jatbi->lang("Nhân viên không tồn tại"),
        ]);
        return;
    }
    if ($existingEmployee['status'] !== 'A') {
        echo json_encode([
            'status' => 'error',
            'content' => $jatbi->lang("Nhân viên không hoạt động, không thể cập nhật bản ghi"),
        ]);
        return;
    }


    // Chuẩn bị dữ liệu để cập nhật vào DB
    $advanceData = [
        "sn" => $sn,
        "TypeID" => $TypeID,
        "Amount" => (float) $Amount,
        "AppliedDate" => date("Y-m-d", strtotime($AppliedDate)),
        "Note" => $Note,
    ];

    // Cập nhật dữ liệu vào bảng salaryadvances
    $updated = $app->update("salaryadvances", $advanceData, ["AdvanceID" => $advanceID]);

    if (!$updated) {
        echo json_encode([
            'status' => 'error',
            'content' => $jatbi->lang("Lỗi khi cập nhật cơ sở dữ liệu"),
        ]);
        return;
    }

    // Ghi log nếu cập nhật thành công
    $jatbi->logs('advance-salary', 'advance-salary-edit', $advanceData);

    // Trả về kết quả thành công
    echo json_encode([
        'status' => 'success',
        'content' => $jatbi->lang("Cập nhật ứng lương thành công"),
        'reload' => true,
    ]);
})->setPermissions(['advance-salary.edit']);


// Route để hiển thị form xác nhận xóa ứng lương
$app->router("/advance-salary/deleted", 'GET', function($vars) use ($app, $jatbi) {
    $vars['title'] = $jatbi->lang("Xóa ứng lương");
    echo $app->render('templates/common/deleted.html', $vars, 'global');
})->setPermissions(['advance-salary.deleted']);

// Route để xử lý xóa ứng lương
$app->router("/advance-salary/deleted", 'POST', function($vars) use ($app, $jatbi) {
    $app->header([
        'Content-Type' => 'application/json',
    ]);

    // Lấy danh sách AdvanceID từ checkbox hoặc query string
    $box = $_POST['box'] ?? $_GET['box'] ?? null;
    if (!$box) {
        echo json_encode([
            'status' => 'error',
            'content' => $jatbi->lang("Vui lòng chọn ít nhất một ứng lương để xóa"),
        ]);
        return;
    }

    // Chuyển đổi $box thành mảng nếu nó là chuỗi (trường hợp xóa một bản ghi từ query string)
    $advanceIDs = is_array($box) ? $box : explode(',', $box);

    // Kiểm tra xem các AdvanceID có tồn tại không
    $existingAdvances = $app->select("salaryadvances", ["AdvanceID"], ["AdvanceID" => $advanceIDs]);
    if (empty($existingAdvances)) {
        echo json_encode([
            'status' => 'error',
            'content' => $jatbi->lang("Không tìm thấy ứng lương nào để xóa"),
        ]);
        return;
    }

    // Lấy danh sách AdvanceID thực sự tồn tại
    $validAdvanceIDs = array_column($existingAdvances, 'AdvanceID');

    // Xóa các bản ghi trong bảng salaryadvances
    $deleted = $app->delete("salaryadvances", ["AdvanceID" => $validAdvanceIDs]);

    if (!$deleted) {
        echo json_encode([
            'status' => 'error',
            'content' => $jatbi->lang("Lỗi khi xóa ứng lương"),
        ]);
        return;
    }

    // Ghi log nếu xóa thành công
    $jatbi->logs('advance-salary', 'advance-salary-deleted', ['AdvanceIDs' => $validAdvanceIDs]);

    // Trả về kết quả thành công
    echo json_encode([
        'status' => 'success',
        'content' => $jatbi->lang("Xóa ứng lương thành công"),
        'reload' => true,
    ]);
})->setPermissions(['advance-salary.deleted']);

?>