<?php

declare(strict_types=1);

function detectClientIp(): string
{
    $keys = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'REMOTE_ADDR',
    ];

    foreach ($keys as $key) {
        if (!isset($_SERVER[$key]) || $_SERVER[$key] === '') {
            continue;
        }

        $value = $_SERVER[$key];
        if ($key === 'HTTP_X_FORWARDED_FOR') {
            $parts = explode(',', $value);
            $value = trim($parts[0] ?? '');
        }

        if (filter_var($value, FILTER_VALIDATE_IP)) {
            return $value;
        }
    }

    return '127.0.0.1';
}

function fetchGeoIp(string $ip): array
{
    $url = 'https://api.ip.sb/geoip/' . rawurlencode($ip) . '?lang=zh-CN';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => 'ipwest-homepage/1.0',
        ]);

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($body === false || $status >= 400) {
            return ['ok' => false, 'error' => $error !== '' ? $error : ('HTTP ' . $status)];
        }

        $json = json_decode($body, true);
        if (!is_array($json)) {
            return ['ok' => false, 'error' => 'ip.sb 返回了无法解析的 JSON'];
        }

        return ['ok' => true, 'data' => $json];
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => 8,
            'header' => "Accept: application/json\r\nUser-Agent: ipwest-homepage/1.0\r\n",
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    if ($body === false) {
        return ['ok' => false, 'error' => '请求 ip.sb 失败'];
    }

    $json = json_decode($body, true);
    if (!is_array($json)) {
        return ['ok' => false, 'error' => 'ip.sb 返回了无法解析的 JSON'];
    }

    return ['ok' => true, 'data' => $json];
}

function hasCjk(string $text): bool
{
    return preg_match('/\p{Han}/u', $text) === 1;
}

function countryNameZh(string $countryCode, string $fallback = ''): string
{
    $countryCode = strtoupper(trim($countryCode));
    if ($countryCode === '') {
        return $fallback !== '' ? $fallback : '-';
    }

    if (class_exists('Locale')) {
        $name = \Locale::getDisplayRegion('-' . $countryCode, 'zh_CN');
        if (is_string($name) && $name !== '') {
            return $name;
        }
    }

    static $map = [
        'US' => '美国',
        'CN' => '中国',
        'JP' => '日本',
        'KR' => '韩国',
        'SG' => '新加坡',
        'HK' => '中国香港',
        'TW' => '中国台湾',
        'DE' => '德国',
        'FR' => '法国',
        'GB' => '英国',
        'CA' => '加拿大',
        'AU' => '澳大利亚',
        'NL' => '荷兰',
        'RU' => '俄罗斯',
        'IN' => '印度',
    ];

    if (isset($map[$countryCode])) {
        return $map[$countryCode];
    }

    return $fallback !== '' ? $fallback : $countryCode;
}

function cityNameZh(string $city): string
{
    $city = trim($city);
    if ($city === '' || hasCjk($city)) {
        return $city;
    }

    static $map = [
        'new york' => '纽约',
        'los angeles' => '洛杉矶',
        'san francisco' => '旧金山',
        'seattle' => '西雅图',
        'chicago' => '芝加哥',
        'london' => '伦敦',
        'paris' => '巴黎',
        'berlin' => '柏林',
        'tokyo' => '东京',
        'osaka' => '大阪',
        'singapore' => '新加坡',
        'hong kong' => '香港',
        'taipei' => '台北',
        'sydney' => '悉尼',
        'melbourne' => '墨尔本',
        'moscow' => '莫斯科',
        'mumbai' => '孟买',
        'delhi' => '德里',
        'bangalore' => '班加罗尔',
        'amsterdam' => '阿姆斯特丹',
        'frankfurt' => '法兰克福',
    ];

    $key = function_exists('mb_strtolower') ? mb_strtolower($city, 'UTF-8') : strtolower($city);
    return $map[$key] ?? $city;
}

function flagEmoji(string $countryCode): string
{
    $countryCode = strtoupper(trim($countryCode));
    if (!preg_match('/^[A-Z]{2}$/', $countryCode)) {
        return '';
    }

    if (!function_exists('mb_chr')) {
        return '';
    }

    $base = 0x1F1E6;
    $first = mb_chr($base + (ord($countryCode[0]) - 65), 'UTF-8');
    $second = mb_chr($base + (ord($countryCode[1]) - 65), 'UTF-8');
    return $first . $second;
}

function flagCdn(string $countryCode): string
{
    $countryCode = strtolower(trim($countryCode));
    if (!preg_match('/^[a-z]{2}$/', $countryCode)) {
        return '';
    }
    return 'https://flagcdn.com/' . $countryCode . '.svg';
}

