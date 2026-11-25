<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = '/';
if (preg_match('#/api(?:/(.*))?$#', $requestPath, $routeMatch)) {
    $path = empty($routeMatch[1]) ? '/' : '/' . $routeMatch[1];
}
$path = rtrim($path, '/') ?: '/';

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


if ($path === '/units' && $method === 'GET') {
    requireUser();
    $rows=database()->query('SELECT id,code,name FROM units WHERE is_active=1 ORDER BY name')->fetchAll();
    jsonResponse(200,['ok'=>true,'data'=>$rows]);
}

/* Work schedule master */
if ($path === '/schedules' && $method === 'GET') {
    requireUser();
    $st=database()->query('SELECT id,code,name,start_time,end_time,break_start,break_end,tolerance_minutes,crosses_midnight,active_days,is_active FROM work_schedules ORDER BY name');
    $rows=$st->fetchAll();
    foreach($rows as &$row){$row['active_days']=json_decode((string)$row['active_days'],true) ?: []; $row['is_active']=(bool)$row['is_active']; $row['crosses_midnight']=(bool)$row['crosses_midnight'];}
    jsonResponse(200,['ok'=>true,'data'=>$rows]);
}
if ($path === '/schedules' && $method === 'POST') {
    $user=requireRole(['admin','operator']); requireCsrf(); $b=requestJson();
    $code=trim((string)($b['code']??'')); $name=trim((string)($b['name']??''));
    $start=(string)($b['start_time']??''); $end=(string)($b['end_time']??'');
    $days=$b['active_days']??[];
    if($code===''||$name===''||strlen($code)>40||strlen($name)>120||!preg_match('/^([01]\\d|2[0-3]):[0-5]\\d(:[0-5]\\d)?$/',$start)||!preg_match('/^([01]\\d|2[0-3]):[0-5]\\d(:[0-5]\\d)?$/',$end)||!is_array($days)||!$days) jsonResponse(422,['ok'=>false,'message'=>'Kode, nama, jam, dan hari aktif wajib valid.']);
    $validDays=['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'];
    if(array_diff($days,$validDays)) jsonResponse(422,['ok'=>false,'message'=>'Daftar hari aktif tidak valid.']);
    $tol=filter_var($b['tolerance_minutes']??0,FILTER_VALIDATE_INT);
    if($tol===false||$tol<0||$tol>600) jsonResponse(422,['ok'=>false,'message'=>'Toleransi harus 0–600 menit.']);
    try{
        $pdo=database();$st=$pdo->prepare('INSERT INTO work_schedules (code,name,start_time,end_time,break_start,break_end,tolerance_minutes,crosses_midnight,active_days,is_active) VALUES (?,?,?,?,?,?,?,?,?,1)');
        $st->execute([$code,$name,$start,$end,$b['break_start']??null,$b['break_end']??null,$tol,!empty($b['crosses_midnight'])?1:0,json_encode(array_values(array_unique($days)),JSON_UNESCAPED_UNICODE)]);
        $id=(int)$pdo->lastInsertId();audit($pdo,(int)$user['id'],'create','work_schedule',$id,null,$b);
        jsonResponse(201,['ok'=>true,'id'=>$id,'message'=>'Jam kerja berhasil dibuat.']);
    }catch(PDOException $e){if($e->getCode()==='23000')jsonResponse(409,['ok'=>false,'message'=>'Kode jam kerja sudah digunakan.']);error_log('[schedule create] '.$e->getMessage());jsonResponse(500,['ok'=>false,'message'=>'Gagal membuat jam kerja.']);}
}

/* Schedule update and safe deactivation. */
if (preg_match('#^/schedules/(\\d+)$#',$path,$m)) {
    $id=(int)$m[1];
    if ($method==='PUT') {
        $user=requireRole(['admin','operator']); requireCsrf(); $b=requestJson();
        $allowed=['code','name','start_time','end_time','break_start','break_end','tolerance_minutes','crosses_midnight','active_days','is_active'];
        $changes=array_intersect_key($b,array_flip($allowed));
        if(!$changes)jsonResponse(422,['ok'=>false,'message'=>'Tidak ada data jadwal yang diperbarui.']);
        if(isset($changes['active_days'])){
            $valid=['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'];
            if(!is_array($changes['active_days'])||!$changes['active_days']||array_diff($changes['active_days'],$valid))jsonResponse(422,['ok'=>false,'message'=>'Hari aktif tidak valid.']);
            $changes['active_days']=json_encode(array_values(array_unique($changes['active_days'])),JSON_UNESCAPED_UNICODE);
        }
        if(isset($changes['tolerance_minutes'])&&(!is_numeric($changes['tolerance_minutes'])||(int)$changes['tolerance_minutes']<0||(int)$changes['tolerance_minutes']>600))jsonResponse(422,['ok'=>false,'message'=>'Toleransi harus 0–600 menit.']);
        foreach(['start_time','end_time','break_start','break_end'] as $field)if(array_key_exists($field,$changes)&&$changes[$field]!==null&&$changes[$field]!==''&&!preg_match('/^([01]\\d|2[0-3]):[0-5]\\d(:[0-5]\\d)?$/',(string)$changes[$field]))jsonResponse(422,['ok'=>false,'message'=>'Format jam tidak valid.']);
        try{
            $pdo=database();$st=$pdo->prepare('SELECT * FROM work_schedules WHERE id=?');$st->execute([$id]);$before=$st->fetch();
            if(!$before)jsonResponse(404,['ok'=>false,'message'=>'Jadwal tidak ditemukan.']);
            $sets=[];$vals=[];
            foreach($changes as $k=>$v){$sets[]=$k.'=?';$vals[]=$v===''?null:$v;}
            $vals[]=$id;$pdo->prepare('UPDATE work_schedules SET '.implode(',',$sets).' WHERE id=?')->execute($vals);
            audit($pdo,(int)$user['id'],'update','work_schedule',$id,$before,$b);jsonResponse(200,['ok'=>true,'message'=>'Jadwal diperbarui.']);
        }catch(PDOException $e){if($e->getCode()==='23000')jsonResponse(409,['ok'=>false,'message'=>'Kode jadwal sudah digunakan.']);error_log('[schedule update] '.$e->getMessage());jsonResponse(500,['ok'=>false,'message'=>'Gagal memperbarui jadwal.']);}
    }
    if($method==='DELETE'){
        $user=requireRole(['admin']);requireCsrf();
        try{$pdo=database();$st=$pdo->prepare('SELECT * FROM work_schedules WHERE id=?');$st->execute([$id]);$before=$st->fetch();if(!$before)jsonResponse(404,['ok'=>false,'message'=>'Jadwal tidak ditemukan.']);
            $pdo->prepare('UPDATE work_schedules SET is_active=0 WHERE id=?')->execute([$id]);audit($pdo,(int)$user['id'],'deactivate','work_schedule',$id,$before,['is_active'=>0]);jsonResponse(200,['ok'=>true,'message'=>'Jadwal dinonaktifkan; riwayat penugasan tetap dipertahankan.']);
        }catch(Throwable $e){error_log('[schedule deactivate] '.$e->getMessage());jsonResponse(500,['ok'=>false,'message'=>'Gagal menonaktifkan jadwal.']);}
    }
}

