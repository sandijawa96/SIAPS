import 'package:flutter/material.dart';

import '../services/app_info_service.dart';

class AppVersionText extends StatelessWidget {
  final TextStyle? style;
  final bool includeAppName;
  final bool includeBuild;
  final String prefix;
  final String fallback;

  const AppVersionText({
    super.key,
    this.style,
    this.includeAppName = false,
    this.includeBuild = true,
    this.prefix = '',
    this.fallback = '',
  });

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<String>(
      future: AppInfoService().getVersionLabel(
        includeBuild: includeBuild,
        includeAppName: includeAppName,
      ),
      builder: (context, snapshot) {
        final value = snapshot.data ?? fallback;

        return Text(
          '$prefix$value'.trim(),
          style: style,
        );
      },
    );
  }
}
