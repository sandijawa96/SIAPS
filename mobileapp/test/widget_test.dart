import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mobileapp/main.dart';
import 'package:mobileapp/providers/auth_provider.dart';
import 'package:mobileapp/screens/login_screen.dart';
import 'package:provider/provider.dart';

void main() {
  Future<void> pumpLoginScreen(
    WidgetTester tester, {
    required Size size,
  }) async {
    tester.view.physicalSize = size;
    tester.view.devicePixelRatio = 1.0;

    await tester.pumpWidget(
      ChangeNotifierProvider(
        create: (_) => AuthProvider(),
        child: const MaterialApp(home: LoginScreen()),
      ),
    );
    await tester.pumpAndSettle();
  }

  Future<void> pumpLoadingScreen(
    WidgetTester tester, {
    required Size size,
  }) async {
    tester.view.physicalSize = size;
    tester.view.devicePixelRatio = 1.0;

    await tester.pumpWidget(
      const MaterialApp(home: LoadingScreen()),
    );
    await tester.pump(const Duration(milliseconds: 100));
  }

  setUp(() {});

  tearDown(() {});

  testWidgets('App bootstraps without crashing', (WidgetTester tester) async {
    await tester.pumpWidget(const MyApp());
    await tester.pump();

    expect(find.byType(MyApp), findsOneWidget);
  });

  testWidgets('Loading screen shows rebuilt splash visuals on tall phones', (
    WidgetTester tester,
  ) async {
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await pumpLoadingScreen(tester, size: const Size(393, 851));

    expect(find.text('Secure'), findsOneWidget);
    expect(find.text('Menyiapkan pusat aktivitas sekolah.'), findsOneWidget);
    expect(
      find.text(
        'SIAPS mobile menyatukan absensi real-time, rekap kehadiran, izin, pengumuman, dan notifikasi sekolah dalam satu akses.',
      ),
      findsOneWidget,
    );
    expect(find.text('Menyiapkan aplikasi...'), findsOneWidget);
    expect(find.text('Copyright Ictsmanis@2025'), findsOneWidget);
    expect(find.byType(SingleChildScrollView), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('Loading screen stays compact on phone landscape', (
    WidgetTester tester,
  ) async {
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await pumpLoadingScreen(tester, size: const Size(844, 390));

    expect(find.text('Secure'), findsOneWidget);
    expect(find.text('Menyiapkan pusat aktivitas sekolah.'), findsOneWidget);
    expect(
      find.text(
        'Akses absensi, izin, pengumuman, dan rekap sekolah dari satu aplikasi mobile.',
      ),
      findsNothing,
    );
    expect(find.text('Menyiapkan aplikasi...'), findsOneWidget);
    expect(find.text('Copyright Ictsmanis@2025'), findsNothing);
    expect(find.byType(SingleChildScrollView), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('Login screen defaults to student mode without scroll', (
    WidgetTester tester,
  ) async {
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await pumpLoginScreen(tester, size: const Size(393, 851));

    expect(find.text('Login Siswa'), findsOneWidget);
    expect(find.text('Masuk sebagai Pegawai'), findsOneWidget);
    expect(find.text('Masuk sebagai Siswa'), findsOneWidget);
    expect(
      find.text(
          'Tanggal lahir dipakai sebagai verifikasi siswa. Pilih sesuai data sekolah.'),
      findsOneWidget,
    );
    expect(find.text('Secure'), findsOneWidget);
    expect(find.byType(SingleChildScrollView), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('Tall phone screens show the compact rebuilt intro', (
    WidgetTester tester,
  ) async {
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    const scenarios = <Size>[
      Size(393, 851),
      Size(375, 812),
      Size(390, 844),
      Size(430, 932),
    ];

    for (final size in scenarios) {
      await pumpLoginScreen(tester, size: size);

      expect(find.text('Login Siswa'), findsOneWidget);
      expect(find.text('Masuk ke pusat aktivitas sekolah.'), findsOneWidget);
      expect(
        find.text(
          'Akses absensi, izin, pengumuman, dan rekap sekolah dari satu aplikasi mobile.',
        ),
        findsOneWidget,
      );
      expect(find.text('Login Anda terekam untuk keamanan sistem.'),
          findsOneWidget);
      expect(find.text('Copyright Ictsmanis@2025'), findsOneWidget);
      expect(find.byType(SingleChildScrollView), findsNothing);
      expect(tester.takeException(), isNull);
    }
  });

  testWidgets('Compact intro appears on medium phones and landscape screens', (
    WidgetTester tester,
  ) async {
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    const scenarios = <Size>[
      Size(360, 740),
      Size(812, 375),
      Size(844, 390),
    ];

    for (final size in scenarios) {
      await pumpLoginScreen(tester, size: size);

      expect(find.text('Login Siswa'), findsOneWidget);
      expect(find.text('Masuk sebagai Pegawai'), findsOneWidget);
      expect(find.text('Masuk ke pusat aktivitas sekolah.'), findsOneWidget);
      expect(find.byType(SingleChildScrollView), findsNothing);
      expect(tester.takeException(), isNull);
    }
  });

  testWidgets('Wide portrait layouts keep the full rebuilt hero copy', (
    WidgetTester tester,
  ) async {
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await pumpLoginScreen(tester, size: const Size(900, 1200));

    expect(find.text('Masuk untuk mulai\nhari sekolah Anda.'), findsOneWidget);
    expect(
      find.text(
        'SIAPS mobile menyatukan absensi real-time, rekap kehadiran, izin, pengumuman, dan notifikasi sekolah dalam satu akses.',
      ),
      findsOneWidget,
    );
    expect(find.byType(SingleChildScrollView), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('Small phones stay focused on the login card without overflow', (
    WidgetTester tester,
  ) async {
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await pumpLoginScreen(tester, size: const Size(320, 640));

    expect(find.text('Login Siswa'), findsOneWidget);
    expect(find.text('Masuk ke pusat aktivitas sekolah.'), findsNothing);
    expect(find.byType(SingleChildScrollView), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('Staff mode shows school credential guidance and aligned actions',
      (
    WidgetTester tester,
  ) async {
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await pumpLoginScreen(tester, size: const Size(393, 851));

    await tester.tap(find.text('Masuk sebagai Pegawai'));
    await tester.pumpAndSettle();

    expect(find.text('Login Pegawai'), findsOneWidget);
    expect(
      find.text('Gunakan User dan Password yang terdaftar di sekolah.'),
      findsOneWidget,
    );
    expect(find.text('Ingat saya'), findsOneWidget);
    expect(find.text('Butuh bantuan?'), findsOneWidget);
    expect(find.byType(SingleChildScrollView), findsNothing);
    expect(tester.takeException(), isNull);
  });
}
