<?php
$headers_ignore = array('host', 'connection');
$headers_req = [  
    'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36',  
    'accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
];
$headers_res = array();
foreach ($_SERVER as $key => $value) {
    if (substr($key, 0, 5) === 'HTTP_') {
        $key = substr($key, 5);
        $key = str_replace('_', '-', $key);
        $key = strtolower($key);
        if (!in_array($key, $headers_ignore)) {
            $headers_req[$key] = $value;
        }
    }
}

$scheme = isset( $_REQUEST[ 'scheme' ] ) && !empty( $_REQUEST[ 'scheme' ] ) ? $_REQUEST[ 'scheme' ] : 'https';
$path = isset( $_REQUEST[ 'path' ] ) && !empty( $_REQUEST[ 'path' ] ) ? $_REQUEST[ 'path' ] : 'www.baidu.com';
$url = $scheme."://".$path;
$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $url);  
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);  
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_NOBODY, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 设置超时时间  
// curl_setopt($ch, CURLOPT_PROXY, 'http://127.0.0.1:7890'); // 设置代理服务器
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers_req);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if (curl_errno($ch)) {
    echo sprintf($err_msg, curl_error($ch));
} else {
    if ($httpCode == 200) {
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $body = substr($response, $header_size);  
        $headers_str = substr($response, 0, $header_size);  
        foreach (explode("\n", $headers_str) as $key => $value) {
            $value = trim($value);
            if ($key <= 0 || empty($value)) {
                continue;
            }
            $name = strtolower(trim(explode(":", $value)[0]));
            $headers_res[$name] = trim(substr($value, strlen($name) + 1));
            if ($name === 'content-length') {

            } elseif ($name === 'set-cookie') {
                $tmp = explode(";", substr($value, 11));
                foreach ($tmp as $k => $v) {
                    if (trim(explode("=", $v)[0]) == 'domain') {
                        $tmp[$k] = 'domain='.$domain;
                    }
                }
                header(sprintf("Set-Cookie: %s", join(";", $tmp)));
            } else {
                header($value);
            }
        }
        echo $body;
    } else {
        echo sprintf("请求失败, 响应码: %s", $httpCode);
    }
}
var_dump(array(
    "headers_req" => $headers_req,
    "headers_res" => $headers_res,
    "scheme" => $scheme,
    "path" => $path,
    "url" => $url
));
phpinfo();
/*
*/
?>
