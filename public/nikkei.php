<?php
$headers_ignore = array(
    'host', 'connection', 'x-forwarded-for', 'true-client-ip', 
    'cf-worker', 
    'cf-connecting-ip', 
    'cf-ipcountry', 
    'accept-encoding'
);
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
$link_urls = array();
function to_tag($tag, $tag_name) {
    global $link_urls, $scheme, $base_url;
    $tag_val = $tag->getAttribute($tag_name);
    if (empty($tag_val)) {
        return;
    }
    if (strpos($tag_val, 'http://') === 0) {
        $tag->setAttribute($tag_name, '/nikkei/http/'.substr($tag_val, 7));
    } elseif (strpos($tag_val, 'https://') === 0) {
        $tag->setAttribute($tag_name, '/nikkei/https/'.substr($tag_val, 8));
    } elseif (strpos($tag_val, '//') === 0) {
        $tag->setAttribute($tag_name, '/nikkei/'.$scheme.'/'.substr($tag_val, 2));
    } elseif (strpos($tag_val, '/') === 0) {
        $tag->setAttribute($tag_name, '/nikkei/'.$scheme.'/'.$base_url.'/'.substr($tag_val, 1));
    }
    array_push($link_urls, array($tag->nodeName, $tag->getAttribute($tag_name), $tag_val));
}

$debug = isset( $_REQUEST[ 'debug' ] ) && !empty( $_REQUEST[ 'debug' ] ) ? $_REQUEST[ 'debug' ] : '0';
$scheme = isset( $_REQUEST[ 'scheme' ] ) && !empty( $_REQUEST[ 'scheme' ] ) ? $_REQUEST[ 'scheme' ] : 'https';
$path = isset( $_REQUEST[ 'path' ] ) && !empty( $_REQUEST[ 'path' ] ) ? $_REQUEST[ 'path' ] : 'www.baidu.com';
// $debug = '1';
// $scheme = 'https';
// $path = 'dash.cloudflare.com';
$url = $scheme."://".$path;
$url_parse = parse_url($url);
$base_url = $url_parse['host'].(empty($url_parse['port']) ? "" : ":".$url_parse['port']);
$hook_script = <<<EOT
function function_hook(func, obj, arg) {
    var scheme = "$scheme";
    var base_url = "$base_url";
    var url = null;
    var tag = arg[0];
    if (typeof tag === 'object') {
        if (tag.tagName === 'SCRIPT') {
            url = tag.attributes.src.value;
            if (url.indexOf('http://') === 0) {
                tag.src = '/nikkei/http/' + url.substr(7);
            } else if (url.indexOf('https://') === 0) {
                tag.src = '/nikkei/https/' + url.substr(8);
            } else if (url.indexOf('//') === 0) {
                tag.src = '/nikkei/' + scheme + '/' + url.substr(2);
            } else if (url.indexOf('/') === 0) {
                tag.src = '/nikkei/' + scheme + '/' + base_url + '/' + url.substr(1);
            }
        } else if (tag.tagName === 'LINK') {
            url = tag.attributes.href.value;
            if (url.indexOf('http://') === 0) {
                tag.href = '/nikkei/http/' + url.substr(7);
            } else if (url.indexOf('https://') === 0) {
                tag.href = '/nikkei/https/' + url.substr(8);
            } else if (url.indexOf('//') === 0) {
                tag.href = '/nikkei/' + scheme + '/' + url.substr(2);
            } else if (url.indexOf('/') === 0) {
                tag.href = '/nikkei/' + scheme + '/' + base_url + '/' + url.substr(1);
            }
        }

        if (url) {
            console.log('hook', func.name, url);
        }
    }
    func.apply(obj, arg);
}

var function_hook_retry = [];
function function_hook_head() {
    var tag = document.head;
    if (!tag) {
        var h = document.getElementsByTagName("head");
        if (h.length > 0) {
            tag = h[0];
        }
    }
    
    if (!tag) {
        console.error("function_hook_head fail")
        function_hook_retry.push(function_hook_head);
        return;
    }

    var func1 = tag.insertBefore;
    tag.insertBefore = function() {
        function_hook(func1, this, arguments);
    }

    var func2 = tag.appendChild;
    tag.appendChild = function() {
        function_hook(func2, this, arguments);
    }

    console.info("%c function_hook_head success", 'color:red')
}

function function_hook_body() {
    var tag = document.body;
    if (!tag) {
        var h = document.getElementsByTagName("body");
        if (h.length > 0) {
            tag = h[0];
        }
    }
    
    if (!tag) {
        console.error("function_hook_body fail")
        function_hook_retry.push(function_hook_body);
        return;
    }

    var func1 = tag.insertBefore;
    tag.insertBefore = function() {
        function_hook(func1, this, arguments);
    }

    var func2 = tag.appendChild;
    tag.appendChild = function() {
        function_hook(func2, this, arguments);
    }

    console.info("%c function_hook_body success", 'color:red')
}

function function_hook_ajax() {
    var tmp = window.XMLHttpRequest.prototype.open;
    window.XMLHttpRequest.prototype.open = function() {
        console.log('Request', arguments);
        // tmp.apply(this, arguments);
    }
}

function_hook_body();
function_hook_head();
function_hook_ajax();
window.onload = () => function_hook_retry.forEach((func) => func.apply(this));
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
        to_tag($tag, 'data-url');
    }

    $links = $dom->getElementsByTagName('script');
    foreach ($links as $tag) {
        to_tag($tag, 'src');
    }
    $head = $dom->getElementsByTagName('head');
    if ($head->length) {
        $element = $dom->createElement('script'); 
        $element->nodeValue = $hook_script;
        
        $script = $dom->getElementsByTagName('script');
        if ($script->length) {
            $child = $script->item(0);
            $head->item(0)->insertBefore($element, $child);
        } else {
            $head->item(0)->appendChild($element);
        }
    }

    return $dom->saveHTML();
}

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
$http_error = '';
$http_exception = null;
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
if (curl_errno($ch)) {
    $debug = '1';
    $http_error = curl_error($ch);
    http_response_code($http_code);
} else {
    $http_error = 'OK';
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
        try {
            echo to_url($body, $headers_res);
        } catch (Exception $e) {
            $debug = '1';
            $http_exception = $e->getMessage();
        }
    } elseif ($http_code == 301 || $http_code == 302) {
        $debug = '1';
        echo sprintf("<div class='center'>跳转地址: <a href='%s'>%s</a></div>", $redirect, $redirect);
    } else {
        $debug = '1';
        http_response_code($http_code);
    }
}
$page_info = array(
    "headers_req" => $headers_req,
    "headers_res" => $headers_res,
    "scheme" => $scheme,
    "path" => $path,
    "url" => $url,
    "url_parse" => $url_parse,
    "base_url" => $base_url,
    "http_code" => $http_code,
    "http_error" => $http_error,
    "header_size" => $header_size,
    "http_exception" => $http_exception,
    "link_urls" => $link_urls
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