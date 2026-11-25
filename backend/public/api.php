<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';
$path = preg_replace('#^/api#', '', $path) ?: '/';

if ($method === 'GET' && $path === '/health') {
    try { database()->query('SELECT 1'); jsonResponse(200,['ok'=>true,'application'=>'CLONEFRINGER','database'=>'connected','time'=>date(DATE_ATOM)]); }
    catch (Throwable $e) { error_log('[health] '.$e->getMessage()); jsonResponse(503,['ok'=>false,'database'=>'unavailable','message'=>'Database belum terhubung atau konfigurasi belum lengkap.']); }
}

if ($method === 'POST' && $path === '/auth/login') {
    startAppSession();
    $body = requestJson();
    $username = trim((string)($body['username'] ?? ''));
    $password = (string)($body['password'] ?? '');
    if ($username === '' || $password === '' || strlen($username) > 80) jsonResponse(422,['ok'=>false,'message'=>'Username dan password wajib diisi.']);
    try {
        $stmt = database()->prepare('SELECT id,username,password_hash,display_name,role,is_active FROM app_users WHERE username=? LIMIT 1');
        $stmt->execute([$username]); $row = $stmt->fetch();
        if (!$row || !(int)$row['is_active'] || !password_verify($password,$row['password_hash'])) {
            usleep(350000);
            jsonResponse(401,['ok'=>false,'message'=>'Username atau password salah.']);
        }
        session_regenerate_id(true);
        $_SESSION['user'] = ['id'=>(int)$row['id'],'username'=>$row['username'],'display_name'=>$row['display_name'],'role'=>$row['role']];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        database()->prepare('UPDATE app_users SET last_login_at=NOW() WHERE id=?')->execute([$row['id']]);
        audit(database(),(int)$row['id'],'login','app_user',(int)$row['id'],null,['username'=>$username]);
        jsonResponse(200,['ok'=>true,'user'=>$_SESSION['user'],'csrf_token'=>$_SESSION['csrf_token']]);
    } catch (Throwable $e) { error_log('[login] '.$e->getMessage()); jsonResponse(503,['ok'=>false,'message'=>'Layanan login belum dapat digunakan.']); }
}

if ($method === 'GET' && $path === '/auth/me') {
    $user = currentUser();
    if (!$user) jsonResponse(401,['ok'=>false,'message'=>'Belum login.']);
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token']=bin2hex(random_bytes(32));
    jsonResponse(200,['ok'=>true,'user'=>$user,'csrf_token'=>$_SESSION['csrf_token']]);
}

if ($method === 'POST' && $path === '/auth/logout') {
    requireUser(); requireCsrf();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p=session_get_cookie_params();
        setcookie(session_name(),'',time()-42000,$p['path'],$p['domain']??'',$p['secure'],$p['httponly']);
    }
    session_destroy();
    jsonResponse(200,['ok'=>true,'message'=>'Berhasil logout.']);
}

if ($path === '/employees' && $method === 'GET') {
    requireUser();
    try {
        $q = trim((string)($_GET['q'] ?? ''));
        $status = trim((string)($_GET['status'] ?? ''));
        $sql = 'SELECT e.id,e.pin,e.full_name,e.unit_id,u.code AS unit_code,e.department,e.position_name,e.employee_status,e.join_date,e.contract_end,e.phone,e.email,e.gender,e.birth_date,e.religion,e.marital_status,e.education,e.blood_type,e.address,e.machine_privilege,e.rfid,e.fingerprint_count,e.notes,e.created_at,e.updated_at FROM employees e LEFT JOIN units u ON u.id=e.unit_id WHERE 1=1';
        $params=[];
        if ($q!=='') { $sql.=' AND (e.pin LIKE ? OR e.full_name LIKE ?)'; $params[]='%'.$q.'%'; $params[]='%'.$q.'%'; }
        $allowed=['active','inactive','resigned','terminated'];
        if ($status!=='' && in_array($status,$allowed,true)) { $sql.=' AND e.employee_status=?'; $params[]=$status; }
        $sql.=' ORDER BY e.full_name ASC LIMIT 500';
        $st=database()->prepare($sql); $st->execute($params);
        jsonResponse(200,['ok'=>true,'data'=>$st->fetchAll()]);
    } catch (Throwable $e) { error_log('[employees list] '.$e->getMessage()); jsonResponse(500,['ok'=>false,'message'=>'Gagal mengambil data pegawai.']); }
}

