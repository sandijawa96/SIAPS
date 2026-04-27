import 'package:flutter/material.dart';
import '../utils/constants.dart';

class MobileDesignMockScreen extends StatelessWidget {
  const MobileDesignMockScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 5,
      child: Scaffold(
        backgroundColor: const Color(0xFFF3F7FF),
        appBar: AppBar(
          title: const Text('Mobile Design Mock'),
          backgroundColor: AppColors.primary,
          foregroundColor: Colors.white,
          bottom: const TabBar(
            isScrollable: true,
            tabs: [
              Tab(text: 'Home Siswa'),
              Tab(text: 'Apps Siswa'),
              Tab(text: 'Home Staff'),
              Tab(text: 'Profil'),
              Tab(text: 'Settings Siswa'),
            ],
          ),
        ),
        body: const TabBarView(
          children: [
            _StudentHomeMock(),
            _StudentAppsMock(),
            _StaffHomeMock(),
            _ProfileMock(),
            _SettingsMock(),
          ],
        ),
        floatingActionButtonLocation: FloatingActionButtonLocation.centerFloat,
        floatingActionButton: FloatingActionButton.extended(
          onPressed: () {},
          backgroundColor: AppColors.primary,
          foregroundColor: Colors.white,
          icon: const Icon(Icons.post_add_outlined),
          label: const Text('Izin'),
        ),
      ),
    );
  }
}

class _StudentHomeMock extends StatelessWidget {
  const _StudentHomeMock();

