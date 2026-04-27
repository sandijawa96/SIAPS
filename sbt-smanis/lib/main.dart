import 'package:flutter/material.dart';

import 'config/app_config.dart';
import 'config/app_theme.dart';
import 'screens/student_dashboard_screen.dart';
import 'services/exam_guard_service.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  ExamGuardService.instance.initialize();
  runApp(const SbtSmanisApp());
}

class SbtSmanisApp extends StatelessWidget {
  const SbtSmanisApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: AppConfig.appName,
      debugShowCheckedModeBanner: false,
      theme: buildAppTheme(),
      home: const StudentDashboardScreen(),
    );
  }
}