/* Calendar events */
if ($path === '/calendar' && $method === 'GET') {
    requireUser();
    $st=database()->query('SELECT c.id,c.name,c.event_type,c.unit_id,u.code AS unit_code,u.name AS unit_name,c.start_date,c.end_date,c.impact,c.notes FROM calendar_events c LEFT JOIN units u ON u.id=c.unit_id ORDER BY c.start_date DESC LIMIT 1000');
    jsonResponse(200,['ok'=>true,'data'=>$st->fetchAll()]);
}
if ($path === '/calendar' && $method === 'POST') {
    $user=requireRole(['admin','operator']);requireCsrf();$b=requestJson();
    $name=trim((string)($b['name']??''));$type=$b['event_type']??'';$impact=$b['impact']??'non_working';
    $start=$b['start_date']??'';$end=$b['end_date']??'';
    if($name===''||strlen($name)>180||!in_array($type,['national_holiday','foundation_holiday','unit_holiday','special_activity'],true)||!in_array($impact,['non_working','working'],true)||!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',(string)$start)||!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',(string)$end)||$end<$start)jsonResponse(422,['ok'=>false,'message'=>'Data kalender tidak valid.']);
    try{$pdo=database();$st=$pdo->prepare('INSERT INTO calendar_events (name,event_type,unit_id,start_date,end_date,impact,notes,created_by) VALUES (?,?,?,?,?,?,?,?)');$st->execute([$name,$type,($b['unit_id']??'')===''?null:($b['unit_id']??null),$start,$end,$impact,$b['notes']??null,$user['id']]);$id=(int)$pdo->lastInsertId();audit($pdo,(int)$user['id'],'create','calendar_event',$id,null,$b);jsonResponse(201,['ok'=>true,'id'=>$id,'message'=>'Kegiatan kalender disimpan.']);}
    catch(PDOException $e){error_log('[calendar create] '.$e->getMessage());jsonResponse(500,['ok'=>false,'message'=>'Gagal menyimpan kalender.']);}
}

/* Attendance exceptions and approvals */
if ($path === '/exceptions' && $method === 'GET') {
    requireUser();
    $st=database()->query('SELECT x.id,x.employee_id,e.pin,e.full_name,x.exception_type,x.start_at,x.end_at,x.status,x.reason,x.replacement_schedule_id,x.approved_by,x.approved_at FROM attendance_exceptions x JOIN employees e ON e.id=x.employee_id ORDER BY x.start_at DESC LIMIT 1000');
    jsonResponse(200,['ok'=>true,'data'=>$st->fetchAll()]);
}
if ($path === '/exceptions' && $method === 'POST') {
    $user=requireRole(['admin','operator']);requireCsrf();$b=requestJson();
    $employeeId=filter_var($b['employee_id']??null,FILTER_VALIDATE_INT);
    $type=$b['exception_type']??'';$start=$b['start_at']??'';$end=$b['end_at']??'';
    $types=['leave','sick','permission','annual_leave','collective_leave','schedule_change','hours_change','shift_swap','overtime','extra_work'];
    if(!$employeeId||!in_array($type,$types,true)||!is_string($start)||!is_string($end)||strtotime($start)===false||strtotime($end)===false||strtotime($end)<strtotime($start))jsonResponse(422,['ok'=>false,'message'=>'Pegawai, jenis, dan rentang waktu pengecualian wajib valid.']);
    try{$pdo=database();$st=$pdo->prepare("INSERT INTO attendance_exceptions (employee_id,exception_type,start_at,end_at,status,reason,replacement_schedule_id,created_by) VALUES (?,?,?,?,'pending',?,?,?)");$st->execute([$employeeId,$type,$start,$end,$b['reason']??null,($b['replacement_schedule_id']??'')===''?null:($b['replacement_schedule_id']??null),$user['id']]);$id=(int)$pdo->lastInsertId();audit($pdo,(int)$user['id'],'create','attendance_exception',$id,null,$b);jsonResponse(201,['ok'=>true,'id'=>$id,'message'=>'Pengajuan tersimpan dan menunggu persetujuan.']);}
    catch(PDOException $e){if($e->getCode()==='23000')jsonResponse(422,['ok'=>false,'message'=>'Pegawai atau jadwal pengganti tidak ditemukan.']);error_log('[exception create] '.$e->getMessage());jsonResponse(500,['ok'=>false,'message'=>'Gagal menyimpan pengecualian.']);}
}
if (preg_match('#^/exceptions/(\\d+)/decision$#',$path,$m) && $method==='POST') {
    $user=requireRole(['admin']);requireCsrf();$id=(int)$m[1];$b=requestJson();$decision=$b['status']??'';
    if(!in_array($decision,['approved','rejected'],true))jsonResponse(422,['ok'=>false,'message'=>'Keputusan harus approved atau rejected.']);
    try{$pdo=database();$st=$pdo->prepare("SELECT * FROM attendance_exceptions WHERE id=?");$st->execute([$id]);$before=$st->fetch();if(!$before)jsonResponse(404,['ok'=>false,'message'=>'Pengajuan tidak ditemukan.']);if($before['status']!=='pending')jsonResponse(409,['ok'=>false,'message'=>'Hanya pengajuan pending yang dapat diputuskan.']);$pdo->prepare('UPDATE attendance_exceptions SET status=?,approved_by=?,approved_at=NOW() WHERE id=?')->execute([$decision,$user['id'],$id]);audit($pdo,(int)$user['id'],'decision','attendance_exception',$id,$before,['status'=>$decision]);jsonResponse(200,['ok'=>true,'message'=>'Keputusan pengajuan disimpan.']);}
    catch(Throwable $e){error_log('[exception decision] '.$e->getMessage());jsonResponse(500,['ok'=>false,'message'=>'Gagal memproses keputusan.']);}
}