  @override
  Widget build(BuildContext context) {
    return _MockPhoneCanvas(
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 110),
        children: const [
          _HeroCard(
            name: 'Abdul Alim',
            subtitle: 'Siswa - XI IPA 2',
            identifier: '232410289',
            icon: Icons.school,
          ),
          SizedBox(height: 12),
          _SectionCard(
            title: 'Ringkasan Operasional',
            icon: Icons.insights_outlined,
            children: [
              Wrap(
                spacing: 10,
                runSpacing: 10,
                children: [
                  _StatusTile(
                    icon: Icons.cloud_done_outlined,
                    label: 'Sinkron Server',
                    value: '07:10:12 WIB',
                    tone: Color(0xFFF2F6FF),
                  ),
                  _StatusTile(
                    icon: Icons.verified_user_outlined,
                    label: 'Schema Aktif',
                    value: 'Sekolah Pagi',
                    tone: Color(0xFFEAF4FF),
                  ),
                  _StatusTile(
                    icon: Icons.my_location_outlined,
                    label: 'Lokasi Saat Ini',
                    value: 'Dalam radius',
                    tone: Color(0xFFEAFBF1),
                  ),
                  _StatusTile(
                    icon: Icons.schedule_outlined,
                    label: 'Jam Efektif',
                    value: '07:00 - 15:00',
                    tone: Color(0xFFFFF6E7),
                  ),
                ],
              ),
            ],
          ),
          SizedBox(height: 12),
          _SectionCard(
            title: 'Absensi Hari Ini',
            icon: Icons.fact_check_outlined,
            children: [
              _InfoRow(label: 'Check-in', value: '06:58 WIB'),
              _InfoRow(label: 'Check-out', value: '--'),
              _InfoRow(label: 'Status', value: 'Siap absen pulang'),
              SizedBox(height: 10),
              _PrimaryActionRow(
                primaryLabel: 'Absen Pulang',
                secondaryLabel: 'Riwayat',
              ),
            ],
          ),
          SizedBox(height: 12),
          _SectionCard(
            title: 'Jadwal Hari Ini',
            icon: Icons.schedule_outlined,
            children: [
              _ScheduleTile(
                time: '07:15 - 08:00',
                title: 'Matematika',
                meta: 'Guru: Ibu Siti',
              ),
              _ScheduleTile(
                time: '08:15 - 09:00',
                title: 'Bahasa Indonesia',
                meta: 'Guru: Bapak Andi',
              ),
              SizedBox(height: 6),
              Align(
                alignment: Alignment.centerRight,
                child: Text(
                  'Lihat Semua',
                  style: TextStyle(
                    fontSize: 12,
                    color: AppColors.primary,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ],
          ),
          SizedBox(height: 12),
          _SectionCard(
            title: 'Statistik Kehadiran',
            icon: Icons.bar_chart_rounded,
            children: [
              _StatsPeriodCard(
                title: 'Bulan Berjalan',
                primaryMetrics: [
                  _StatsMetricData('Masuk', '15', 'hari', Color(0xFFEAFBF1)),
                  _StatsMetricData('Izin', '1', 'hari', Color(0xFFEAF4FF)),
                  _StatsMetricData('Alpa', '0', 'hari', Color(0xFFFFEDEE)),
                  _StatsMetricData('Cuti', '0', 'hari', Color(0xFFF4EEFF)),
                ],
                secondaryMetrics: [
                  _InlineMetricData('Terlambat', '14 m'),
                  _InlineMetricData('TAP', '0 m'),
                  _InlineMetricData('Total TK', '0 m'),
                ],
              ),
              SizedBox(height: 12),
              _StatsPeriodCard(
                title: 'Bulan Sebelumnya',
                primaryMetrics: [
                  _StatsMetricData('Masuk', '18', 'hari', Color(0xFFEAFBF1)),
                  _StatsMetricData('Izin', '0', 'hari', Color(0xFFEAF4FF)),
                  _StatsMetricData('Alpa', '1', 'hari', Color(0xFFFFEDEE)),
                  _StatsMetricData('Cuti', '0', 'hari', Color(0xFFF4EEFF)),
                ],
                secondaryMetrics: [
                  _InlineMetricData('Terlambat', '22 m'),
                  _InlineMetricData('TAP', '5 m'),
                  _InlineMetricData('Total TK', '45 m'),
                ],
              ),
              SizedBox(height: 8),
              Text(
                'Pola lama yang dipertahankan hanya hero identitas, panel absensi hari ini, dan jadwal hari ini.',
                style: TextStyle(
                  fontSize: 12,
                  height: 1.4,
                  color: Colors.black54,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _StudentAppsMock extends StatelessWidget {
  const _StudentAppsMock();

  @override
  Widget build(BuildContext context) {
    return _MockPhoneCanvas(
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 110),
        children: const [
          _LauncherSection(
            title: 'Absensi',
            icon: Icons.access_time_filled_outlined,
            items: [
              _LauncherItem('Riwayat Absensi', Icons.history),
              _LauncherItem('Rekap Bulanan', Icons.calendar_month_outlined),
              _LauncherItem('Kalender Kehadiran', Icons.event_note_outlined),
            ],
          ),
          SizedBox(height: 12),
          _LauncherSection(
            title: 'Akademik',
            icon: Icons.menu_book_outlined,
            items: [
              _LauncherItem('Jadwal Pelajaran', Icons.schedule_outlined),
            ],
          ),
          SizedBox(height: 12),
          _LauncherSection(
            title: 'Administrasi',
            icon: Icons.assignment_outlined,
            items: [
              _LauncherItem('Ajukan Izin', Icons.post_add_outlined),
              _LauncherItem('Riwayat Izin', Icons.inventory_2_outlined),
              _LauncherItem('Notifikasi', Icons.notifications_outlined),
            ],
          ),
          SizedBox(height: 12),
          _LauncherSection(
            title: 'Akun',
            icon: Icons.person_outline,
            items: [
              _LauncherItem('Data Pribadi', Icons.badge_outlined),
              _LauncherItem('Ubah Foto Profil', Icons.photo_camera_outlined),
              _LauncherItem('Ubah Password', Icons.lock_outline),
            ],
          ),
        ],
      ),
    );
  }
}

class _StaffHomeMock extends StatelessWidget {
  const _StaffHomeMock();

  @override
  Widget build(BuildContext context) {
    return _MockPhoneCanvas(
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 110),
        children: const [
          _HeroCard(
            name: 'Siti Nurjanah',
            subtitle: 'Wali Kelas - Guru',
            identifier: '19781212xxxx',
            icon: Icons.work_outline,
          ),
          SizedBox(height: 12),
          _SectionCard(
            title: 'Notice',
            icon: Icons.info_outline,
            children: [
              Text(
                'Absensi pegawai menggunakan aplikasi JSA. Mobile app ini fokus untuk jadwal, izin, notifikasi, dan approval sesuai hak akses.',
              ),
            ],
          ),
          SizedBox(height: 12),
          _SectionCard(
            title: 'Jadwal Hari Ini',
            icon: Icons.schedule_outlined,
            children: [
              _ScheduleTile(
                time: '07:15 - 08:00',
                title: 'XI IPA 2 - Matematika',
                meta: 'Ruang 12',
              ),
            ],
          ),
          SizedBox(height: 12),
          _SectionCard(
            title: 'Approval Menunggu',
            icon: Icons.approval_outlined,
            children: [
              _InfoRow(label: 'Pengajuan izin pending', value: '3'),
              SizedBox(height: 10),
              _PrimaryActionRow(
                primaryLabel: 'Buka Approval',
                secondaryLabel: 'Izin Saya',
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _ProfileMock extends StatelessWidget {
  const _ProfileMock();

  @override
  Widget build(BuildContext context) {
    return _MockPhoneCanvas(
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 110),
        children: const [
          _SectionCard(
            title: 'Profil Saya',
            icon: Icons.person_outline,
            children: [
              Center(
                child: CircleAvatar(
                  radius: 42,
                  backgroundColor: Color(0xFFDAECFF),
                  child: Icon(Icons.person, size: 42, color: AppColors.primary),
                ),
              ),
              SizedBox(height: 12),
              Center(
                child: Text(
                  'Abdul Alim',
                  style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800),
                ),
              ),
              SizedBox(height: 4),
              Center(child: Text('Siswa - XI IPA 2')),
              SizedBox(height: 14),
              _InfoRow(label: 'Username', value: '232410289'),
              _InfoRow(label: 'Email', value: '232410289@sman1...'),
              _InfoRow(label: 'NIS', value: '232410289'),
              SizedBox(height: 10),
              _PrimaryActionRow(
                primaryLabel: 'Edit Data Pribadi',
                secondaryLabel: 'Ubah Foto',
              ),
            ],
          ),
          SizedBox(height: 12),
          _SectionCard(
            title: 'Keamanan Akun',
            icon: Icons.lock_outline,
            children: [
              _MenuLine('Ubah Password', Icons.password_outlined),
              _MenuLine('Logout', Icons.logout),
            ],
          ),
        ],
      ),
    );
  }
}

class _SettingsMock extends StatelessWidget {
  const _SettingsMock();

  @override
  Widget build(BuildContext context) {
    return _MockPhoneCanvas(
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 110),
        children: const [
          _SectionCard(
            title: 'Binding Perangkat Siswa',
            icon: Icons.phonelink_lock_outlined,
            children: [
              _InfoRow(label: 'Perangkat aktif', value: 'Redmi Note 12'),
              _InfoRow(label: 'Binding', value: 'Terkunci ke perangkat ini'),
              SizedBox(height: 8),
              Text(
                'Panel ini hanya muncul pada akun siswa. Akun non-siswa tidak memakai device binding di SIAP mobile.',
                style: TextStyle(fontSize: 12, height: 1.4),
              ),
            ],
          ),
          SizedBox(height: 12),
          _SectionCard(
            title: 'Izin Perangkat',
            icon: Icons.verified_user_outlined,
            children: [
              _InfoRow(label: 'Kamera', value: 'Diizinkan'),
              _InfoRow(label: 'Lokasi', value: 'Diizinkan'),
              _InfoRow(label: 'Media', value: 'Diizinkan'),
            ],
          ),
          SizedBox(height: 12),
          _SectionCard(
            title: 'Sistem',
            icon: Icons.cloud_done_outlined,
            children: [
              _InfoRow(label: 'Server', value: 'load.sman1sumbercirebon.sch.id'),
              _InfoRow(label: 'Sinkron terakhir', value: '07:10:12 WIB'),
            ],
          ),
          SizedBox(height: 12),
          _SectionCard(
            title: 'Bantuan',
            icon: Icons.help_outline,
            children: [
              _MenuLine('Notifikasi', Icons.notifications_outlined),
              _MenuLine('Tentang Aplikasi', Icons.info_outline),
            ],
          ),
        ],
      ),
    );
  }
}

class _MockPhoneCanvas extends StatelessWidget {
  final Widget child;

  const _MockPhoneCanvas({required this.child});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          colors: [Color(0xFFE8F2FF), Color(0xFFF6FAFF)],
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
        ),
      ),
      child: child,
    );
  }
}

class _HeroCard extends StatelessWidget {
  final String name;
  final String subtitle;
  final String identifier;
  final IconData icon;

  const _HeroCard({
    required this.name,
    required this.subtitle,
    required this.identifier,
    required this.icon,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF0C4A7A), AppColors.primary, AppColors.accent],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(22),
        boxShadow: [
          BoxShadow(
            color: AppColors.primary.withValues(alpha: 0.35),
            blurRadius: 24,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 6,
                  ),
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.18),
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: Text(
                    subtitle,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                Text(
                  name,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 24,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  identifier,
                  style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.82),
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
          ),
          CircleAvatar(
            radius: 36,
            backgroundColor: Colors.white.withValues(alpha: 0.18),
            child: Icon(icon, color: Colors.white, size: 32),
          ),
        ],
      ),
    );
  }
}

class _SectionCard extends StatelessWidget {
  final String title;
  final IconData icon;
  final List<Widget> children;