function formatGeoData(array $data): array
{
    $countryCode = (string) ($data['country_code'] ?? '');
    $countryRaw = (string) ($data['country'] ?? '');
    $regionRaw = (string) ($data['region'] ?? '');
    $cityRaw = (string) ($data['city'] ?? '');

    $countryZh = countryNameZh($countryCode, $countryRaw);
    $regionZh = $regionRaw !== '' ? $regionRaw : '-';
    $cityZh = cityNameZh($cityRaw);

    return [
        'ip' => (string) ($data['ip'] ?? '-'),
        'asn' => (string) ($data['asn'] ?? '-'),
        'organization' => (string) ($data['organization'] ?? '-'),
        'isp' => (string) ($data['isp'] ?? '-'),
        'timezone' => (string) ($data['timezone'] ?? '-'),
        'latitude' => (string) ($data['latitude'] ?? '-'),
        'longitude' => (string) ($data['longitude'] ?? '-'),
        'country_code' => $countryCode,
        'country_zh' => $countryZh,
        'region_zh' => $regionZh,
        'city_zh' => $cityZh !== '' ? $cityZh : '-',
        'flag_emoji' => flagEmoji($countryCode),
        'flag_svg' => flagCdn($countryCode),
    ];
}

function esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function viewVal(array $view, string $key, string $fallback = '-'): string
{
    $value = isset($view[$key]) ? trim((string) $view[$key]) : '';
    return $value !== '' ? esc($value) : esc($fallback);
}

function isWindowsClient(): bool
{
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
    return stripos($ua, 'windows') !== false;
}

