<?php

if (!defined('ECLO')) die("Hacking attempt");
$jatbi = new Jatbi($app);
$setting = $app->getValueData('setting');

$app->router("/staffConfiguration/department", 'GET', function($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Cấu hình nhân sự");
    $vars['title1'] = $jatbi->lang("Phòng ban");
    $vars['employee'] = $app->select("employee",["name (text)","sn (value)"],[]);
    echo $app->render('templates/staffConfiguration/department.html', $vars);
})->setPermissions(['staffConfiguration']);


$app->router("/staffConfiguration/position", 'GET', function($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Chức vụ");
    echo $app->render('templates/staffConfiguration/position.html', $vars);
})->setPermissions(['staffConfiguration']);

$app->router("/staffConfiguration/position", 'POST', function($vars) use ($app, $jatbi) {
    $app->header(['Content-Type' => 'application/json']);

    // Nhận dữ liệu từ DataTable
    $draw = $_POST['draw'] ?? 0;
    $start = $_POST['start'] ?? 0;
    $length = $_POST['length'] ?? 10;
    $searchValue = $_POST['search']['value'] ?? '';

    $orderColumnIndex = $_POST['order'][0]['column'] ?? 1; // Mặc định cột positionId
    $orderDir = strtoupper($_POST['order'][0]['dir'] ?? 'DESC');

    // Danh sách cột hợp lệ
    $validColumns = ["checkbox", "positionName", "positionId", "note", "active"];
    $orderColumn = $validColumns[$orderColumnIndex] ?? "id";

    // Điều kiện lọc dữ liệu
    $where = [
        "AND" => [
            "OR" => [
                "positions.positionId[~]" => $searchValue,
                "positions.positionName[~]" => $searchValue,
            ]
        ],
        "LIMIT" => [$start, $length],
        "ORDER" => [$orderColumn => $orderDir]
    ];

    $count = $app->count("positions", ["AND" => $where["AND"]]);
    $datas = $app->select("positions", '*', $where) ?? [];

    // Xử lý dữ liệu đầu ra
    $formattedData = array_map(function($data) use ($app, $jatbi) {
        return [
            "checkbox" => $app->component("box", ["data" => $data['id']]),
            "positionName" => $data['positionName'],
            "positionId" => $data['positionId'],
            "note" => $data['note'] ?? $jatbi->lang("Không có ghi chú"),
            "active" => $app->component("status", [
                "url" => "/staffConfiguration/position-status/" . $data['id'], 
                "data" => $data['active'], 
                "permission" => ['staffConfiguration.edit']
            ]),
            "action" => $app->component("action", [
                "button" => [
                    [
                        'type' => 'button',
                        'name' => $jatbi->lang("Sửa"),
                        'permission' => ['position.edit'],
                        'action' => ['data-url' => '/staffConfiguration/position-edit?id=' . $data['id'], 'data-action' => 'modal']
                    ],
                    [
                        'type' => 'button',
                        'name' => $jatbi->lang("Xóa"),
                        'permission' => ['position.delete'],
                        'action' => ['data-url' => '/staffConfiguration/position-delete?id=' . $data['id'], 'data-action' => 'modal']
                    ]
                ]
            ]),
        ];
    }, $datas);

    // Trả về dữ liệu JSON
    echo json_encode([
        "draw" => $draw,
        "recordsTotal" => $count,
        "recordsFiltered" => $count,
        "data" => $formattedData
    ]);
})->setPermissions(['staffConfiguration']);


?>
   