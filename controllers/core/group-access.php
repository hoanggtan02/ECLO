<?php
if (!defined('ECLO')) die("Hacking attempt");
$jatbi = new Jatbi($app);
$setting = $app->getValueData('setting');

// 👉 Route GET hiển thị danh sách nhóm kiểm soát
$app->router("/control/group-access", 'GET', function($vars) use ($app, $jatbi) {
    $vars['title'] = $jatbi->lang("Nhóm kiểm soát");
    echo $app->render('templates/group-access/group-access.html', $vars);
})->setPermissions(['group-access']);

// 👉 Route POST lấy dữ liệu nhóm kiểm soát (DataTable)
$app->router("/control/group-access", 'POST', function($vars) use ($app, $jatbi) {
    $app->header(['Content-Type' => 'application/json']);

    $draw = $_POST['draw'] ?? 0;
    $start = $_POST['start'] ?? 0;
    $length = $_POST['length'] ?? 10;
    $searchValue = $_POST['search']['value'] ?? '';

    $orderColumnIndex = $_POST['order'][0]['column'] ?? 1;
    $orderDir = strtoupper($_POST['order'][0]['dir'] ?? 'DESC');
    
    $validColumns = ["acGroupNumber", "name", "acTzNumber1", "acTzNumber2", "acTzNumber3"];
    $orderColumn = $validColumns[$orderColumnIndex] ?? "acGroupNumber";

    $where = [
        "AND" => [
            "OR" => [
                "group-access.acGroupNumber[~]" => $searchValue,
                "group-access.name[~]" => $searchValue,
            ]
        ],
        "LIMIT" => [$start, $length],
        "ORDER" => [$orderColumn => $orderDir]
    ];

    $count = $app->count("group-access", ["AND" => $where["AND"]]);
    
    $datas = $app->select("group-access", ['acGroupNumber', 'name', 'acTzNumber1', 'acTzNumber2', 'acTzNumber3'], $where) ?? [];
    
    // Lấy danh sách acTzNumber cần tìm
    $acTzNumbers = array_unique(array_filter(array_merge(
        array_column($datas, 'acTzNumber1'),
        array_column($datas, 'acTzNumber2'),
        array_column($datas, 'acTzNumber3')
    ), function($value) {
        return $value !== "0" && !empty($value);
    }));

    // Lấy danh sách timeperiods để map với acTzNumber
    $timeperiods = [];
    if (!empty($acTzNumbers)) {
        $timeperiods = $app->select("timeperiod", [
            "acTzNumber", 
            "monStart", "monEnd",
            "tueStart", "tueEnd",
            "wedStart", "wedEnd",
            "thursStart", "thursEnd",
            "friStart", "friEnd",
            "satStart", "satEnd",
            "sunStart", "sunEnd"
        ], ["acTzNumber" => $acTzNumbers]);
    }

    // Mapping thứ trong tuần
    $days = [
        "mon"   => "Thứ Hai",
        "tue"   => "Thứ Ba",
        "wed"   => "Thứ Tư",
        "thurs" => "Thứ Năm",
        "fri"   => "Thứ Sáu",
        "sat"   => "Thứ Bảy",
        "sun"   => "Chủ Nhật"
    ];

    // Chuyển danh sách timeperiod thành array dễ tìm kiếm
    $tzMapping = [];
    foreach ($timeperiods as $tp) {
        $label = [];
        foreach ($days as $key => $dayName) {
            $start = $tp[$key . "Start"];
            $end = $tp[$key . "End"];
            if ($start !== "00:00" || $end !== "23:59") {
                $label[] = "$dayName: $start-$end";
            }
        }
        $tzMapping[$tp['acTzNumber']] = !empty($label) ? implode(" | ", $label) : "Cả tuần: 00:00-23:59";
    }

    // Format dữ liệu trả về
    $formattedData = array_map(function($data) use ($app, $jatbi, $tzMapping) {
        return [
            "acGroupNumber" => $data['acGroupNumber'],
            "name" => $data['name'],
            "acTzNumber1" => $data['acTzNumber1'] == "0" ? "Không có" : ($tzMapping[$data['acTzNumber1']] ?? "Không có"),
            "acTzNumber2" => $data['acTzNumber2'] == "0" ? "Không có" : ($tzMapping[$data['acTzNumber2']] ?? "Không có"),
            "acTzNumber3" => $data['acTzNumber3'] == "0" ? "Không có" : ($tzMapping[$data['acTzNumber3']] ?? "Không có"),
            "action" => $app->component("action", [
                "button" => [
                    [
                        'type' => 'button',
                        'name' => $jatbi->lang("Sửa"),
                        'permission' => ['group-access.edit'],
                        'action' => ['data-url' => '/control/group-access-edit?id='.$data['acGroupNumber'], 'data-action' => 'modal']
                    ],
                    [
                        'type' => 'button',
                        'name' => $jatbi->lang("Xóa"),
                        'permission' => ['group-access.deleted'],
                        'action' => ['data-url' => '/control/group-access-deleted?id='.$data['acGroupNumber'], 'data-action' => 'modal']
                    ],
                ]
            ]),            
        ];
    }, $datas);

    echo json_encode([
        "draw" => $draw,
        "recordsTotal" => $count,
        "recordsFiltered" => $count,
        "data" => $formattedData
    ]);
})->setPermissions(['group-access']);

