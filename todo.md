TODO LIST

### JANGAN SENTUH2 DATABASE NYA JANGAN UBAH SELAIN YANG ADA DI TODO LIST INI

1. [x] perbaiki bug ini
Stok yang sedang dipinjam/dipakai tetap di lokasi ini.

padahal sudah tidak ada barang yang di pakai atau di peminjaman tapi lokasi(ruangan dan lemari) sama wadah nya tidak bisa di ganti

sudah tidak ada
mysql> select * from barang_dipakai; select * from peminjaman; select * from detail_peminjaman;
+--------------------------------------+--------+------------+---------+---------------------+---------------------+---------------------+---------------------+--------------------------------------+--------------------------------------+--------------------------------------+
| barang_dipakai_id                    | jumlah | keterangan | status  | tanggal_pasang      | tanggal_tarik       | created_at          | updated_at          | barang_id                            | ruangan_id                           | akun_id                              |
+--------------------------------------+--------+------------+---------+---------------------+---------------------+---------------------+---------------------+--------------------------------------+--------------------------------------+--------------------------------------+
| 01a19b96-8312-70b3-84dd-97255c86bbc8 |      9 | NULL       | ditarik | 2026-11-02 00:00:00 | 2026-11-02 09:28:20 | 2026-11-02 09:28:14 | 2026-11-02 09:28:20 | 01a19b86-1666-70a1-9a79-be8f5f0fe568 | 01a18b79-bee0-72a8-aa63-fcc62f2b7336 | 01a18b55-493a-7395-9844-8fd93d862172 |
| 01a1aac6-f098-7297-863f-a51141789b01 |      1 | NULL       | ditarik | 2026-11-05 00:00:00 | 2026-11-05 08:16:10 | 2026-11-05 08:15:26 | 2026-11-05 08:16:10 | 01a19bc2-045d-7248-b980-d2a1219fa90c | 01a18b79-bee0-72a8-aa63-fcc62f2b7336 | 01a18b55-493a-7395-9844-8fd93d862172 |
+--------------------------------------+--------+------------+---------+---------------------+---------------------+---------------------+---------------------+--------------------------------------+--------------------------------------+--------------------------------------+
2 rows in set (0.00 sec)

Empty set (0.00 sec)

Empty set (0.00 sec)

barang yang dipakai sudah di tarik jadi harus nya bisa di ganti

2. [x] yang di form untuk stok perlokasinya inputannya buat agar menyesuaikan dengan ukuran teks yang di pilih jadi tidak tetap panjangnya sama saja walaupun teks yang di pilih lebih pendek inputan nya tetap panjang jagnan gitu,buat agar sesuai jadi saat yang di pilih panjang ikut memanjang jika pendek ikut memendek khusu yang di form bagian stok perlokasi saya

3. [x] arrow di sidebar << yang saat sidebar besar buat agar warna nya putih tapi arrow nya << hijau dan berikan hover untuk background nya yang keren,buat agar tidak menutupi tulisan InvenTrack
Sistem Inventaris Jurusan itu

4. [x] sembunyikan scroll bar di table data log mutasi

5. [x] perbaiki ajaxz di halaman barang untuk catat pemakaain barang itu masih ada yang bug dan 10 saja barang nya itu ada 11 dan yang ke 1 tidak ganti tapi yang 2-11 bisa berganti sesuai dengna table datanya

6. [x] di button halaman barang untuk tambah barang,tambah wadah,Simpan import,simpan perubahan,dan hapus barang nya itu samakan dengna yang di halaman akun ada animasi muter2 nya saat di click agar button nya tidak jelek.dan untuk button hapus di semua halaman admin buat agar button hapus nya di kanan modal pop up nya bukan di tengah

7. [x] animasi toast nya berikan waktu untuk hilang nya gitu biar tidak polos dan notif nya jangan hilang sebelum watku nya habis. buat animasi toast nya seperti yang saya kirimkan tampilan nya untuk yang gagal untuk yang berhasil kamu seusaikan tinggal kamu ubah jadi warna hijau

ITU DULU SAJA DEH NANTI KALAU ADA YANG BELUM BENAR SAYA UPDATE LAGI
