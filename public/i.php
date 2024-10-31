<?php

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

$headers = [  
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36',  
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',  
];  

$path = isset( $_REQUEST[ 'path' ] ) ? $_REQUEST[ 'path' ] : '/rss.html';
$url = 'https://cn.nikkei.com/' . $path; 
// $url = 'https://cn.nikkei.com/rss.html';
// $url = 'https://blog.csdn.net/wuxiaobingandbob/article/details/70314102';
$ch = curl_init();  

curl_setopt($ch, CURLOPT_URL, $url);  
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);  
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_NOBODY, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 设置超时时间  
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
        echo $body;
    } else {
        echo sprintf($err_msg, 'RSS源异常: '.$httpCode);
    }
}

curl_close($ch);
?>
