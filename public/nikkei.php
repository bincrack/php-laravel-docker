<?php
$headers_ignore = array('host', 'connection');
$headers = [  
    'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36',  
    'accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
];  
foreach ($_SERVER as $key => $value) {
    if (substr($key, 0, 5) === 'HTTP_') {
        $key = substr($key, 5);
        $key = str_replace('_', '-', $key);
        $key = strtolower($key);
        if (!in_array($key, $headers_ignore)) {
            $headers[$key] = $value;
        }
    }
}
var_dump($headers);

$scheme = isset( $_REQUEST[ 'scheme' ] ) && !empty( $_REQUEST[ 'scheme' ] ) ? $_REQUEST[ 'scheme' ] : 'https';
$path = isset( $_REQUEST[ 'path' ] ) && !empty( $_REQUEST[ 'path' ] ) ? $_REQUEST[ 'path' ] : 'www.baidu.com';
$url = $scheme."://".$path;
var_dump($scheme);
var_dump($path);
var_dump($url);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);  
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);  
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_NOBODY, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 设置超时时间  
// curl_setopt($ch, CURLOPT_PROXY, 'http://127.0.0.1:7890'); // 设置代理服务器
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if (curl_errno($ch)) {
    echo sprintf($err_msg, curl_error($ch));
} else {
    if ($httpCode == 200) {
    } else {
        echo sprintf("请求失败, 响应码: %s", $httpCode);
    }
}
phpinfo();
?>
