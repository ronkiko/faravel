<?php

define('PUN_ROOT', __DIR__ . '/');
require PUN_ROOT . 'include/common.php';

if ($pun_user['g_read_board'] == '0') exit('Access denied');
if ($pun_user['is_guest']) exit('Access denied');

$title = 'Семантический поиск';
$debug = isset($_GET['debug']) && $_GET['debug'] === '1';

$query = isset($_GET['query']) ? trim($_GET['query']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 5;

$min_score = isset($_GET['min_score']) ? intval($_GET['min_score']) : (isset($_SESSION['min_score']) ? $_SESSION['min_score'] : 0);
$_SESSION['min_score'] = $min_score;

$score_labels = [
    0 => 'Минимальная',
    25 => 'Низкая',
    50 => 'Средняя',
    75 => 'Повышенная',
    100 => 'Максимальная'
];

$score_desc = $score_labels[$min_score] ?? '';

$main = <<<HTML
<style>
.search-wrapper {
    border: 1px solid #ccc;
    padding: 1.5em;
    border-radius: 8px;
    background: #fefefe;
    max-width: 700px;
    margin: 2em auto;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
.search-wrapper h1 {
    font-size: 1.4em;
    margin-bottom: 1em;
}
.search-form {
    display: flex;
    flex-direction: column;
    gap: 1em;
}
.search-form input[type="text"] {
    font-size: 1em;
    padding: 0.6em;
    border: 1px solid #ccc;
    border-radius: 6px;
    font-family: var(--font-sans);
    background: #fff;
    box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);
}
.search-form input[type="submit"] {
    padding: 0.5em 1.2em;
    font-size: 1em;
    background: #007acc;
    color: #fff;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    align-self: flex-start;
    transition: background 0.3s;
}
.search-form input[type="submit"]:hover {
    background: #005fa3;
}
.slider-label {
    font-size: 0.95em;
    color: #333;
    font-family: var(--font-sans);
}
.slider-meta {
    font-size: 0.85em;
    font-style: italic;
    margin-top: 0.2em;
    color: #555;
    display: flex;
    align-items: center;
    gap: 0.4em;
}
.slider-meta .help {
    cursor: pointer;
    font-style: normal;
    position: relative;
    display: inline-block;
    width: 1.2em;
    height: 1.2em;
    border-radius: 50%;
    background: #333;
    color: #fff;
    text-align: center;
    font-size: 0.75em;
    line-height: 1.2em;
}
.slider-meta .help::after {
    content: attr(data-tooltip);
    position: absolute;
    bottom: 120%;
    left: 50%;
    transform: translateX(-50%);
    background: #333;
    color: #fff;
    padding: 0.5em;
    border-radius: 6px;
    font-size: 0.75em;
    max-width: 250px;
    white-space: normal;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s;
    z-index: 10;
    text-align: left;
}
.slider-meta .help:hover::after {
    opacity: 1;
}
.dot-bar-wrapper {
    position: relative;
    padding-top: 1em;
}
.dot-bar-line {
    position: absolute;
    top: 50%;
    left: 10px;
    right: 10px;
    height: 2px;
    background: #ccc;
    z-index: 0;
    transform: translateY(-50%);
}
.dot-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    z-index: 1;
}
.dot-bar label {
    font-size: 0.85em;
    color: #222;
    text-align: center;
    font-family: var(--font-sans);
}
.dot-bar span {
    display: inline-block;
    width: 14px;
    height: 14px;
    background: #ccc;
    border-radius: 50%;
    margin-bottom: 0.3em;
    transition: background 0.2s;
}
.dot-bar input[type="radio"] {
    display: none;
}
.dot-bar input[type="radio"]:checked + span {
    background: #007acc;
}
.topic {
    background: #fafafa;
    border: 1px solid #ddd;
    padding: 1em;
    border-radius: 6px;
    margin: 1em auto;
    max-width: 700px;
    font-family: var(--font-sans);
    line-height: 1.5;
}
.topic a {
    font-size: 1.1em;
    color: #0366d6;
    text-decoration: none;
    font-weight: bold;
}
.topic a:hover {
    text-decoration: underline;
}
.topic .meta {
    color: #666;
    font-size: 0.9em;
    margin-top: 0.2em;
    margin-bottom: 0.4em;
}
.topic .snippet {
    font-size: 0.95em;
    color: #222;
}
</style>
<div class="search-wrapper">
    <h1>{$title}</h1>
    <form method="get" action="search.php" class="search-form">
        <input type="text" name="query" value="{$query}" placeholder="Введите запрос...">
        <div class="slider-label">Точность совпадения</div>
        <div class="slider-meta">{$score_desc}
            <span class="help" data-tooltip="Эта настройка определяет, насколько точно результаты должны совпадать с вашим запросом. Чем выше значение, тем строже фильтрация. Например, 50% = только результаты с совпадением выше 0.5.">?</span>
        </div>
        <div class="dot-bar-wrapper">
            <div class="dot-bar-line"></div>
            <div class="dot-bar">
