export const getTrackingStatusReasonLabel = (reason, status = null) => {
  switch (reason) {
    case 'tracking_terbaru':
      return 'Snapshot terbaru masih valid dan berada di dalam area sekolah.';
    case 'di_luar_area':
      return 'Snapshot terbaru masih valid, tetapi berada di luar area sekolah.';
    case 'tracking_dinonaktifkan_admin':
      return 'Tracking realtime sedang dinonaktifkan oleh admin. Dashboard menampilkan lokasi terakhir yang diketahui.';
    case 'di_luar_jadwal_tracking':
      return 'Tracking realtime sedang dijeda karena saat ini berada di luar hari atau jam sekolah.';
    case 'data_kedaluwarsa':
      return 'Snapshot terakhir melewati ambang realtime dan perlu pembaruan.';
    case 'gps_nonaktif':
      return 'GPS perangkat tidak aktif. Dashboard menampilkan lokasi terakhir yang diketahui.';
    case 'belum_ada_data_hari_ini':
      return 'Belum ada snapshot tracking yang masuk pada hari ini.';
    case 'pemantauan_tambahan_aktif':
      return 'Pemantauan tambahan aktif, tetapi snapshot terbaru belum masuk.';
    default:
      break;
  }

  switch (status) {
    case 'active':
      return 'Snapshot terbaru masih valid dan berada di dalam area sekolah.';
    case 'outside_area':
      return 'Snapshot terbaru masih valid, tetapi berada di luar area sekolah.';
    case 'tracking_disabled':
      return 'Tracking realtime sedang dinonaktifkan oleh admin. Dashboard menampilkan lokasi terakhir yang diketahui.';
    case 'outside_schedule':
      return 'Tracking realtime sedang dijeda karena saat ini berada di luar hari atau jam sekolah.';
    case 'stale':
      return 'Snapshot terakhir melewati ambang realtime dan perlu pembaruan.';
    case 'gps_disabled':
      return 'GPS perangkat tidak aktif. Dashboard menampilkan lokasi terakhir yang diketahui.';
    case 'no_data':
      return 'Belum ada snapshot tracking yang masuk pada hari ini.';
    default:
      return 'Status tracking belum memiliki keterangan tambahan.';
  }
};