  const _SectionCard({
    required this.title,
    required this.icon,
    required this.children,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, color: AppColors.primary),
              const SizedBox(width: 8),
              Text(
                title,
                style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
              ),
            ],
          ),
          const SizedBox(height: 12),
          ...children,
        ],
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  final String label;
  final String value;

  const _InfoRow({required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 5),
      child: Row(
        children: [
          Expanded(
            child: Text(
              label,
              style: TextStyle(color: Colors.grey[700]),
            ),
          ),
          Text(
            value,
            style: const TextStyle(fontWeight: FontWeight.w700),
          ),
        ],
      ),
    );
  }
}

class _PrimaryActionRow extends StatelessWidget {
  final String primaryLabel;
  final String secondaryLabel;

  const _PrimaryActionRow({
    required this.primaryLabel,
    required this.secondaryLabel,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: FilledButton(
            onPressed: () {},
            style: FilledButton.styleFrom(
              backgroundColor: AppColors.primary,
              padding: const EdgeInsets.symmetric(vertical: 12),
            ),
            child: Text(primaryLabel),
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: OutlinedButton(
            onPressed: () {},
            style: OutlinedButton.styleFrom(
              padding: const EdgeInsets.symmetric(vertical: 12),
            ),
            child: Text(secondaryLabel),
          ),
        ),
      ],
    );
  }
}

