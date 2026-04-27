<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$tokenName = 'load-test-admin-' . now()->format('YmdHis');
$outputPath = $argv[1] ?? '';

$candidateUsers = User::query()->orderBy('id')->limit(500)->get();
$adminUser = $candidateUsers->first(static function (User $user): bool {
    try {
        return $user->hasPermissionTo('manage_attendance_settings');
    } catch (\Throwable $e) {
        return false;
    }
});

if (!$adminUser) {
    fwrite(STDERR, "[ERROR] Tidak ditemukan user dengan permission manage_attendance_settings.\n");
    exit(1);
}

$adminUser->tokens()->where('name', 'like', 'load-test-admin-%')->delete();
$token = $adminUser->createToken($tokenName)->plainTextToken;

$payload = [
    'user_id' => $adminUser->id,
    'nama' => $adminUser->nama_lengkap ?: $adminUser->name,
    'token' => $token,
];

if ($outputPath !== '') {
    file_put_contents(
        $outputPath,
        json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
    echo "[OK] Admin token saved: {$outputPath}\n";
}

echo "[OK] Admin user: {$payload['nama']} (ID {$payload['user_id']})\n";
echo "[OK] Token: {$payload['token']}\n";

