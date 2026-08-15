<?php
session_start();

require '/opt/vendor/autoload.php';
use Smarty\Smarty;
$smarty = new Smarty();
$security = new \Smarty\Security($smarty);
$security->disabled_special_smarty_vars = ['env'];
$security->secure_dir = ['/'];
$security->trusted_uri = ['#.*#'];
$smarty->enableSecurity($security);

$smarty->assign('admin', '752aad00e66df04499db7a9042a5f41f');
$message = $_POST['message'] ?? '';

const TURNSTILE_SITE_KEY   = '0x4AAAAAAEFUhJ0MKonBlc5M';
const TURNSTILE_SECRET_KEY = '0x4AAAAAAEFUhELflC_7KVotkQemyjIbtds';
const POW_DIFFICULTY = 4;

function powNewChallenge(): string {
    $challenge = bin2hex(random_bytes(16));
    $_SESSION['pow_challenge'] = $challenge;
    $_SESSION['pow_used'] = false;
    return $challenge;
}

function powVerify(string $challenge, string $nonce, int $difficulty): bool {
    if (!isset($_SESSION['pow_challenge']) || $_SESSION['pow_challenge'] !== $challenge) {
        return false;
    }
    if (!empty($_SESSION['pow_used'])) {
        return false;
    }
    $hash = hash('sha256', $challenge . $nonce);
    $prefix = str_repeat('0', $difficulty);
    if (substr($hash, 0, $difficulty) === $prefix) {
        $_SESSION['pow_used'] = true;
        return true;
    }
    return false;
}

function turnstileVerify(string $token, string $remoteIp, string $secret): bool {
    if ($token === '' || $secret === '' || $secret === 'YOUR_TURNSTILE_SECRET_KEY') {
        return false;
    }
    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_POSTFIELDS => http_build_query([
            'secret'   => $secret,
            'response' => $token,
            'remoteip' => $remoteIp,
        ]),
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    if ($response === false) {
        return false;
    }
    $data = json_decode($response, true);
    return is_array($data) && !empty($data['success']);
}

function extractClientIp(string $xff): ?string {
    if ($xff === '') {
        return null;
    }
    $parts = explode(',', $xff);
    $candidate = trim($parts[0]);
    return filter_var($candidate, FILTER_VALIDATE_IP) ? $candidate : null;
}

$ipLogFile = '/var/www/data/sent_ips.json';
$forwarded_for = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
$clientIp = extractClientIp($forwarded_for);
$isAllowed = $clientIp !== null && preg_match('/^127\.0\.(\d{1,3})\.(\d{1,3})$/', $clientIp, $m) && (int)$m[1] <= 255 && (int)$m[2] <= 255;

$rateLimitKey = $isAllowed ? $clientIp : 'unknown';

function loadSentIps(string $file): array {
    if (!file_exists($file)) {
        return [];
    }
    $contents = file_get_contents($file);
    $data = json_decode($contents, true);
    return is_array($data) ? $data : [];
}

function markIpAsSent(string $file, string $ip): void {
    $fp = fopen($file, 'c+');
    if ($fp === false) {
        return;
    }
    if (flock($fp, LOCK_EX)) {
        $size = filesize($file);
        $contents = $size > 0 ? fread($fp, $size) : '';
        $data = json_decode($contents, true);
        if (!is_array($data)) {
            $data = [];
        }
        $data[$ip] = true;
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data));
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
}

$sentIps = loadSentIps($ipLogFile);
$alreadySent = array_key_exists($rateLimitKey, $sentIps);

$display_result = '';
if (isset($_POST['message']) && $_POST['message'] !== '') {
    $powChallengeSubmitted = $_POST['pow_challenge'] ?? '';
    $powNonceSubmitted     = $_POST['pow_nonce'] ?? '';
    $turnstileToken        = $_POST['cf-turnstile-response'] ?? '';

    $powOk = powVerify($powChallengeSubmitted, $powNonceSubmitted, POW_DIFFICULTY);
    $turnstileOk = turnstileVerify($turnstileToken, $clientIp ?? ($_SERVER['REMOTE_ADDR'] ?? ''), TURNSTILE_SECRET_KEY);

    if (!$powOk || !$turnstileOk) {
        $display_result = "Verification Failed";
    } elseif ($alreadySent) {
        $display_result = "You only allowed to send one message";
    } else {
        try {
            $output = $smarty->fetch('string:' . $message);
            $display_result = "Your message send";
            markIpAsSent($ipLogFile, $rateLimitKey);
        } catch (Exception $e) {
            $display_result = "Your message not send";
        }
    }
}

