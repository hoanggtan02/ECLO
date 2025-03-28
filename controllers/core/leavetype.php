<?php
if (!defined('ECLO')) die("Hacking attempt");
$jatbi = new Jatbi($app);
$setting = $app->getValueData('setting');

// Hiển thị danh sách loại nghỉ phép
$app->router("/manager/leavetypes", 'GET', function($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Loại nghỉ phép");
    $vars['add'] = '/manager/leavetypes-add';
    $vars['deleted'] = '/manager/leavetypes-deleted';

    $data = $app->select("leavetype", [
        "LeaveTypeID",
        "SalaryType",
        "Code",
        "Name",
        "MaxLeaveDays",
        "Unit",
        "Notes",
        "Status"
    ], [
        "ORDER" => ["LeaveTypeID" => "DESC"]
    ]);

    $vars['data'] = $data;
    echo $app->render('templates/staffConfiguration/leavetypes.html', $vars);
})->setPermissions(['leavetype']);

// Tải dữ liệu cho DataTables
$app->router("/manager/leavetypes", 'POST', function($vars) use ($app, $jatbi) {
    $app->header(['Content-Type' => 'application/json']);

    $draw = $_POST['draw'] ?? 0;
    $start = $_POST['start'] ?? 0;
    $length = $_POST['length'] ?? 10;
    $searchValue = $_POST['search']['value'] ?? '';

    $orderColumnIndex = $_POST['order'][0]['column'] ?? 0;
    $orderDir = strtoupper($_POST['order'][0]['dir'] ?? 'DESC');
    $validColumns = [
        1 => "leavetype.SalaryType",
        2 => "leavetype.Code",
        3 => "leavetype.Name",
        4 => "leavetype.MaxLeaveDays", // Cột này sẽ được gộp, nhưng vẫn cần để sắp xếp
        5 => "leavetype.Status"
    ];
    $orderColumn = $validColumns[$orderColumnIndex] ?? "leavetype.LeaveTypeID";

    $where = [
        "AND" => [
            "OR" => [
                "leavetype.SalaryType[~]" => $searchValue,
                "leavetype.Code[~]" => $searchValue,
                "leavetype.Name[~]" => $searchValue,
                "leavetype.MaxLeaveDays[~]" => $searchValue,
                "leavetype.Unit[~]" => $searchValue,
                "leavetype.Notes[~]" => $searchValue
            ]
        ],
        "LIMIT" => [$start, $length],
        "ORDER" => [$orderColumn => $orderDir]
    ];

    $datas = $app->select("leavetype", [
        "LeaveTypeID",
        "SalaryType",
        "Code",
        "Name",
        "MaxLeaveDays",
        "Unit",
        "Notes",
        "Status"
    ], $where) ?? [];

    $recordsFiltered = $app->count("leavetype");
    $recordsTotal = $app->count("leavetype");

    $formattedData = array_map(function($data) use ($app, $jatbi) {
        // Gộp MaxLeaveDays và Unit thành một cột
        $leaveLimit = $data['MaxLeaveDays'] 
            ? ($data['Unit'] == 'Year' ? 'Năm' : 'Tháng') . ' / ' . $data['MaxLeaveDays'] . ' Ngày'
            : 'Không giới hạn';

        return [
            "checkbox" => "<input class='form-check-input checker' type='checkbox' value='{$data['LeaveTypeID']}'>",
            "SalaryType" => $data['SalaryType'],
            "Code" => $data['Code'],
            "Name" => $data['Name'],
            "LeaveLimit" => $leaveLimit, // Cột mới gộp MaxLeaveDays và Unit
            "Status" => $data['Status'] ? '<span class="badge bg-success">Hoạt động</span>' : '<span class="badge bg-danger">Không hoạt động</span>',
            "action" => $app->component("action", [
                "button" => [
                    [
                        'type' => 'button',
                        'name' => $jatbi->lang("Sửa"),
                        'permission' => ['leavetype.edit'],
                        'action' => ['data-url' => '/manager/leavetypes-edit?id='.$data['LeaveTypeID'], 'data-action' => 'modal']
                    ],
                    [
                        'type' => 'button',
                        'name' => $jatbi->lang("Xóa"),
                        'permission' => ['leavetype.deleted'],
                        'action' => ['data-url' => '/manager/leavetypes-deleted?id='.$data['LeaveTypeID'], 'data-action' => 'modal']
                    ],
                ]
            ]),
        ];
    }, $datas);

    echo json_encode([
        "draw" => $draw,
        "recordsTotal" => $recordsTotal,
        "recordsFiltered" => $recordsFiltered,
        "data" => $formattedData
    ]);
})->setPermissions(['leavetype']);

// Thêm loại nghỉ phép mới
$app->router("/manager/leavetypes-add", 'GET', function($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Thêm loại nghỉ phép");
    $vars['data'] = [
        "SalaryType" => 'Nghỉ có lương',
        "Code" => '',
        "Name" => '',
        "MaxLeaveDays" => '',
        "Unit" => 'Year',
        "Notes" => '',
        "Status" => 1
    ];

    echo $app->render('templates/staffConfiguration/leavetypes-post.html', $vars, 'global');
})->setPermissions(['leavetype.add']);

