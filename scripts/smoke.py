"""Run isolated integration checks using the bundled PHP runtime."""
import http.cookiejar, os, re, subprocess, tempfile, time, urllib.request, urllib.parse, urllib.error, shutil
from pathlib import Path
ROOT=Path(__file__).resolve().parent.parent
BASE='http://127.0.0.1:8017'
PHP=os.environ.get('PHP_BINARY') or (str(ROOT/'.runtime/php/php.exe') if (ROOT/'.runtime/php/php.exe').exists() else shutil.which('php'))
if not PHP: raise SystemExit('Install PHP with pdo_sqlite and add it to PATH.')
PHP_ARGS=[PHP]
if (ROOT/'.runtime/php/php.exe').exists() and Path(PHP).resolve()==(ROOT/'.runtime/php/php.exe').resolve():
    PHP_ARGS+=['-c',str(ROOT/'.runtime/php/php.ini'),'-d','extension_dir='+str(ROOT/'.runtime/php/ext')]
def client(): return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def req(c,path='/',data=None):
    try:
        with c.open(BASE+path, data=urllib.parse.urlencode(data).encode() if data is not None else None) as r:
            return r.status,r.read().decode('utf-8',errors='replace')
    except urllib.error.HTTPError as ex: return ex.code,ex.read().decode('utf-8',errors='replace')
def token(c,path='/'):
    # Anonymous home has no forms; login always includes a token.
    body=req(c,'/?page=login')[1]
    return re.search(r'name="csrf" value="([a-f0-9]+)"',body)[1]
def post(c,data): return req(c,'/',dict(data,csrf=token(c)))
checks=0
def check(condition,label):
    global checks
    assert condition,label
    checks+=1
    print('PASS',label)
with tempfile.TemporaryDirectory() as temp:
    env=dict(os.environ,APP_DB_PATH=str(Path(temp)/'test.sqlite'))
    log=open(Path(temp)/'server.log','w+')
    proc=subprocess.Popen(PHP_ARGS+['-S','127.0.0.1:8017','-t',str(ROOT/'public')],env=env,stdout=log,stderr=log)
    try:
        for _ in range(40):
            try: req(client()); break
            except OSError: time.sleep(.1)
        a,b=client(),client()
        check(req(a)[0]==200,'Homepage')
        check('Solo Max-Level' in req(a,'/?page=comics&s=Solo')[1], 'Search matches title')
        check('No comics found' in req(a,'/?page=comics&s=zzzz')[1], 'Empty search')
        check('2 titles found' in req(a,'/?page=comics&genre=Adventure')[1], 'Genre filter')
        check('11 images' in req(a,'/?page=reader&id=sword-hound')[1], 'Reader')
        check(req(a,'/?page=reader&id=player')[0]==200,'Player reader')
        for slug, count in [('sword-hound',11),('player',14),('reincarnator',76),('solo',6)]:
            body=req(a,'/?page=reader&id='+slug)[1]
            paths=re.findall(r'src="(/assets/manhwa/[^"]+)"',body)
            check(len(paths)==count, slug+' image count')
            nums=[int(Path(path).stem) for path in paths]
            check(nums==sorted(nums),slug+' numeric page order')
            check(all(req(a,path)[0]==200 for path in paths),slug+' all images served')
        check('reader-wide' in req(a,'/?page=reader&id=solo&width=wide')[1],'Wide reader')
        check(req(a,'/?page=comic&id=missing')[0]==404,'Unknown comic')
        check(req(a,'/?page=missing')[0]==404,'Unknown page')
        check(req(a,'/?page[]=login')[0]==200,'Array query handled')
        check(req(a,'/',{'action':'logout','csrf':'wrong'})[0]==403,'Invalid CSRF rejected')
        check('Welcome back' in post(a,{'action':'bookmark','id':'solo'})[1], 'Bookmark requires login')
        check('valid email' in post(a,{'action':'register','name':'Test','email':'invalid','password':'testpass123','confirm':'testpass123'})[1], 'Registration validation')
        data={'action':'register','name':'<script>alert(1)</script>','email':'one@example.test','password':'testpass123','confirm':'testpass123'}
        status,body=post(a,data)
        check(status==200 and 'Your collection starts here' in body,'Register and authenticate')
        check('&lt;script&gt;' in body and '<script>alert' not in body,'Name escaped')
        check('Remove bookmark' in post(a,{'action':'bookmark','id':'solo'})[1],'Bookmark added')
        post(a,{'action':'bookmark','id':'solo'})
        check(req(a,'/?page=bookmarks')[1].count('class="card"')==1,'Duplicate bookmarks prevented')
        check('already uses' in post(b,dict(data,email='ONE@example.test'))[1],'Duplicate email case insensitive')
        post(b,dict(data,name='Second',email='two@example.test'))
        check('Your collection starts here' in req(b,'/?page=bookmarks')[1],'Bookmarks isolated per user')
        check('Your collection starts here' in post(a,{'action':'bookmark','id':'solo','remove':'1','return':'bookmarks'})[1],'Bookmark removed')
        post(a,{'action':'logout'})
        check('Log in to save comics' in req(a,'/?page=bookmarks')[1],'Logout')
        check('incorrect' in post(a,{'action':'login','email':"' OR 1=1 --",'password':'bad'})[1],'SQL injection rejected')
        check('incorrect' in post(a,{'action':'login','email':'one@example.test','password':'bad'})[1],'Wrong password rejected')
        check('Your collection starts here' in post(a,{'action':'login','email':'ONE@example.test','password':'testpass123'})[1],'Login with correct password')
        for path in ['/storage/app.sqlite','/app/bootstrap.php','/.runtime/php/php.ini','/register-log-in-out.rar']:
            check(req(a,path)[0]==404,'Private file blocked: '+path)
        for path in ['/assets/app.css','/assets/manhwa/sword-hound/hero.jpg','/assets/manhwa/sword-hound/cover.png']:
            check(req(a,path)[0]==200,'Asset served: '+path)
        c=client()
        for _ in range(5): post(c,{'action':'login','email':'rate@example.test','password':'bad'})
        check(post(c,{'action':'login','email':'rate@example.test','password':'bad'})[0]==429,'Login rate limited')
        log.flush(); log.seek(0); output=log.read()
        check(not re.search(r'PHP (Warning|Fatal|Deprecated|Notice)',output),'No PHP runtime warnings')
        print(f'{checks} checks passed.')
    finally:
        proc.terminate(); proc.wait(timeout=10); log.close()
