<?php
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    if (str_starts_with($class, $prefix)) {
        $path = __DIR__ . '/app/' . str_replace('App\\', '', $class);
        $path = str_replace('\\', '/', $path) . '.php';
        if (file_exists($path)) require $path;
    }
});

use App\Services\Database;
use App\Services\Auth;
use App\Services\Csrf;
use App\Services\View;
use App\Security\ProxyGuard;

$config = require __DIR__ . '/config/config.php';
$pdo = Database::pdo($config['db']);

function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function jsonResponse(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
function requestData(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) return $_POST;
    $json = json_decode($raw, true);
    return is_array($json) ? $json : $_POST;
}
function normalizeUrl(string $url): string {
    $p = parse_url(trim($url));
    if (!$p || !isset($p['scheme'], $p['host'])) return '';
    $scheme = strtolower($p['scheme']);
    $host = strtolower($p['host']);
    $path = $p['path'] ?? '/';
    if (strlen($path) > 1) $path = rtrim($path, '/');
    $q = isset($p['query']) ? '?' . $p['query'] : '';
    return $scheme . '://' . $host . $path . $q;
}
function logActivity(PDO $pdo, ?int $projectId, ?int $userId, ?int $guestId, string $event, array $payload = []): void {
    $stmt = $pdo->prepare('INSERT INTO activity_log (project_id, actor_user_id, actor_guest_id, event_type, event_json, created_at) VALUES (:p,:u,:g,:t,:j,NOW())');
    $stmt->execute(['p'=>$projectId,'u'=>$userId,'g'=>$guestId,'t'=>$event,'j'=>json_encode($payload)]);
}

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$user = Auth::user($pdo);

if ($method === 'GET' && $path === '/login') {
    View::render('auth/login', ['csrf' => Csrf::token(), 'error' => null]); exit;
}
if ($method === 'POST' && $path === '/login') {
    if (!Csrf::verify($_POST['_csrf'] ?? null)) die('CSRF');
    $key = 'login_attempts_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $_SESSION[$key] = $_SESSION[$key] ?? ['count' => 0, 'ts' => time()];
    if (time() - $_SESSION[$key]['ts'] > $config['security']['login_rate_limit_window_seconds']) $_SESSION[$key] = ['count'=>0,'ts'=>time()];
    if ($_SESSION[$key]['count'] >= $config['security']['login_rate_limit_attempts']) {
        View::render('auth/login', ['csrf'=>Csrf::token(), 'error'=>'Too many attempts']); exit;
    }
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => strtolower(trim($_POST['email'] ?? ''))]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($_POST['password'] ?? '', $row['password_hash'])) {
        $_SESSION[$key]['count']++;
        View::render('auth/login', ['csrf'=>Csrf::token(), 'error'=>'Invalid credentials']); exit;
    }
    Auth::login($pdo, $row);
    header('Location: /projects'); exit;
}
if ($method === 'POST' && $path === '/logout') {
    if (!Csrf::verify($_POST['_csrf'] ?? null)) die('CSRF');
    Auth::logout($pdo);
    header('Location: /login'); exit;
}
if ($path === '/' ) { header('Location: ' . ($user ? '/projects' : '/login')); exit; }

