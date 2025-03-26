<?php
    if (!defined('ECLO')) die("Hacking attempt");
    $jatbi = new Jatbi($app);
    $setting = $app->getValueData('setting');

    $app->router("/reward_discipline", 'GET', function($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Kỉ luật và khen thưởng");
        // $vars['add'] = '/reward_discipline-add';
        // $vars['deleted'] = '/reward_discipline-deleted';
        echo $app->render('templates/reward_discipline/reward_discipline.html', $vars);
    })->setPermissions(['reward_discipline']);

    $app->router("/reward_discipline", 'POST', function($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
    
        // Nhận dữ liệu từ DataTable
        $draw = $_POST['draw'] ?? 0;
        $start = $_POST['start'] ?? 0;
        $length = $_POST['length'] ?? 10;
        $searchValue = $_POST['search']['value'] ?? '';
        $type = $_POST['type'] ?? '';
        $personSN = $_POST['personsn'] ?? ''; // Lọc theo personSN
        $startDate = $_POST['start_date'] ?? '';
        $endDate = $_POST['end_date'] ?? '';
    
        $orderColumnIndex = $_POST['order'][0]['column'] ?? 1;
        $orderDir = strtoupper($_POST['order'][0]['dir'] ?? 'DESC');
    
        // Danh sách cột hợp lệ
        $validColumns = [
            "checkbox",
            "reward_discipline.type",
            "employee.name",
            "reward_discipline.amount",
            "reward_discipline.apply_date",
            "reward_discipline.content",
            "reward_discipline.created_at"
        ];
        $orderColumn = $validColumns[$orderColumnIndex] ?? "reward_discipline.created_at";
    
        // Điều kiện lọc dữ liệu
        $conditions = ["AND" => []];
    
        if (!empty($searchValue)) {
            $conditions["AND"]["OR"] = [
                "employee.name[~]" => $searchValue,
                "reward_discipline.content[~]" => $searchValue
            ];
        }
    
        if (!empty($type)) {
            $conditions["AND"]["reward_discipline.type"] = $type;
        }

        if (!empty($personSN)) {
            $conditions["AND"]["reward_discipline.personSN"] = $personSN;
        }
    
        if (!empty($startDate) && !empty($endDate)) {
            $conditions["AND"]["reward_discipline.apply_date[<>]"] = [
                date("Y-m-d", strtotime($startDate)),
                date("Y-m-d", strtotime($endDate))
            ];
        }
    
        // Kiểm tra nếu conditions bị trống, tránh lỗi SQL
        if (empty($conditions["AND"])) {
            unset($conditions["AND"]);
        }
        
        // Đếm tổng số bản ghi (không dùng LIMIT)
        $count = $app->count("reward_discipline", [
            "[>]employee" => ["personSN" => "sn"]
        ], "reward_discipline.id", $conditions);
    
        // Truy vấn danh sách thưởng/phạt với LIMIT, ORDER
        $datas = $app->select("reward_discipline", [
            "[>]employee" => ["personSN" => "sn"]
        ], [
            "reward_discipline.id",
            "reward_discipline.personSN",
            "employee.name",
            "reward_discipline.type",
            "reward_discipline.amount",
            "reward_discipline.apply_date",
            "reward_discipline.content",
            "reward_discipline.created_at"
        ], array_merge($conditions, [
            "LIMIT" => [$start, $length],
            "ORDER" => [$orderColumn => $orderDir]
        ])) ?? [];
        
        // Xử lý dữ liệu đầu ra
        $formattedData = array_map(function($data) use ($app, $jatbi) {
            return [
                "checkbox" => $app->component("box", ["data" => $data['id']]),
                "type" => ($data['type'] === 'reward') ? 'Khen thưởng' : 'Kỷ luật',
                "employee_name" => $data['name'] ?? $jatbi->lang("Không xác định"),
                "amount" => number_format($data['amount'], 0),
                "apply_date" => date("d/m/Y", strtotime($data['apply_date'])),
                "content" => $data['content'],
                "created_at" => date("d/m/Y H:i:s", strtotime($data['created_at'])),
                "action" => $app->component("action", [
                    "button" => [
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Sửa"),
                            'permission' => ['reward_discipline.edit'],
                            'action' => ['data-url' => '/reward_discipline-edit?id=' . $data['id'], 'data-action' => 'modal']
                        ],
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Xóa"),
                            'permission' => ['reward_discipline.deleted'],
                            'action' => ['data-url' => '/reward_discipline-deleted?id=' . $data['id'], 'data-action' => 'modal']
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
            "data" => $formattedData
        ]);
    })->setPermissions(['reward_discipline']);
    
    $app->router("/reward_discipline-add", 'GET', function($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Thêm khen thưởng kỉ luật");
        echo $app->render('templates/reward_discipline/reward_discipline-post.html', $vars, 'global');
    })->setPermissions(['reward_discipline.add']);
    
    $app->router("/reward_discipline-add", 'POST', function($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
    
        // Lấy dữ liệu từ request và lọc XSS
        $personSN  = $app->xss($_POST['personSN'] ?? '');
        $type      = $app->xss($_POST['type'] ?? ''); // reward | discipline
        $amount    = (int) str_replace(',', '', $app->xss($_POST['amount'] ?? ''));
        $applyDate = $app->xss($_POST['apply_date'] ?? '');
        $content   = $app->xss($_POST['content'] ?? '');
    
        // Kiểm tra dữ liệu đầu vào
        $errors = [];
        if (empty($personSN))  $errors[] = "Mã nhân viên";
        if (empty($type))      $errors[] = "Loại (Khen thưởng/Kỷ luật)";
        if (empty($applyDate)) $errors[] = "Ngày áp dụng";
    
        if (!empty($errors)) {
            echo json_encode(["status" => "error", "content" => "Thiếu dữ liệu: " . implode(", ", $errors)]);
            return;
        }
    
        // Kiểm tra type hợp lệ
        if (!in_array($type, ['reward', 'discipline'])) {
            echo json_encode(["status" => "error", "content" => "Loại không hợp lệ"]);
            return;
        }
    
        try {
            // Lưu vào database
            $app->insert("reward_discipline", [
                "personSN"   => $personSN,
                "type"       => $type,
                "amount"     => $amount,
                "apply_date" => $applyDate,
                "content"    => $content,
                "created_at" => date("Y-m-d H:i:s")
            ]);
    
            echo json_encode([
                "status"  => "success",
                "content" => "Thêm thành công!",
                "type"    => $type,
                "amount"  => number_format($amount, 0, ',', '.') // Chỉ format khi hiển thị
            ]);
        } catch (Exception $e) {
            echo json_encode(["status" => "error", "content" => "Lỗi hệ thống: " . $e->getMessage()]);
        }
    })->setPermissions(['reward_discipline.add']);
    
    
    // Xóa khen thưởng / kỷ luật
    $app->router("/reward_discipline-deleted", 'GET', function($vars) use ($app, $jatbi) {
        $vars['title'] = $jatbi->lang("Xóa Khen Thưởng / Kỷ Luật");
        echo $app->render('templates/common/deleted.html', $vars, 'global');
    })->setPermissions(['reward_discipline.deleted']);

    $app->router("/reward_discipline-deleted", 'POST', function($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        
        // Lấy danh sách ID cần xóa
        $recordIds = [];
        if (!empty($_GET['id'])) {
            $recordIds[] = $app->xss($_GET['id']);
        } elseif (!empty($_GET['box'])) {
            $recordIds = array_map('trim', explode(',', $app->xss($_GET['box'])));
        }
        
        if (empty($recordIds)) {
            echo json_encode(["status" => "error", "content" => "Thiếu ID cần xóa"]);
            return;
        }
        
        try {
            $deletedCount = 0;
            $errors = [];
        
            foreach ($recordIds as $recordId) {
                if (empty($recordId)) continue; // Bỏ qua nếu giá trị rỗng
        
                // Xóa khỏi database
                $deleteResult = $app->delete("reward_discipline", ["id" => $recordId]);
        
                if ($deleteResult->rowCount() > 0) {
                    $deletedCount++;
                } else {
                    $errors[] = "ID $recordId: Không tìm thấy hoặc không thể xóa";
                }
            }
        
            if (!empty($errors)) {
                echo json_encode([
                    "status" => "error",
                    "content" => "Một số bản ghi xóa thất bại",
                    "errors" => $errors
                ]);
            } else {
                echo json_encode([
                    "status" => "success",
                    "content" => "Đã xóa thành công $deletedCount bản ghi"
                ]);
            }
        } catch (Exception $e) {
            echo json_encode(["status" => "error", "content" => "Lỗi: " . $e->getMessage()]);
        }
    })->setPermissions(['reward_discipline.deleted']);


// Sửa khen thưởng / kỷ luật
$app->router("/reward_discipline-edit", 'GET', function($vars) use ($app, $jatbi) {
    $vars['title'] = $jatbi->lang("Sửa Khen Thưởng / Kỷ Luật");

    $id = isset($_GET['id']) ? $app->xss($_GET['id']) : null;
    if (!$id) {
        echo $app->render('templates/common/error-modal.html', $vars, 'global');
        return;
    }

    $vars['data'] = $app->get("reward_discipline", "*", ["id" => $id]);
    $vars['data']['edit'] = true;
    if ($vars['data']) {
        echo $app->render('templates/reward_discipline/reward_discipline-post.html', $vars, 'global');
    } else {
        echo $app->render('templates/common/error-modal.html', $vars, 'global');
    }
})->setPermissions(['reward_discipline.edit']);

$app->router("/reward_discipline-edit", 'POST', function($vars) use ($app, $jatbi) {
    $app->header([
        'Content-Type' => 'application/json',
    ]);

    $id = isset($_POST['id']) ? $app->xss($_POST['id']) : null;


    if (!$id) {
        echo json_encode(["status" => "error", "content" => $jatbi->lang("ID không hợp lệ")]);
        return;
    }

    $data = $app->get("reward_discipline", "*", ["id" => $id]);

    if (!$data) {
        echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy bản ghi")]);
        return;
    }

    // Lấy dữ liệu từ request
    $personSN  = isset($_POST['personSN']) ? $app->xss($_POST['personSN']) : '';
    $type      = isset($_POST['type']) ? $app->xss($_POST['type']) : ''; // reward | discipline
    $amount    = isset($_POST['amount']) ? $app->xss($_POST['amount']) : '';
    $applyDate = isset($_POST['apply_date']) ? $app->xss($_POST['apply_date']) : '';
    $content   = isset($_POST['content']) ? $app->xss($_POST['content']) : '';

    // Kiểm tra dữ liệu đầu vào
    $errors = [];
    if ($personSN === '')  $errors[] = "Mã nhân viên không được để trống";
    if ($type === '')      $errors[] = "Loại không được để trống";
    if ($amount === '')    $errors[] = "Số tiền không được để trống";
    if ($applyDate === '') $errors[] = "Ngày áp dụng không được để trống";

    if (!empty($errors)) {
        echo json_encode(["status" => "error", "content" => implode(", ", $errors)]);
        return;
    }

    // Kiểm tra type hợp lệ
    if (!in_array($type, ['reward', 'discipline'])) {
        echo json_encode(["status" => "error", "content" => "Loại không hợp lệ"]);
        return;
    }

    // Chuyển đổi số tiền về định dạng lưu trữ chuẩn
    $amount = str_replace(',', '', $amount); // Loại bỏ dấu phẩy

    // Cập nhật dữ liệu
    $update = [
        "personSN"   => $personSN,
        "type"       => $type,
        "amount"     => $amount,
        "apply_date" => $applyDate,
        "content"    => $content,
        "created_at" => date("Y-m-d H:i:s"), // Cập nhật thời gian chỉnh sửa
    ];

    // Debug: Log dữ liệu cập nhật
    error_log("Update Data: " . json_encode($update));

    // Thực hiện cập nhật
    $result = $app->update("reward_discipline", $update, ["id" => $id]);

    if (!$result) {
        error_log("SQL Update Error: " . json_encode($app->error()));
        echo json_encode(["status" => "error", "content" => "Lỗi cập nhật dữ liệu"]);
        return;
    }

    // Ghi log thay đổi
    $jatbi->logs('reward_discipline', 'reward_discipline-edit', $update);

    // Phản hồi thành công
    echo json_encode(["status" => "success", "content" => $jatbi->lang("Cập nhật thành công!")]);
})->setPermissions(['reward_discipline.edit']);

    
?>