/* Machine inventory (network communication is deliberately not attempted here). */
if ($path === '/machines' && $method === 'GET') {
    requireUser();
    $rows=database()->query('SELECT id,name,model,serial_number,host,port,connection_type,location,status,last_sync_at,notes,created_at,updated_at FROM machines ORDER BY name')->fetchAll();
    jsonResponse(200,['ok'=>true,'data'=>$rows]);
}
if ($path === '/machines' && $method === 'POST') {
    $user=requireRole(['admin','operator']);requireCsrf();$b=requestJson();
    $name=trim((string)($b['name']??''));$host=trim((string)($b['host']??''));$port=filter_var($b['port']??4370,FILTER_VALIDATE_INT);
    $conn=$b['connection_type']??'tcp';$status=$b['status']??'unknown';
    if($name===''||$host===''||strlen($name)>120||strlen($host)>255||$port===false||$port<1||$port>65535||!in_array($conn,['tcp','udp','http','sdk','file'],true)||!in_array($status,['active','inactive','maintenance','unknown'],true))jsonResponse(422,['ok'=>false,'message'=>'Konfigurasi mesin tidak valid.']);
    try{$pdo=database();$st=$pdo->prepare('INSERT INTO machines (name,model,serial_number,host,port,connection_type,location,status,notes) VALUES (?,?,?,?,?,?,?,?,?)');$st->execute([$name,$b['model']??null,$b['serial_number']??null,$host,$port,$conn,$b['location']??null,$status,$b['notes']??null]);$id=(int)$pdo->lastInsertId();audit($pdo,(int)$user['id'],'create','machine',$id,null,$b);jsonResponse(201,['ok'=>true,'id'=>$id,'message'=>'Data mesin tersimpan.']);}
    catch(PDOException $e){if($e->getCode()==='23000')jsonResponse(409,['ok'=>false,'message'=>'Nomor seri mesin sudah terdaftar.']);error_log('[machine create] '.$e->getMessage());jsonResponse(500,['ok'=>false,'message'=>'Gagal menyimpan mesin.']);}
}

if (preg_match('#^/machines/(\\d+)$#',$path,$machineMatch)) {
    $machineId=(int)$machineMatch[1];
    if ($method==='PUT') {
        $user=requireRole(['admin','operator']);requireCsrf();$b=requestJson();
        $allowed=['name','model','serial_number','host','port','connection_type','location','status','notes'];
        $changes=array_intersect_key($b,array_flip($allowed));
        if(!$changes)jsonResponse(422,['ok'=>false,'message'=>'Tidak ada perubahan mesin.']);
        if(isset($changes['name'])&&trim((string)$changes['name'])==='')jsonResponse(422,['ok'=>false,'message'=>'Nama mesin wajib diisi.']);
        if(isset($changes['host'])&&trim((string)$changes['host'])==='')jsonResponse(422,['ok'=>false,'message'=>'Host mesin wajib diisi.']);
        if(isset($changes['port'])&&(!filter_var($changes['port'],FILTER_VALIDATE_INT)||$changes['port']<1||$changes['port']>65535))jsonResponse(422,['ok'=>false,'message'=>'Port mesin tidak valid.']);
        if(isset($changes['connection_type'])&&!in_array($changes['connection_type'],['tcp','udp','http','sdk','file'],true))jsonResponse(422,['ok'=>false,'message'=>'Jenis koneksi tidak valid.']);
        if(isset($changes['status'])&&!in_array($changes['status'],['active','inactive','maintenance','unknown'],true))jsonResponse(422,['ok'=>false,'message'=>'Status mesin tidak valid.']);
        try{$pdo=database();$st=$pdo->prepare('SELECT * FROM machines WHERE id=?');$st->execute([$machineId]);$before=$st->fetch();if(!$before)jsonResponse(404,['ok'=>false,'message'=>'Mesin tidak ditemukan.']);$sets=[];$values=[];foreach($changes as $key=>$value){$sets[]=$key.'=?';$values[]=$value===''?null:$value;}$values[]=$machineId;$pdo->prepare('UPDATE machines SET '.implode(',',$sets).' WHERE id=?')->execute($values);audit($pdo,(int)$user['id'],'update','machine',$machineId,$before,$changes);jsonResponse(200,['ok'=>true,'message'=>'Konfigurasi mesin diperbarui.']);}
        catch(PDOException $e){if($e->getCode()==='23000')jsonResponse(409,['ok'=>false,'message'=>'Nomor serial mesin sudah digunakan.']);error_log('[machine update] '.$e->getMessage());jsonResponse(500,['ok'=>false,'message'=>'Gagal memperbarui mesin.']);}
    }
    if($method==='DELETE'){
        $user=requireRole(['admin']);requireCsrf();
        try{$pdo=database();$st=$pdo->prepare('SELECT * FROM machines WHERE id=?');$st->execute([$machineId]);$before=$st->fetch();if(!$before)jsonResponse(404,['ok'=>false,'message'=>'Mesin tidak ditemukan.']);$pdo->prepare("UPDATE machines SET status='inactive' WHERE id=?")->execute([$machineId]);audit($pdo,(int)$user['id'],'deactivate','machine',$machineId,$before,['status'=>'inactive']);jsonResponse(200,['ok'=>true,'message'=>'Mesin dinonaktifkan; riwayat scanlog tetap dipertahankan.']);}
        catch(Throwable $e){error_log('[machine deactivate] '.$e->getMessage());jsonResponse(500,['ok'=>false,'message'=>'Gagal menonaktifkan mesin.']);}
    }
}

