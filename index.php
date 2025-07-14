<?php

session_start();
$baseUrl = getenv('BASE_URL');
$baseUrlIp = 'http://' . $_SERVER["SERVER_NAME"] . ':' . $_SERVER["SERVER_PORT"];

checkTheAllowedUri($baseUrl);

if (isset($_GET['share'])) {
    shareTokenPage($_GET['share'], $baseUrl, $baseUrlIp);
}

login();

if (isset($_GET['printtoken'])) {
    printToken($_GET['printtoken']);
} elseif (isset($_GET['gen'])) {
    gen(
        $_GET['tokenName'] ?? '',
        $_GET['expire'] ?? null,
        $_GET['uploadLimit'] ?? null,
        $_GET['downloadLimit'] ?? null
    );
} elseif (isset($_GET['qrcode'])) {
    printQrCode($_GET['qrcode']);
} elseif (isset($_GET['shareqr'])) {
    printShareQrCode($_GET['shareqr'], $baseUrl, $baseUrlIp);
} elseif (isset($_GET['delete'])) {
    delete($_GET['delete']);
} else {
    getHtmlBodyAndTags($baseUrl, $baseUrlIp);
}

function loggedIn()
{
    return $_SESSION['loggedIn'];
}

function login()
{
    if (!loggedIn()) {
        require_once 'login.php';
        die();
    }
}


function printToken($id)
{
    $output = shell_exec('cd ../ && /app/VpnHoodServer print ' . escapeshellarg($id));
    echo getToken($output);
}

function printQrCode($id)
{
    $output = shell_exec('cd ../ && /app/VpnHoodServer print ' . escapeshellarg($id));
    $token = getToken($output);
    require_once __DIR__ . '/lib/phpqrcode/qrlib.php';
    header('Content-Type: image/svg+xml');
    echo QRcode::svg($token, false, QR_ECLEVEL_L, 3, 4, false);
    die();
}

function printShareQrCode($id, $baseUrl, $baseUrlIp)
{
    $link = $baseUrlIp . $baseUrl . '?share=' . urlencode($id);
    require_once __DIR__ . '/lib/phpqrcode/qrlib.php';
    header('Content-Type: image/svg+xml');
    echo QRcode::svg($link, false, QR_ECLEVEL_L, 3, 4, false);
    die();
}

function shareTokenPage($id, $baseUrl, $baseUrlIp)
{
    $output = shell_exec('cd ../ && /app/VpnHoodServer print ' . escapeshellarg($id));
    $token = getToken($output);
    echo getHtmlHeader() . '<body><div class="container text-center mt-4">'
        . '<h3>VPN Token</h3>'
        . '<p id="shareToken" class="mb-3">' . htmlspecialchars($token) . '</p>'
        . '<p class="text-muted">Tap and hold to copy on mobile devices.</p>'
        . '</div>'
        . '<script>
            function copy(){
                var text=document.getElementById("shareToken").innerText;
                if(!navigator.clipboard||!navigator.clipboard.writeText){
                    alert("Clipboard access was denied. Please copy the token manually.");
                    return;
                }
                navigator.clipboard.writeText(text)
                    .then(function(){alert("Copied");})
                    .catch(function(){alert("Clipboard access was denied. Please copy the token manually.");});
            }
        </script>'
        . '</body></html>';
    die();
}

function gen($name = 'Reza Server', $expire = null, $uploadLimit = null, $downloadLimit = null)
{
    $cmd = 'cd ../ && /app/VpnHoodServer gen -name=' . escapeshellarg($name);
    if ($expire) {
        $cmd .= ' -expire=' . escapeshellarg($expire);
    }
    $output = shell_exec($cmd);
    $token = getToken($output);

    // determine the latest generated token id
    $dir = '../storage/access/';
    $id = getLastTokenId($dir);
    if ($id && ($uploadLimit || $downloadLimit)) {
        $limitData = [];
        if ($uploadLimit) {
            $limitData['upload'] = (int)$uploadLimit * 1024 * 1024;
        }
        if ($downloadLimit) {
            $limitData['download'] = (int)$downloadLimit * 1024 * 1024;
        }
        file_put_contents($dir . $id . '.limit', json_encode($limitData));
    }

    echo $token;
}

function delete($id)
{
    echo shell_exec('rm ../storage/access/' . escapeshellarg($id) . '*');
}

function getToken($output)
{
    $start = strpos($output, 'vh://');
    $token = substr($output, $start);
    $end = strpos($token, '---');
    $endOfString = substr($token, $end);
    $token = str_replace($endOfString, '', $token);
    return trim($token);
}

function listFilesSortedByDate($dir)
{
    $ignored = array('.', '..', '.svn', '.htaccess');

    $files = array();
    foreach (scandir($dir) as $file) {
        if (in_array($file, $ignored)) {
            continue;
        }
        $files[$file] = filemtime($dir . '/' . $file);
    }

    arsort($files);
    $files = array_keys($files);

    return ($files) ? $files : false;
}

