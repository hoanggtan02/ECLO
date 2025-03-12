<?php 
// Đường dẫn tới file ảnh
$imagePath = 'z6398751549502_a7cc5486ec456b8d84ccd21422de21a6.jpg';

// Đọc nội dung file ảnh
$imageData = file_get_contents($imagePath);

// Chuyển sang base64
$base64Image = base64_encode($imageData);

// (Tùy chọn) Thêm tiền tố data URI để hiển thị trực tiếp trên web
$imageType = pathinfo($imagePath, PATHINFO_EXTENSION);
$base64ImageWithPrefix = ',' . $base64Image;

echo $base64ImageWithPrefix;
?>