/* Calendar event update and delete. */
if (preg_match('#^/calendar/(\\d+)$#',$path,$m)) {
    $id=(int)$m[1];
    if($method==='PUT'){
        $user=requireRole(['admin','operator']);requireCsrf();$b=requestJson();
        $allowed=['name','event_type','unit_id','start_date','end_date','impact','notes'];$changes=array_intersect_key($b,array_flip($allowed));
        if(!$changes)jsonResponse(422,['ok'=>false,'message'=>'Tidak ada data kalender yang diperbarui.']);
        $types=['national_holiday','foundation_holiday','unit_holiday','special_activity'];
        if(isset($changes['event_type'])&&!in_array($changes['event_type'],$types,true))jsonResponse(422,['ok'=>false,'message'=>'Jenis kalender tidak valid.']);
        if(isset($changes['impact'])&&!in_array($changes['impact'],['non_working','working'],true))jsonResponse(422,['ok'=>false,'message'=>'Dampak kalender tidak valid.']);
        foreach(['start_date','end_date'] as $field)if(isset($changes[$field])&&!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',(string)$changes[$field]))jsonResponse(422,['ok'=>false,'message'=>'Tanggal kalender tidak valid.']);
        try{$pdo=database();$st=$pdo->prepare('SELECT * FROM calendar_events WHERE id=?');$st->execute([$id]);$before=$st->fetch();if(!$before)jsonResponse(404,['ok'=>false,'message'=>'Kegiatan kalender tidak ditemukan.']);
            $start=$changes['start_date']??$before['start_date'];$end=$changes['end_date']??$before['end_date'];if($end<$start)jsonResponse(422,['ok'=>false,'message'=>'Tanggal selesai tidak boleh sebelum tanggal mulai.']);
            if(array_key_exists('unit_id',$changes)&&$changes['unit_id']==='')$changes['unit_id']=null;
            $sets=[];$vals=[];foreach($changes as $k=>$v){$sets[]=$k.'=?';$vals[]=$v;}$vals[]=$id;$pdo->prepare('UPDATE calendar_events SET '.implode(',',$sets).' WHERE id=?')->execute($vals);
            audit($pdo,(int)$user['id'],'update','calendar_event',$id,$before,$b);jsonResponse(200,['ok'=>true,'message'=>'Kegiatan kalender diperbarui.']);
        }catch(Throwable $e){error_log('[calendar update] '.$e->getMessage());jsonResponse(500,['ok'=>false,'message'=>'Gagal memperbarui kalender.']);}
    }
    if($method==='DELETE'){
        $user=requireRole(['admin']);requireCsrf();
        try{$pdo=database();$st=$pdo->prepare('SELECT * FROM calendar_events WHERE id=?');$st->execute([$id]);$before=$st->fetch();if(!$before)jsonResponse(404,['ok'=>false,'message'=>'Kegiatan kalender tidak ditemukan.']);
            $pdo->prepare('DELETE FROM calendar_events WHERE id=?')->execute([$id]);audit($pdo,(int)$user['id'],'delete','calendar_event',$id,$before,null);jsonResponse(200,['ok'=>true,'message'=>'Kegiatan kalender dihapus.']);
        }catch(Throwable $e){error_log('[calendar delete] '.$e->getMessage());jsonResponse(500,['ok'=>false,'message'=>'Gagal menghapus kalender.']);}
    }
}

/* Per-employee schedule assignments with optional individual overrides. */
if ($path === '/schedule-assignments' && $method === 'GET') {
    requireUser();
    $rows=database()->query('SELECT a.id,a.employee_id,e.pin,e.full_name,a.schedule_id,s.name AS schedule_name,a.effective_from,a.effective_to,a.custom_start,a.custom_end,a.custom_tolerance FROM employee_schedule_assignments a JOIN employees e ON e.id=a.employee_id JOIN work_schedules s ON s.id=a.schedule_id ORDER BY e.full_name,a.effective_from DESC')->fetchAll();
    jsonResponse(200,['ok'=>true,'data'=>$rows]);
}
if ($path === '/schedule-assignments' && $method === 'POST') {
    $user=requireRole(['admin','operator']);requireCsrf();$b=requestJson();
    $employee=filter_var($b['employee_id']??null,FILTER_VALIDATE_INT);$schedule=filter_var($b['schedule_id']??null,FILTER_VALIDATE_INT);$from=$b['effective_from']??'';
    $to=$b['effective_to']??null;
    if(!$employee||!$schedule||!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',(string)$from)||($to!==null&&$to!==''&&(!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',(string)$to)||$to<$from)))jsonResponse(422,['ok'=>false,'message'=>'Penugasan jadwal tidak valid.']);
    try{
        $pdo=database();$pdo->beginTransaction();
        $same=$pdo->prepare('SELECT * FROM employee_schedule_assignments WHERE employee_id=? AND effective_from=? LIMIT 1 FOR UPDATE');$same->execute([$employee,$from]);$existing=$same->fetch();
        $future=$pdo->prepare('SELECT MIN(effective_from) FROM employee_schedule_assignments WHERE employee_id=? AND effective_from>?');$future->execute([$employee,$from]);$nextDate=$future->fetchColumn();
        $effectiveTo=$to?:null;if($nextDate){$dayBefore=date('Y-m-d',strtotime($nextDate.' -1 day'));if($effectiveTo===null||$effectiveTo>$dayBefore)$effectiveTo=$dayBefore;}
        $prior=$pdo->prepare('SELECT * FROM employee_schedule_assignments WHERE employee_id=? AND effective_from<? AND (effective_to IS NULL OR effective_to>=?) FOR UPDATE');$prior->execute([$employee,$from,$from]);$closed=$prior->fetchAll();
        foreach($closed as $row)$pdo->prepare('UPDATE employee_schedule_assignments SET effective_to=? WHERE id=?')->execute([date('Y-m-d',strtotime($from.' -1 day')),$row['id']]);
        $values=[$schedule,$effectiveTo,$b['custom_start']??null,$b['custom_end']??null,($b['custom_tolerance']??'')===''?null:($b['custom_tolerance']??null)];
        if($existing){$id=(int)$existing['id'];$before=$existing;$pdo->prepare('UPDATE employee_schedule_assignments SET schedule_id=?,effective_to=?,custom_start=?,custom_end=?,custom_tolerance=? WHERE id=?')->execute([...$values,$id]);audit($pdo,(int)$user['id'],'update','schedule_assignment',$id,$before,['schedule_id'=>$schedule,'effective_from'=>$from,'effective_to'=>$effectiveTo,'custom_start'=>$values[2],'custom_end'=>$values[3],'custom_tolerance'=>$values[4]]);}
        else{$st=$pdo->prepare('INSERT INTO employee_schedule_assignments (employee_id,schedule_id,effective_from,effective_to,custom_start,custom_end,custom_tolerance) VALUES (?,?,?,?,?,?,?)');$st->execute([$employee,$schedule,$from,$effectiveTo,...array_slice($values,2)]);$id=(int)$pdo->lastInsertId();audit($pdo,(int)$user['id'],'create','schedule_assignment',$id,null,['employee_id'=>$employee,'schedule_id'=>$schedule,'effective_from'=>$from,'effective_to'=>$effectiveTo,'custom_start'=>$values[2],'custom_end'=>$values[3],'custom_tolerance'=>$values[4]]);}
        $pdo->commit();jsonResponse(201,['ok'=>true,'id'=>$id,'message'=>'Penugasan jadwal tersimpan; riwayat sebelumnya tetap terjaga.']);
    }catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();if($e instanceof PDOException&&$e->getCode()==='23000')jsonResponse(422,['ok'=>false,'message'=>'Pegawai atau master jadwal tidak ditemukan.']);error_log('[assignment create] '.$e->getMessage());jsonResponse(500,['ok'=>false,'message'=>'Gagal menetapkan jadwal.']);}
}
if ($path === '/unit-schedule-defaults' && $method === 'GET') {
    requireUser();
    $rows=database()->query('SELECT d.id,d.unit_id,u.code AS unit_code,u.name AS unit_name,d.schedule_id,s.name AS schedule_name,d.effective_from,d.effective_to FROM unit_schedule_defaults d JOIN units u ON u.id=d.unit_id JOIN work_schedules s ON s.id=d.schedule_id ORDER BY u.name,d.effective_from DESC')->fetchAll();
    jsonResponse(200,['ok'=>true,'data'=>$rows]);
}
if ($path === '/unit-schedule-defaults' && $method === 'POST') {
    $user=requireRole(['admin','operator']);requireCsrf();$b=requestJson();
    $unit=filter_var($b['unit_id']??null,FILTER_VALIDATE_INT);$schedule=filter_var($b['schedule_id']??null,FILTER_VALIDATE_INT);$from=$b['effective_from']??'';
    $to=$b['effective_to']??null;
    if(!$unit||!$schedule||!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',(string)$from)||($to!==null&&$to!==''&&(!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',(string)$to)||$to<$from)))jsonResponse(422,['ok'=>false,'message'=>'Default jadwal unit tidak valid.']);
    try{
        $pdo=database();$pdo->beginTransaction();
        $same=$pdo->prepare('SELECT * FROM unit_schedule_defaults WHERE unit_id=? AND effective_from=? LIMIT 1 FOR UPDATE');
        $same->execute([$unit,$from]);$existing=$same->fetch();
        $future=$pdo->prepare('SELECT MIN(effective_from) FROM unit_schedule_defaults WHERE unit_id=? AND effective_from>?');
        $future->execute([$unit,$from]);$nextDate=$future->fetchColumn();
        $effectiveTo=$to?:null;
        if($nextDate){$dayBefore=date('Y-m-d',strtotime($nextDate.' -1 day'));if($effectiveTo===null||$effectiveTo>$dayBefore)$effectiveTo=$dayBefore;}
        $prior=$pdo->prepare('SELECT * FROM unit_schedule_defaults WHERE unit_id=? AND effective_from<? AND (effective_to IS NULL OR effective_to>=?) FOR UPDATE');
        $prior->execute([$unit,$from,$from]);$closed=$prior->fetchAll();
        foreach($closed as $row){$pdo->prepare('UPDATE unit_schedule_defaults SET effective_to=? WHERE id=?')->execute([date('Y-m-d',strtotime($from.' -1 day')),$row['id']]);}
        if($existing){
            $id=(int)$existing['id'];$pdo->prepare('UPDATE unit_schedule_defaults SET schedule_id=?,effective_to=? WHERE id=?')->execute([$schedule,$effectiveTo,$id]);
            audit($pdo,(int)$user['id'],'update','unit_schedule_default',$id,$existing,['schedule_id'=>$schedule,'effective_from'=>$from,'effective_to'=>$effectiveTo]);
        }else{
            $st=$pdo->prepare('INSERT INTO unit_schedule_defaults (unit_id,schedule_id,effective_from,effective_to) VALUES (?,?,?,?)');$st->execute([$unit,$schedule,$from,$effectiveTo]);$id=(int)$pdo->lastInsertId();
            audit($pdo,(int)$user['id'],'create','unit_schedule_default',$id,null,['unit_id'=>$unit,'schedule_id'=>$schedule,'effective_from'=>$from,'effective_to'=>$effectiveTo]);
        }
        $pdo->commit();jsonResponse(201,['ok'=>true,'id'=>$id,'message'=>'Default jadwal unit tersimpan tanpa menimpa riwayat sebelumnya.']);
    }catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();if($e instanceof PDOException&&$e->getCode()==='23000')jsonResponse(422,['ok'=>false,'message'=>'Unit atau master jadwal tidak ditemukan.']);error_log('[unit default create] '.$e->getMessage());jsonResponse(500,['ok'=>false,'message'=>'Gagal menyimpan default jadwal.']);}
}