function getLastTokenId($dir)
{
    $files = listFilesSortedByDate($dir);
    if (!$files) {
        return null;
    }
    foreach ($files as $file) {
        if (is_file($dir . $file) && strpos($file, '.token') !== false) {
            return str_replace('.token', '', $file);
        }
    }
    return null;
}

function getTokenInfo(string $dir, $id)
{
    $tokenContent = file_get_contents($dir . $id . '.token');
    $usageContent = file_get_contents($dir . $id . '.usage');
    $limitFile = $dir . $id . '.limit';
    $tokenInfo = json_decode($tokenContent, true);
    $usageInfo = json_decode($usageContent, true);
    $limitInfo = [];
    if (is_file($limitFile)) {
        $limitInfo = json_decode(file_get_contents($limitFile), true);
    }
    $tokenName = $tokenInfo['Token']['name'] ?? $tokenInfo['Name'] ?? 'NO NAME';
    $expireRaw = $tokenInfo['ExpirationTime'] ?? $tokenInfo['Token']['ExpirationTime'] ?? null;
    $expire = $expireRaw ? date('Y-m-d', strtotime($expireRaw)) : 'Never';
    $uploadUsed = $usageInfo['SentTraffic'] ?? 0;
    $downloadUsed = $usageInfo['ReceivedTraffic'] ?? 0;
    $uploadLimit = $limitInfo['upload'] ?? 0;
    $downloadLimit = $limitInfo['download'] ?? 0;
    $remainingUpload = $uploadLimit ? max($uploadLimit - $uploadUsed, 0) : 0;
    $remainingDownload = $downloadLimit ? max($downloadLimit - $downloadUsed, 0) : 0;
    return [
        'name' => $tokenName,
        'upload' => humanFileSize($usageInfo['SentTraffic']),
        'download' => humanFileSize($usageInfo['ReceivedTraffic']),
        'expiration' => $expire,
        'remaining_upload' => $uploadLimit ? humanFileSize($remainingUpload) : 'Unlimited',
        'remaining_download' => $downloadLimit ? humanFileSize($remainingDownload) : 'Unlimited',
    ];
}

function humanFileSize($size, $unit = "")
{
    if ((!$unit && $size >= 1 << 30) || $unit == "GB") {
        return number_format($size / (1 << 30), 2) . "GB";
    }
    if ((!$unit && $size >= 1 << 20) || $unit == "MB") {
        return number_format($size / (1 << 20), 2) . "MB";
    }
    if ((!$unit && $size >= 1 << 10) || $unit == "KB") {
        return number_format($size / (1 << 10), 2) . "KB";
    }
    return number_format($size) . " bytes";
}

function getBootstrapCard($tokenInfo, $id)
{
    return '<div class="card token-card">
                                <div class="card-body">
                                        <h5 class="card-title">' . $tokenInfo['name'] . '</h5>
                                        <p class="card-text">' . $id . '</p>
                                        <p class="card-text">Expires: <strong>' . $tokenInfo['expiration'] . '</strong></p>
                                        <p class="card-text">Downloaded: <strong>' . $tokenInfo['download'] . '</strong> Uploaded: <strong>' . $tokenInfo['upload'] . '</strong></p>
                                        <p class="card-text">Remaining Download: <strong>' . $tokenInfo['remaining_download'] . '</strong> Remaining Upload: <strong>' . $tokenInfo['remaining_upload'] . '</strong></p>
                                        <a href="?printtoken=' . $id . '" class="btn btn-primary" onclick="getToken(\'' . $id . '\')">Show Token</a>
                                        <button type="button" class="btn btn-secondary ms-1" onclick="openShareModal(\'' . $id . '\')">Share</button>
                                        <a href="?delete=' . $id . '" class="btn " onclick="deleteToken(\'' . $id . '\')"><i class="bi bi-trash text-danger"></i></a>
                                        <div class="card-text" >
                                            <div class="spinner-border text-primary d-none" id="' . $id . '_spinner" role="status">
                            <span class="sr-only"></span>
                        </div>
                                            <p class="card-text d-none" id="' . $id . '" ></p>
                                            <img class="img-fluid d-none" id="' . $id . '_qr" alt="QR Code" />
                                            <a class="btn btn-info d-none copy-btn" onclick="copyText(\'' . $id . '\')" id="' . $id . '_cpbtn"> Copy To Clipboard</a>
                    </div>
                                </div>
                        </div>';
}

function showCardsForTokens()
{
    $dir = '../storage/access/';
    $files = listFilesSortedByDate($dir);
    if (!$files) {
        return '<p>No tokens found.</p>';
    }
    foreach ($files as $file) {
        if (is_file($dir . $file) && strpos($file, '.token') !== false) {
            $id = str_replace('.token', '', $file);
            $tokenInfo = getTokenInfo($dir, $id);
            $output .= getBootstrapCard($tokenInfo, $id);
        }
    }
    return $output ?? '';
}