if ($method === 'GET' && $path === '/projects') {
    $user = Auth::requireAuth($pdo);
    $projects = $pdo->query('SELECT p.*, c.name AS client_name, (SELECT COUNT(*) FROM threads t WHERE t.project_id=p.id AND t.status="active") active_count, (SELECT COUNT(*) FROM threads t WHERE t.project_id=p.id AND t.status="resolved") resolved_count, (SELECT MAX(created_at) FROM activity_log a WHERE a.project_id=p.id) last_activity FROM projects p JOIN clients c ON c.id=p.client_id ORDER BY p.created_at DESC')->fetchAll();
    View::render('projects/index', ['projects'=>$projects,'user'=>$user,'csrf'=>Csrf::token()]); exit;
}
if ($method === 'GET' && $path === '/projects/new') {
    Auth::requireAuth($pdo);
    $clients = $pdo->query('SELECT * FROM clients ORDER BY name')->fetchAll();
    View::render('projects/new', ['csrf'=>Csrf::token(),'clients'=>$clients]); exit;
}
if ($method === 'GET' && $path === '/clients/new') {
    Auth::requireAuth($pdo);
    View::render('projects/new-client', ['csrf'=>Csrf::token()]); exit;
}
if ($method === 'POST' && $path === '/clients') {
    Auth::requireAuth($pdo);
    if (!Csrf::verify($_POST['_csrf'] ?? null)) die('CSRF');
    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        View::render('projects/new-client', ['csrf'=>Csrf::token(), 'error' => 'Client name is required.']);
        exit;
    }
    $stmt = $pdo->prepare('INSERT INTO clients (name, contact_email, created_at, updated_at) VALUES (:name, :email, NOW(), NOW())');
    $stmt->execute([
        'name' => $name,
        'email' => !empty($_POST['contact_email']) ? strtolower(trim($_POST['contact_email'])) : null,
    ]);
    header('Location: /projects/new');
    exit;
}
if ($method === 'POST' && $path === '/projects') {
    $user = Auth::requireAuth($pdo);
    if (!Csrf::verify($_POST['_csrf'] ?? null)) die('CSRF');
    $base = normalizeUrl($_POST['base_url'] ?? '');
    $host = parse_url($base, PHP_URL_HOST);
    $hosts = array_values(array_unique([$host, str_starts_with($host, 'www.') ? substr($host,4) : 'www.'.$host]));
    $stmt = $pdo->prepare('INSERT INTO projects (client_id,title,base_url,allowed_hosts_json,status,created_by_user_id,created_at,updated_at) VALUES (:c,:t,:b,:h,:s,:u,NOW(),NOW())');
    $stmt->execute(['c'=>(int)$_POST['client_id'],'t'=>trim($_POST['title']),'b'=>$base,'h'=>json_encode($hosts),'s'=>$_POST['status'] ?? 'active','u'=>$user['id']]);
    logActivity($pdo, (int)$pdo->lastInsertId(), $user['id'], null, 'project_created');
    header('Location: /projects'); exit;
}
if ($method==='GET' && preg_match('#^/projects/(\d+)$#',$path,$m)) {
    $user=Auth::requireAuth($pdo); $id=(int)$m[1];
    $stmt=$pdo->prepare('SELECT p.*, c.name client_name FROM projects p JOIN clients c ON c.id=p.client_id WHERE p.id=:id');$stmt->execute(['id'=>$id]);$project=$stmt->fetch();
    View::render('projects/show',['project'=>$project,'csrf'=>Csrf::token(),'user'=>$user]); exit;
}
if ($method==='GET' && preg_match('#^/projects/(\d+)/view$#',$path,$m)) {
    $user=Auth::requireAuth($pdo); $id=(int)$m[1];
    $s=$pdo->prepare('SELECT * FROM projects WHERE id=:id');$s->execute(['id'=>$id]);$project=$s->fetch();
    View::render('projects/viewer',['project'=>$project,'authMode'=>'team','shareToken'=>'','csrf'=>Csrf::token(),'user'=>$user]); exit;
}
if ($method==='GET' && preg_match('#^/share/([A-Za-z0-9]+)$#',$path,$m)) {
    $token=$m[1];
    $stmt=$pdo->prepare('SELECT * FROM share_links WHERE token_hash=:h AND is_enabled=1 AND (expires_at IS NULL OR expires_at>NOW()) LIMIT 1');
    $stmt->execute(['h'=>hash('sha256',$token)]); $link=$stmt->fetch();
    if(!$link){ http_response_code(403); echo 'Link expired or disabled. Contact the team.'; exit; }
    if ($method==='GET' && empty($_SESSION['guest_id_'.$link['project_id']])) {
        echo '<form method="post"><input name="_csrf" type="hidden" value="'.e(Csrf::token()).'"><input name="name" required placeholder="Name"><input name="email" required type="email" placeholder="Email"><button>Continue</button></form>'; exit;
    }
}
if ($method==='POST' && preg_match('#^/share/([A-Za-z0-9]+)$#',$path,$m)) {
    if (!Csrf::verify($_POST['_csrf'] ?? null)) die('CSRF');
    $token=$m[1];
    $stmt=$pdo->prepare('SELECT * FROM share_links WHERE token_hash=:h AND is_enabled=1 AND (expires_at IS NULL OR expires_at>NOW()) LIMIT 1');$stmt->execute(['h'=>hash('sha256',$token)]);$link=$stmt->fetch();
    if(!$link){ http_response_code(403); exit('Link expired or disabled. Contact the team.'); }
    $raw=bin2hex(random_bytes(32));
    $ins=$pdo->prepare('INSERT INTO guest_sessions (project_id,share_link_id,guest_name,guest_email,session_token_hash,created_at,last_seen_at) VALUES (:p,:s,:n,:e,:h,NOW(),NOW())');
    $ins->execute(['p'=>$link['project_id'],'s'=>$link['id'],'n'=>trim($_POST['name']),'e'=>strtolower(trim($_POST['email'])),'h'=>hash('sha256',$raw)]);
    $_SESSION['guest_id_'.$link['project_id']] = (int)$pdo->lastInsertId();
    header('Location: /share/'.$token.'/view'); exit;
}
if ($method==='GET' && preg_match('#^/share/([A-Za-z0-9]+)/view$#',$path,$m)) {
    $token=$m[1];
    $stmt=$pdo->prepare('SELECT * FROM share_links WHERE token_hash=:h AND is_enabled=1 AND (expires_at IS NULL OR expires_at>NOW()) LIMIT 1');$stmt->execute(['h'=>hash('sha256',$token)]);$link=$stmt->fetch();
    if(!$link || empty($_SESSION['guest_id_'.$link['project_id']])) { header('Location: /share/'.$token); exit; }
    $p=$pdo->prepare('SELECT * FROM projects WHERE id=:id');$p->execute(['id'=>$link['project_id']]);$project=$p->fetch();
    View::render('projects/viewer',['project'=>$project,'authMode'=>'guest','shareToken'=>$token,'csrf'=>Csrf::token(),'user'=>null]); exit;
}