$ajaxAction = isset($_GET['ajax']) ? trim((string) $_GET['ajax']) : '';
if ($ajaxAction === 'geoip') {
    header('Content-Type: application/json; charset=utf-8');
    $ip = isset($_GET['ip']) ? trim((string) $_GET['ip']) : '';
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => '无效 IP'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $response = fetchGeoIp($ip);
    if (!($response['ok'] ?? false)) {
        http_response_code(502);
        echo json_encode(['ok' => false, 'error' => (string) ($response['error'] ?? '查询失败')], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['ok' => true, 'data' => formatGeoData((array) $response['data'])], JSON_UNESCAPED_UNICODE);
    exit;
}

$inputIp = isset($_GET['ip']) ? trim((string) $_GET['ip']) : '';
$error = null;
$result = null;
$queryIp = $inputIp;
if ($inputIp !== '') {
    if (!filter_var($inputIp, FILTER_VALIDATE_IP)) {
        $error = '请输入有效的 IPv4 或 IPv6 地址';
    } else {
        $response = fetchGeoIp($inputIp);
        if (!($response['ok'] ?? false)) {
            $error = (string) ($response['error'] ?? '查询失败');
        } else {
            $result = $response['data'];
        }
    }
}

$title = 'IPWest - IP 检测';
$resultView = is_array($result) ? formatGeoData($result) : null;
$isWindows = isWindowsClient();
?>
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= $title ?></title>
  <link rel="stylesheet" href="/assets/style.css" />
</head>
<body data-server-ip="<?= esc(detectClientIp()) ?>">
  <div class="shell">
    <header class="topbar">
      <div class="brand">
        <span class="brand-badge">ip</span>
        <span class="brand-logo">ipwest</span>
      </div>
      <div class="topbar-right">
        <form class="search" method="get" action="">
          <input
            type="text"
            name="ip"
            value="<?= htmlspecialchars($inputIp, ENT_QUOTES, 'UTF-8') ?>"
            placeholder="输入任意 IP 地址，例如 1.1.1.1"
          />
          <button type="submit">检测</button>
        </form>
        <button
          class="theme-toggle"
          id="theme-toggle"
          type="button"
          aria-label="Toggle theme"
          title="Toggle theme"
        >
          <span class="theme-icon" id="theme-icon">◐</span>
        </button>
      </div>
    </header>

    <main class="hero">
      <h1>IPWest</h1>
      <p>A modern IP inspector.</p>
      <section class="hero-command">
        <div class="hero-command-head">
          <div>
            <span class="dot red"></span>
            <span class="dot yellow"></span>
            <span class="dot green"></span>
            <strong>Unix</strong>
            <span>Windows</span>
          </div>
          <span>copy</span>
        </div>
        <div class="hero-command-line">$ curl --proto '=https' --tlsv1.2 -LsSf https://api.ip.sb/geoip/1.1.1.1?lang=zh-CN</div>
      </section>
      <div class="hero-actions">
        <a href="#auto-results" class="btn btn-primary">Get Started</a>
        <a href="#monitor" class="btn btn-secondary">Config Builder</a>
      </div>

      <?php if ($inputIp !== ''): ?>
        <?php if ($error !== null): ?>
          <div class="error"><?= esc($error) ?></div>
        <?php elseif (is_array($resultView)): ?>
          <section class="result-stack">
            <article class="result">
              <div class="result-head">
                <div>
                  <span class="dot red"></span>
                  <span class="dot yellow"></span>
                  <span class="dot green"></span>
                </div>
                <div>Query: <?= esc($queryIp) ?></div>
              </div>
              <div class="result-grid">
                <div class="kv"><div class="k">IP</div><div class="v"><?= viewVal($resultView, 'ip') ?></div></div>
                <div class="kv"><div class="k">ASN</div><div class="v"><?= viewVal($resultView, 'asn') ?></div></div>
                <div class="kv"><div class="k">Organization</div><div class="v"><?= viewVal($resultView, 'organization') ?></div></div>
                <div class="kv">
                  <div class="k">Location</div>
                  <div class="v">
                    <?php if ($isWindows && ($resultView['flag_svg'] ?? '') !== ''): ?>
                      <span class="flag-inline"><img class="flag-svg" src="<?= viewVal($resultView, 'flag_svg') ?>" alt="flag" loading="lazy" /></span>
                    <?php else: ?>
                      <span class="flag-inline flag-emoji"><?= viewVal($resultView, 'flag_emoji', '🏳️') ?></span>
                    <?php endif; ?>
                    <?= viewVal($resultView, 'country_zh') ?> / <?= viewVal($resultView, 'region_zh') ?> / <?= viewVal($resultView, 'city_zh') ?>
                  </div>
                </div>
                <div class="kv"><div class="k">ISP</div><div class="v"><?= viewVal($resultView, 'isp') ?></div></div>
                <div class="kv"><div class="k">Timezone</div><div class="v"><?= viewVal($resultView, 'timezone') ?></div></div>
                <div class="kv"><div class="k">Latitude</div><div class="v"><?= viewVal($resultView, 'latitude') ?></div></div>
                <div class="kv"><div class="k">Longitude</div><div class="v"><?= viewVal($resultView, 'longitude') ?></div></div>
              </div>
            </article>
          </section>
        <?php endif; ?>
      <?php else: ?>
        <div class="error is-hidden" id="auto-error"></div>
        <section class="result-stack" id="auto-results">
          <article class="result result-loading" id="auto-loading">
            <div class="result-head">
              <div>
                <span class="dot red"></span>
                <span class="dot yellow"></span>
                <span class="dot green"></span>
              </div>
              <div>Auto Detect</div>
            </div>
            <div class="result-grid">
              <div class="kv"><div class="k">Status</div><div class="v">正在检测 IPv4 / IPv6...</div></div>
            </div>
          </article>
        </section>
      <?php endif; ?>
    </main>

    <section class="terminal-wrap">
      <div class="terminal">
        <pre>IPWEST daemon: connected | refresh 1s

Processes:
ipv4-check   online   43ms   cpu 0.0%   mem 22.6MB
ipv6-check   online   41ms   cpu 0.0%   mem 21.9MB
geoip-fetch  online   56ms   cpu 0.0%   mem 24.1MB

Live status:
[ok] API endpoint reachable
[ok] Dual-stack detection enabled
[ok] Country/city localization enabled</pre>
      </div>
    </section>

    <section class="features">
      <article class="feature">
        <h3>Instant Query</h3>
        <p>打开页面即可看到当前访问 IP 的完整定位与网络信息。</p>
      </article>
      <article class="feature">
        <h3>Manual Check</h3>
        <p>支持输入 IPv4 或 IPv6，快速排查出口、代理和运营商问题。</p>
      </article>
      <article class="feature">
        <h3>Simple Deploy</h3>
        <p>单文件 PHP 即可运行，适合放到任意 LEMP/LAMP 环境直接上线。</p>
      </article>
    </section>

    <section class="monitor" id="monitor">
      <h2>Define. Start. Monitor.</h2>
      <div class="monitor-grid">
        <article class="pane">
          <div class="pane-head">ipwest.toml</div>
          <div class="pane-body">[web]
command = "php -S 0.0.0.0:8080"
cwd = "./"
restart = "always"

[check_ipv4]
endpoint = "https://api-ipv4.ip.sb/ip"
timeout_ms = 5000

[check_ipv6]
endpoint = "https://api-ipv6.ip.sb/ip"
timeout_ms = 5000</div>
        </article>
        <article class="pane">
          <div class="pane-head">$ ipwest status</div>
          <div class="pane-body">name        status   cpu   mem   uptime
web         online   0.2%  6.1M  2m14s
ipv4-check  online   0.1%  2.8M  2m14s
ipv6-check  online   0.1%  2.9M  2m14s
geoip       online   0.2%  4.4M  2m14s</div>
        </article>
      </div>
    </section>

    <footer class="footer">
      <div>
        <div class="footer-brand">ipwest</div>
        <div>A modern process manager style IP tool.</div>
      </div>
      <div class="footer-links">
        <a href="#">Documentation</a>
        <a href="#">GitHub</a>
        <a href="#">Quick Start</a>
        <a href="#">Releases</a>
        <a href="#">API Reference</a>
        <a href="#">MIT License</a>
      </div>
    </footer>
  </div>
  <aside class="cookie-banner" id="cookie-banner" aria-live="polite">
    <div class="cookie-text">本网站使用 Cookies 改善体验（包含语言、基础统计与偏好设置）。</div>
    <div class="cookie-actions">
      <button class="cookie-btn" id="cookie-essential" type="button">仅必要</button>
      <button class="cookie-btn accept" id="cookie-accept" type="button">同意全部</button>
    </div>
  </aside>
  <script src="/assets/app.js" defer></script>
</body>
</html>
