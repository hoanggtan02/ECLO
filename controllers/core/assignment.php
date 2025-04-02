<?php
if (!defined('ECLO')) die("Hacking attempt");
$jatbi = new Jatbi($app);
$setting = $app->getValueData('setting');

$app->router("/manager/assignments", 'GET', function($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Phân công");
    $vars['add'] = '/manager/assignments-add';
    $vars['deleted'] = '/manager/assignments-deleted';

    // Sử dụng Medoo để lấy dữ liệu từ bảng assignments
    $data = $app->select("assignments", [
        "id",
        "timeperiod_id",
        "employee_id",
        "apply_date",
        "notes"
    ], [
        "ORDER" => ["apply_date" => "DESC"]
    ]);

    if (!empty($data)) {
        // Lấy danh sách employee_id
        $employeeIds = array_column($data, 'employee_id');

        // Lấy thông tin nhân viên từ bảng employee
        $employeeData = $app->select("employee", [
            "sn",
            "name"
        ], [
            "sn" => $employeeIds
        ]);
        $employeeMap = array_column($employeeData, null, 'sn');

        // Gắn thông tin tên nhân viên vào dữ liệu
        foreach ($data as &$row) {
            $row['employee_name'] = $employeeMap[$row['employee_id']]['name'] ?? '';
        }
    }

    $vars['data'] = $data;
    echo $app->render('templates/employee/assignments.html', $vars);
})->setPermissions(['assignment']);

$app->router("/manager/assignments", 'POST', function($vars) use ($app, $jatbi) {
    $app->header(['Content-Type' => 'application/json']);

    // Lấy tham số từ DataTables
    $draw = $_POST['draw'] ?? 0;
    $start = $_POST['start'] ?? 0;
    $length = $_POST['length'] ?? 10;
    $searchValue = $_POST['search']['value'] ?? '';

    // Xử lý sắp xếp
    $orderColumnIndex = $_POST['order'][0]['column'] ?? 0;
    $orderDir = strtoupper($_POST['order'][0]['dir'] ?? 'DESC');
    $validColumns = [
        1 => "assignments.employee_id", // Sắp xếp trên employee_id
        2 => "employee.name",           // Sắp xếp trên employee_name
        3 => "assignments.timeperiod_id", // Sắp xếp trên timeperiod_id
        4 => "assignments.apply_date",  // Sắp xếp trên apply_date
        5 => "assignments.notes"        // Sắp xếp trên notes
    ];
    $orderColumn = $validColumns[$orderColumnIndex] ?? "assignments.apply_date";

    // Xây dựng điều kiện truy vấn
    $where = [
        "AND" => [
            "OR" => [
                "assignments.employee_id[~]" => $searchValue, // Tìm kiếm trên employee_id
                "employee.name[~]" => $searchValue,           // Tìm kiếm trên employee_name
                "assignments.timeperiod_id[~]" => $searchValue, // Tìm kiếm trên timeperiod_id
                "assignments.apply_date[~]" => $searchValue,  // Tìm kiếm trên apply_date
                "assignments.notes[~]" => $searchValue        // Tìm kiếm trên notes
            ]
        ],
        "LIMIT" => [$start, $length],
        "ORDER" => [$orderColumn => $orderDir]
    ];

    // Truy vấn dữ liệu với Medoo, chỉ join với employee
    $datas = $app->select("assignments", [
        "[>]employee" => ["employee_id" => "sn"]
    ], [
        "assignments.id",
        "assignments.timeperiod_id",
        "assignments.employee_id",
        "employee.name(employee_name)",
        "assignments.apply_date",
        "assignments.notes"
    ], $where) ?? [];

    // Đếm số bản ghi sau khi lọc
    $recordsFiltered = $app->count("assignments");

    // Đếm tổng số bản ghi
    $recordsTotal = $app->count("assignments");

    // Định dạng dữ liệu trả về cho DataTables
    $formattedData = array_map(function($data) use ($app, $jatbi) {
        return [
            "checkbox" => $app->component("box", ["data" => $data['id']]),
            "employee_id" => $data['employee_id'] ?? '', // Thêm employee_id
            "employee_name" => $data['employee_name'] ?? '',
            "timeperiod_id" => $data['timeperiod_id'] ?? '', // Sử dụng timeperiod_id
            "apply_date" => date('d/m/Y', strtotime($data['apply_date'])),
            "notes" => $data['notes'] ?: '',
            "action" => $app->component("action", [
                "button" => [
                    [
                        'type' => 'button',
                        'name' => $jatbi->lang("Sửa"),
                        'permission' => ['assignment.edit'],
                        'action' => ['data-url' => '/manager/assignments-edit?id='.$data['id'], 'data-action' => 'modal'] // Sử dụng id
                    ],
                    [
                        'type' => 'button',
                        'name' => $jatbi->lang("Xóa"),
                        'permission' => ['assignment.deleted'],
                        'action' => ['data-url' => '/manager/assignments-deleted?id='.$data['id'], 'data-action' => 'modal'] // Sử dụng id
                    ],
                ]
            ]),
        ];
    }, $datas);

    // Trả về JSON cho DataTables
    echo json_encode([
        "draw" => $draw,
        "recordsTotal" => $recordsTotal,
        "recordsFiltered" => $recordsFiltered,
        "data" => $formattedData
    ]);
})->setPermissions(['assignment']);

