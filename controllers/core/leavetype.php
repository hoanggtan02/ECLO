<?php

if (!defined('ECLO')) die("Hacking attempt");
$jatbi = new Jatbi($app);
$setting = $app->getValueData('setting');


//========================================Loại nghỉ phép========================================
// Route để hiển thị giao diện quản lý loại nghỉ phép
$app->router("/staffConfiguration/leavetypes", 'GET', function($vars) use ($app, $jatbi) {
    $vars['title'] = $jatbi->lang("Cấu hình nhân sự");
    $vars['title1'] = $jatbi->lang("Loại nghỉ phép");
    $vars['add'] = '/staffConfiguration/leavetypes-add';
    $vars['deleted'] = '/staffConfiguration/leavetypes-deleted';
    $vars['active'] = "leavetypes";
    $data = $app->select("leavetype", [
        "LeaveTypeID",
        "SalaryType",
        "Code",
        "Name",
        "MaxLeaveDays",
        "Unit",
        "Notes",
        "Status"
    ]);
    $vars['data'] = $data;
    echo $app->render('templates/staffConfiguration/leavetypes.html', $vars);
})->setPermissions(['leavetype']);

// Route để xử lý yêu cầu POST từ DataTables và trả về dữ liệu JSON
$app->router("/staffConfiguration/leavetypes", 'POST', function($vars) use ($app, $jatbi) {
    $app->header(['Content-Type' => 'application/json']);

    // Lấy các tham số từ DataTables
    $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
    $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
    $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
    $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'LeaveTypeID';
    $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
    $status = isset($_POST['status']) ? [$_POST['status'], $_POST['status']] : '';

    // Điều kiện WHERE cho truy vấn
    $where = [
        "AND" => [
            "OR" => [
                "leavetype.LeaveTypeID[~]" => $searchValue,
                "leavetype.SalaryType[~]" => $searchValue,
                "leavetype.Code[~]" => $searchValue,
                "leavetype.Name[~]" => $searchValue,
                "leavetype.MaxLeaveDays[~]" => $searchValue,
                "leavetype.Unit[~]" => $searchValue,
                "leavetype.Notes[~]" => $searchValue,
            ],
            "status[<>]" => $status,
        ],
        "LIMIT" => [$start, $length],
        "ORDER" => [$orderName => strtoupper($orderDir)]
    ];

    // Debug: Kiểm tra điều kiện WHERE
    error_log("WHERE condition for leavetype: " . json_encode($where));

    // Đếm tổng số bản ghi (không tính LIMIT)
    $count = $app->count("leavetype", ["AND" => $where['AND']]);
    error_log("Total records in leavetype: " . $count);

    // Lấy dữ liệu từ bảng leavetype
    $datas = [];
    $app->select("leavetype", [
        "LeaveTypeID",
        "SalaryType",
        "Code",
        "Name",
        "MaxLeaveDays",
        "Unit",
        "Notes",
        "Status"
    ], $where, function ($data) use (&$datas, $jatbi, $app) {
        // Debug: Kiểm tra dữ liệu lấy được từ bảng leavetype
        error_log("Record from leavetype: " . json_encode($data));

        // Gộp MaxLeaveDays và Unit thành một cột
        $leaveLimit = $data['MaxLeaveDays'] 
            ? ($data['Unit'] == 'Year' ? 'Năm' : 'Tháng') . ' / ' . $data['MaxLeaveDays'] . ' Ngày'
            : 'Không giới hạn';

        $notes = $data['Notes'] ?: $jatbi->lang("Không có ghi chú");
        // Thay thế ký tự xuống dòng \n bằng <br>
        $notes = str_replace("\n", "<br>", $notes);
        // Nếu chuỗi dài hơn 20 ký tự, tự động chèn <br> sau mỗi 20 ký tự
        $notes = wordwrap($notes, 20, "<br>", true);

        $datas[] = [
            "checkbox"    => $app->component("box", ["data" => $data['LeaveTypeID']]),
            "LeaveTypeID" => $data['LeaveTypeID'],
            "SalaryType"  => $data['SalaryType'] ?: $jatbi->lang("Không xác định"),
            "Code"        => $data['Code'] ?: $jatbi->lang("Không xác định"),
            "Name"        => $data['Name'] ?: $jatbi->lang("Không xác định"),
            "LeaveLimit"  => $leaveLimit,
            "Notes"       => $notes,
            "Status"      => $app->component("status", [
                "url" => "/staffConfiguration/leavetypes-status/" . $data['LeaveTypeID'],
                "data" => $data['Status'],
                "permission" => ['leavetype.edit']
            ]),
            "action"      => $app->component("action", [
                "button" => [
                    [
                        'type' => 'button',
                        'name' => $jatbi->lang("Sửa"),
                        'permission' => ['leavetype.edit'],
                        'action' => ['data-url' => '/staffConfiguration/leavetypes-edit/' . $data['LeaveTypeID'], 'data-action' => 'modal']
                    ],
                    [
                        'type' => 'button',
                        'name' => $jatbi->lang("Xóa"),
                        'permission' => ['leavetype.deleted'],
                        'action' => ['data-url' => '/staffConfiguration/leavetypes-deleted?box=' . $data['LeaveTypeID'], 'data-action' => 'modal']
                    ],
                ]
            ]),
        ];
    });

    // Debug: Kiểm tra dữ liệu trả về
    error_log("Data for DataTables: " . json_encode($datas));

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
})->setPermissions(['leavetype']);

