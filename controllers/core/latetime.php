<?php
if (!defined('ECLO')) die("Hacking attempt");
$jatbi = new Jatbi($app);
$setting = $app->getValueData('setting');

//========================================Đi trễ về sớm========================================
// Route để hiển thị giao diện quản lý đi trễ về sớm
$app->router("/staffConfiguration/latetime", 'GET', function($vars) use ($app, $jatbi) {
    $vars['title'] = $jatbi->lang("Cấu hình nhân sự");
    $vars['title1'] = $jatbi->lang("Đi trễ về sớm");
    $vars['add'] = '/staffConfiguration/latetime-add';
    $vars['deleted'] = '/staffConfiguration/latetime-deleted';
    $data = $app->select("latetime", ["id", "type", "sn", "value", "amount", "apply_date", "content", "status"]);
    $vars['data'] = $data;
    $vars['active'] = 'latetime';
    echo $app->render('templates/staffConfiguration/latetime.html', $vars);
})->setPermissions(['latetime']);




$app->router("/staffConfiguration/latetime", 'POST', function($vars) use ($app, $jatbi) {
    $app->header(['Content-Type' => 'application/json']);

    // Lấy các tham số từ DataTables
    $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
    $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
    $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
    $status = isset($_POST['status']) ? $_POST['status'] : '';
    $orderColumnIndex = isset($_POST['order'][0]['column']) ? $_POST['order'][0]['column'] : 1;
    $orderDir = strtoupper(isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC');

    // Danh sách cột hợp lệ
    $validColumns = [
        "checkbox",
        "latetime.id",
        "latetime.type",
        "employee.name",
        "latetime.value",
        "latetime.amount",
        "latetime.apply_date",
        "latetime.content",
        "latetime.status",
        "action"
    ];
    $orderColumn = $validColumns[$orderColumnIndex] ?? "latetime.created_at";

    // Điều kiện lọc dữ liệu
    $conditions = ["AND" => []];

    if (!empty($searchValue)) {
        $conditions["AND"]["OR"] = [
            "latetime.id[~]" => $searchValue,
            "latetime.type[~]" => $searchValue,
            "latetime.sn[~]" => $searchValue,
            "latetime.value[~]" => $searchValue,
            "latetime.amount[~]" => $searchValue,
            "latetime.apply_date[~]" => $searchValue,
            "latetime.content[~]" => $searchValue,
            "employee.name[~]" => $searchValue
        ];
    }

    if (!empty($status)) {
        $conditions["AND"]["latetime.status"] = $status;
    }

    // Kiểm tra nếu conditions bị trống
    if (empty($conditions["AND"])) {
        unset($conditions["AND"]);
    }

    // Đếm tổng số bản ghi với JOIN
    $count = $app->count("latetime", [
        "[>]employee" => ["sn" => "sn"]
    ], "latetime.id", $conditions);

    // Truy vấn danh sách dữ liệu
    $datas = $app->select("latetime", [
        "[>]employee" => ["sn" => "sn"]
    ], [
        "latetime.id",
        "latetime.type",
        "latetime.sn",
        "latetime.value",
        "latetime.amount",
        "latetime.apply_date",
        "latetime.content",
        "latetime.status",
        "employee.name(employee_name)"
    ], array_merge($conditions, [
        "LIMIT" => [$start, $length],
        "ORDER" => [$orderColumn => $orderDir]
    ])) ?? [];

    // Xử lý dữ liệu đầu ra
    $formattedData = array_map(function($data) use ($app, $jatbi) {
        $content = $data['content'] ? str_replace("\n", "<br>", wordwrap($data['content'], 20, "<br>", true)) : $jatbi->lang("Không có nội dung");

        return [
            "checkbox" => $app->component("box", ["data" => $data['id']]),
            "id" => $data['id'],
            "type" => $data['type'] ?: $jatbi->lang("Không xác định"),
            "sn" => $data['employee_name'] ?: $jatbi->lang("Không xác định"),
            "value" => $data['value'] ? $data['value'] . ' phút' : $jatbi->lang("Không xác định"),
            "amount" => $data['amount'] ? number_format($data['amount'], 0, '.', ',') : $jatbi->lang("Không xác định"),
            "apply_date" => $data['apply_date'] ? date('d/m/Y', strtotime($data['apply_date'])) : $jatbi->lang("Không xác định"),
            "content" => $content,
            "status" => $app->component("status", [
                "url" => "/staffConfiguration/latetime-status/" . $data['id'],
                "data" => $data['status'],
                "permission" => ['latetime.edit']
            ]),
            "action" => $app->component("action", [
                "button" => [
                    [
                        'type' => 'button',
                        'name' => $jatbi->lang("Sửa"),
                        'permission' => ['latetime.edit'],
                        'action' => ['data-url' => '/staffConfiguration/latetime-edit/' . $data['id'], 'data-action' => 'modal']
                    ],
                    [
                        'type' => 'button',
                        'name' => $jatbi->lang("Xóa"),
                        'permission' => ['latetime.deleted'],
                        'action' => ['data-url' => '/staffConfiguration/latetime-deleted?box=' . $data['id'], 'data-action' => 'modal']
                    ],
                ]
            ]),
        ];
    }, $datas);

    // Trả về dữ liệu dưới dạng JSON cho DataTables
    echo json_encode([
        "draw" => $draw,
        "recordsTotal" => $count,
        "recordsFiltered" => $count,
        "data" => $formattedData
    ]);
})->setPermissions(['latetime']);

// Route thêm mới (GET)
$app->router("/staffConfiguration/latetime-add", 'GET', function($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Thêm Đi trễ về sớm");
    $employees = $app->select("employee", ["sn", "name"], ["status" => 'A'], ["name" => "ASC"]);
    $vars['employees'] = $employees ?: [];
    $vars['data'] = [
        "sn"         => '',
        "type"       => '',
        "value"      => '',
        "amount"     => '',
        "apply_date" => date('Y-m-d'),
        "content"    => '',
        "status"     => '1',
    ];
    echo $app->render('templates/staffConfiguration/latetime-post.html', $vars, 'global');
})->setPermissions(['latetime.add']);

// Route thêm mới (POST)
$app->router("/staffConfiguration/latetime-add", 'POST', function($vars) use ($app, $jatbi) {
    $app->header([
        'Content-Type' => 'application/json',
    ]);

    $type = trim($_POST['type'] ?? '');
    $sn = trim($_POST['sn'] ?? '');
    $value = trim($_POST['value'] ?? '');
    $amount = trim($_POST['amount'] ?? '');
    $apply_date = trim($_POST['apply_date'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $status = trim($_POST['status'] ?? '');

    if ($type == '' || $sn == '' || $value == '' || $amount == '' || $apply_date == '') {
        echo json_encode([
            'status' => 'error',
            'content' => $jatbi->lang("Vui lòng điền đầy đủ thông tin bắt buộc"),
        ]);
        return;
    }

    // Kiểm tra sn có tồn tại trong bảng employee không
    $existingEmployee = $app->get("employee", ["sn"], ["sn" => $sn]);
    if (!$existingEmployee) {
        echo json_encode([
            'status' => 'error',
            'content' => $jatbi->lang("Nhân viên không tồn tại"),
        ]);
        return;
    }

    $latetimeData = [
        "type"       => $type,
        "sn"         => $sn,
        "value"      =>  $value,
        "amount"     =>  $amount,
        "apply_date" => date("Y-m-d", strtotime($apply_date)),
        "content"    => $content,
        "status"     => $status,
    ];

    $inserted = $app->insert("latetime", $latetimeData);

    if (!$inserted) {
        echo json_encode([
            'status' => 'error',
            'content' => $jatbi->lang("Lỗi khi thêm vào cơ sở dữ liệu"),
        ]);
        return;
    }

    $jatbi->logs('latetime', 'latetime-add', $latetimeData);

    echo json_encode([
        'status' => 'success',
        'content' => $jatbi->lang("Thêm đi trễ về sớm thành công"),
        'reload' => true,
    ]);
})->setPermissions(['latetime.add']);



$app->router("/staffConfiguration/latetime-edit/{id}", 'GET', function($vars) use ($app, $jatbi, $setting) {
    $id = $vars['id'];

    // Kiểm tra xem bản ghi latetime có tồn tại không
    $latetime = $app->select("latetime", ["id", "type", "sn", "value", "amount", "apply_date", "content", "status"], ["id" => $id]);
    if (empty($latetime)) {
        $vars['title'] = $jatbi->lang("Sửa Đi trễ về sớm");
        $vars['error'] = $jatbi->lang("Bản ghi không tồn tại");
        echo $app->render('templates/error.html', $vars, 'global');
        return;
    }

    // Lấy danh sách nhân viên từ bảng employee
    $employees = $app->select("employee", ["sn", "name"], [], ["name" => "ASC"]); // Sắp xếp theo tên
    $employeeMap = [];
    foreach ($employees as $employee) {
        $employeeMap[$employee['sn']] = $employee['name'];
    }

    // Lấy tên nhân viên theo `sn`, nếu không có thì đặt giá trị mặc định
    $latetime[0]['employee_name'] = $employeeMap[$latetime[0]['sn']] ?? $jatbi->lang("Không xác định") . " ({$latetime[0]['sn']})";

    // Truyền dữ liệu vào template
    $vars['title'] = $jatbi->lang("Sửa Đi trễ về sớm");
    $vars['employees'] = $employees ?: [];
    $vars['data'] = [
        'id'           => $latetime[0]['id'],
        'type'         => $latetime[0]['type'],
        'sn'           => $latetime[0]['sn'],
        'employee_name' => $latetime[0]['employee_name'], 
        'value'        => $latetime[0]['value'],
        'amount'       => $latetime[0]['amount'],
        'apply_date'   => $latetime[0]['apply_date'],
        'content'      => $latetime[0]['content'],
        'status'       => $latetime[0]['status'],
        'edit'         => 1,
    ];

    echo $app->render('templates/staffConfiguration/latetime-post.html', $vars, 'global');
})->setPermissions(['latetime.edit']);



$app->router("/staffConfiguration/latetime-edit/{id}", 'POST', function($vars) use ($app, $jatbi) {
    $app->header([
        'Content-Type' => 'application/json',
    ]);

    $id = $vars['id'];
    $existingLatetime = $app->get("latetime", ["id"], ["id" => $id]);
    if (!$existingLatetime) {
        echo json_encode([
            'status' => 'error',
            'content' => $jatbi->lang("Bản ghi không tồn tại"),
        ]);
        return;
    }

    $type = trim($_POST['type'] ?? '');
    $sn = trim($_POST['sn'] ?? '');
    $value = trim($_POST['value'] ?? '');
    $amount = trim($_POST['amount'] ?? '');
    $apply_date = trim($_POST['apply_date'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $status = trim($_POST['status'] ?? '');

    if ($type == '' || $sn == '' || $value == '' || $amount == '' || $apply_date == '') {
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


    $latetimeData = [
        "type"       => $type,
        "sn"         => $sn,
        "value"      => (int) $value,
        "amount"     => (float) $amount,
        "apply_date" => date("Y-m-d", strtotime($apply_date)),
        "content"    => $content,
        "status"     => $status,
    ];

    $updated = $app->update("latetime", $latetimeData, ["id" => $id]);

    if (!$updated) {
        echo json_encode([
            'status' => 'error',
            'content' => $jatbi->lang("Lỗi khi cập nhật cơ sở dữ liệu"),
        ]);
        return;
    }

    $jatbi->logs('latetime', 'latetime-edit', $latetimeData);

    echo json_encode([
        'status' => 'success',
        'content' => $jatbi->lang("Cập nhật đi trễ về sớm thành công"),
        'reload' => true,
    ]);
})->setPermissions(['latetime.edit']);

//----------------------------------------Xóa Đi trễ về sớm----------------------------------------
$app->router("/staffConfiguration/latetime-deleted", 'GET', function($vars) use ($app, $jatbi) {
    $vars['title'] = $jatbi->lang("Xóa Đi trễ về sớm");
    echo $app->render('templates/common/deleted.html', $vars, 'global');
})->setPermissions(['latetime.deleted']);

$app->router("/staffConfiguration/latetime-deleted", 'POST', function($vars) use ($app, $jatbi) {
    $app->header([
        'Content-Type' => 'application/json',
    ]);

    $box = $_POST['box'] ?? $_GET['box'] ?? null;
    if (!$box) {
        echo json_encode([
            'status' => 'error',
            'content' => $jatbi->lang("Vui lòng chọn ít nhất một bản ghi để xóa"),
        ]);
        return;
    }

    $ids = is_array($box) ? $box : explode(',', $box);
    $existingRecords = $app->select("latetime", ["id"], ["id" => $ids]);
    if (empty($existingRecords)) {
        echo json_encode([
            'status' => 'error',
            'content' => $jatbi->lang("Không tìm thấy bản ghi nào để xóa"),
        ]);
        return;
    }

    $validIDs = array_column($existingRecords, 'id');
    $deleted = $app->delete("latetime", ["id" => $validIDs]);

    if (!$deleted) {
        echo json_encode([
            'status' => 'error',
            'content' => $jatbi->lang("Lỗi khi xóa bản ghi"),
        ]);
        return;
    }

    $jatbi->logs('latetime', 'latetime-deleted', ['ids' => $validIDs]);

    echo json_encode([
        'status' => 'success',
        'content' => $jatbi->lang("Xóa thành công"),
        'reload' => true,
    ]);
})->setPermissions(['latetime.deleted']);

//----------------------------------------Cập nhật trạng thái----------------------------------------
$app->router("/staffConfiguration/latetime-status/{id}", 'POST', function($vars) use ($app, $jatbi) {
    $app->header([
        'Content-Type' => 'application/json',
    ]);
    $data = $app->get("latetime", "*", ["id" => $vars['id']]);
    if ($data) {
        if ($data['status'] === 'A') {
            $status = "D";
        } elseif ($data['status'] === 'D') {
            $status = "A";
        }
        $app->update("latetime", ["status" => $status], ["id" => $data['id']]);
        $jatbi->logs('latetime', 'latetime-status', $data);
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
    } else {
        echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
    }
})->setPermissions(['latetime.edit']);
?>