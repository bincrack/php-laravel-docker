<?php
phpinfo();
exit();
$headers_ignore = array('host', 'connection');
$headers = [  
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36',  
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',  
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
// phpinfo();
// exit();

$err_msg = <<<EOT
<?xml version="1.0" encoding="utf-8"?>
<rss version="1.0">
    <channel>
        <title>日经中文网</title>
        <description></description>
        <link>https://cn.nikkei.com/</link>
        <item>
            <title>RSS订阅获取失败</title>
            <link>http://cn.nikkei.com/</link>
            <description>%s</description>
            <pubDate>Thu, 31 Oct 2024 09:34:22 +0000</pubDate>
        </item>
        </item>
    </channel>
</rss>
EOT;


$domain = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : 'baidu.com';
$base = "https://$domain/nikkei";

function to_rss($body) { 
    global $base;
    $xml = simplexml_load_string($body);
    foreach ($xml->channel->item as $k => $v) {
        $v->link = str_replace("http://cn.nikkei.com", "$base/https://cn.nikkei.com", $v->link);;
    }
    return $xml->asXML();
}

$scheme = isset( $_REQUEST[ 'scheme' ] ) && !empty( $_REQUEST[ 'scheme' ] ) ? $_REQUEST[ 'scheme' ] : 'https';
$path = isset( $_REQUEST[ 'path' ] ) && !empty( $_REQUEST[ 'path' ] ) ? $_REQUEST[ 'path' ] : 'www.baidu.com';
$url = $scheme.'://'.$path;
// $url = 'https://cn.nikkei.com/rss.html'; 
var_dump($scheme);
var_dump($path);
var_dump($url);
phpinfo();
exit();
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
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $body = substr($response, $header_size);  
        if ($path == '/rss.html') {
            header('content-disposition: inline; filename=RSS-0.xml');
            header('content-type: application/xml; charset=utf-8; filename=RSS-0.xml');

            echo to_rss($body);
        } else {
            $headers = substr($response, 0, $header_size);  
            // var_dump($headers);
            foreach (explode("\n", $headers) as $key => $value) {
                $value = trim($value);
                if ($key > 0 && !empty($value)) {
                    // var_dump($value);
                    $name = strtolower(trim(explode(":", $value)[0]));
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
            }
            if ($url === 'https://cn.nikkei.com/rss.html') {
                echo to_rss($body);
            } else {
                echo $body;
            }
        }
    } else {
        if ($url === 'https://cn.nikkei.com/rss.html') {
            echo sprintf($err_msg, 'RSS源异常: '.$httpCode);
        } else {
            echo sprintf("请求失败, 响应码: %s", $httpCode);
        }
    }
}

curl_close($ch);
?>