$powChallenge = powNewChallenge();
$turnstileSiteKey = htmlspecialchars(TURNSTILE_SITE_KEY, ENT_QUOTES);
$powDifficultyJs = POW_DIFFICULTY;

$header = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Customer Service</title>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  html, body {
    height: 100%;
  }
  body {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: space-between;
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 20px;
  }
  .page-header {
    width: 100%;
    max-width: 520px;
    text-align: center;
    padding: 24px 0;
  }
  .page-header h1 {
    font-size: 28px;
    color: #fff;
    text-shadow: 0 2px 8px rgba(0,0,0,0.2);
  }
  .page-header h1 span {
    color: #ffe08a;
  }
  .content-wrap {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
  }
  .card {
    background: rgba(255, 255, 255, 0.97);
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    padding: 48px 40px;
    max-width: 520px;
    width: 100%;
    text-align: center;
    position: relative;
  }
  p.subtitle {
    color: #7a7a8c;
    margin-bottom: 32px;
    font-size: 15px;
  }
  form {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }
  textarea {
    width: 100%;
    min-height: 120px;
    padding: 14px 18px;
    border: 2px solid #e4e4ef;
    border-radius: 12px;
    font-size: 15px;
    font-family: inherit;
    outline: none;
    resize: vertical;
    transition: border-color 0.2s ease;
  }
  textarea:focus {
    border-color: #764ba2;
  }
  button {
    padding: 14px 24px;
    border: none;
    border-radius: 12px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: #fff;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: opacity 0.2s ease;
  }
  button:hover {
    opacity: 0.9;
  }
  .result {
    margin-top: 28px;
    padding: 18px;
    border-radius: 12px;
    background: #f3f1fb;
    color: #3d3350;
    font-size: 15px;
    text-align: center;
    word-break: break-word;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 60px;
  }
  .result strong {
    color: #764ba2;
  }
  header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    text-align: center;
    padding: 20px;
    font-size: 14px;
    font-weight: 600;
    letter-spacing: 1px;
    width: 100%;
  }

</style>
</head>
<body>

  <div class="page-header">
    <h1>👋 Welcome to Our <span>Customer Service</span></h1>
  </div>
  <div class="content-wrap">
    <div class="card">
      <p class="subtitle">In case you have a problem, please clarify the whole steps</p>
      <form method="POST" action="" id="contactForm">
        <textarea name="message" placeholder="Send message..." autofocus></textarea>
        <input type="hidden" name="pow_challenge" id="pow_challenge" value="{$powChallenge}">
        <input type="hidden" name="pow_nonce" id="pow_nonce" value="">
        <div class="cf-turnstile" data-sitekey="{$turnstileSiteKey}" data-callback="onTurnstileSuccess"></div>
        <button type="submit" id="submitBtn" disabled>Solving challenge...</button>
      </form>
      <script>
        const POW_CHALLENGE = "{$powChallenge}";
        const POW_DIFFICULTY = {$powDifficultyJs};
        let powSolved = false;
        let turnstileSolved = false;

        function updateButton() {
          const btn = document.getElementById('submitBtn');
          if (powSolved && turnstileSolved) {
            btn.disabled = false;
            btn.textContent = 'Send';
          }
        }

        function onTurnstileSuccess() {
          turnstileSolved = true;
          updateButton();
        }

        async function solvePow(challenge, difficulty) {
          const prefix = '0'.repeat(difficulty);
          let nonce = 0;
          while (true) {
            const data = new TextEncoder().encode(challenge + nonce);
            const digest = await crypto.subtle.digest('SHA-256', data);
            const hex = Array.from(new Uint8Array(digest))
              .map(b => b.toString(16).padStart(2, '0')).join('');
            if (hex.startsWith(prefix)) {
              return nonce.toString();
            }
            nonce++;
          }
        }

        (async () => {
          const nonce = await solvePow(POW_CHALLENGE, POW_DIFFICULTY);
          document.getElementById('pow_nonce').value = nonce;
          powSolved = true;
          updateButton();
        })();
      </script>
HTML;
$footer = <<<HTML
    </div>
  </div>
  <header>
    &copy; <strong>Arab Security Cyber Wargames</strong> 2026
  </header>
</body>
</html>
HTML;

$resultBlock = '';
if ($display_result !== '') {
    $resultBlock = '<div class="result"><strong>' . htmlspecialchars($display_result) . '</strong></div>';
}

$page = $header . $resultBlock . $footer;
echo $page;
?>