// Thêm mới assignment
$app->router("/manager/assignments-add", 'GET', function($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Thêm phân công");
    $vars['employees'] = $app->select("employee", ["sn", "name"], ["ORDER" => ["name" => "ASC"]]);
    $vars['timeperiods'] = $app->select("timeperiod", ["acTzNumber", "name"], ["ORDER" => ["name" => "ASC"]]);
    $vars['data'] = [
        "timeperiod_id" => '',
        "employee_id" => '',
        "apply_date" => date('Y-m-d'),
        "notes" => ''
    ];

    echo $app->render('templates/employee/assignments-post.html', $vars, 'global');
})->setPermissions(['assignment.add']);

$app->router("/manager/assignments-add", 'POST', function($vars) use ($app, $jatbi) {
    $app->header(['Content-Type' => 'application/json']);

    $timeperiod_id = $app->xss($_POST['timeperiod_id'] ?? '');
    $employee_id = $app->xss($_POST['employee_id'] ?? '');
    $apply_date = $app->xss($_POST['apply_date'] ?? '');
    $notes = $app->xss($_POST['notes'] ?? '');

    if (empty($timeperiod_id) || empty($employee_id) || empty($apply_date)) {
        echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống các trường bắt buộc")]);
        return;
    }

    try {
        $existing = $app->get("assignments", "*", [
            "timeperiod_id" => $timeperiod_id,
            "employee_id" => $employee_id,
            "apply_date" => $apply_date
        ]);
        if ($existing) {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Phân công này đã tồn tại")]);
            return;
        }

        $app->insert("assignments", [
            "timeperiod_id" => $timeperiod_id,
            "employee_id" => $employee_id,
            "apply_date" => $apply_date,
            "notes" => $notes
        ]);
        echo json_encode(["status" => "success", "content" => $jatbi->lang("Thêm phân công thành công")]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "content" => "Lỗi: " . $e->getMessage()]);
    }
})->setPermissions(['assignment.add']);

// Sửa assignment
$app->router("/manager/assignments-edit", 'GET', function($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Sửa phân công");
    $id = $app->xss($_GET['id'] ?? '');


    if (!$id) {
        $vars['error_message'] = $jatbi->lang("ID không hợp lệ");
        echo $app->render('templates/common/error-modal.html', $vars, 'global');
        return;
    }

    $vars['data'] = $app->get("assignments", "*", ["id" => $id]);
    if (!$vars['data']) {
        $vars['error_message'] = $jatbi->lang("Phân công không tồn tại");
        echo $app->render('templates/common/error-modal.html', $vars, 'global');
        return;
    }
    $vars['employees'] = $app->select("employee", ["sn", "name"], ["ORDER" => ["name" => "ASC"]]);
    $vars['timeperiods'] = $app->select("timeperiod", ["acTzNumber", "name"], ["ORDER" => ["name" => "ASC"]]);
    $vars['data']['edit'] = true;

    if ($vars['data']) {
        echo $app->render('templates/employee/assignments-post.html', $vars, 'global');
    } else {
        echo $app->render('templates/common/error-modal.html', $vars, 'global');
    }
})->setPermissions(['assignment.edit']);

$app->router("/manager/assignments-edit", 'POST', function($vars) use ($app, $jatbi) {
    $app->header(['Content-Type' => 'application/json']);

    $timeperiod_id = $app->xss($_POST['timeperiod_id'] ?? '');
    $apply_date = $app->xss($_POST['apply_date'] ?? '');
    $notes = $app->xss($_POST['notes'] ?? '');
    $id = $app->xss($_POST['id'] ?? '');



    if (empty($timeperiod_id) || empty($id) || empty($apply_date)) {
        echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống các trường bắt buộc")]);
        return;
    }

    try {  
        $app->update("assignments", [
            "timeperiod_id" => $timeperiod_id,
            "apply_date" => $apply_date,
            "notes" => $notes
        ], ["id" => $id]);
        echo json_encode(["status" => "success", "content" => $jatbi->lang("Sửa phân công thành công")]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "content" => "Lỗi: " . $e->getMessage()]);
    }
})->setPermissions(['assignment.edit']);

// Xóa assignment
$app->router("/manager/assignments-deleted", 'GET', function($vars) use ($app, $jatbi) {
    $vars['title'] = $jatbi->lang("Xóa phân công");
    echo $app->render('templates/common/deleted.html', $vars, 'global');
})->setPermissions(['assignment.deleted']);

$app->router("/manager/assignments-deleted", 'POST', function($vars) use ($app, $jatbi) {
    $app->header(['Content-Type' => 'application/json']);

    $idString = $_GET['box'] ?? '';
    $ids = array_filter(explode(",", $idString));

    if (empty($ids)) {
        echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng chọn ít nhất một phân công để xóa")]);
        return;
    }

    try {
        $successCount = 0;
        foreach ($ids as $id) {
            $id = trim($app->xss($id));
            if (empty($id)) continue;

            if ($app->delete("assignments", ["id" => $id])) {
                $successCount++;
            }
        }

        if ($successCount === 0) {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Không có phân công nào được xóa")]);
            return;
        }

        $message = $successCount === count($ids) 
            ? $jatbi->lang("Xóa thành công phân công") 
            : $jatbi->lang("Đã xóa $successCount phân công trên tổng" );
        echo json_encode(["status" => "success", "content" => $message]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "content" => $jatbi->lang("Lỗi: ") . $e->getMessage()]);
    }
})->setPermissions(['assignment.deleted']);
?>