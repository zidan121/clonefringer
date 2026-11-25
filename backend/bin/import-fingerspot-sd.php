<?php
declare(strict_types=1);

/**
 * Import a Fingerspot Personnel database already restored into a SEPARATE staging DB.
 * Default mode is read-only. Pass --apply only after reviewing the report.
 *
 * Required environment:
 * LEGACY_DB_HOST, LEGACY_DB_PORT, LEGACY_DB_NAME, LEGACY_DB_USER, LEGACY_DB_PASS
 * CLONEFRINGER_DB_HOST, CLONEFRINGER_DB_PORT, CLONEFRINGER_DB_NAME,
 * CLONEFRINGER_DB_USER, CLONEFRINGER_DB_PASS
 */
function envRequired(string $name): string {
    $value = getenv($name);
    if ($value === false || $value === '') throw new RuntimeException("Environment {$name} belum diatur.");
    return $value;
}
function connectDb(string $prefix): PDO {
    $host = getenv($prefix . '_DB_HOST') ?: '127.0.0.1';
    $port = getenv($prefix . '_DB_PORT') ?: '3306';
    $name = envRequired($prefix . '_DB_NAME');
    $user = envRequired($prefix . '_DB_USER');
    $pass = getenv($prefix . '_DB_PASS') ?: '';
    return new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}
