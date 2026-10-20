<?php
declare(strict_types=1);
header("Content-Security-Policy: default-src 'self'; img-src 'self'; style-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');
session_start(['use_strict_mode'=>true,'cookie_httponly'=>true,'cookie_samesite'=>'Lax','cookie_secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function go(string $url): never { header('Location: '.$url, true, 303); exit; }
function field(string $key): string { return is_string($_POST[$key] ?? null) ? $_POST[$key] : ''; }
function query(string $key, string $default=''): string { return is_string($_GET[$key] ?? null) ? $_GET[$key] : $default; }
function csrf(): string { return '<input type="hidden" name="csrf" value="'.e($_SESSION['csrf']).'">'; }
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$catalog = require __DIR__.'/catalog.php';
$dbPath = getenv('APP_DB_PATH') ?: dirname(__DIR__).'/storage/app.sqlite';
$db = new PDO('sqlite:'.$dbPath, null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec('PRAGMA foreign_keys = ON; PRAGMA busy_timeout = 5000;');
$db->exec('CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, email TEXT NOT NULL UNIQUE COLLATE NOCASE, password TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS bookmarks (user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE, comic_id TEXT NOT NULL, PRIMARY KEY(user_id,comic_id));
CREATE TABLE IF NOT EXISTS login_attempts (email TEXT PRIMARY KEY, attempts INTEGER NOT NULL, started INTEGER NOT NULL);');
$user = null;
if (isset($_SESSION['user_id'])) {
    $stmt=$db->prepare('SELECT id,name,email FROM users WHERE id=?'); $stmt->execute([$_SESSION['user_id']]);
    $user=$stmt->fetch() ?: null;
}
$page=query('page','home');
$error='';
$flash=$_SESSION['flash'] ?? ''; unset($_SESSION['flash']);
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!hash_equals($_SESSION['csrf'],field('csrf'))) { http_response_code(403); exit('This form expired. Reload the page and try again.'); }
    $action=field('action');
    if ($action==='register') {
        $page='register'; $name=trim(field('name')); $email=strtolower(trim(field('email'))); $password=field('password');
        if ($name==='' || strlen($name)>100 || strlen($email)>254 || !filter_var($email,FILTER_VALIDATE_EMAIL)) $error='Enter a name (up to 100 characters) and a valid email address.';
        elseif (strlen($password)<8 || strlen($password)>72) $error='Use a password between 8 and 72 bytes long.';
        elseif ($password!==field('confirm')) $error='The passwords do not match.';
        else {
            try { $stmt=$db->prepare('INSERT INTO users(name,email,password) VALUES(?,?,?)'); $stmt->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
                session_regenerate_id(true); $_SESSION['user_id']=(int)$db->lastInsertId(); $_SESSION['csrf']=bin2hex(random_bytes(32)); $_SESSION['flash']='Account created. You can now save comics.'; go('/?page=bookmarks');
            } catch (PDOException $ex) { if ($ex->getCode()!=='23000') throw $ex; $error='An account already uses that email address.'; }
        }
    } elseif ($action==='login') {
        $page='login'; $email=strtolower(trim(field('email'))); $password=field('password');
        $db->prepare('DELETE FROM login_attempts WHERE started < ?')->execute([time()-900]);
        $stmt=$db->prepare('SELECT attempts FROM login_attempts WHERE email=?'); $stmt->execute([$email]);
        if ((int)$stmt->fetchColumn()>=5) { http_response_code(429); $error='Too many attempts. Try again in 15 minutes.'; }
        else {
            $stmt=$db->prepare('SELECT * FROM users WHERE email=?'); $stmt->execute([$email]); $account=$stmt->fetch();
            $hash=$account['password'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
            $valid=password_verify($password,$hash);
            if ($account && $valid) { session_regenerate_id(true); $_SESSION['user_id']=(int)$account['id']; $_SESSION['csrf']=bin2hex(random_bytes(32)); $db->prepare('DELETE FROM login_attempts WHERE email=?')->execute([$email]); go('/?page=bookmarks'); }
            $db->prepare('INSERT INTO login_attempts(email,attempts,started) VALUES(?,1,?) ON CONFLICT(email) DO UPDATE SET attempts=attempts+1')->execute([$email,time()]);
            $error='Email or password is incorrect.';
        }
    } elseif ($action==='logout') { $_SESSION=[]; session_regenerate_id(true); go('/'); }
    elseif ($action==='bookmark') {
        if (!$user) go('/?page=login');
        $id=field('id'); if (!isset($catalog[$id])) { http_response_code(404); exit('Comic not found.'); }
        if (field('remove')==='1') $db->prepare('DELETE FROM bookmarks WHERE user_id=? AND comic_id=?')->execute([$user['id'],$id]);
        else $db->prepare('INSERT OR IGNORE INTO bookmarks(user_id,comic_id) VALUES(?,?)')->execute([$user['id'],$id]);
        $_SESSION['flash']=field('remove')==='1' ? 'Bookmark removed.' : 'Comic saved to your bookmarks.';
        go(field('return')==='bookmarks' ? '/?page=bookmarks' : '/?page=comic&id='.rawurlencode($id));
    } else { http_response_code(400); exit('Unknown action.'); }
}
$saved=[];
if ($user) { $stmt=$db->prepare('SELECT comic_id FROM bookmarks WHERE user_id=?'); $stmt->execute([$user['id']]); $saved=$stmt->fetchAll(PDO::FETCH_COLUMN); }
