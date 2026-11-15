<?php

use App\Http\Controllers\Admin\ManageBarang;
use App\Models\Barang;
use App\Models\Lemari;
use App\Models\LogInput;
use App\Models\Ruangan;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(LazilyRefreshDatabase::class);

it('imports into an existing cabinet without room or cabinet code columns', function () {
    Storage::fake('local');
    $admin = User::query()->create([
        'username' => 'admin-import-existing-cabinet',
        'first_name' => 'Admin',
        'last_name' => 'Import Lemari',
        'password_hash' => 'test-password',
        'role' => 'admin',
        'is_active' => true,
    ]);
    $room = Ruangan::query()->create([
        'kode_ruangan' => 'ROOM-EXISTING',
        'nama_ruangan' => 'Ruang Existing',
    ]);
    $cabinet = Lemari::query()->create([
        'ruangan_id' => $room->ruangan_id,
        'kode_lemari' => 'CAB-EXISTING',
        'nama_lemari' => 'Lemari Existing',
    ]);
    $log = LogInput::query()->create([
        'jenis_input' => 'barang',
        'nama_file' => 'existing-cabinet.csv',
        'total_baris' => 0,
        'baris_berhasil' => 0,
        'baris_gagal' => 0,
        'status' => 'proses',
        'mulai_at' => now(),
    ]);
    $filePath = 'temp/barang-import/existing-cabinet.csv';
    Storage::disk('local')->put($filePath, implode("\n", [
        'Kategori,Nama Barang,Satuan,Jumlah Total,Kondisi,Nama Lemari',
        'Peralatan,Kunci Existing,PCS,4,Baru,Lemari Existing',
    ]));

    app(ManageBarang::class)->processImport(
        $log->log_input_id,
        $filePath,
        null,
        $admin->akun_id,
    );

    $log->refresh();
    $barang = Barang::query()->where('nama_barang', 'Kunci Existing')->firstOrFail();

    expect($log->status)->toBe('selesai')
        ->and($log->baris_berhasil)->toBe(1)
        ->and($log->baris_gagal)->toBe(0);
    $this->assertDatabaseHas('lokasi_barang', [
        'barang_id' => $barang->barang_id,
        'ruangan_id' => $room->ruangan_id,
        'lemari_id' => $cabinet->lemari_id,
    ]);
});

it('warns which required columns are missing from the import header', function () {
    Storage::fake('local');
    $admin = User::query()->create([
        'username' => 'admin-import-missing-header',
        'first_name' => 'Admin',
        'last_name' => 'Import Header',
        'password_hash' => 'test-password',
        'role' => 'admin',
        'is_active' => true,
    ]);
    $log = LogInput::query()->create([
        'jenis_input' => 'barang',
        'nama_file' => 'missing-name.csv',
        'total_baris' => 0,
        'baris_berhasil' => 0,
        'baris_gagal' => 0,
        'status' => 'proses',
        'mulai_at' => now(),
    ]);
    $filePath = 'temp/barang-import/missing-name.csv';
    Storage::disk('local')->put($filePath, implode("\n", [
        'Kategori,Satuan,Jumlah Total,Kondisi,Ruangan',
        'Peralatan,PCS,4,Baru,Ruang A',
    ]));

    app(ManageBarang::class)->processImport(
        $log->log_input_id,
        $filePath,
        null,
        $admin->akun_id,
    );

    $log->refresh();

    expect($log->status)->toBe('gagal')
        ->and($log->detail_error[1])->toContain('Nama Barang')
        ->and($log->detail_error[1])->toContain('Kolom wajib belum tersedia');
    $this->assertDatabaseCount('barang', 0);

    $response = $this->actingAs($admin)
        ->getJson(route('admin.barang.import.status', $log))
        ->assertOk();

    expect($response->json('message'))->toContain('Nama Barang');
});

