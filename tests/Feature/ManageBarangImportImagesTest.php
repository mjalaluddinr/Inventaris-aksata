<?php

use App\Models\Barang;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

uses(RefreshDatabase::class);

test('it extracts and stores images matched by the spreadsheet filename', function () {
  Storage::fake('local');
  Storage::fake('public');

  $admin = User::query()->create([
    'username' => 'admin-import',
    'first_name' => 'Admin',
    'last_name' => 'Import',
    'password_hash' => 'password',
    'role' => 'admin',
    'is_active' => true,
  ]);

  $imageContents = 'image-file-content';
  $zipPath = tempnam(sys_get_temp_dir(), 'barang-images-');
  $zip = new ZipArchive;
  expect($zip->open($zipPath, ZipArchive::OVERWRITE))->toBeTrue();
  $zip->addFromString('pano.jpeg', $imageContents);
  $zip->addFromString('folder/tidak-dipakai.png', 'unused-image-content');
  $zip->close();

  try {
    $response = $this->actingAs($admin)->postJson(route('admin.barang.import'), [
      'file' => UploadedFile::fake()->createWithContent(
        'barang.csv',
        "kategori,kode_barang,nama_barang,satuan,stok_total,gambar\nPeralatan,BRG-001,Laptop,unit,1,PANO.JPEG\n",
      ),
      'images_zip' => new UploadedFile($zipPath, 'images.zip', 'application/zip', UPLOAD_ERR_OK, true),
    ]);
  } finally {
    @unlink($zipPath);
  }

  $response->assertOk()->assertJsonPath('success_rows', 1);

  $barang = Barang::query()->firstOrFail();
  expect($barang->image_path)->toStartWith('barang_img/');
  expect(Storage::disk('public')->exists($barang->image_path))->toBeTrue();
  expect(Storage::disk('public')->get($barang->image_path))->toBe($imageContents);
  expect(Storage::disk('public')->exists('barang_img/folder/tidak-dipakai.png'))->toBeFalse();
  expect(Storage::disk('local')->allFiles('temp/barang-import'))->toBe([]);
});