class _StatusTile extends StatelessWidget {
  final IconData icon;
  final String label;
  final String value;
  final Color tone;

  const _StatusTile({
    required this.icon,
    required this.label,
    required this.value,
    required this.tone,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 148,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: tone,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: AppColors.primary, size: 18),
          const SizedBox(height: 10),
          Text(
            label,
            style: TextStyle(
              fontSize: 11,
              color: Colors.grey[700],
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            value,
            style: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}

class _StatsPeriodCard extends StatelessWidget {
  final String title;
  final List<_StatsMetricData> primaryMetrics;
  final List<_InlineMetricData> secondaryMetrics;

  const _StatsPeriodCard({
    required this.title,
    required this.primaryMetrics,
    required this.secondaryMetrics,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xFFF8FBFF),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFDCE8F7)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: primaryMetrics
                .map((metric) => _StatsMetricBox(data: metric))
                .toList(),
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: secondaryMetrics
                .map((metric) => _InlineMetric(metric: metric))
                .toList(),
          ),
        ],
      ),
    );
  }
}

class _StatsMetricData {
  final String label;
  final String value;
  final String unit;
  final Color tone;

  const _StatsMetricData(this.label, this.value, this.unit, this.tone);
}

class _InlineMetricData {
  final String label;
  final String value;

