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

/* Calendar events */
if ($path === '/calendar' && $method === 'GET') {
    requireUser();
    $st=database()->query('SELECT c.id,c.name,c.event_type,c.unit_id,u.code AS unit_code,c.start_date,c.end_date,c.impact,c.notes FROM calendar_events c LEFT JOIN units u ON u.id=c.unit_id ORDER BY c.start_date DESC LIMIT 1000');
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
    try{$pdo=database();$st=$pdo->prepare('INSERT INTO employee_schedule_assignments (employee_id,schedule_id,effective_from,effective_to,custom_start,custom_end,custom_tolerance) VALUES (?,?,?,?,?,?,?)');$st->execute([$employee,$schedule,$from,$to?:null,$b['custom_start']??null,$b['custom_end']??null,($b['custom_tolerance']??'')===''?null:($b['custom_tolerance']??null)]);$id=(int)$pdo->lastInsertId();audit($pdo,(int)$user['id'],'create','schedule_assignment',$id,null,$b);jsonResponse(201,['ok'=>true,'id'=>$id,'message'=>'Jadwal pegawai berhasil ditetapkan.']);}
    catch(PDOException $e){if($e->getCode()==='23000')jsonResponse(422,['ok'=>false,'message'=>'Pegawai atau master jadwal tidak ditemukan.']);error_log('[assignment create] '.$e->getMessage());jsonResponse(500,['ok'=>false,'message'=>'Gagal menetapkan jadwal.']);}
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
    try{$pdo=database();$st=$pdo->prepare('INSERT INTO unit_schedule_defaults (unit_id,schedule_id,effective_from,effective_to) VALUES (?,?,?,?)');$st->execute([$unit,$schedule,$from,$to?:null]);$id=(int)$pdo->lastInsertId();audit($pdo,(int)$user['id'],'create','unit_schedule_default',$id,null,$b);jsonResponse(201,['ok'=>true,'id'=>$id,'message'=>'Default jadwal unit tersimpan.']);}
    catch(PDOException $e){if($e->getCode()==='23000')jsonResponse(422,['ok'=>false,'message'=>'Unit atau master jadwal tidak ditemukan.']);error_log('[unit default create] '.$e->getMessage());jsonResponse(500,['ok'=>false,'message'=>'Gagal menyimpan default jadwal.']);}
}

jsonResponse(404,['ok'=>false,'message'=>'Endpoint tidak ditemukan.']);
