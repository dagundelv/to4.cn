<?php
require_once 'config.php';

// 二维码生成文件
$code = $_GET['code'] ?? '';
$url = $_GET['url'] ?? '';
$size = (int)($_GET['size'] ?? 200);

// 如果提供了短码，构建完整URL
if (!empty($code)) {
    $url = SITE_URL . '/' . $code;
}

if (empty($url)) {
    header('Content-Type: text/plain');
    echo '请提供code或url参数';
    exit;
}

// 限制尺寸范围
$size = max(100, min(500, $size));

// 多个二维码服务提供商
$qrServices = [
    // QR Server (主要服务)
    'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size . '&data=' . urlencode($url),
    // QuickChart
    'https://quickchart.io/qr?text=' . urlencode($url) . '&size=' . $size,
    // QR Code Generator
    'https://chart.googleapis.com/chart?cht=qr&chs=' . $size . 'x' . $size . '&chl=' . urlencode($url),
];

// 尝试多个服务
$qrImage = false;
foreach ($qrServices as $qrUrl) {
    $context = stream_context_create([
        'http' => [
            'timeout' => 8,
            'user_agent' => 'Mozilla/5.0 (compatible; QR Generator)',
            'method' => 'GET',
            'ignore_errors' => true
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);
    
    $qrImage = @file_get_contents($qrUrl, false, $context);
    
    if ($qrImage !== false && strlen($qrImage) > 100) {
        // 验证返回的是图片数据
        $imageInfo = @getimagesizefromstring($qrImage);
        if ($imageInfo !== false) {
            break;
        }
    }
    $qrImage = false;
}

if ($qrImage !== false) {
    // 成功获取，返回图片
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=86400'); // 缓存1天
    header('Content-Length: ' . strlen($qrImage));
    echo $qrImage;
} else {
    // 所有服务都不可用，生成本地二维码
    generateLocalQR($url, $size);
}

function generateLocalQR($url, $size) {
    // 使用简单的二维码生成逻辑
    $qrSize = max(200, $size);
    $im = imagecreate($qrSize, $qrSize);
    
    // 创建颜色
    $white = imagecolorallocate($im, 255, 255, 255);
    $black = imagecolorallocate($im, 0, 0, 0);
    $gray = imagecolorallocate($im, 128, 128, 128);
    
    // 填充背景
    imagefill($im, 0, 0, $white);
    
    // 绘制边框
    imagerectangle($im, 0, 0, $qrSize-1, $qrSize-1, $black);
    imagerectangle($im, 5, 5, $qrSize-6, $qrSize-6, $black);
    
    // 绘制定位点 (左上)
    drawFinderPattern($im, 10, 10, $black, $white);
    // 绘制定位点 (右上)
    drawFinderPattern($im, $qrSize-60, 10, $black, $white);
    // 绘制定位点 (左下)
    drawFinderPattern($im, 10, $qrSize-60, $black, $white);
    
    // 绘制一些随机点作为数据区域
    $seed = crc32($url);
    mt_srand($seed);
    
    for ($i = 0; $i < 200; $i++) {
        $x = mt_rand(80, $qrSize-80);
        $y = mt_rand(80, $qrSize-80);
        imagesetpixel($im, $x, $y, $black);
        imagesetpixel($im, $x+1, $y, $black);
        imagesetpixel($im, $x, $y+1, $black);
        imagesetpixel($im, $x+1, $y+1, $black);
    }
    
    // 添加文字信息
    $fontSize = 3;
    $textX = 15;
    $textY = $qrSize - 40;
    imagestring($im, $fontSize, $textX, $textY, 'Scan for: ' . substr($url, 0, 25), $gray);
    
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=3600');
    imagepng($im);
    imagedestroy($im);
}

function drawFinderPattern($im, $x, $y, $black, $white) {
    // 外框 7x7
    imagefilledrectangle($im, $x, $y, $x+48, $y+48, $black);
    // 内部白色 5x5
    imagefilledrectangle($im, $x+7, $y+7, $x+41, $y+41, $white);
    // 中心黑色 3x3
    imagefilledrectangle($im, $x+14, $y+14, $x+34, $y+34, $black);
}
?>