it('requires an explicit cabinet code when the imported cabinet is new', function () {
    Storage::fake('local');
    $admin = User::query()->create([
        'username' => 'admin-import-new-cabinet',
        'first_name' => 'Admin',
        'last_name' => 'Import Baru',
        'password_hash' => 'test-password',
        'role' => 'admin',
        'is_active' => true,
    ]);
    $room = Ruangan::query()->create([
        'kode_ruangan' => 'ROOM-NEW-CAB',
        'nama_ruangan' => 'Ruang Lemari Baru',
    ]);
    $log = LogInput::query()->create([
        'jenis_input' => 'barang',
        'nama_file' => 'new-cabinet.csv',
        'total_baris' => 0,
        'baris_berhasil' => 0,
        'baris_gagal' => 0,
        'status' => 'proses',
        'mulai_at' => now(),
    ]);
    $filePath = 'temp/barang-import/new-cabinet.csv';
    Storage::disk('local')->put($filePath, implode("\n", [
        'Kategori,Nama Barang,Satuan,Jumlah Total,Kondisi,Ruangan,Nama Lemari',
        'Peralatan,Kunci Baru,PCS,4,Baru,Ruang Lemari Baru,Lemari Baru',
    ]));

    app(ManageBarang::class)->processImport(
        $log->log_input_id,
        $filePath,
        null,
        $admin->akun_id,
    );

    $log->refresh();

    expect($log->status)->toBe('gagal')
        ->and($log->detail_error[2][0]['message'])->toContain('Kode Lemari wajib diisi');
    $this->assertDatabaseMissing('lemari', [
        'ruangan_id' => $room->ruangan_id,
        'nama_lemari' => 'Lemari Baru',
    ]);
    $this->assertDatabaseMissing('barang', ['nama_barang' => 'Kunci Baru']);

    $response = $this->actingAs($admin)
        ->getJson(route('admin.barang.import.status', $log))
        ->assertOk();

    expect($response->json('message'))->toContain('Kode Lemari wajib diisi');
});

it('creates a new imported cabinet with its supplied code', function () {
    Storage::fake('local');
    $admin = User::query()->create([
        'username' => 'admin-import-coded-cabinet',
        'first_name' => 'Admin',
        'last_name' => 'Import Kode',
        'password_hash' => 'test-password',
        'role' => 'admin',
        'is_active' => true,
    ]);
    $room = Ruangan::query()->create([
        'kode_ruangan' => 'ROOM-CODED',
        'nama_ruangan' => 'Ruang Kode',
    ]);
    $log = LogInput::query()->create([
        'jenis_input' => 'barang',
        'nama_file' => 'coded-cabinet.csv',
        'total_baris' => 0,
        'baris_berhasil' => 0,
        'baris_gagal' => 0,
        'status' => 'proses',
        'mulai_at' => now(),
    ]);
    $filePath = 'temp/barang-import/coded-cabinet.csv';
    Storage::disk('local')->put($filePath, implode("\n", [
        'Kategori,Nama Barang,Satuan,Jumlah Total,Kondisi,Ruangan,Nama Lemari,Kode Lemari',
        'Peralatan,Kunci Berkode,PCS,4,Baru,Ruang Kode,Lemari Berkode,CAB-MANUAL',
    ]));

    app(ManageBarang::class)->processImport(
        $log->log_input_id,
        $filePath,
        null,
        $admin->akun_id,
    );

    $log->refresh();

    expect($log->status)->toBe('selesai')
        ->and($log->baris_berhasil)->toBe(1);
    $this->assertDatabaseHas('lemari', [
        'ruangan_id' => $room->ruangan_id,
        'kode_lemari' => 'CAB-MANUAL',
        'nama_lemari' => 'Lemari Berkode',
    ]);
});