function getJsContent()
{
    return file_get_contents('app.js');
}

function getHtmlHeader()
{
    return '<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta http-equiv="X-UA-Compatible" content="ie=edge">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css"
              integrity="sha384-rbsA2VBKQhggwzxH7pPCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65" crossorigin="anonymous">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.2/font/bootstrap-icons.css"
              integrity="sha384-b6lVK+yci+bfDmaY1u0zE8YYJt0TZxLEAFyYSLHId4xoVvsrQu3INevFKo+Xir8e" crossorigin="anonymous">
        <link rel="stylesheet" href="style.css">
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.min.js"
	        integrity="sha384-cuYeSxntonz0PPNlHhBs68uyIAVpIIOZZ5JqeqvYYIcEL727kskC66kF92t6Xl2V"
	        crossorigin="anonymous"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"
	        integrity="sha384-kenU1KFdBIe4zVF0s0G1M5b4hcpxyD9F7jL+jjXkk+Q2h455rYXK/7HAuoJl+0I4"
	        crossorigin="anonymous"></script>
	<title>HoodVPN TokenManager</title>
</head>';
}

function getGenerateButton()
{
    return '<div class="row">
                <form class="row g-3" action="?gen=1" METHOD="get">
                        <div class="col-auto">
                                <label for="tokenName" class="visually-hidden">Give a Token Name</label>
                                <input name="tokenName" type="text" class="form-control" id="tokenName" placeholder="Token Name">
                        </div>
                        <div class="col-auto">
                                <label for="expire" class="visually-hidden">Expiration</label>
                                <input name="expire" type="date" class="form-control" id="expire" placeholder="YYYY/MM/DD">
                        </div>
                        <div class="col-auto">
                                <label for="uploadLimit" class="visually-hidden">Upload Limit (MB)</label>
                                <input name="uploadLimit" type="number" class="form-control" id="uploadLimit" placeholder="Upload MB">
                        </div>
                        <div class="col-auto">
                                <label for="downloadLimit" class="visually-hidden">Download Limit (MB)</label>
                                <input name="downloadLimit" type="number" class="form-control" id="downloadLimit" placeholder="Download MB">
                        </div>
                        <div class="col-auto">
                                <button type="submit" class="btn btn-primary mb-3" onclick="generateToken(\'new_code\')">Generate Token</button>
                                <div class="card-text" >
                                            <div class="spinner-border text-primary d-none" id="new_code_spinner" role="status">
                            <span class="sr-only"></span>
                        </div>
                                            <p class="card-text d-none" id="new_code" ></p>
                                            <img class="img-fluid d-none" id="new_code_qr" alt="QR Code" />
                                            <a class="btn btn-info d-none copy-btn" onclick="copyText(\'new_code\')" id="new_code_cpbtn"> Copy To Clipboard</a>
                </div>
			</div>
		</form>
        </div>';
}

function getSearchBox()
{
    return '<div class="row mb-3">'
        . '<input type="text" id="searchInput" class="form-control" '
        . 'placeholder="Search token" onkeyup="filterTokens()">'
        . '</div>';
}

function getHtmlBodyAndTags($baseUrl, $baseUrlIp)
{
    echo getHtmlHeader() . '
<body>
<div class="container">
        <div class="row mb-3">
                <h1 class="h1"><a href="' . $baseUrl . '">Home</a></h1>
                <a href="' . $baseUrl . '/logout" class="btn btn-secondary ms-3">Logout</a>
        </div>
        ' . getGenerateButton() . '
        ' . getSearchBox() . '
        <div class="row">
                <div class="col-sm-6">
                        ' . showCardsForTokens() . '
                </div>
        </div>
        <div class="modal fade" id="shareModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Share Token</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center">
                        <img class="img-fluid mb-3" id="shareModalQr" alt="QR Code" />
                        <input id="shareLinkInput" class="form-control" readonly />
                        <p class="mt-2">Scan the QR code or open the link on your mobile device.</p>
                    </div>
                </div>
            </div>
        </div>
</div>
<script>
    var baseUrlAll = "' . $baseUrlIp . $baseUrl . '";
	' . getJsContent() . '
</script>
</body>
</html>';
}

function checkTheAllowedUri($baseUrl)
{
    $queryString = $_SERVER["QUERY_STRING"] ? '?' . $_SERVER["QUERY_STRING"] : '';
    $allowedUri = $baseUrl . $queryString;
    if ($_SERVER['REQUEST_URI'] === $baseUrl.'/logout'){
        $_SESSION['loggedIn']=false;
        echo 'loged out';
    }
    if ($_SERVER['REQUEST_URI'] !== $allowedUri
        && $_SERVER['REQUEST_URI']!== $baseUrl.'/login'
    ) {
        header('Location: ' . $baseUrl);
        die();
    }

}
