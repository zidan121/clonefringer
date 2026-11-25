<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found\n");
}

try {
    $pdo=database();
    $count=(int)$pdo->query('SELECT COUNT(*) FROM app_users')->fetchColumn();
    if ($count>0) {
        fwrite(STDERR,"Admin awal sudah pernah dibuat. Gunakan pengelolaan pengguna setelah login.\n");
        exit(1);
    }
    $username=trim((string)readline('Username admin: '));
    $display=trim((string)readline('Nama admin: '));
    $password=(string)readline('Password admin (minimal 12 karakter): ');
    if ($username==='' || $display==='' || strlen($password)<12) {
        fwrite(STDERR,"Input tidak valid. Password minimal 12 karakter.\n");
        exit(1);
    }
    $hash=password_hash($password,PASSWORD_DEFAULT);
    $st=$pdo->prepare("INSERT INTO app_users (username,password_hash,display_name,role,is_active) VALUES (?,?,?,'admin',1)");
    $st->execute([$username,$hash,$display]);
    fwrite(STDOUT,"Admin berhasil dibuat. Simpan kredensial dengan aman dan hapus akses shell sementara jika tidak diperlukan.\n");
} catch(Throwable $e) {
    fwrite(STDERR,"Gagal membuat admin: ".$e->getMessage()."\n");
    exit(1);
}