// APIs
if (str_starts_with($path, '/api/')) {
    $data = requestData();
    $projectId = null;
    if (preg_match('#^/api/projects/(\d+)#', $path, $mm)) $projectId = (int)$mm[1];
    $guestId = $projectId ? ($_SESSION['guest_id_'.$projectId] ?? null) : null;
    $team = $user ? true : false;
    if (in_array($method,['POST','PUT'],true) && !Csrf::verify($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($data['_csrf'] ?? null))) jsonResponse(['error'=>'CSRF'], 419);

    if ($method==='GET' && preg_match('#^/api/projects/(\d+)/threads$#',$path,$m)) {
        $pid=(int)$m[1]; if(!$team && !$guestId) jsonResponse(['error'=>'Unauthorized'],403);
        $where=' WHERE t.project_id=:p'; $params=['p'=>$pid];
        if(!empty($_GET['status']) && $_GET['status']!=='all'){ $where.=' AND t.status=:st'; $params['st']=$_GET['status'];}
        if(!empty($_GET['label'])){ $where.=' AND t.label=:lb'; $params['lb']=$_GET['label'];}
        if(!empty($_GET['assignee'])){ $where.=' AND t.assignee_user_id=:au'; $params['au']=(int)$_GET['assignee'];}
        if(!empty($_GET['q'])){ $where.=' AND EXISTS (SELECT 1 FROM messages m WHERE m.thread_id=t.id AND m.body_text LIKE :q)'; $params['q']='%'.$_GET['q'].'%';}
        if(!empty($_GET['page_url'])){ $where.=' AND p.page_url_normalized=:pu'; $params['pu']=normalizeUrl($_GET['page_url']);}
        $sql='SELECT t.*, p.page_url_normalized, (SELECT body_text FROM messages m WHERE m.thread_id=t.id ORDER BY m.id ASC LIMIT 1) first_message FROM threads t JOIN pages p ON p.id=t.page_id'.$where.' ORDER BY t.updated_at DESC';
        $st=$pdo->prepare($sql); $st->execute($params); jsonResponse(['threads'=>$st->fetchAll()]);
    }
    if ($method==='POST' && preg_match('#^/api/projects/(\d+)/threads$#',$path,$m)) {
        $pid=(int)$m[1]; if(!$team && !$guestId) jsonResponse(['error'=>'Unauthorized'],403);
        $pageNorm = normalizeUrl($data['page_url_normalized'] ?? $data['page_url'] ?? '');
        $pg=$pdo->prepare('SELECT id FROM pages WHERE project_id=:p AND page_url_normalized=:u LIMIT 1');$pg->execute(['p'=>$pid,'u'=>$pageNorm]);$page=$pg->fetch();
        if(!$page){$ins=$pdo->prepare('INSERT INTO pages (project_id,page_url_normalized,first_seen_at,last_seen_at) VALUES (:p,:u,NOW(),NOW())');$ins->execute(['p'=>$pid,'u'=>$pageNorm]);$pageId=(int)$pdo->lastInsertId();}
        else{$pageId=(int)$page['id'];$pdo->prepare('UPDATE pages SET last_seen_at=NOW() WHERE id=:i')->execute(['i'=>$pageId]);}
        $thr=$pdo->prepare('INSERT INTO threads (project_id,page_id,status,priority,label,assignee_user_id,anchor_json,device_preset,created_by_user_id,created_by_guest_id,created_at,updated_at) VALUES (:p,:pg,"active",:pr,:lb,:a,:an,:d,:u,:g,NOW(),NOW())');
        $thr->execute(['p'=>$pid,'pg'=>$pageId,'pr'=>$data['priority']??'medium','lb'=>$data['label']??'other','a'=>$data['assignee_user_id']??null,'an'=>json_encode($data['anchor']??[]),'d'=>$data['device_preset']??'desktop','u'=>$team?$user['id']:null,'g'=>$guestId]);
        $tid=(int)$pdo->lastInsertId();
        $msg=$pdo->prepare('INSERT INTO messages (thread_id,author_user_id,author_guest_id,visibility,body_text,created_at,updated_at) VALUES (:t,:u,:g,:v,:b,NOW(),NOW())');
        $visibility = ($team ? ($data['message']['visibility'] ?? 'public') : 'public');
        $msg->execute(['t'=>$tid,'u'=>$team?$user['id']:null,'g'=>$guestId,'v'=>$visibility,'b'=>trim($data['message']['body_text'] ?? '')]);
        $mid=(int)$pdo->lastInsertId();
        $job=$pdo->prepare('INSERT INTO jobs (type,payload_json,status,attempts,run_after,created_at,updated_at) VALUES ("screenshot_capture",:p,"queued",0,NOW(),NOW(),NOW())');
        $job->execute(['p'=>json_encode(['project_id'=>$pid,'thread_id'=>$tid,'message_id'=>$mid,'page_url'=>$pageNorm,'device_preset'=>$data['device_preset']??'desktop'])]);
        logActivity($pdo,$pid,$team?$user['id']:null,$guestId,'thread_created',['thread_id'=>$tid]);
        jsonResponse(['thread_id'=>$tid,'message_id'=>$mid,'screenshot_status'=>'queued'],201);
    }
    if ($method==='POST' && preg_match('#^/api/threads/(\d+)/messages$#',$path,$m)) {
        $tid=(int)$m[1];
        $t=$pdo->prepare('SELECT t.*, p.project_id, sl.allow_guest_resolve FROM threads t JOIN pages p ON p.id=t.page_id LEFT JOIN share_links sl ON sl.project_id=t.project_id AND sl.is_enabled=1 ORDER BY sl.id DESC LIMIT 1');
        $t->execute();$thr=$t->fetch();
        if(!$team && empty($_SESSION['guest_id_'.$thr['project_id']])) jsonResponse(['error'=>'Unauthorized'],403);
        $visibility = ($team ? ($data['visibility'] ?? 'public') : 'public');
        $ins=$pdo->prepare('INSERT INTO messages (thread_id,author_user_id,author_guest_id,visibility,body_text,created_at,updated_at) VALUES (:t,:u,:g,:v,:b,NOW(),NOW())');
        $ins->execute(['t'=>$tid,'u'=>$team?$user['id']:null,'g'=>$team?null:$_SESSION['guest_id_'.$thr['project_id']],'v'=>$visibility,'b'=>trim($data['body_text']??'')]);
        $pdo->prepare('UPDATE threads SET updated_at=NOW() WHERE id=:i')->execute(['i'=>$tid]);
        logActivity($pdo,$thr['project_id'],$team?$user['id']:null,$team?null:$_SESSION['guest_id_'.$thr['project_id']],'replied',['thread_id'=>$tid]);
        jsonResponse(['ok'=>true],201);
    }
    if ($method==='POST' && preg_match('#^/api/threads/(\d+)/(resolve|reopen)$#',$path,$m)) {
        $tid=(int)$m[1];$act=$m[2];
        $s=$pdo->prepare('SELECT project_id FROM threads WHERE id=:i');$s->execute(['i'=>$tid]);$thr=$s->fetch();
        if(!$team){
            $lk=$pdo->prepare('SELECT allow_guest_resolve FROM share_links WHERE project_id=:p AND is_enabled=1 ORDER BY id DESC LIMIT 1');$lk->execute(['p'=>$thr['project_id']]);$r=$lk->fetch();
            if(empty($_SESSION['guest_id_'.$thr['project_id']]) || empty($r['allow_guest_resolve'])) jsonResponse(['error'=>'Forbidden'],403);
        }
        $status = $act==='resolve'?'resolved':'active';
        $pdo->prepare('UPDATE threads SET status=:s,resolved_at='.($status==='resolved'?'NOW()':'NULL').',updated_at=NOW() WHERE id=:i')->execute(['s'=>$status,'i'=>$tid]);
        logActivity($pdo,$thr['project_id'],$team?$user['id']:null,$team?null:$_SESSION['guest_id_'.$thr['project_id']],$status==='resolved'?'resolved':'reopened',['thread_id'=>$tid]);
        jsonResponse(['ok'=>true]);
    }
    if ($method==='POST' && preg_match('#^/api/projects/(\d+)/pages/seen$#',$path,$m)) {
        $pid=(int)$m[1]; if(!$team && !$guestId) jsonResponse(['error'=>'Unauthorized'],403);
        $u=normalizeUrl($data['page_url'] ?? '');
        $s=$pdo->prepare('INSERT INTO pages (project_id,page_url_normalized,first_seen_at,last_seen_at) VALUES (:p,:u,NOW(),NOW()) ON DUPLICATE KEY UPDATE last_seen_at=NOW()');
        $s->execute(['p'=>$pid,'u'=>$u]); jsonResponse(['ok'=>true]);
    }
    if ($method==='POST' && preg_match('#^/api/projects/(\d+)/share-link$#',$path,$m)) {
        if(!$team || $user['role']!=='admin') jsonResponse(['error'=>'Forbidden'],403);
        $pid=(int)$m[1];$raw=bin2hex(random_bytes(16));
        $pdo->prepare('INSERT INTO share_links (project_id,token_hash,is_enabled,expires_at,allow_guest_resolve,created_at) VALUES (:p,:h,1,NULL,0,NOW())')->execute(['p'=>$pid,'h'=>hash('sha256',$raw)]);
        jsonResponse(['token'=>$raw]);
    }
    if ($method==='PUT' && preg_match('#^/api/projects/(\d+)/share-link$#',$path,$m)) {
        if(!$team || $user['role']!=='admin') jsonResponse(['error'=>'Forbidden'],403);
        $pid=(int)$m[1];
        $pdo->prepare('UPDATE share_links SET is_enabled=:e, expires_at=:x, allow_guest_resolve=:r WHERE project_id=:p ORDER BY id DESC LIMIT 1')
            ->execute(['e'=>(int)($data['is_enabled']??1),'x'=>$data['expires_at']??null,'r'=>(int)($data['allow_guest_resolve']??0),'p'=>$pid]);
        jsonResponse(['ok'=>true]);
    }
    if ($method==='GET' && preg_match('#^/api/projects/(\d+)/export\.json$#',$path,$m)) {
        $pid=(int)$m[1]; if(!$team) jsonResponse(['error'=>'Unauthorized'],403);
        $rows=$pdo->prepare('SELECT t.*, p.page_url_normalized FROM threads t JOIN pages p ON p.id=t.page_id WHERE t.project_id=:p');$rows->execute(['p'=>$pid]);
        $threads=$rows->fetchAll(); jsonResponse(['project_id'=>$pid,'threads'=>$threads]);
    }
    if ($method==='GET' && preg_match('#^/api/projects/(\d+)/export\.csv$#',$path,$m)) {
        $pid=(int)$m[1]; if(!$team) jsonResponse(['error'=>'Unauthorized'],403);
        header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="project-'.$pid.'.csv"');
        $out=fopen('php://output','w'); fputcsv($out,['thread_id','status','priority','label','page_url']);
        $rows=$pdo->prepare('SELECT t.id,t.status,t.priority,t.label,p.page_url_normalized FROM threads t JOIN pages p ON p.id=t.page_id WHERE t.project_id=:p');$rows->execute(['p'=>$pid]);
        foreach($rows as $r) fputcsv($out,$r); fclose($out); exit;
    }
}

