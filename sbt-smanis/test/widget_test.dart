import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sbt_smanis/main.dart';

void main() {
  Future<void> pumpDashboard(WidgetTester tester, Size size) async {
    tester.view.physicalSize = size;
    tester.view.devicePixelRatio = 1;

    await tester.pumpWidget(const SbtSmanisApp());
    await tester.pumpAndSettle();
  }

  testWidgets('dashboard siswa tampil', (tester) async {
    await tester.pumpWidget(const SbtSmanisApp());

    expect(find.text('SBT SMANIS'), findsOneWidget);
    expect(find.text('SMAN 1 Sumber Cirebon'), findsOneWidget);
    expect(find.text('Smartphone Based Test'), findsOneWidget);
    expect(find.text('Browser tes siswa'), findsOneWidget);
    expect(find.text('Mode fokus'), findsOneWidget);
    expect(find.text('Secure'), findsOneWidget);
    expect(find.text('Masuk ke ruang ujian.'), findsOneWidget);
    expect(find.text('Mulai Ujian'), findsOneWidget);
    expect(find.text('Tentang'), findsOneWidget);
    expect(find.text('Keluar'), findsOneWidget);

    final image = tester.widget<Image>(find.byType(Image).first);
    expect((image.image as AssetImage).assetName, 'assets/icon.png');

    final appLongNameTop = tester
        .getTopLeft(find.text('Smartphone Based Test'))
        .dy;
    final browserBadgeTop = tester
        .getTopLeft(find.text('Browser tes siswa'))
        .dy;
    final focusBadgeTop = tester.getTopLeft(find.text('Mode fokus')).dy;

    expect(appLongNameTop, lessThan(browserBadgeTop));
    expect(appLongNameTop, lessThan(focusBadgeTop));
  });

  testWidgets('dashboard satu layar tanpa scroll pada berbagai device', (
    tester,
  ) async {
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    const sizes = <Size>[
      Size(320, 568),
      Size(320, 480),
      Size(360, 592),
      Size(360, 640),
      Size(393, 851),
      Size(430, 932),
      Size(568, 320),
      Size(640, 360),
      Size(844, 390),
      Size(932, 430),
      Size(1024, 768),
      Size(900, 1200),
      Size(1180, 820),
    ];

    for (final size in sizes) {
      await pumpDashboard(tester, size);

      expect(find.byType(SingleChildScrollView), findsNothing);
      expect(find.text('Mulai Ujian'), findsOneWidget);
      expect(find.text('Tentang'), findsOneWidget);
      expect(find.text('Keluar'), findsOneWidget);
      expect(find.text('Browser tes siswa'), findsOneWidget);
      expect(find.text('Mode fokus'), findsOneWidget);
      if (size.width > size.height) {
        expect(find.text('Secure'), findsOneWidget);
      }
      expect(find.text('Dibuat dengan Penuh Cinta'), findsOneWidget);
      expect(find.text('SMANIS'), findsOneWidget);
      expect(tester.takeException(), isNull, reason: 'Ukuran $size');
    }
  });
}
