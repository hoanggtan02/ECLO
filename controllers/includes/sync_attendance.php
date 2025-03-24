<?php
$api_url = "http://camera.ellm.io:8190/api/record/findList";
$response = file_get_contents($api_url);
$data = json_decode($response, true);

if (!empty($data) && isset($data['records'])) {
    foreach ($data['records'] as $record) {
        $personName = $record['personName'];
        $personSn = $record['personSn'];
        $personType = $record['personType'];
        $createTime = $record['createTime'];
        $checkImgUrl = $record['checkImgUrl'] ?? null; 

        // Kiểm tra xem dữ liệu đã tồn tại chưa
        $exists = $app->select("records", ["id"], [
            "personSn" => $personSn,
            "createTime" => $createTime
        ]);

        if (empty($exists)) {
            // Lưu vào database
            $app->insert("records", [
                "personName" => $personName,
                "personSn" => $personSn,
                "personType" => $personType,
                "createTime" => $createTime,
                "checkImgUrl" => $checkImgUrl
            ]);

            // Gửi dữ liệu mới đến WebSocket
            $wsClient = stream_socket_client("tcp://localhost:8080", $errno, $errstr, 30);
            if ($wsClient) {
                fwrite($wsClient, json_encode($record));
                fclose($wsClient);
            }
        }
    }
}
?>