HTML;

foreach ([0, 25, 50, 75, 100] as $val) {
    $checked = ($min_score === $val) ? ' checked' : '';
    $main .= "<label><input type=\"radio\" name=\"min_score\" value=\"{$val}\"{$checked}><span></span>{$val}%</label>";
}

$main .= <<<HTML
            </div>
        </div>
        <input type="submit" value="Искать">
        <input type="hidden" name="debug" value="{$debug}">
    </form>
</div>
HTML;

if ($query !== '') {
    $cache_file = PUN_ROOT.'cache/search_cache_'.md5($query.'_'.$min_score).'.json';

    $payload = json_encode([
        'query' => $query,
        'top_k' => 100,
        'min_score' => $min_score / 100
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    if (!$debug && file_exists($cache_file) && filemtime($cache_file) > time() - 600) {
        $results = json_decode(file_get_contents($cache_file), true);
    } else {
        $ch = curl_init('http://forum_search:5000/search');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $payload
        ]);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $results = ($code === 200 && $res) ? json_decode($res, true) : [];
        if (!$debug && $results) file_put_contents($cache_file, json_encode($results));
    }

    $total = count($results);
    $main .= "<div style=\"max-width:700px;margin:0 auto 1em auto;padding-left:1em;font-family:var(--font-sans);\"><p>Найдено совпадений: {$total}</p></div>";

    if ($debug) {
        $pretty = htmlspecialchars(json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $main .= '<pre class="debug">POST http://forum_search:5000/search\n\n' . htmlspecialchars($payload) . '\n\nОтвет:\n' . $pretty . '</pre>';
    } elseif (empty($results)) {
        $main .= '<p>Ничего не найдено.</p>';
    } else {
        $start = ($page - 1) * $per_page;
        $paged = array_slice($results, $start, $per_page);

        foreach ($paged as $row) {
            $pid = (int)($row['id'] ?? 0);
            $score = round(($row['score'] ?? 0) * 100);
            $subject = htmlspecialchars($row['subject'] ?? 'Без названия');
            $snippet_raw = $row['snippet'] ?? '';

            $snippet_stripped = strip_tags($snippet_raw);
            $snippet_escaped = htmlspecialchars($snippet_stripped);

            $highlighted = preg_replace_callback('/' . preg_quote($query, '/') . '/iu', function ($m) {
                return '<mark>' . htmlspecialchars($m[0]) . '</mark>';
            }, $snippet_escaped);

            $main .= <<<HTML
<div class="topic">
    <a href="viewtopic.php?pid={$pid}#p{$pid}">{$subject}</a>
    <div class="meta">Уверенность: {$score}%</div>
    <div class="snippet">{$highlighted}</div>
</div>
HTML;
        }

        $pages = ceil($total / $per_page);
        if ($pages > 1) {
            $main .= '<div class="pagination">Страницы: ';
            for ($i = 1; $i <= $pages; $i++) {
                if ($i == $page) {
                    $main .= " <strong>{$i}</strong> ";
                } else {
                    $q = urlencode($query);
                    $main .= " <a href=\"search.php?query={$q}&page={$i}&min_score={$min_score}" . ($debug ? '&debug=1' : '') . "\">{$i}</a> ";
                }
            }
            $main .= '</div>';
        }
    }
}

$main .= '<p><a href="index.php">← Назад к списку разделов</a></p>';

message($title, $main);