// Thêm loại nghỉ phép mới
$app->router("/staffConfiguration/leavetypes-add", 'GET', function($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Thêm Loại nghỉ phép");
    $vars['data'] = [
        "SalaryType"  => 'Nghỉ có lương',
        "Code"        => '',
        "Name"        => '',
        "MaxLeaveDays" => '',
        "Unit"        => 'Year',
        "Notes"       => '',
        "Status"      => 'A',
    ];
    echo $app->render('templates/staffConfiguration/leavetypes-post.html', $vars, 'global');
})->setPermissions(['leavetype.add']);

$app->router("/staffConfiguration/leavetypes-add", 'POST', function($vars) use ($app, $jatbi) {
    $app->header(['Content-Type' => 'application/json']);

    $salaryType = trim($_POST['SalaryType'] ?? '');
    $code = trim($_POST['Code'] ?? '');
    $name = trim($_POST['Name'] ?? '');
    $maxLeaveDays = trim($_POST['MaxLeaveDays'] ?? '');
    $unit = trim($_POST['Unit'] ?? '');
    $notes = trim($_POST['Notes'] ?? '');
    $status = trim($_POST['Status'] ?? '');

    if (empty($code) || empty($name)) {
        echo json_encode([
            'status' => 'error',
            'content' => $jatbi->lang("Vui lòng điền đầy đủ thông tin bắt buộc"),
        ]);
        return;
    }

    $existing = $app->get("leavetype", "*", ["Code" => $code]);
    if ($existing) {
        echo json_encode([
            'status' => 'error',
            'content' => $jatbi->lang("Mã loại nghỉ phép đã tồn tại"),
        ]);
        return;
    }

    $leavetypeData = [
        "SalaryType"   => $salaryType,
        "Code"         => $code,
        "Name"         => $name,
        "MaxLeaveDays" => $maxLeaveDays ?: null,
        "Unit"         => $unit,
        "Notes"        => $notes,
        "Status"       => $status,
    ];

    $inserted = $app->insert("leavetype", $leavetypeData);
    if (!$inserted) {
        echo json_encode([
            'status' => 'error',
            'content' => $jatbi->lang("Lỗi khi thêm vào cơ sở dữ liệu"),
        ]);
        return;
    }

    $jatbi->logs('leavetype', 'leavetypes-add', $leavetypeData);
    echo json_encode([
        'status' => 'success',
        'content' => $jatbi->lang("Thêm loại nghỉ phép thành công"),
        'reload' => true,
    ]);
})->setPermissions(['leavetype.add']);

// Sửa loại nghỉ phép
$app->router("/staffConfiguration/leavetypes-edit/{id}", 'GET', function($vars) use ($app, $jatbi, $setting) {
    $id = $vars['id'];

    $leavetype = $app->select("leavetype", [
        "LeaveTypeID",
        "SalaryType",
        "Code",
        "Name",
        "MaxLeaveDays",
        "Unit",
        "Notes",
        "Status"
    ], ["LeaveTypeID" => $id]);

    $vars['title'] = $jatbi->lang("Sửa Loại nghỉ phép");
    $vars['data'] = [
        'LeaveTypeID'  => $leavetype[0]['LeaveTypeID'],
        'SalaryType'   => $leavetype[0]['SalaryType'],
        'Code'         => $leavetype[0]['Code'],
        'Name'         => $leavetype[0]['Name'],
        'MaxLeaveDays' => $leavetype[0]['MaxLeaveDays'],
        'Unit'         => $leavetype[0]['Unit'],
        'Notes'        => $leavetype[0]['Notes'],
        'Status'       => $leavetype[0]['Status'],
        'edit'         => 1,
    ];

    echo $app->render('templates/staffConfiguration/leavetypes-post.html', $vars, 'global');
})->setPermissions(['leavetype.edit']);

