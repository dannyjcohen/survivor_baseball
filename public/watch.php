<?php
declare(strict_types=1);

/**
 * Multi-watch — single-file deploy (copy this file only to public/).
 * Requires project config + helpers (same paths as rest of the app).
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/src/helpers.php';

final class WatchEmbedResolver
{
    private const MAX_BYTES = 3145728;
    private const TIMEOUT_SEC = 20;

    /** @return array{ok: bool, embeds?: list<string>, error?: string} */
    public static function resolve(string $rawUrl): array
    {
        $rawUrl = trim($rawUrl);
        if ($rawUrl === '') {
            return ['ok' => false, 'error' => 'Empty URL'];
        }
        if (!preg_match('#^https?://#i', $rawUrl)) {
            $rawUrl = 'https://' . $rawUrl;
        }
        $parts = parse_url($rawUrl);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return ['ok' => false, 'error' => 'Invalid URL'];
        }
        if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return ['ok' => false, 'error' => 'Only http(s) URLs are allowed'];
        }
        if (!self::hostResolvesToPublicIps($parts['host'])) {
            return ['ok' => false, 'error' => 'URL host is not allowed'];
        }
        $fetchUrl = self::rebuildUrl($parts);
        $html = self::httpGet($fetchUrl);
        if ($html === null) {
            return ['ok' => false, 'error' => 'Could not fetch page'];
        }
        $embeds = self::extractIframeSrcs($html, $fetchUrl);
        if ($embeds === []) {
            return ['ok' => false, 'error' => 'No iframe with a usable src found in HTML'];
        }
        return ['ok' => true, 'embeds' => $embeds];
    }

    private static function hostResolvesToPublicIps(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return (bool) filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );
        }
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if ($records === false || $records === []) {
            $fallback = @gethostbynamel($host);
            if ($fallback === false || $fallback === []) {
                return false;
            }
            $records = [];
            foreach ($fallback as $ip) {
                $records[] = ['ip' => $ip];
            }
        }
        $any = false;
        foreach ($records as $r) {
            $ip = $r['ip'] ?? $r['ipv6'] ?? null;
            if ($ip === null || $ip === '') {
                continue;
            }
            $any = true;
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }
        return $any;
    }

    /** @param array<string, mixed> $parts */
    private static function rebuildUrl(array $parts): string
    {
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        $frag = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';
        return $scheme . '://' . $host . $port . $path . $query . $frag;
    }

    private static function httpGet(string $url): ?string
    {
        if (!function_exists('curl_init')) {
            return null;
        }
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => self::TIMEOUT_SEC,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml;q=0.9,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9',
            ],
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $code < 200 || $code >= 400) {
            return null;
        }
        if (strlen($body) > self::MAX_BYTES) {
            return null;
        }
        return is_string($body) ? $body : null;
    }

    /**
     * @return list<string>
     */
    private static function extractIframeSrcs(string $html, string $pageUrl): array
    {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $wrapped = '<?xml encoding="UTF-8">' . $html;
        if (@$dom->loadHTML($wrapped, LIBXML_NOWARNING | LIBXML_NOERROR) === false) {
            return [];
        }
        $xp = new DOMXPath($dom);
        $ordered = [];
        $preferred = $xp->query('//iframe[@id="cx-iframe"][@src]');
        if ($preferred !== false && $preferred->length > 0) {
            $ordered[] = $preferred->item(0);
        }
        $all = $dom->getElementsByTagName('iframe');
        foreach ($all as $node) {
            if (!($node instanceof DOMElement)) {
                continue;
            }
            if ($preferred !== false && $preferred->length > 0 && $node->getAttribute('id') === 'cx-iframe') {
                continue;
            }
            $ordered[] = $node;
        }
        $seen = [];
        $out = [];
        foreach ($ordered as $node) {
            if (!($node instanceof DOMElement)) {
                continue;
            }
            $src = trim($node->getAttribute('src'));
            if ($src === '') {
                continue;
            }
            $abs = self::absolutizeIframeSrc($pageUrl, $src);
            if ($abs === '' || !preg_match('#^https?://#i', $abs)) {
                continue;
            }
            if (preg_match('#^javascript:#i', $abs)) {
                continue;
            }
            $key = strtolower($abs);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $abs;
        }
        usort($out, static function (string $a, string $b): int {
            $score = static function (string $u): int {
                if (stripos($u, 'new-stream-embed') !== false) {
                    return 0;
                }
                if (preg_match('#/(embed|player)/#i', $u)) {
                    return 1;
                }
                return 2;
            };
            return $score($a) <=> $score($b);
        });
        return $out;
    }

    private static function absolutizeIframeSrc(string $pageUrl, string $src): string
    {
        $src = trim($src);
        if ($src === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $src)) {
            return $src;
        }
        if (strpos($src, '//') === 0) {
            return 'https:' . $src;
        }
        $page = parse_url($pageUrl);
        if ($page === false || empty($page['scheme']) || empty($page['host'])) {
            return $src;
        }
        $scheme = $page['scheme'];
        $host = $page['host'];
        $port = isset($page['port']) ? ':' . $page['port'] : '';
        if ($src[0] === '/') {
            return $scheme . '://' . $host . $port . $src;
        }
        $path = $page['path'] ?? '/';
        $dir = preg_replace('#/[^/]*$#', '/', $path);
        if ($dir === '' || $dir === '/') {
            $dir = '/';
        }
        return $scheme . '://' . $host . $port . $dir . $src;
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['watch_resolve'] ?? '') === '1') {
    header('Content-Type: application/json; charset=utf-8');
    $url = isset($_POST['url']) ? trim((string) $_POST['url']) : '';
    echo json_encode(WatchEmbedResolver::resolve($url));
    exit;
}

