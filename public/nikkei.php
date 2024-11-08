<?php
$headers_ignore = array('host', 'connection', 'x-forwarded-for', 'true-client-ip', 'cf-connecting-ip', 'accept-encoding');
$headers_req = array();
$headers_def_req = array(
    'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36',  
    'accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
);
$headers_res = array();
foreach ($_SERVER as $key => $value) {
    if (substr($key, 0, 5) === 'HTTP_') {
        $key = substr($key, 5);
        $key = str_replace('_', '-', $key);
        $key = strtolower($key);
        if (!in_array($key, $headers_ignore)) {
            $headers_def_req[$key] = $value;
        }
    }
}
foreach ($headers_def_req as $key => $value) {
    array_push($headers_req, $key.': '.$value);
}
$link_url = null;
$link_urls = array();
function to_tag($tag, $tag_name) {
    global $link_urls;
    $tag_val = $tag->getAttribute($tag_name);
    if (empty($tag_val)) {
        return;
    }
    if (strpos($tag_val, 'http://') === 0) {
        $tag->setAttribute($tag_name, ''.substr($tag_val, 7));
    } elseif (strpos($tag_val, 'https://') === 0) {
        $tag->setAttribute($tag_name, ''.substr($tag_val, 8));
    } elseif (strpos($tag_val, '//') === 0) {
        $tag->setAttribute($tag_name, ''.substr($tag_val, 2));
    } elseif (strpos($tag_val, '/') === 0) {
    }
    array_push($link_urls, array($tag->getAttribute($tag_name), $tag_val));
}
$hook_script = <<<EOT
function function_hook(func, obj, arg) {
    var url = null;
    var tag = arg[0];
    if (typeof tag === 'object') {
        if (tag.tagName === 'SCRIPT') {
            url = tag.src;
            if (tag.src.indexOf('https://') === 0) {
                tag.src = tag.src.substr(8);
            }
        } else if (tag.tagName === 'LINK') {
            url = tag.href;
            if (tag.href.indexOf('https://') === 0) {
                tag.href = tag.href.substr(8);
            }
        }

        if (url) {
            console.log('hook', func.name, url);
        }
    }
    func.apply(obj, arg);
}
function function_hook_head_insert() {
    var h = document.getElementsByTagName("head");
    var tmp = h[0].insertBefore;
    h[0].insertBefore = function() {
        function_hook(tmp, this, arguments);
    }
}
function function_hook_head_append() {
    var h = document.getElementsByTagName("head");
    var tmp = h[0].appendChild;
    h[0].appendChild = function() {
        function_hook(tmp, this, arguments);
    }
}
function function_hook_ajax() {
    var tmp = window.XMLHttpRequest.prototype.open;
    window.XMLHttpRequest.prototype.open = function() {
        console.log('Request', arguments);
        // tmp.apply(this, arguments);
    }
}
function_hook_head_insert();
function_hook_head_append();
function_hook_ajax();
// debugger;
EOT;
function to_url($body, $headers) {
    global $hook_script;
    $content_type = $headers['content-type'];
    if (empty($content_type)) {
        return $body;
    }

    list($type, $subType) = explode(';', $content_type);
    if ($type != 'text/html') {
        return $body;
    }

    $dom = new DOMDocument();
    $dom->loadHTML($body);
    $links = $dom->getElementsByTagName('link');
    foreach ($links as $tag) {
        to_tag($tag, 'href');
    }

    $links = $dom->getElementsByTagName('img');
    foreach ($links as $tag) {
        to_tag($tag, 'src');
    }

    $links = $dom->getElementsByTagName('script');
    foreach ($links as $tag) {
        to_tag($tag, 'src');
    }
    $head = $dom->getElementsByTagName('head');
    if ($head->length) {
        $child = null;
        $script = $dom->getElementsByTagName('script');
        if ($script->length) {
            $child = $script->item(0);
        }
        $element = $dom->createElement('script'); 
        $element->nodeValue = $hook_script;

        $head->item(0)->insertBefore($element, $child);
    }

    return $dom->saveHTML();
}

$debug = isset( $_REQUEST[ 'debug' ] ) && !empty( $_REQUEST[ 'debug' ] ) ? $_REQUEST[ 'debug' ] : '0';
$scheme = isset( $_REQUEST[ 'scheme' ] ) && !empty( $_REQUEST[ 'scheme' ] ) ? $_REQUEST[ 'scheme' ] : 'https';
$path = isset( $_REQUEST[ 'path' ] ) && !empty( $_REQUEST[ 'path' ] ) ? $_REQUEST[ 'path' ] : 'www.baidu.com';
// $debug = '1';
// $scheme = 'https';
// $path = 'dash.cloudflare.com';
$url = $scheme."://".$path;
$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $url);  
curl_setopt($ch, CURLOPT_FAILONERROR, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);  
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_NOBODY, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 设置超时时间  
// curl_setopt($ch, CURLOPT_PROXY, 'http://127.0.0.1:7890'); // 设置代理服务器
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers_req);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if (curl_errno($ch)) {
    echo sprintf("<div class='center'>请求失败: %s</div>", curl_error($ch));
} else {
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $body = substr($response, $header_size);  
    $headers_str = substr($response, 0, $header_size);  
    $redirect = '';
    foreach (explode("\n", $headers_str) as $key => $value) {
        $value = trim($value);
        if ($key <= 0 || empty($value)) {
            continue;
        }
        $name = strtolower(trim(explode(":", $value)[0]));
        $headers_res[$name] = trim(substr($value, strlen($name) + 1));
        if ($name === 'content-length') {

        } elseif ($name === 'location') {
            $redirect = substr($value, 9);
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
    if ($http_code == 200 || $http_code == 400 || $http_code == 403) {
        echo to_url($body, $headers_res);
    } elseif ($http_code == 301 || $http_code == 302) {
        echo sprintf("<div class='center'>跳转地址: <a href='%s'>%s</a></div>", $redirect, $redirect);
    } else {
        echo sprintf("<div class='center'>请求失败, 响应码: %s</div>", $http_code);
    }
}
$page_info = array(
    "headers_req" => $headers_req,
    "headers_res" => $headers_res,
    "scheme" => $scheme,
    "path" => $path,
    "url" => $url,
    "link_urls" => $link_urls,
    "http_code" => $http_code
);
if ($debug == '1') {
?>
<style type="text/css">
    .center td.e, .center td.v, .center pre {
        font-size: 14px;
    }
</style>
<div class="center">
<table>
<?php	foreach ( $page_info as $k => $v ) { ?>
    <tr>
        <td class="e"><?=$k ?> </td>
        <td class="v"><pre><?=var_export($v, true) ?></pre></td>
    </tr>
<?php	} ?>
</table>
</div>
<?php
    phpinfo();
}
?>