/* Immutable fingerprint imports and explicit attendance processing. */
if ($path === '/scanlogs' && $method === 'GET') {
    requireUser();
    $from = (string)($_GET['from'] ?? date('Y-m-01'));
    $to = (string)($_GET['to'] ?? date('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$to) || $to < $from || (strtotime($to)-strtotime($from)) > 2678400) {
        jsonResponse(422,['ok'=>false,'message'=>'Rentang tanggal log tidak valid atau lebih dari 31 hari.']);
    }
    $st=database()->prepare('SELECT r.id,r.machine_id,m.name AS machine_name,COALESCE(r.employee_id,pm.employee_id) AS employee_id,e.full_name,r.pin,r.scanned_at,r.verify_method,r.device_event_code,r.device_transaction_id,r.source,r.imported_at FROM raw_scanlogs r LEFT JOIN machines m ON m.id=r.machine_id LEFT JOIN scanlog_pin_mappings pm ON pm.pin=r.pin LEFT JOIN employees e ON e.id=COALESCE(r.employee_id,pm.employee_id) WHERE r.scanned_at>=? AND r.scanned_at<? ORDER BY r.scanned_at ASC LIMIT 10000');
    $st->execute([$from.' 00:00:00',date('Y-m-d',strtotime($to.' +1 day')).' 00:00:00']);
    jsonResponse(200,['ok'=>true,'data'=>$st->fetchAll()]);
}
if ($path === '/scanlogs/unmatched' && $method === 'GET') {
    requireUser();
    $st=database()->query("SELECT r.pin,COUNT(*) AS scan_count,MIN(r.scanned_at) AS first_seen,MAX(r.scanned_at) AS last_seen FROM raw_scanlogs r LEFT JOIN scanlog_pin_mappings pm ON pm.pin=r.pin WHERE r.employee_id IS NULL AND pm.id IS NULL GROUP BY r.pin ORDER BY last_seen DESC");
    jsonResponse(200,['ok'=>true,'data'=>$st->fetchAll()]);
}
if ($path === '/scanlogs/map-pin' && $method === 'POST') {
    $user=requireRole(['admin','operator']); requireCsrf(); $b=requestJson();
    $pin=trim((string)($b['pin']??'')); $employee=filter_var($b['employee_id']??null,FILTER_VALIDATE_INT);
    if($pin===''||strlen($pin)>50||!$employee)jsonResponse(422,['ok'=>false,'message'=>'PIN dan pegawai tujuan wajib dipilih.']);
    try{$pdo=database();$check=$pdo->prepare('SELECT id FROM employees WHERE id=? AND employee_status<>\'terminated\'');$check->execute([$employee]);if(!$check->fetchColumn())jsonResponse(404,['ok'=>false,'message'=>'Pegawai tujuan tidak ditemukan.']);$beforeStmt=$pdo->prepare('SELECT * FROM scanlog_pin_mappings WHERE pin=?');$beforeStmt->execute([$pin]);$before=$beforeStmt->fetch()?:null;$pdo->prepare('INSERT INTO scanlog_pin_mappings (pin,employee_id,mapped_by,notes) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE employee_id=VALUES(employee_id),mapped_by=VALUES(mapped_by),mapped_at=CURRENT_TIMESTAMP,notes=VALUES(notes)')->execute([$pin,$employee,$user['id'],$b['notes']??null]);audit($pdo,(int)$user['id'],'map_pin','scanlog_pin_mapping',null,$before,['pin'=>$pin,'employee_id'=>$employee]);jsonResponse(200,['ok'=>true,'message'=>'PIN berhasil dipetakan. Log mentah tidak diubah; pemetaan berlaku pada proses rekap berikutnya.']);}catch(Throwable $e){error_log('[pin mapping] '.$e->getMessage());jsonResponse(500,['ok'=>false,'message'=>'Gagal menyimpan pemetaan PIN.']);}
}
if ($path === '/scanlogs/import' && $method === 'POST') {
    $user=requireRole(['admin','operator']); requireCsrf(); $b=requestJson();
    $rows=$b['rows']??null; $machine=filter_var($b['machine_id']??null,FILTER_VALIDATE_INT);
    if (!is_array($rows) || count($rows)<1 || count($rows)>1000 || !$machine) jsonResponse(422,['ok'=>false,'message'=>'Pilih mesin sumber dan impor 1–1000 baris.']);
    $machineCheck=database()->prepare('SELECT id FROM machines WHERE id=? AND status<>\'inactive\'');$machineCheck->execute([$machine]);if(!$machineCheck->fetchColumn())jsonResponse(422,['ok'=>false,'message'=>'Mesin sumber tidak ditemukan atau nonaktif.']);
    $pdo=database(); $inserted=0; $duplicates=0; $unmatched=0;
    try {
        $pdo->beginTransaction();
        $lookup=$pdo->prepare('SELECT id FROM employees WHERE pin=? LIMIT 1');
        $mapped=$pdo->prepare('SELECT employee_id FROM scanlog_pin_mappings WHERE pin=? LIMIT 1');
        $insert=$pdo->prepare('INSERT IGNORE INTO raw_scanlogs (machine_id,employee_id,pin,scanned_at,verify_method,device_event_code,device_transaction_id,source,raw_payload) VALUES (?,?,?,?,?,?,?,?,?)');
        foreach($rows as $row) {
            if (!is_array($row)) throw new InvalidArgumentException('Format salah satu baris log tidak valid.');
            $pin=trim((string)($row['pin']??'')); $at=(string)($row['scanned_at']??'');
            if ($pin==='' || strlen($pin)>50 || !preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}$/',$at) || strtotime($at)===false) throw new InvalidArgumentException('Setiap baris wajib memiliki PIN dan waktu scan YYYY-MM-DD HH:MM:SS yang valid.');
            $lookup->execute([$pin]); $employee=$lookup->fetchColumn();
            if(!$employee){$mapped->execute([$pin]);$employee=$mapped->fetchColumn();}
            $payload=json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            $insert->execute([$machine,$employee?:null,$pin,str_replace('T',' ',$at),$row['verify_method']??null,$row['device_event_code']??null,($row['device_transaction_id']??'')===''?null:(string)$row['device_transaction_id'],'file_import',$payload]);
            if($insert->rowCount()>0){$inserted++;if(!$employee)$unmatched++;}else{$duplicates++;}
        }
        audit($pdo,(int)$user['id'],'import','raw_scanlogs',null,null,['inserted'=>$inserted,'duplicates'=>$duplicates,'unmatched'=>$unmatched,'machine_id'=>$machine]);
        $pdo->commit();
        jsonResponse(201,['ok'=>true,'inserted'=>$inserted,'duplicates'=>$duplicates,'unmatched'=>$unmatched,'message'=>'Impor selesai. Data log mentah tidak dapat diedit atau dihapus.']);
    } catch(InvalidArgumentException $e) { if($pdo->inTransaction())$pdo->rollBack(); jsonResponse(422,['ok'=>false,'message'=>$e->getMessage()]); }
      catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); error_log('[scanlog import] '.$e->getMessage()); jsonResponse(500,['ok'=>false,'message'=>'Impor gagal; transaksi dibatalkan. Periksa duplikasi ID transaksi dan konfigurasi database.']); }
}
if ($path === '/attendance/process' && $method === 'POST') {
    $user=requireRole(['admin','operator']); requireCsrf(); $b=requestJson();
    $from=(string)($b['from']??''); $to=(string)($b['to']??'');
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)||$to<$from||(strtotime($to)-strtotime($from))>2678400) jsonResponse(422,['ok'=>false,'message'=>'Periode rekap wajib valid dan maksimal 31 hari.']);
    $pdo=database();
    try {
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO processing_batches (period_start,period_end,status,ruleset_version,started_by,started_at) VALUES (?,?,'running','attendance-v1.0',?,NOW())")->execute([$from,$to,$user['id']]);
        $batch=(int)$pdo->lastInsertId();
        $scan=$pdo->prepare('SELECT r.id,COALESCE(r.employee_id,pm.employee_id) AS employee_id,r.pin,r.scanned_at FROM raw_scanlogs r LEFT JOIN scanlog_pin_mappings pm ON pm.pin=r.pin WHERE r.scanned_at>=? AND r.scanned_at<? AND COALESCE(r.employee_id,pm.employee_id) IS NOT NULL ORDER BY COALESCE(r.employee_id,pm.employee_id),r.scanned_at,r.id');
        $scan->execute([$from.' 00:00:00',date('Y-m-d',strtotime($to.' +1 day')).' 00:00:00']);
        $groups=[];
        foreach($scan->fetchAll() as $r){$day=substr($r['scanned_at'],0,10);$key=$r['employee_id'].'|'.$day;$groups[$key][]=$r;}
        // Seed scheduled employee-days even when there is no fingerprint scan, so absences are visible.
        $activeEmployees=$pdo->query("SELECT id,join_date,contract_end FROM employees WHERE employee_status='active'")->fetchAll();
        for($cursor=strtotime($from);$cursor<=strtotime($to);$cursor=strtotime('+1 day',$cursor)){
            $day=date('Y-m-d',$cursor);
            foreach($activeEmployees as $activeEmployee){$join=$activeEmployee['join_date'];$end=$activeEmployee['contract_end'];if(($join&&$day<$join)||($end&&$day>$end))continue;$key=$activeEmployee['id'].'|'.$day;if(!isset($groups[$key]))$groups[$key]=[];}
        }
        $employeeStmt=$pdo->prepare('SELECT id,unit_id,employee_status,join_date,contract_end FROM employees WHERE id=?');
        $assignment=$pdo->prepare('SELECT a.schedule_id,COALESCE(a.custom_start,s.start_time) AS start_time,COALESCE(a.custom_end,s.end_time) AS end_time,COALESCE(a.custom_tolerance,s.tolerance_minutes) AS tolerance_minutes,s.active_days,s.crosses_midnight FROM employee_schedule_assignments a JOIN work_schedules s ON s.id=a.schedule_id WHERE a.employee_id=? AND a.effective_from<=? AND (a.effective_to IS NULL OR a.effective_to>=?) ORDER BY a.effective_from DESC,a.id DESC LIMIT 1');
        $unitDefault=$pdo->prepare('SELECT d.schedule_id,s.start_time,s.end_time,s.tolerance_minutes,s.active_days,s.crosses_midnight FROM unit_schedule_defaults d JOIN work_schedules s ON s.id=d.schedule_id WHERE d.unit_id=? AND d.effective_from<=? AND (d.effective_to IS NULL OR d.effective_to>=?) ORDER BY d.effective_from DESC,d.id DESC LIMIT 1');
        $holiday=$pdo->prepare("SELECT COUNT(*) FROM calendar_events WHERE impact='non_working' AND start_date<=? AND end_date>=? AND (unit_id IS NULL OR unit_id=?)");
        $exception=$pdo->prepare("SELECT exception_type FROM attendance_exceptions WHERE employee_id=? AND status='approved' AND start_at<? AND end_at>=? ORDER BY start_at LIMIT 1");
        $save=$pdo->prepare('INSERT INTO attendance_daily_results (batch_id,employee_id,attendance_date,schedule_id,first_scan_at,last_scan_at,scan_count,attendance_status,late_minutes,early_leave_minutes,source_scan_ids,calculation_detail) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
        $count=0;
        foreach($groups as $key=>$logs){
            [$employeeId,$day]=explode('|',$key,2);$employeeStmt->execute([$employeeId]);$emp=$employeeStmt->fetch();if(!$emp)continue;
            $first=$logs[0]['scanned_at']??null;$last=$logs? $logs[count($logs)-1]['scanned_at']:null;$ids=array_map(static fn($x)=>(int)$x['id'],$logs);
            $status='hadir';$late=0;$early=0;$scheduleId=null;$detail=['rule'=>'first_scan_arrival_last_scan_departure','intermediate_scans_retained'=>true];
            if(($emp['join_date']&&$day<$emp['join_date'])||($emp['contract_end']&&$day>$emp['contract_end'])||$emp['employee_status']!=='active'){
                if(count($logs)===0)continue;
                $status='perlu_review_status_pegawai';
                $detail['review_reason']='Tanggal berada di luar masa aktif pegawai atau status pegawai bukan aktif.';
            }else{
                $assignment->execute([$employeeId,$day,$day]);$sch=$assignment->fetch();
                if(!$sch && $emp['unit_id']){$unitDefault->execute([$emp['unit_id'],$day,$day]);$sch=$unitDefault->fetch();}
                if(!$sch){$status='jadwal_belum_diatur';}
            else {
                $scheduleId=(int)$sch['schedule_id'];$days=json_decode((string)$sch['active_days'],true)?:[];
                $weekday=['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'][(int)date('w',strtotime($day))];
                if(!in_array($weekday,$days,true)){if(count($logs)===0)continue;$status='scan_di_hari_nonaktif';}
                elseif((int)$sch['crosses_midnight']===1){$status='perlu_review_shift_lintas_hari';}
                else {
                    $holiday->execute([$day,$day,$emp['unit_id']]);if((int)$holiday->fetchColumn()>0){if(count($logs)===0)continue;$status='hari_libur_nonkerja';}
                    else {
                        $exception->execute([$employeeId,date('Y-m-d',strtotime($day.' +1 day')).' 00:00:00',$day.' 00:00:00']);$ex=$exception->fetchColumn();
                        if($ex){$status='izin_disetujui';$detail['approved_exception']=$ex;}
                        elseif(count($logs)===0){$status='tidak_hadir';}
                        else {
                            $expectedIn=$day.' '.$sch['start_time'];$expectedOut=$day.' '.$sch['end_time'];
                            if(count($logs)===1){
                                // A single scan cannot establish both arrival and departure; keep it for review.
                                $status='perlu_review_scan_tunggal';
                                $detail['review_reason']='Hanya ada satu scan; waktu pulang belum dapat dipastikan.';
                            }else{
                                $threshold=strtotime($expectedIn)+((int)$sch['tolerance_minutes']*60);
                                $late=max(0,(int)floor((strtotime($first)-$threshold)/60));
                                $early=max(0,(int)floor((strtotime($expectedOut)-strtotime($last))/60));
                                if($late>0)$status='terlambat';elseif($early>0)$status='pulang_awal';
                            }
                        }
                    }
                }
            }
            }
            $detail['scan_ids']=$ids;$detail['scan_count']=count($logs);
            $save->execute([$batch,$employeeId,$day,$scheduleId,$first,$last,count($logs),$status,$late,$early,json_encode($ids),json_encode($detail,JSON_UNESCAPED_UNICODE)]);
            $count++;
        }
        $pdo->prepare("UPDATE processing_batches SET status='completed',finished_at=NOW(),row_count=? WHERE id=?")->execute([$count,$batch]);
        audit($pdo,(int)$user['id'],'process','attendance_batch',$batch,null,['from'=>$from,'to'=>$to,'row_count'=>$count,'ruleset'=>'attendance-v1.0']);
        $pdo->commit();
        jsonResponse(201,['ok'=>true,'batch_id'=>$batch,'row_count'=>$count,'message'=>'Rekap selesai. Hasil disimpan sebagai batch baru; log fingerprint asli tetap utuh.']);
    } catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); error_log('[attendance process] '.$e->getMessage()); jsonResponse(500,['ok'=>false,'message'=>'Proses rekap gagal dan dibatalkan. Periksa konfigurasi jadwal dan database.']); }
}
if ($path === '/attendance/results' && $method === 'GET') {
    requireUser();
    $batch=filter_var($_GET['batch_id']??null,FILTER_VALIDATE_INT);
    if(!$batch)jsonResponse(422,['ok'=>false,'message'=>'batch_id wajib dipilih.']);
    $st=database()->prepare('SELECT r.id,r.employee_id,e.pin,e.full_name,r.attendance_date,r.schedule_id,s.name AS schedule_name,r.first_scan_at,r.last_scan_at,r.scan_count,r.attendance_status,r.late_minutes,r.early_leave_minutes,r.source_scan_ids,r.calculation_detail FROM attendance_daily_results r JOIN employees e ON e.id=r.employee_id LEFT JOIN work_schedules s ON s.id=r.schedule_id WHERE r.batch_id=? ORDER BY r.attendance_date,e.full_name');
    $st->execute([$batch]);jsonResponse(200,['ok'=>true,'data'=>$st->fetchAll()]);
}
if (preg_match('#^/attendance/results/(\\d+)/scanlogs$#',$path,$detailMatch) && $method === 'GET') {
    requireUser();
    $resultId=(int)$detailMatch[1];
    $resultStmt=database()->prepare('SELECT source_scan_ids FROM attendance_daily_results WHERE id=?');
    $resultStmt->execute([$resultId]);
    $sourceIdsJson=$resultStmt->fetchColumn();
    if($sourceIdsJson===false)jsonResponse(404,['ok'=>false,'message'=>'Hasil kehadiran tidak ditemukan.']);
    $sourceIds=json_decode((string)$sourceIdsJson,true);
    if(!is_array($sourceIds)||!$sourceIds)jsonResponse(200,['ok'=>true,'data'=>[]]);
    $ids=array_values(array_unique(array_filter(array_map('intval',$sourceIds),static fn($id)=>$id>0)));
    if(!$ids)jsonResponse(200,['ok'=>true,'data'=>[]]);
    $marks=implode(',',array_fill(0,count($ids),'?'));
    $sql='SELECT r.id,r.pin,r.scanned_at,m.name AS machine_name,r.verify_method,r.device_event_code FROM raw_scanlogs r LEFT JOIN machines m ON m.id=r.machine_id WHERE r.id IN ('.$marks.') ORDER BY r.scanned_at,r.id';
    $st=database()->prepare($sql);$st->execute($ids);
    jsonResponse(200,['ok'=>true,'data'=>$st->fetchAll()]);
}
if ($path === '/attendance/batches' && $method === 'GET') {
    requireUser();
    $rows=database()->query('SELECT id,period_start,period_end,status,ruleset_version,started_at,finished_at,row_count,created_at FROM processing_batches ORDER BY id DESC LIMIT 100')->fetchAll();
    jsonResponse(200,['ok'=>true,'data'=>$rows]);
}

jsonResponse(404,['ok'=>false,'message'=>'Endpoint tidak ditemukan.']);
