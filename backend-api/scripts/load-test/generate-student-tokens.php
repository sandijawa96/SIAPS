<?php

declare(strict_types=1);

use App\Models\LokasiGps;
use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Contracts\Console\Kernel;

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$countArg = $argv[1] ?? '100';
$outputArg = $argv[2] ?? (__DIR__ . '/tokens.generated.json');
$accuracyArg = $argv[3] ?? '10';

$count = max(1, (int) $countArg);
$accuracy = max(1, (int) $accuracyArg);
$outputPath = $outputArg;

$location = LokasiGps::query()->where('is_active', true)->first();
if (!$location) {
    fwrite(STDERR, "[ERROR] Tidak ada lokasi GPS aktif. Aktifkan minimal 1 lokasi dulu.\n");
    exit(1);
}

$aliases = RoleNames::aliases(RoleNames::SISWA);
$students = User::query()
    ->whereHas('roles', static function ($query) use ($aliases): void {
        $query->whereIn('name', $aliases);
    })
    ->orderBy('id')
    ->limit($count)
    ->get();

if ($students->isEmpty()) {
    fwrite(STDERR, "[ERROR] Tidak ada user siswa dengan role aktif.\n");
    exit(1);
}

$now = now()->format('YmdHis');
$tokenNamePrefix = "load-test-{$now}";
$rows = [];

foreach ($students as $index => $student) {
    // Bersihkan token load-test lama user ini agar tabel token tidak bengkak.
    $student->tokens()->where('name', 'like', 'load-test-%')->delete();

    $token = $student->createToken($tokenNamePrefix . '-' . ($index + 1))->plainTextToken;

    $rows[] = [
        'id' => $student->id,
        'nama' => $student->nama_lengkap ?: $student->name,
        'token' => $token,
        'latitude' => (float) $location->latitude,
        'longitude' => (float) $location->longitude,
        'accuracy' => $accuracy,
        'lokasi_id' => (int) $location->id,
    ];
}

file_put_contents(
    $outputPath,
    json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);

echo "[OK] Generated " . count($rows) . " student tokens\n";
echo "[OK] Output: {$outputPath}\n";
echo "[OK] Lokasi aktif: {$location->nama_lokasi} (ID {$location->id}) radius {$location->radius}m\n";
echo "[OK] Accuracy default: {$accuracy}m\n";