if ($method==='GET' && preg_match('#^/proxy/(\d+)$#',$path,$m)) {
    $projectId=(int)$m[1]; $url=$_GET['url'] ?? '';
    if (!$user && empty($_SESSION['guest_id_'.$projectId])) { http_response_code(403); exit('Unauthorized'); }
    $p=$pdo->prepare('SELECT * FROM projects WHERE id=:i');$p->execute(['i'=>$projectId]);$project=$p->fetch();
    $allowed=json_decode($project['allowed_hosts_json'], true) ?: [];
    if (!ProxyGuard::validateUrl($url,$allowed)) { http_response_code(403); exit('Blocked URL'); }
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>10,CURLOPT_USERAGENT=>'Anton Lens Proxy',CURLOPT_HTTPHEADER=>['Cookie:']]);
    $body=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); $ctype=curl_getinfo($ch,CURLINFO_CONTENT_TYPE) ?: 'text/html';
    if ($body===false) { http_response_code(502); exit('Fetch failed'); }
    if (strlen($body) > 5*1024*1024) { http_response_code(413); exit('Too large'); }
    if ($code>=300 && $code<400) {
        $loc=curl_getinfo($ch,CURLINFO_REDIRECT_URL) ?: ''; if(!ProxyGuard::validateUrl($loc,$allowed)){ http_response_code(403); exit('Blocked redirect'); }
    }
    if (str_contains($ctype,'text/html')) {
        $bridge = '<script>(function(){const post=(t,p)=>parent.postMessage(Object.assign({type:t},p||{}),"*");post("MARKUP_IFRAME_READY",{projectId:'.$projectId.',pageUrl:location.href});let ts=0;addEventListener("scroll",()=>{const n=Date.now();if(n-ts>100){ts=n;post("MARKUP_SCROLL",{scrollY:window.scrollY,scrollX:window.scrollX});}});const emit=()=>post("MARKUP_URL_CHANGED",{pageUrl:location.href});const op=history.pushState;history.pushState=function(){op.apply(history,arguments);emit();};const or=history.replaceState;history.replaceState=function(){or.apply(history,arguments);emit();};addEventListener("popstate",emit);addEventListener("message",(e)=>{if(e.data&&e.data.type==="MARKUP_SCROLL_TO"){window.scrollTo(0,e.data.scrollY||0);}});})();</script>';
        if (stripos($body, '</head>') !== false) $body = preg_replace('/<\/head>/i', '<base href="'.e($url).'">'.$bridge.'</head>', $body, 1);
        else $body = $bridge . $body;
    }
    header('Content-Type: '.$ctype); echo $body; exit;
}

if ($method==='GET' && preg_match('#^/screenshot/(\d+)$#',$path,$m)) {
    $id=(int)$m[1];
    $s=$pdo->prepare('SELECT sc.*, t.project_id FROM screenshots sc JOIN threads t ON t.id=sc.thread_id WHERE sc.id=:i');$s->execute(['i'=>$id]);$r=$s->fetch();
    if(!$r) { http_response_code(404); exit; }
    if (!$user && empty($_SESSION['guest_id_'.$r['project_id']])) { http_response_code(403); exit; }
    $file=__DIR__.'/'.$r['file_path']; if(!is_file($file)){ http_response_code(404); exit; }
    header('Content-Type: '.$r['mime']); readfile($file); exit;
}

http_response_code(404);
echo 'Not found';
