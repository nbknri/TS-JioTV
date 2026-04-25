<?php
// Copyright 2021-2025 SnehTV, Inc.
// Licensed under MIT (https://github.com/mitthu786/TS-JioTV/blob/main/LICENSE)
// Created By: TechieSneh
// Modified with Auto-Token Refresh Fix for JioTV (bpk-tv)

error_reporting(0);
include "functions.php";

// Set common headers
header("Content-Type: application/vnd.apple.mpegurl");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Expose-Headers: Content-Length, Content-Range");
header("Access-Control-Allow-Headers: Range");
header("Accept-Ranges: bytes");

// Get parameters
$id = htmlspecialchars($_REQUEST['id'] ?? '');
$cid = htmlspecialchars($_REQUEST['cid'] ?? '');
$cooks = htmlspecialchars($_REQUEST['ck'] ?? '');

if (empty($cid) || empty($cooks)) {
    http_response_code(400);
    exit("Missing required parameters");
}

$chs = explode('-', $id);
$cookie = hex2bin($cooks); 

// --- AUTO-REFRESH LOGIC START ---
$token_dir = __DIR__ . "/assets/data/tokens";

if (!is_dir($token_dir)) {
    mkdir($token_dir, 0755, true);
}

$cache_file = $token_dir . "/token_$cid.txt";

if (file_exists($cache_file)) {
    $cached_ck = trim(file_get_contents($cache_file));
    if (!empty($cached_ck)) {
        $cookie = $cached_ck;
    }
}

$headers = [
    'Cookie: ' . $cookie,
    'Content-Type: application/x-www-form-urlencoded',
    'User-Agent: plaYtv/7.1.3 (Linux;Android 14) ExoPlayerLib/2.11.7'
];

$url = sprintf("https://jiotvmblive.cdn.jio.com/bpk-tv/%s/Fallback/%s", $chs[0], $id);
$hs = cUrlGetData($url, $headers);

if (empty($hs) || strpos($hs, '#EXTM3U') === false) {
    $haystack = getJioTvData($cid);
    if (!empty($haystack->result)) {
        [$baseUrl, $query] = array_pad(explode('?', $haystack->result, 2), 2, '');
        $cookie = str_contains($query, "minrate=") ? explode("&", $query)[2] : $query;
        
        file_put_contents($cache_file, $cookie);
        
        $headers[0] = 'Cookie: ' . $cookie;
        $hs = cUrlGetData($url, $headers);
    }
}

$hdnea_file = $token_dir . "/hdnea_$cid.txt";
$cuk = "";

if (file_exists($hdnea_file) && (time() - filemtime($hdnea_file) < 270)) {
    $cuk = file_get_contents($hdnea_file);
} else {
    $cookies = getCookiesFromUrl($url, $headers);
    if (isset($cookies['__hdnea__'])) {
        $cuk = bin2hex('__hdnea__=' . $cookies['__hdnea__']);
        file_put_contents($hdnea_file, $cuk);
    } else {
        $cuk = bin2hex($cookie);
    }
}
// --- AUTO-REFRESH LOGIC END ---

// Prepare replacement arrays
global $PROXY;
[$search, $replace] = $PROXY
    ? [
        [',URI="https://tv.media.jio.com/fallback/bpk-tv/', $chs[0] . '-', '.ts'],
        [',URI="auth.php?ck=' . $cuk . '&pkey=', "auth.php?ck=$cuk&ts=bpk-tv/{$chs[0]}/Fallback/{$chs[0]}-", '.ts']
    ]
    : [
        [',URI="https://tv.media.jio.com/fallback/bpk-tv/', $chs[0] . '-', '.ts'],
        [
            ',URI="auth.php?ck=' . $cuk . '&pkey=',
            "https://jiotvmblive.cdn.jio.com/bpk-tv/{$chs[0]}/Fallback/{$chs[0]}-",
            ".ts?" . hex2bin($cuk)
        ]
    ];

echo str_replace($search, $replace, $hs);
?>