// 👉 Route GET hiển thị trang thêm nhóm kiểm soát
$app->router("/control/group-access-add", 'GET', function($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Thêm Nhóm Kiểm Soát");
    echo $app->render('templates/group-access/group-access-post.html', $vars, 'global');
})->setPermissions(['group-access.add']);

// 👉 Route POST xử lý thêm nhóm kiểm soát
$app->router("/control/group-access-add", 'POST', function($vars) use ($app, $jatbi) {
    $app->header(['Content-Type' => 'application/json']);

    $data = [
        "acGroupNumber" => $app->xss($_POST['acGroupNumber'] ?? ''),
        "name" => $app->xss($_POST['name'] ?? ''),
        "acTzNumber1" => $app->xss($_POST['acTzNumber1'] ?? ''),
        "acTzNumber2" => $app->xss($_POST['acTzNumber2'] ?? ''),
        "acTzNumber3" => $app->xss($_POST['acTzNumber3'] ?? ''),
    ];

    if (empty($data["acGroupNumber"]) || empty($data["name"])) {
        echo json_encode(["status" => "error", "content" => "Vui lòng không để trống"]);
        return;
    }

    $app->insert("group-access", $data);
    $jatbi->logs('group-access', 'group-access-add', $data);

    $app->apiPost('http://camera.ellm.io:8190/api/ac_group/merge', array_merge($data, [
        'deviceKey' => '77ed8738f236e8df86',
        'secret' => '123456',
    ]), ['Authorization: Bearer your_token']);

    echo json_encode(["status" => "success", "content" => "Thêm nhóm kiểm soát thành công"]);
})->setPermissions(['group-access.add']);

// 👉 Route GET hiển thị trang chỉnh sửa nhóm kiểm soát
$app->router("/control/group-access-edit", 'GET', function($vars) use ($app, $jatbi) {
    $vars['title'] = $jatbi->lang("Sửa Nhóm Kiểm Soát");

    $acGroupNumber = $_GET['id'] ?? null;
    if (!$acGroupNumber) {
        echo $app->render('templates/common/error-modal.html', $vars, 'global');
        return;
    }

    $vars['data'] = $app->get("group-access", "*", ["acGroupNumber" => $acGroupNumber]);
    if ($vars['data']) {
        echo $app->render('templates/group-access/group-access-post.html', $vars, 'global');
    } else {
        echo $app->render('templates/common/error-modal.html', $vars, 'global');
    }
})->setPermissions(['group-access.edit']);

// 👉 Route POST xử lý cập nhật nhóm kiểm soát
$app->router("/control/group-access-edit", 'POST', function($vars) use ($app, $jatbi) {
    $app->header(['Content-Type' => 'application/json']);

    $acGroupNumber = $_POST['acGroupNumber'] ?? null;
    if (!$acGroupNumber) {
        echo json_encode(["status" => "error", "content" => "Mã nhóm không hợp lệ"]);
        return;
    }

    $update = [
        "name" => $app->xss($_POST['name'] ?? ''),
        "acTzNumber1" => $app->xss($_POST['acTzNumber1'] ?? ''),
        "acTzNumber2" => $app->xss($_POST['acTzNumber2'] ?? ''),
        "acTzNumber3" => $app->xss($_POST['acTzNumber3'] ?? ''),
    ];

    $app->update("group-access", $update, ["acGroupNumber" => $acGroupNumber]);
    $jatbi->logs('group-access', 'group-access-edit', $update);

    $app->apiPost('http://camera.ellm.io:8190/api/ac_group/merge', array_merge($update, [
        'deviceKey' => '77ed8738f236e8df86',
        'secret' => '123456',
        'acGroupNumber' => $acGroupNumber,
    ]), ['Authorization: Bearer your_token']);

    echo json_encode(["status" => "success", "content" => "Cập nhật thành công"]);
})->setPermissions(['group-access.edit']);
?>