$title = 'Multi-watch';
$selfWatch = basename(__FILE__);
$resolveUrl = app_url($selfWatch);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?></title>
    <style>
:root{--bg:#f6f7f9;--card:#fff;--text:#1a1a1a;--muted:#5c6570;--border:#dde1e6;--danger:#b00020;--danger-bg:#fde8ec;--link:#0b5cad}
*{box-sizing:border-box}
body{margin:0;font-family:Georgia,"Times New Roman",serif;background:var(--bg);color:var(--text);line-height:1.45}
.wrap{max-width:1100px;margin:0 auto;padding:0 1rem}
.site-header{background:var(--card);border-bottom:1px solid var(--border)}
.site-header .wrap{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.75rem;padding:.75rem 1rem}
.brand{font-weight:700;text-decoration:none;color:var(--text);font-size:1.15rem}
.nav{display:flex;flex-wrap:wrap;gap:.5rem 1rem}
.nav a{color:var(--link);text-decoration:none;font-size:.95rem}
.nav a.active{font-weight:700;text-decoration:underline}
.main.watch-page{max-width:none;width:100%;padding-left:clamp(.75rem,2vw,1.5rem);padding-right:clamp(.75rem,2vw,1.5rem);box-sizing:border-box;padding-top:1.25rem;padding-bottom:3rem}
.site-footer{border-top:1px solid var(--border);padding:1rem;background:var(--card)}
.muted{color:var(--muted);font-size:.9rem}
h1.watch-title{font-size:1.5rem;margin:0 0 1rem}
.card{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:1rem 1.1rem;box-shadow:0 1px 2px rgba(0,0,0,.04)}
.watch-toolbar{margin-bottom:1rem}
.watch-add-row{margin-bottom:.75rem}
.watch-add-controls{display:flex;flex-wrap:wrap;gap:.5rem;align-items:stretch}
.watch-draft-input{flex:1;min-width:min(100%,12rem);padding:.5rem .65rem;font-size:1rem;border:1px solid var(--border);border-radius:6px}
.watch-add-error{margin:.4rem 0 0;font-size:.88rem;color:var(--danger)}
.watch-max-note{margin:.5rem 0 0;font-size:.9rem}
.watch-tags{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center}
.watch-tag{display:inline-flex;align-items:center;gap:.35rem;padding:.25rem .45rem .25rem .65rem;background:#eef0f3;border:1px solid var(--border);border-radius:999px;font-size:.92rem;font-family:system-ui,-apple-system,Segoe UI,sans-serif}
.watch-tag-label{font-weight:600}
.watch-tag-remove{display:inline-flex;align-items:center;justify-content:center;width:1.5rem;height:1.5rem;margin:0;padding:0;border:none;border-radius:999px;background:transparent;color:var(--muted);font-size:1.15rem;line-height:1;cursor:pointer}
.watch-tag-remove:hover{background:var(--danger-bg);color:var(--danger)}
.watch-grid{display:grid;gap:clamp(.75rem,1.5vw,1.25rem);align-items:start;width:100%}
.watch-empty{margin:0;padding:1rem 0}
.watch-grid--1{grid-template-columns:1fr}
.watch-grid--2{grid-template-columns:repeat(auto-fit,minmax(min(100%,22rem),1fr))}
.watch-grid--3{grid-template-columns:repeat(auto-fit,minmax(min(100%,20rem),1fr))}
@media(min-width:1100px){.watch-grid--3{grid-template-columns:repeat(3,1fr)}}
.watch-grid--4{grid-template-columns:repeat(auto-fit,minmax(min(100%,22rem),1fr))}
@media(min-width:900px){.watch-grid--4{grid-template-columns:repeat(2,1fr)}}
.watch-grid--5,.watch-grid--6{grid-template-columns:repeat(auto-fit,minmax(min(100%,18rem),1fr))}
@media(min-width:1000px){.watch-grid--5,.watch-grid--6{grid-template-columns:repeat(3,1fr)}}
.watch-frame-wrap{position:relative;width:100%;padding-bottom:56.25%;height:0;background:#111;border-radius:8px;overflow:hidden;border:1px solid var(--border)}
.watch-frame-wrap iframe{position:absolute;top:0;left:0;width:100%;height:100%;border:0}
.watch-err{margin:0;font-size:.88rem;line-height:1.4}
.watch-direct-hint{font-size:.82rem;color:var(--muted);margin:0 0 .4rem;line-height:1.4}
.watch-direct-hint a{color:var(--link)}
.watch-pending{margin:0;font-size:.9rem;text-align:center;padding:2rem 1rem}
.btn{display:inline-block;padding:.45rem .9rem;border-radius:6px;border:1px solid var(--border);background:#fff;cursor:pointer;font-size:.95rem}
.btn-primary{background:var(--link);color:#fff;border-color:var(--link)}
    </style>
</head>
<body>
    <header class="site-header">
        <div class="wrap">
            <a class="brand" href="<?= h(app_url('dashboard.php')) ?>">Survivor Pool</a>
            <nav class="nav">
                <a href="<?= h(app_url('dashboard.php')) ?>">Dashboard</a>
                <a href="<?= h(app_url('picks.php')) ?>">Picks</a>
                <a href="<?= h(app_url('decision.php')) ?>">Decision Helper</a>
                <a href="<?= h(app_url('history.php')) ?>">History</a>
                <a href="<?= h(app_url('daily.php')) ?>">Daily</a>
                <a href="<?= h(app_url($selfWatch)) ?>" class="active">Multi-watch</a>
                <a href="<?= h(app_url('admin.php')) ?>">Admin</a>
            </nav>
        </div>
    </header>
    <main class="main watch-page">
        <h1 class="watch-title">Multi-watch</h1>
        <div class="watch-toolbar card">
            <div class="watch-add-row">
                <div class="watch-add-controls">
                    <input type="text" id="watch-url-draft" class="watch-draft-input" placeholder="Paste a URL, then Add" autocomplete="off" spellcheck="false" aria-label="Video or page URL">
                    <button type="button" id="watch-add-btn" class="btn btn-primary">Add</button>
                </div>
                <p id="watch-add-error" class="watch-add-error" hidden></p>
                <p id="watch-max-note" class="watch-max-note muted" hidden>Maximum of 6 players. Remove one to add another.</p>
            </div>
            <div id="watch-tags" class="watch-tags" aria-label="Added players"></div>
        </div>
        <div id="watch-grid" class="watch-grid watch-grid--0" aria-live="polite" data-resolve-url="<?= h($resolveUrl) ?>" data-max="6"></div>
    </main>
    <footer class="site-footer">
        <div class="wrap muted">Private pool · 2 entries · Vanilla PHP</div>
    </footer>
<script>
(function(){var urls=[],serverState={},serverTimers={};function trim(s){return String(s||'').trim()}function getMax(){var g=document.getElementById('watch-grid');var m=g?parseInt(g.getAttribute('data-max')||'6',10):6;return m>0&&m<=12?m:6}function absolutize(src){if(!src)return null;src=trim(src);if(src.indexOf('//')===0)return'https:'+src;if(/^https?:\/\//i.test(src))return src;return'https://'+src}function extractIframeSrc(html){var m=html.match(/<iframe[^>]+src=["']([^"']+)["']/i);return m?trim(m[1]):null}function normalizePageUrl(s){s=trim(s);if(!s)return null;if(!/^https?:\/\//i.test(s))s='https://'+s;return s}function toKnownEmbedSrc(u){var host=u.hostname.replace(/^www\./,'').toLowerCase();if(host==='youtube.com'||host==='m.youtube.com'||host==='music.youtube.com'){if(u.pathname.indexOf('/embed/')===0)return'https://www.youtube.com'+u.pathname+u.search;var pathParts=u.pathname.split('/').filter(Boolean);if(pathParts[0]==='shorts'&&pathParts[1])return'https://www.youtube.com/embed/'+encodeURIComponent(pathParts[1]);if(pathParts[0]==='live'&&pathParts[1])return'https://www.youtube.com/embed/'+encodeURIComponent(pathParts[1]);var v=u.searchParams.get('v');if(v)return'https://www.youtube.com/embed/'+encodeURIComponent(v)}if(host==='youtu.be'){var yid=u.pathname.replace(/^\//,'').split('/')[0];if(yid)return'https://www.youtube.com/embed/'+encodeURIComponent(yid)}if(host==='vimeo.com'){var vp=u.pathname.split('/').filter(Boolean);if(vp[0]&&/^\d+$/.test(vp[0]))return'https://player.vimeo.com/video/'+vp[0]}if(host==='player.vimeo.com'&&u.pathname.indexOf('/video/')===0)return u.origin+u.pathname.split('?')[0];var parent=encodeURIComponent(window.location.hostname||'localhost');if(host==='twitch.tv'){var vm=u.pathname.match(/\/videos\/(\d+)/);if(vm)return'https://player.twitch.tv/?video=v'+vm[1]+'&parent='+parent;var cm=u.pathname.match(/\/clip\/([^/?]+)/);if(cm)return'https://clips.twitch.tv/embed?clip='+encodeURIComponent(cm[1])+'&parent='+parent}if(host==='dailymotion.com'){var dm=u.pathname.match(/\/video\/([a-z0-9]+)/i);if(dm)return'https://www.dailymotion.com/embed/video/'+dm[1]}if(host==='dai.ly'){var did=u.pathname.replace(/^\//,'').split('/')[0];if(did)return'https://www.dailymotion.com/embed/video/'+did}return null}function parseInput(raw){raw=trim(raw);if(!raw)return null;if(raw.indexOf('<iframe')!==-1){var ifs=extractIframeSrc(raw);if(!ifs)return null;return{type:'embed',src:absolutize(ifs)}}var urlStr=normalizePageUrl(raw);if(!urlStr)return null;var u;try{u=new URL(urlStr)}catch(e){return null}if(u.protocol!=='http:'&&u.protocol!=='https:')return null;var host=u.hostname.replace(/^www\./,'').toLowerCase();if(host==='gooz.aapmains.net'&&u.pathname.indexOf('/new-stream-embed/')===0)return{type:'embed',src:u.origin+u.pathname.split('?')[0]};var known=toKnownEmbedSrc(u);if(known)return{type:'embed',src:known};return{type:'page',url:u.href}}function ensureServerResolve(pageUrl){var st=serverState[pageUrl];if(st&&(st.status==='loading'||st.status==='ok'))return;if(st&&st.status==='fail')return;clearTimeout(serverTimers[pageUrl]);serverState[pageUrl]={status:'loading'};serverTimers[pageUrl]=setTimeout(function(){var grid=document.getElementById('watch-grid');var resolveUrl=grid?grid.getAttribute('data-resolve-url'):null;if(!resolveUrl)return;var fd=new FormData();fd.append('watch_resolve','1');fd.append('url',pageUrl);fetch(resolveUrl,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(data){var still=false;for(var x=0;x<urls.length;x++){var p=parseInput(urls[x]);if(p&&p.type==='page'&&p.url===pageUrl)still=true}if(!still)return;if(data.ok&&data.embeds&&data.embeds.length){serverState[pageUrl]={status:'ok',src:data.embeds[0]}}else{serverState[pageUrl]={status:'fail',fallbackUrl:pageUrl,err:data.error||'No iframe found'}}refresh()}).catch(function(){var still=false;for(var x=0;x<urls.length;x++){var p=parseInput(urls[x]);if(p&&p.type==='page'&&p.url===pageUrl)still=true}if(!still)return;serverState[pageUrl]={status:'fail',fallbackUrl:pageUrl,err:'Network error'};refresh()})},600)}function buildPlayers(){var out=[];for(var i=0;i<urls.length;i++){var val=urls[i];var parsed=parseInput(val);if(!parsed){out.push({slot:i,mode:'err',err:'Invalid URL. Use https://… or paste an iframe snippet.'});continue}if(parsed.type==='embed'){out.push({slot:i,mode:'play',src:parsed.src,direct:false});continue}ensureServerResolve(parsed.url);var st=serverState[parsed.url];if(!st){out.push({slot:i,mode:'pending'})}else if(st.status==='loading'){out.push({slot:i,mode:'pending'})}else if(st.status==='ok'){out.push({slot:i,mode:'play',src:st.src,direct:false})}else{out.push({slot:i,mode:'play',src:st.fallbackUrl,direct:true,resolveFail:st.err||''})}}return out}function render(grid,players){var n=players.length;grid.className='watch-grid watch-grid--'+n;grid.innerHTML='';if(n===0){var empty=document.createElement('p');empty.className='watch-empty muted';empty.textContent='Add a stream URL above.';grid.appendChild(empty);return}for(var j=0;j<players.length;j++){var p=players[j];var cell=document.createElement('div');cell.className='watch-cell';cell.setAttribute('data-slot',String(p.slot));if(p.mode==='err'){var err=document.createElement('div');err.className='watch-err card';err.textContent='Player '+(p.slot+1)+': '+p.err;cell.appendChild(err)}else if(p.mode==='pending'){var pend=document.createElement('div');pend.className='watch-pending card muted';pend.textContent='Fetching page to find embed…';cell.appendChild(pend)}else if(p.mode==='play'){if(p.direct){var hint=document.createElement('div');hint.className='watch-direct-hint';if(p.resolveFail){hint.appendChild(document.createTextNode('Could not read an iframe from the page ('+p.resolveFail+'). Loading the full page; if it stays blank, '))}else{hint.appendChild(document.createTextNode('This page may block embedding. If the frame stays blank, '))}var a=document.createElement('a');a.href=p.src;a.target='_blank';a.rel='noopener noreferrer';a.textContent='open in a new tab';hint.appendChild(a);hint.appendChild(document.createTextNode('.'));cell.appendChild(hint)}var wrap=document.createElement('div');wrap.className='watch-frame-wrap';var iframe=document.createElement('iframe');iframe.setAttribute('src',p.src);iframe.setAttribute('title','Player '+(p.slot+1));iframe.setAttribute('allowfullscreen','');iframe.setAttribute('allow','accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share');iframe.setAttribute('loading','lazy');iframe.setAttribute('referrerpolicy','no-referrer-when-downgrade');wrap.appendChild(iframe);cell.appendChild(wrap)}grid.appendChild(cell)}}function showAddError(msg){var el=document.getElementById('watch-add-error');if(!el)return;el.textContent=msg;el.hidden=false}function clearAddError(){var el=document.getElementById('watch-add-error');if(!el)return;el.textContent='';el.hidden=true}function renderTags(){var el=document.getElementById('watch-tags');if(!el)return;el.innerHTML='';for(var i=0;i<urls.length;i++){var tag=document.createElement('span');tag.className='watch-tag';var lab=document.createElement('span');lab.className='watch-tag-label';lab.textContent='Player '+(i+1);tag.appendChild(lab);var btn=document.createElement('button');btn.type='button';btn.className='watch-tag-remove';btn.setAttribute('aria-label','Remove Player '+(i+1));btn.setAttribute('data-index',String(i));btn.appendChild(document.createTextNode('\u00d7'));tag.appendChild(btn);el.appendChild(tag)}}function removeServerStateForUrl(pageUrl){clearTimeout(serverTimers[pageUrl]);delete serverTimers[pageUrl];delete serverState[pageUrl]}function countPageUrlUsage(pageUrl){var n=0;for(var i=0;i<urls.length;i++){var p=parseInput(urls[i]);if(p&&p.type==='page'&&p.url===pageUrl)n++}return n}function removePlayer(index){if(index<0||index>=urls.length)return;var removed=urls[index];var parsed=parseInput(removed);urls.splice(index,1);if(parsed&&parsed.type==='page'){if(countPageUrlUsage(parsed.url)===0){removeServerStateForUrl(parsed.url)}}refresh()}function tryAdd(){clearAddError();var draft=document.getElementById('watch-url-draft');if(!draft)return;var raw=trim(draft.value);var max=getMax();if(urls.length>=max){showAddError('Maximum of '+max+' players.');return}if(!raw){showAddError('Paste a URL first.');return}var parsed=parseInput(raw);if(!parsed){showAddError('That does not look like a valid URL or iframe snippet.');return}if(parsed.type==='page'){urls.push(parsed.url)}else{urls.push(raw)}draft.value='';draft.focus();refresh()}function updateAddRowVisibility(){var max=getMax();var controls=document.querySelector('.watch-add-controls');var note=document.getElementById('watch-max-note');if(controls){controls.hidden=urls.length>=max}if(note){note.hidden=urls.length<max}}function refresh(){renderTags();updateAddRowVisibility();var players=buildPlayers();render(document.getElementById('watch-grid'),players)}document.addEventListener('DOMContentLoaded',function(){var draft=document.getElementById('watch-url-draft');var addBtn=document.getElementById('watch-add-btn');var tags=document.getElementById('watch-tags');if(addBtn){addBtn.addEventListener('click',tryAdd)}if(draft){draft.addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();tryAdd()}});draft.addEventListener('input',clearAddError)}if(tags){tags.addEventListener('click',function(e){var btn=e.target.closest('.watch-tag-remove');if(!btn)return;var idx=parseInt(btn.getAttribute('data-index')||'-1',10);if(!isNaN(idx))removePlayer(idx)})}refresh()})})();
</script>
</body>
</html>