  const _InlineMetricData(this.label, this.value);
}

class _StatsMetricBox extends StatelessWidget {
  final _StatsMetricData data;

  const _StatsMetricBox({required this.data});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 132,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: data.tone,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            data.label,
            style: TextStyle(
              fontSize: 11,
              color: Colors.grey[700],
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            data.value,
            style: const TextStyle(
              fontSize: 24,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            data.unit,
            style: TextStyle(
              fontSize: 11,
              color: Colors.grey[700],
            ),
          ),
        ],
      ),
    );
  }
}

class _InlineMetric extends StatelessWidget {
  final _InlineMetricData metric;

  const _InlineMetric({required this.metric});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: const Color(0xFFDCE8F7)),
      ),
      child: RichText(
        text: TextSpan(
          style: DefaultTextStyle.of(context).style.copyWith(
                fontSize: 12,
                color: Colors.grey[800],
              ),
          children: [
            TextSpan(
              text: '${metric.label}: ',
              style: const TextStyle(fontWeight: FontWeight.w600),
            ),
            TextSpan(
              text: metric.value,
              style: const TextStyle(fontWeight: FontWeight.w800),
            ),
          ],
        ),
      ),
    );
  }
}

class _MetricChip extends StatelessWidget {
  final String label;
  final String value;

  const _MetricChip({required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: const Color(0xFFF4F8FF),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            value,
            style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            style: TextStyle(fontSize: 12, color: Colors.grey[700]),
          ),
        ],
      ),
    );
  }
}

class _ScheduleTile extends StatelessWidget {
  final String time;
  final String title;
  final String meta;

  const _ScheduleTile({
    required this.time,
    required this.title,
    required this.meta,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFF7FAFF),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 72,
            padding: const EdgeInsets.symmetric(vertical: 6),
            decoration: BoxDecoration(
              color: const Color(0xFFDAECFF),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Text(
              time,
              textAlign: TextAlign.center,
              style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 4),
                Text(
                  meta,
                  style: TextStyle(color: Colors.grey[700], fontSize: 12),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _LauncherSection extends StatelessWidget {
  final String title;
  final IconData icon;
  final List<_LauncherItem> items;

  const _LauncherSection({
    required this.title,
    required this.icon,
    required this.items,
  });

  @override
  Widget build(BuildContext context) {
    return _SectionCard(
      title: title,
      icon: icon,
      children: items
          .map(
            (item) => _MenuLine(item.label, item.icon),
          )
          .toList(),
    );
  }
}

class _LauncherItem {
  final String label;
  final IconData icon;

  const _LauncherItem(this.label, this.icon);
}

class _MenuLine extends StatelessWidget {
  final String label;
  final IconData icon;

  const _MenuLine(this.label, this.icon);

  @override
  Widget build(BuildContext context) {
    return ListTile(
      contentPadding: EdgeInsets.zero,
      dense: true,
      leading: Icon(icon, color: AppColors.primary),
      title: Text(label),
      trailing: const Icon(Icons.chevron_right),
      onTap: () {},
    );
  }
}
