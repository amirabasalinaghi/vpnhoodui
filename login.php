<?php

session_start();
$baseUrl = getenv('BASE_URL');

$userName = getenv('USERNAME');
$password = getenv('PASSWORD');

// configuration for login attempts
$maxAttempts = 5;
$attemptWindow = 300; // seconds
$blockDuration = 300; // seconds

// initialize session variables
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['first_attempt_time'] = time();
}

// check if login is temporarily blocked
if (isset($_SESSION['login_block_until']) && $_SESSION['login_block_until'] > time()) {
    $remaining = $_SESSION['login_block_until'] - time();
    $error = 'Too many login attempts. Try again in ' . ceil($remaining / 60) . ' minute(s).';
    if (isset($_POST['username'])) {
        $_SESSION['error'] = $error;
        header('Location: ' . $baseUrl . '/login');
        die();
    }
    getHtmlBodyAndTagsLogin($baseUrl, $error);
    die();
}

if (isset($_POST['username'])) {
    if (time() - $_SESSION['first_attempt_time'] > $attemptWindow) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['first_attempt_time'] = time();
    }

    $postUser = escapeshellcmd($_POST['username']);
    $postPass = escapeshellcmd($_POST['password']);
    if (
        $postUser === escapeshellcmd($userName)
        && $postPass === escapeshellcmd($password)
    ) {
        $_SESSION['loggedIn'] = true;
        $_SESSION['login_attempts'] = 0;
        unset($_SESSION['login_block_until']);
        header('Location: ' . $baseUrl);
    } else {
        $_SESSION['loggedIn'] = false;
        $_SESSION['login_attempts']++;
        if ($_SESSION['login_attempts'] >= $maxAttempts) {
            $_SESSION['login_block_until'] = time() + $blockDuration;
        }
        $_SESSION['error'] = 'Invalid username or password.';
        header('Location: ' . $baseUrl . '/login');
    }
    die();
}

// display login page with optional error message
$errorMsg = $_SESSION['error'] ?? '';
unset($_SESSION['error']);
getHtmlBodyAndTagsLogin($baseUrl, $errorMsg);


function getHtmlBodyAndTagsLogin($baseUrl, $error = '')
{
    echo getHtmlHeader() . '
<body>
<div class="container">
        <div class="row">
                <h1 class="h1"><a href="' . $baseUrl . '">Home</a></h1>
        </div>';
    if ($error) {
        echo '<div class="alert alert-danger" role="alert">' . htmlspecialchars($error) . '</div>';
    }
    echo '<form class="form-group" method="post" action="' . $baseUrl . '/login">
          <input type="text" name="username" id="username" class="form-control" placeholder="Username" aria-describedby="helpId">
          <input type="password" name="password" id="password" class="form-control" placeholder="Password" aria-describedby="helpId">
          <button type="submit" >Login</button>
        </form>

</div>
</body>
</html>';
}