it('explains when an imported cabinet code belongs to a different cabinet name', function () {
    Storage::fake('local');
    $admin = User::query()->create([
        'username' => 'admin-import-cabinet-code-conflict',
        'first_name' => 'Admin',
        'last_name' => 'Import Lemari',
        'password_hash' => 'test-password',
        'role' => 'admin',
        'is_active' => true,
    ]);
    $room = Ruangan::query()->create([
        'kode_ruangan' => 'LAB-RPL-B',
        'nama_ruangan' => 'Lab RPL B',
    ]);
    Lemari::query()->create([
        'ruangan_id' => $room->ruangan_id,
        'kode_lemari' => 'B2',
        'nama_lemari' => 'Lemari 4',
    ]);
    $log = LogInput::query()->create([
        'jenis_input' => 'barang',
        'nama_file' => 'cabinet-code-conflict.csv',
        'total_baris' => 0,
        'baris_berhasil' => 0,
        'baris_gagal' => 0,
        'status' => 'proses',
        'mulai_at' => now(),
    ]);
    $filePath = 'temp/barang-import/cabinet-code-conflict.csv';
    Storage::disk('local')->put($filePath, implode("\n", [
        'Kategori,Nama Barang,Satuan,Jumlah Total,Kondisi,Kode Ruangan,Ruangan,Kode Lemari,Lemari',
        'Jaringan,Router,Unit,3,Baru,LAB-RPL-B,Lab RPL B,B2,Lemari 6',
    ]));

    app(ManageBarang::class)->processImport(
        $log->log_input_id,
        $filePath,
        null,
        $admin->akun_id,
    );

    $log->refresh();
    expect($log->detail_error[2][0]['message'])
        ->toContain('Kode lemari tidak sesuai dengan nama lemari');
    $this->assertDatabaseMissing('barang', ['nama_barang' => 'Router']);

    $this->actingAs($admin)
        ->getJson(route('admin.barang.import.status', $log))
        ->assertOk()
        ->assertJsonPath('failed_rows', 1)
        ->assertJsonPath(
            'message',
            'Import gagal diproses. Periksa format file, kolom wajib, dan arsip ZIP. Data ke-1 (baris Excel ke-2): Kode lemari tidak sesuai dengan nama lemari pada ruangan ini.',
        );
});

it('imports a PNG image whose ZIP filename ends in jpg', function () {
    Storage::fake('local');
    Storage::fake('supabase');
    $admin = User::query()->create([
        'username' => 'admin-import-image-mime',
        'first_name' => 'Admin',
        'last_name' => 'Import Image',
        'password_hash' => 'test-password',
        'role' => 'admin',
        'is_active' => true,
    ]);
    $room = Ruangan::query()->create([
        'kode_ruangan' => 'ROOM-IMAGE-MIME',
        'nama_ruangan' => 'Ruang Gambar',
    ]);
    $log = LogInput::query()->create([
        'jenis_input' => 'barang',
        'nama_file' => 'image-import.csv',
        'total_baris' => 0,
        'baris_berhasil' => 0,
        'baris_gagal' => 0,
        'status' => 'proses',
        'mulai_at' => now(),
    ]);
    $filePath = 'temp/barang-import/image-import.csv';
    $imagesZipPath = 'temp/barang-import/image-import.zip';
    Storage::disk('local')->put($filePath, implode("\n", [
        'Kategori,Nama Barang,Satuan,Jumlah Total,Kondisi,Ruangan,Gambar Barang',
        'Peralatan,Barang Bergambar,PCS,1,Baru,Ruang Gambar,gambar.jpg',
    ]));

    $temporaryZipPath = tempnam(sys_get_temp_dir(), 'barang-import-');
    try {
        $zip = new ZipArchive;
        expect($zip->open($temporaryZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();
        $imageBytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+nmC8AAAAASUVORK5CYII=',
            true,
        );
        expect($imageBytes)->toBeString();
        expect($zip->addFromString('gambar.jpg', $imageBytes))->toBeTrue();
        expect($zip->close())->toBeTrue();
        Storage::disk('local')->put($imagesZipPath, file_get_contents($temporaryZipPath));
    } finally {
        if (is_file($temporaryZipPath)) {
            unlink($temporaryZipPath);
        }
    }

    app(ManageBarang::class)->processImport(
        $log->log_input_id,
        $filePath,
        $imagesZipPath,
        $admin->akun_id,
    );

    $log->refresh();

    expect($log->status)->toBe('selesai')
        ->and($log->baris_berhasil)->toBe(1);

    $barang = Barang::query()->where('nama_barang', 'Barang Bergambar')->firstOrFail();

    expect($barang->image_path)->toEndWith('.png');
    Storage::disk('supabase')->assertExists(substr($barang->image_path, strlen(Barang::SUPABASE_IMAGE_PATH_PREFIX)));
});