$app->router("/manager/leavetypes-add", 'POST', function($vars) use ($app, $jatbi) {
    $app->header(['Content-Type' => 'application/json']);

    $salaryType = $app->xss($_POST['SalaryType'] ?? '');
    $code = $app->xss($_POST['Code'] ?? '');
    $name = $app->xss($_POST['Name'] ?? '');
    $maxLeaveDays = $app->xss($_POST['MaxLeaveDays'] ?? '');
    $unit = $app->xss($_POST['Unit'] ?? '');
    $notes = $app->xss($_POST['Notes'] ?? '');
    $status = $app->xss($_POST['Status'] ?? 1);

    if (empty($code) || empty($name)) {
        echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống các trường bắt buộc")]);
        return;
    }

    try {
        $existing = $app->get("leavetype", "*", ["Code" => $code]);
        if ($existing) {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Mã loại nghỉ phép đã tồn tại")]);
            return;
        }

        $app->insert("leavetype", [
            "SalaryType" => $salaryType,
            "Code" => $code,
            "Name" => $name,
            "MaxLeaveDays" => $maxLeaveDays ?: null,
            "Unit" => $unit,
            "Notes" => $notes,
            "Status" => $status
        ]);
        echo json_encode(["status" => "success", "content" => $jatbi->lang("Thêm loại nghỉ phép thành công")]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "content" => "Lỗi: " . $e->getMessage()]);
    }
})->setPermissions(['leavetype.add']);

// Sửa loại nghỉ phép
$app->router("/manager/leavetypes-edit", 'GET', function($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Sửa loại nghỉ phép");
    $id = $app->xss($_GET['id'] ?? '');

    if (!$id) {
        $vars['error_message'] = $jatbi->lang("ID không hợp lệ");
        echo $app->render('templates/common/error-modal.html', $vars, 'global');
        return;
    }

    $vars['data'] = $app->get("leavetype", "*", ["LeaveTypeID" => $id]);
    if (!$vars['data']) {
        $vars['error_message'] = $jatbi->lang("Loại nghỉ phép không tồn tại");
        echo $app->render('templates/common/error-modal.html', $vars, 'global');
        return;
    }
    $vars['data']['edit'] = true;

    echo $app->render('templates/staffConfiguration/leavetypes-post.html', $vars, 'global');
})->setPermissions(['leavetype.edit']);

$app->router("/manager/leavetypes-edit", 'POST', function($vars) use ($app, $jatbi) {
    $app->header(['Content-Type' => 'application/json']);

    $id = $app->xss($_POST['id'] ?? '');
    $salaryType = $app->xss($_POST['SalaryType'] ?? '');
    $code = $app->xss($_POST['Code'] ?? '');
    $name = $app->xss($_POST['Name'] ?? '');
    $maxLeaveDays = $app->xss($_POST['MaxLeaveDays'] ?? '');
    $unit = $app->xss($_POST['Unit'] ?? '');
    $notes = $app->xss($_POST['Notes'] ?? '');
    $status = $app->xss($_POST['Status'] ?? 1);

    if (empty($id) || empty($code) || empty($name)) {
        echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống các trường bắt buộc")]);
        return;
    }

    try {
        $existing = $app->get("leavetype", "*", ["Code" => $code, "LeaveTypeID[!]" => $id]);
        if ($existing) {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Mã loại nghỉ phép đã tồn tại")]);
            return;
        }

        $app->update("leavetype", [
            "SalaryType" => $salaryType,
            "Code" => $code,
            "Name" => $name,
            "MaxLeaveDays" => $maxLeaveDays ?: null,
            "Unit" => $unit,
            "Notes" => $notes,
            "Status" => $status
        ], ["LeaveTypeID" => $id]);
        echo json_encode(["status" => "success", "content" => $jatbi->lang("Sửa loại nghỉ phép thành công")]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "content" => "Lỗi: " . $e->getMessage()]);
    }
})->setPermissions(['leavetype.edit']);

// Xóa loại nghỉ phép
$app->router("/manager/leavetypes-deleted", 'GET', function($vars) use ($app, $jatbi) {
    $vars['title'] = $jatbi->lang("Xóa loại nghỉ phép");
    echo $app->render('templates/common/deleted.html', $vars, 'global');
})->setPermissions(['leavetype.deleted']);

$app->router("/manager/leavetypes-deleted", 'POST', function($vars) use ($app, $jatbi) {
    $app->header(['Content-Type' => 'application/json']);

    $idString = $_GET['id'] ?? '';
    $ids = array_filter(explode(",", $idString));

    if (empty($ids)) {
        echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng chọn ít nhất một loại nghỉ phép để xóa")]);
        return;
    }

    try {
        $successCount = 0;
        foreach ($ids as $id) {
            $id = trim($app->xss($id));
            if (empty($id)) continue;

            if ($app->delete("leavetype", ["LeaveTypeID" => $id])) {
                $successCount++;
            }
        }

        if ($successCount === 0) {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Không có loại nghỉ phép nào được xóa")]);
            return;
        }

        $message = $successCount === count($ids) 
            ? $jatbi->lang("Xóa thành công loại nghỉ phép") 
            : $jatbi->lang("Đã xóa $successCount loại nghỉ phép trên tổng");
        echo json_encode(["status" => "success", "content" => $message]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "content" => $jatbi->lang("Lỗi: ") . $e->getMessage()]);
    }
})->setPermissions(['leavetype.deleted']);
?>