$app->router("/staffConfiguration/leavetypes-edit/{id}", 'POST', function($vars) use ($app, $jatbi) {
    $app->header(['Content-Type' => 'application/json']);

    $id = $vars['id'];

    $existingLeavetype = $app->get("leavetype", ["LeaveTypeID"], ["LeaveTypeID" => $id]);
    if (!$existingLeavetype) {
        echo json_encode([
            'status' => 'error',
            'content' => $jatbi->lang("Bản ghi không tồn tại"),
        ]);
        return;
    }

    $salaryType = trim($_POST['SalaryType'] ?? '');
    $code = trim($_POST['Code'] ?? '');
    $name = trim($_POST['Name'] ?? '');
    $maxLeaveDays = trim($_POST['MaxLeaveDays'] ?? '');
    $unit = trim($_POST['Unit'] ?? '');
    $notes = trim($_POST['Notes'] ?? '');
    $status = trim($_POST['Status'] ?? '');

    if (empty($code) || empty($name)) {
        echo json_encode([
            'status' => 'error',
            'content' => $jatbi->lang("Vui lòng điền đầy đủ thông tin bắt buộc"),
        ]);
        return;
    }

    $existing = $app->get("leavetype", "*", ["Code" => $code, "LeaveTypeID[!]" => $id]);
    if ($existing) {
        echo json_encode([
            'status' => 'error',
            'content' => $jatbi->lang("Mã loại nghỉ phép đã tồn tại"),
        ]);
        return;
    }

    $leavetypeData = [
        "SalaryType"   => $salaryType,
        "Code"         => $code,
        "Name"         => $name,
        "MaxLeaveDays" => $maxLeaveDays ?: null,
        "Unit"         => $unit,
        "Notes"        => $notes,
        "Status"       => $status,
    ];

    $updated = $app->update("leavetype", $leavetypeData, ["LeaveTypeID" => $id]);
    if (!$updated) {
        echo json_encode([
            'status' => 'error',
            'content' => $jatbi->lang("Lỗi khi cập nhật cơ sở dữ liệu"),
        ]);
        return;
    }

    $jatbi->logs('leavetype', 'leavetypes-edit', $leavetypeData);
    echo json_encode([
        'status' => 'success',
        'content' => $jatbi->lang("Cập nhật loại nghỉ phép thành công"),
        'reload' => true,
    ]);
})->setPermissions(['leavetype.edit']);

// Xóa loại nghỉ phép
$app->router("/staffConfiguration/leavetypes-deleted", 'GET', function($vars) use ($app, $jatbi) {
    $vars['title'] = $jatbi->lang("Xóa Loại nghỉ phép");
    echo $app->render('templates/common/deleted.html', $vars, 'global');
})->setPermissions(['leavetype.deleted']);

$app->router("/staffConfiguration/leavetypes-deleted", 'POST', function($vars) use ($app, $jatbi) {
    $app->header(['Content-Type' => 'application/json']);

    $box = $_POST['box'] ?? $_GET['box'] ?? null;
    if (!$box) {
        echo json_encode([
            'status' => 'error',
            'content' => $jatbi->lang("Vui lòng chọn ít nhất một bản ghi để xóa"),
        ]);
        return;
    }

    $ids = is_array($box) ? $box : explode(',', $box);
    $existingRecords = $app->select("leavetype", ["LeaveTypeID"], ["LeaveTypeID" => $ids]);
    if (empty($existingRecords)) {
        echo json_encode([
            'status' => 'error',
            'content' => $jatbi->lang("Không tìm thấy bản ghi nào để xóa"),
        ]);
        return;
    }

    $validIDs = array_column($existingRecords, 'LeaveTypeID');
    $deleted = $app->delete("leavetype", ["LeaveTypeID" => $validIDs]);
    if (!$deleted) {
        echo json_encode([
            'status' => 'error',
            'content' => $jatbi->lang("Lỗi khi xóa bản ghi"),
        ]);
        return;
    }

    $jatbi->logs('leavetype', 'leavetypes-deleted', ['ids' => $validIDs]);
    echo json_encode([
        'status' => 'success',
        'content' => $jatbi->lang("Xóa thành công"),
        'reload' => true,
    ]);
})->setPermissions(['leavetype.deleted']);

// Cập nhật trạng thái loại nghỉ phép
$app->router("/staffConfiguration/leavetypes-status/{id}", 'POST', function($vars) use ($app, $jatbi) {
    $app->header(['Content-Type' => 'application/json']);
    
    $data = $app->get("leavetype", "*", ["LeaveTypeID" => $vars['id']]);
    
    if ($data) {
        $status = ($data['Status'] === 'A') ? 'D' : 'A';
        $app->update("leavetype", ["Status" => $status], ["LeaveTypeID" => $data['LeaveTypeID']]);
        $jatbi->logs('leavetype', 'leavetypes-status', $data);
        echo json_encode([
            'status' => 'success',
            'content' => $jatbi->lang("Cập nhật thành công"),
            'reload' => true,
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'content' => $jatbi->lang("Không tìm thấy dữ liệu"),
        ]);
    }
})->setPermissions(['leavetype.edit']);

?>