if ($path === '/employees' && $method === 'POST') {
    $user=requireRole(['admin','operator']); requireCsrf();
    $b=requestJson();
    $pin=trim((string)($b['pin']??'')); $name=trim((string)($b['full_name']??''));
    if ($pin==='' || $name==='' || strlen($pin)>50 || strlen($name)>180) jsonResponse(422,['ok'=>false,'message'=>'PIN dan nama wajib diisi dengan panjang yang valid.']);
    $status=$b['employee_status']??'active';
    if (!in_array($status,['active','inactive','resigned','terminated'],true)) jsonResponse(422,['ok'=>false,'message'=>'Status pegawai tidak valid.']);
    $fields=['unit_id','department','position_name','join_date','contract_end','phone','email','gender','birth_date','religion','marital_status','education','blood_type','address','machine_privilege','rfid','fingerprint_count','notes'];
    $values=[];
    foreach($fields as $f) $values[$f]=$b[$f]??null;
    if ($values['unit_id']==='') $values['unit_id']=null;
    if ($values['fingerprint_count']===null) $values['fingerprint_count']=0;
    if ($values['gender']!==null && !in_array($values['gender'],['male','female','other'],true)) jsonResponse(422,['ok'=>false,'message'=>'Jenis kelamin tidak valid.']);
    try {
        $pdo=database();
        $cols=array_merge(['pin','full_name','employee_status'],$fields);
        $vals=array_merge([$pin,$name,$status],array_values($values));
        $sql='INSERT INTO employees ('.implode(',',$cols).') VALUES ('.implode(',',array_fill(0,count($cols),'?')).')';
        $st=$pdo->prepare($sql); $st->execute($vals); $id=(int)$pdo->lastInsertId();
        audit($pdo,(int)$user['id'],'create','employee',$id,null,['pin'=>$pin,'full_name'=>$name,'employee_status'=>$status]);
        jsonResponse(201,['ok'=>true,'id'=>$id,'message'=>'Pegawai berhasil ditambahkan.']);
    } catch (PDOException $e) { if ($e->getCode()==='23000') jsonResponse(409,['ok'=>false,'message'=>'PIN sudah digunakan atau referensi unit tidak valid.']); error_log('[employee create] '.$e->getMessage()); jsonResponse(500,['ok'=>false,'message'=>'Gagal menyimpan pegawai.']); }
}

if (preg_match('#^/employees/(\d+)$#',$path,$m)) {
    $id=(int)$m[1];
    if ($method==='PUT') {
        $user=requireRole(['admin','operator']); requireCsrf(); $b=requestJson();
        $allowed=['pin','full_name','unit_id','department','position_name','employee_status','join_date','contract_end','phone','email','gender','birth_date','religion','marital_status','education','blood_type','address','machine_privilege','rfid','fingerprint_count','notes'];
        $changes=array_intersect_key($b,array_flip($allowed));
        if (!$changes) jsonResponse(422,['ok'=>false,'message'=>'Tidak ada kolom yang diperbarui.']);
        if (isset($changes['pin']) && (trim((string)$changes['pin'])==='' || strlen((string)$changes['pin'])>50)) jsonResponse(422,['ok'=>false,'message'=>'PIN tidak valid.']);
        if (isset($changes['full_name']) && (trim((string)$changes['full_name'])==='' || strlen((string)$changes['full_name'])>180)) jsonResponse(422,['ok'=>false,'message'=>'Nama tidak valid.']);
        if (isset($changes['employee_status']) && !in_array($changes['employee_status'],['active','inactive','resigned','terminated'],true)) jsonResponse(422,['ok'=>false,'message'=>'Status tidak valid.']);
        try {
            $pdo=database(); $st=$pdo->prepare('SELECT * FROM employees WHERE id=?'); $st->execute([$id]); $before=$st->fetch();
            if (!$before) jsonResponse(404,['ok'=>false,'message'=>'Pegawai tidak ditemukan.']);
            $sets=[];$vals=[];
            foreach($changes as $k=>$v){$sets[]="{$k}=?";$vals[]=$v===''?null:$v;}
            $vals[]=$id; $pdo->prepare('UPDATE employees SET '.implode(',',$sets).' WHERE id=?')->execute($vals);
            audit($pdo,(int)$user['id'],'update','employee',$id,$before,$changes);
            jsonResponse(200,['ok'=>true,'message'=>'Data pegawai diperbarui.']);
        } catch(PDOException $e){if($e->getCode()==='23000')jsonResponse(409,['ok'=>false,'message'=>'PIN duplikat atau referensi tidak valid.']);error_log('[employee update] '.$e->getMessage());jsonResponse(500,['ok'=>false,'message'=>'Gagal memperbarui pegawai.']);}
    }
    if ($method==='DELETE') {
        $user=requireRole(['admin']); requireCsrf();
        try {
            $pdo=database();$st=$pdo->prepare('SELECT * FROM employees WHERE id=?');$st->execute([$id]);$before=$st->fetch();
            if(!$before)jsonResponse(404,['ok'=>false,'message'=>'Pegawai tidak ditemukan.']);
            // Keep historical attendance and exceptions intact: deactivate instead of deleting.
            $pdo->prepare("UPDATE employees SET employee_status='inactive' WHERE id=?")->execute([$id]);
            audit($pdo,(int)$user['id'],'deactivate','employee',$id,$before,['employee_status'=>'inactive']);
            jsonResponse(200,['ok'=>true,'message'=>'Pegawai dinonaktifkan; riwayat tetap dipertahankan.']);
        } catch(Throwable $e){error_log('[employee deactivate] '.$e->getMessage());jsonResponse(500,['ok'=>false,'message'=>'Gagal menonaktifkan pegawai.']);}
    }
}

jsonResponse(404,['ok'=>false,'message'=>'Endpoint tidak ditemukan.']);
