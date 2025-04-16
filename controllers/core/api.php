<?php
if (!defined('ECLO')) die("Hacking attempt");

$jatbi = new Jatbi($app);
$setting = $app->getValueData('setting');

$app->router("/api", 'POST', function($vars) use ($app, $jatbi, $setting) {
    $app->header(['Content-Type' => 'application/json']);

    // Lấy dữ liệu từ request
    $input = file_get_contents("php://input");
    file_put_contents("log1.txt", "Raw Input: " . $input . PHP_EOL, FILE_APPEND);

    // Chuyển đổi từ URL-encoded string thành mảng
    parse_str($input, $decoded_params);

    if (!$decoded_params) {
        file_put_contents("log1.txt", "❌ Lỗi: Không thể parse dữ liệu!" . PHP_EOL, FILE_APPEND);
        echo json_encode(["status" => "error", "message" => "Invalid data format"]);
        return;
    }

    // Lấy dữ liệu với kiểm tra giá trị có tồn tại không
    $id          = isset($decoded_params['recordId']) ? $decoded_params['recordId'] : null;
    $sn          = isset($decoded_params['personSn']) ? $decoded_params['personSn'] : null;
    $checkImgUrl = isset($decoded_params['checkImgBase64']) ? urldecode($decoded_params['checkImgBase64']) : null;
    $personName  = isset($decoded_params['personName']) ? urldecode($decoded_params['personName']) : null;
    $personType  = isset($decoded_params['personType']) ? $decoded_params['personType'] : null;
    $createTime  = isset($decoded_params['recordTimeStr']) ? $decoded_params['recordTimeStr'] : null;
    $flag        = isset($decoded_params['openDoorFlag']) ? $decoded_params['openDoorFlag'] : null;


    // Ghi log các biến để kiểm tra
    $log_data = "Parsed Data:\n";
    foreach ($decoded_params as $key => $value) {
        $log_data .= "$key: $value\n";
    }
    file_put_contents("log1.txt", $log_data . PHP_EOL, FILE_APPEND);

    // Chuẩn bị dữ liệu chèn vào database
    $insert = [
        "id" => $id,
        "personSn"     => $sn,
        "personName"   => $personName,
        "personType"   => $personType,
        "createTime"   => $createTime,
        "checkImgUrl"  => $checkImgUrl
    ];

    // Thêm dữ liệu vào bảng API (log request)
    $app->insert("API", ["data" => json_encode($decoded_params)]);

    if ($flag == 1) {
        $app->insert("record", $insert);
    } else {
        file_put_contents("log1.txt", "⚠️ Bỏ qua insert record vì flag = 1\n", FILE_APPEND);
    }
    
    echo json_encode(["status" => "success"]);
});
?>