function tableExists(PDO $pdo, string $table): bool {
    $q = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
    $q->execute([$table]); return (int)$q->fetchColumn() > 0;
}
function cleanDate(mixed $value): ?string {
    $v = trim((string)$value);
    return ($v === '' || str_starts_with($v, '0000-00-00')) ? null : $v;
}
function genderValue(mixed $value): ?string {
    return match ((int)$value) { 1 => 'male', 2 => 'female', default => null };
}
function report(array $data): void {
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

try {
    $apply = in_array('--apply', $argv, true);
    $legacy = connectDb('LEGACY');
    $target = connectDb('CLONEFRINGER');
    foreach (['pegawai','att_log','device','jam_kerja','jdw_kerja_m','jdw_kerja_d','jdw_kerja_pegawai'] as $table) {
        if (!tableExists($legacy, $table)) throw new RuntimeException("Tabel sumber wajib tidak ditemukan: {$table}");
    }
    foreach (['units','employees','machines','raw_scanlogs'] as $table) {
        if (!tableExists($target, $table)) throw new RuntimeException("Tabel tujuan wajib tidak ditemukan: {$table}");
    }
    $unitId = $target->query("SELECT id FROM units WHERE code='SDIT' LIMIT 1")->fetchColumn();
    if (!$unitId) throw new RuntimeException("Unit SDIT belum tersedia. Jalankan database/schema.sql terlebih dahulu.");

    $counts = [];
    foreach (['pegawai','att_log','device','jam_kerja','jdw_kerja_m','jdw_kerja_d','jdw_kerja_pegawai'] as $table) {
        $counts[$table] = (int)$legacy->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    }
    $placeholderScans = (int)$legacy->query("SELECT COUNT(*) FROM att_log WHERE DATE(scan_date)='2000-01-01' OR scan_date='0000-00-00 00:00:00'")->fetchColumn();
    $validScans = (int)$legacy->query("SELECT COUNT(*) FROM att_log WHERE scan_date >= '2001-01-01 00:00:00'")->fetchColumn();
    $unmatchedPins = $legacy->query("SELECT a.pin,COUNT(*) AS scan_count,MIN(a.scan_date) AS first_seen,MAX(a.scan_date) AS last_seen FROM att_log a LEFT JOIN pegawai p ON p.pegawai_pin=a.pin WHERE a.scan_date >= '2001-01-01 00:00:00' AND p.pegawai_id IS NULL GROUP BY a.pin ORDER BY a.pin")->fetchAll();
    $schedulePatternRows = $legacy->query("SELECT m.jdw_kerja_m_id,m.jdw_kerja_m_name,d.jdw_kerja_d_idx,d.jdw_kerja_d_hari,d.jdw_kerja_d_libur,j.jk_name,j.jk_bcin,j.jk_ecout,j.jk_tol_late FROM jdw_kerja_m m LEFT JOIN jdw_kerja_d d ON d.jdw_kerja_m_id=m.jdw_kerja_m_id LEFT JOIN jam_kerja j ON j.jk_id=d.jk_id ORDER BY m.jdw_kerja_m_id,d.jdw_kerja_d_idx")->fetchAll();
    $report = [
        'mode' => $apply ? 'APPLY' : 'DRY_RUN',
        'source_scope' => 'SD',
        'source_counts' => $counts,
        'scanlog_valid_date_count' => $validScans,
        'scanlog_placeholder_date_count' => $placeholderScans,
        'target_unit' => 'SDIT',
        'unmatched_source_pins' => $unmatchedPins,
        'schedule_pattern_details' => $schedulePatternRows,
        'notes' => [
            'PIN dipertahankan sebagai teks.',
            'Data pegawai dengan PIN yang sudah ada tidak ditimpa.',
            'Scan dengan tanggal placeholder 2000-01-01/zero-date tidak dimasukkan ke riwayat absensi.',
            'Kredensial aktivasi dan communication key mesin tidak dibaca atau dipindahkan.',
            'Jadwal lama hanya dianalisis; pola mingguan yang berbeda harus dipetakan sebelum dipakai untuk rekap.',
            'Impor scanlog menggunakan ID transaksi deterministik agar dapat dijalankan ulang tanpa menggandakan log.',
        ],
    ];
    if (!$apply) {
        $report['next_step'] = 'Periksa laporan ini. Jalankan ulang dengan --apply hanya setelah backup database tujuan dan persetujuan operator.';
        report($report);
        exit(0);
    }

    $target->beginTransaction();
    $employeeMap = [];
    $employeeRows = $legacy->query("SELECT pegawai_id,pegawai_pin,pegawai_nama,pegawai_nip,pegawai_telp,pegawai_status,tempat_lahir,tgl_lahir,tgl_mulai_kerja,tgl_resign,gender,photo_path FROM pegawai ORDER BY pegawai_id")->fetchAll();
    $findEmployee = $target->prepare('SELECT id FROM employees WHERE pin=? LIMIT 1');
    $insertEmployee = $target->prepare("INSERT INTO employees (pin,full_name,unit_id,employee_status,join_date,phone,gender,birth_date,notes) VALUES (?,?,?,?,?,?,?,?,?)");
    $employeeInserted = 0;
    foreach ($employeeRows as $row) {
        $pin = trim((string)$row['pegawai_pin']);
        if ($pin === '') continue;
        $findEmployee->execute([$pin]); $id = $findEmployee->fetchColumn();
        if (!$id) {
            $status = (int)$row['pegawai_status'] === 1 ? 'active' : ((int)$row['pegawai_status'] === 2 ? 'resigned' : 'inactive');
            $notes = trim((string)($row['tempat_lahir'] ?? ''));
            $insertEmployee->execute([$pin, (string)$row['pegawai_nama'], $unitId, $status, cleanDate($row['tgl_mulai_kerja']), $row['pegawai_telp'] ?: null, genderValue($row['gender']), cleanDate($row['tgl_lahir']), $notes !== '' ? 'Tempat lahir: '.$notes : null]);
            $id = (int)$target->lastInsertId(); $employeeInserted++;
        }
        $employeeMap[(int)$row['pegawai_id']] = (int)$id;
    }

    $machineMap = [];
    $deviceRows = $legacy->query("SELECT sn,device_name,dev_id,dev_type,ip_address,ethernet_port FROM device ORDER BY sn")->fetchAll();
    $findMachine = $target->prepare('SELECT id FROM machines WHERE serial_number=? LIMIT 1');
    $insertMachine = $target->prepare("INSERT INTO machines (name,model,serial_number,host,port,connection_type,status,location,notes) VALUES (?,?,?,?,?,'file','unknown','SD','Diimpor dari backup Fingerspot; koneksi langsung belum diuji.')");
    $machineInserted = 0;
    foreach ($deviceRows as $device) {
        $sn = trim((string)$device['sn']); if ($sn === '') continue;
        $findMachine->execute([$sn]); $id = $findMachine->fetchColumn();
        if (!$id) {
            $host = trim((string)$device['ip_address']) ?: 'legacy-file-import';
            $port = (int)($device['ethernet_port'] ?: 4370); if ($port < 1 || $port > 65535) $port = 4370;
            $model = 'Tipe perangkat lama '.$device['dev_type'];
            $insertMachine->execute([trim((string)$device['device_name']) ?: 'Mesin '.$device['dev_id'], $model, $sn, $host, $port]);
            $id = (int)$target->lastInsertId(); $machineInserted++;
        }
        $machineMap[$sn] = (int)$id;
    }

    $findPin = $target->prepare('SELECT id FROM employees WHERE pin=? LIMIT 1');
    $insertScan = $target->prepare("INSERT IGNORE INTO raw_scanlogs (machine_id,employee_id,pin,scanned_at,verify_method,device_event_code,device_transaction_id,source,raw_payload) VALUES (?,?,?,?,?,?,?,?,?)");
    $scanInserted = 0; $scanUnmatched = 0; $scanDuplicates = 0;
    $scanQuery = $legacy->query("SELECT sn,scan_date,pin,verifymode,inoutmode,reserved,work_code,att_id FROM att_log WHERE scan_date >= '2001-01-01 00:00:00' AND DATE(scan_date)<>'2000-01-01' ORDER BY scan_date,sn,pin");
    while ($scan = $scanQuery->fetch()) {
        $pin = trim((string)$scan['pin']);
        $findPin->execute([$pin]); $employeeId = $findPin->fetchColumn();
        $machineId = $machineMap[(string)$scan['sn']] ?? null;
        $transactionId = hash('sha256', (string)$scan['sn'].'|'.(string)$scan['scan_date'].'|'.$pin);
        $payload = json_encode($scan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $insertScan->execute([$machineId, $employeeId ?: null, $pin, $scan['scan_date'], (string)$scan['verifymode'], (string)$scan['inoutmode'], $transactionId, 'file_import', $payload]);
        if ($insertScan->rowCount() > 0) { $scanInserted++; if (!$employeeId) $scanUnmatched++; }
        else $scanDuplicates++;
    }
    $target->commit();
    $report['result'] = [
        'employees_inserted' => $employeeInserted,
        'employees_matched_or_existing' => count($employeeMap),
        'machines_inserted' => $machineInserted,
        'scanlogs_inserted' => $scanInserted,
        'scanlogs_unmatched_pin' => $scanUnmatched,
        'scanlogs_already_present' => $scanDuplicates,
        'schedule_patterns_found' => $counts['jdw_kerja_m'],
        'schedule_assignments_found' => $counts['jdw_kerja_pegawai'],
        'schedule_import' => 'Belum dijalankan otomatis; perlu pemetaan pola per hari yang aman.',
    ];
    $report['status'] = 'committed';
    report($report);
} catch (Throwable $e) {
    if (isset($target) && $target instanceof PDO && $target->inTransaction()) $target->rollBack();
    fwrite(STDERR, 'IMPORT ERROR: '.$e->getMessage().PHP_EOL);
    exit(1);
}
