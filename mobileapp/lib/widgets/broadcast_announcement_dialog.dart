import 'package:flutter/material.dart';

import '../services/notification_service.dart';

class BroadcastAnnouncementDialog extends StatelessWidget {
  const BroadcastAnnouncementDialog({
    super.key,
    required this.item,
    required this.onDismiss,
    required this.onCtaTap,
  });

  final AppNotificationItem item;
  final VoidCallback onDismiss;
  final VoidCallback onCtaTap;

  bool get _isFlyer => item.popupVariant == 'flyer';

  @override
  Widget build(BuildContext context) {
    final mediaQuery = MediaQuery.of(context);

    return Dialog(
      backgroundColor: Colors.transparent,
      elevation: 0,
      insetPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 24),
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxWidth: _isFlyer ? 680 : 560,
          maxHeight: mediaQuery.size.height * 0.88,
        ),
        child: Container(
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(28),
            boxShadow: const [
              BoxShadow(
                color: Color(0x33111827),
                blurRadius: 36,
                offset: Offset(0, 18),
              ),
            ],
          ),
          child: ClipRRect(
            borderRadius: BorderRadius.circular(28),
            child: Material(
              color: Colors.white,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Flexible(
                    child: SingleChildScrollView(
                      child: _isFlyer
                          ? _FlyerPopupBody(item: item, onDismiss: onDismiss)
                          : _InfoPopupBody(item: item, onDismiss: onDismiss),
                    ),
                  ),
                  _PopupFooter(
                    dismissLabel: item.popupDismissLabel,
                    ctaLabel: item.popupCtaLabel,
                    onDismiss: onDismiss,
                    onCtaTap: onCtaTap,
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _InfoPopupBody extends StatelessWidget {
  const _InfoPopupBody({
    required this.item,
    required this.onDismiss,
  });

  final AppNotificationItem item;
  final VoidCallback onDismiss;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: [
            Color(0xFFE0F2FE),
            Color(0xFFF5FAFF),
            Colors.white,
          ],
        ),
      ),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(22, 22, 22, 20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _DialogHeader(
              badgeLabel: 'Informasi Sekolah',
              icon: Icons.info_outline_rounded,
              iconBackground: const Color(0xFF0F172A),
              iconColor: Colors.white,
              title: item.popupTitle,
              onDismiss: onDismiss,
              dismissible: !item.popupSticky,
            ),
            const SizedBox(height: 18),
            Container(
              width: double.infinity,
              decoration: BoxDecoration(
                color: const Color(0xFFFDFEFF).withOpacity(0.92),
                borderRadius: BorderRadius.circular(24),
                border: Border.all(color: const Color(0xFFBAE6FD)),
                boxShadow: const [
                  BoxShadow(
                    color: Color(0x140E7490),
                    blurRadius: 28,
                    offset: Offset(0, 12),
                  ),
                ],
              ),
              child: Padding(
                padding: const EdgeInsets.fromLTRB(18, 18, 18, 18),
                child: _ScrollableMessage(
                  message: item.message,
                  maxHeight: 320,
                  textColor: const Color(0xFF334155),
                  textSize: 15,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _FlyerPopupBody extends StatelessWidget {
  const _FlyerPopupBody({
    required this.item,
    required this.onDismiss,
  });

  final AppNotificationItem item;
  final VoidCallback onDismiss;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: [
            Color(0xFFFFFFFF),
            Color(0xFFF8FAFC),
          ],
        ),
      ),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 22, 20, 20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            _DialogHeader(
              badgeLabel: 'Flyer Sekolah',
              icon: Icons.campaign_outlined,
              iconBackground: const Color(0xFF123B67),
              iconColor: Colors.white,
              title: item.popupTitle,
              onDismiss: onDismiss,
              dismissible: !item.popupSticky,
              centered: true,
            ),
            if (item.popupImageUrl != null) ...[
              const SizedBox(height: 18),
              _FlyerImageStage(imageUrl: item.popupImageUrl!, title: item.popupTitle),
            ],
            if (item.message.trim().isNotEmpty) ...[
              const SizedBox(height: 16),
              Container(
                width: double.infinity,
                constraints: const BoxConstraints(maxWidth: 600),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(22),
                  border: Border.all(color: const Color(0xFFE2E8F0)),
                  boxShadow: const [
                    BoxShadow(
                      color: Color(0x12111827),
                      blurRadius: 24,
                      offset: Offset(0, 10),
                    ),
                  ],
                ),
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(18, 16, 18, 16),
                  child: _ScrollableMessage(
                    message: item.message,
                    maxHeight: 220,
                    textColor: const Color(0xFF475569),
                    textSize: 14,
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _DialogHeader extends StatelessWidget {
  const _DialogHeader({
    required this.badgeLabel,
    required this.icon,
    required this.iconBackground,
    required this.iconColor,
    required this.title,
    required this.onDismiss,
    required this.dismissible,
    this.centered = false,
  });

  final String badgeLabel;
  final IconData icon;
  final Color iconBackground;
  final Color iconColor;
  final String title;
  final VoidCallback onDismiss;
  final bool dismissible;
  final bool centered;

  @override
  Widget build(BuildContext context) {
    final titleColumn = Column(
      crossAxisAlignment:
          centered ? CrossAxisAlignment.center : CrossAxisAlignment.start,
      children: [
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
          decoration: BoxDecoration(
            color: const Color(0xFFF1F5F9),
            borderRadius: BorderRadius.circular(999),
            border: Border.all(color: const Color(0xFFE2E8F0)),
          ),
          child: Text(
            badgeLabel,
            style: const TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w800,
              letterSpacing: 0.5,
              color: Color(0xFF0F766E),
            ),
          ),
        ),
        const SizedBox(height: 14),
        Container(
          width: 54,
          height: 54,
          decoration: BoxDecoration(
            color: iconBackground,
            borderRadius: BorderRadius.circular(18),
            boxShadow: const [
              BoxShadow(
                color: Color(0x22111827),
                blurRadius: 18,
                offset: Offset(0, 10),
              ),
            ],
          ),
          child: Icon(icon, color: iconColor, size: 24),
        ),
        const SizedBox(height: 16),
        Text(
          title,
          textAlign: centered ? TextAlign.center : TextAlign.left,
          style: const TextStyle(
            fontSize: 28,
            height: 1.14,
            fontWeight: FontWeight.w800,
            color: Color(0xFF0F172A),
          ),
        ),
      ],
    );

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: centered
              ? Align(
                  alignment: Alignment.topCenter,
                  child: titleColumn,
                )
              : titleColumn,
        ),
        if (dismissible)
          Padding(
            padding: EdgeInsets.only(left: centered ? 8 : 12),
            child: IconButton(
              onPressed: onDismiss,
              style: IconButton.styleFrom(
                backgroundColor: const Color(0xFFF8FAFC),
                foregroundColor: const Color(0xFF475569),
              ),
              icon: const Icon(Icons.close_rounded),
            ),
          ),
      ],
    );
  }
}

class _FlyerImageStage extends StatelessWidget {
  const _FlyerImageStage({
    required this.imageUrl,
    required this.title,
  });

  final String imageUrl;
  final String title;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      constraints: const BoxConstraints(maxWidth: 560),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xFFF8FAFC),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: const [
          BoxShadow(
            color: Color(0x14111827),
            blurRadius: 28,
            offset: Offset(0, 12),
          ),
        ],
      ),
      child: Container(
        constraints: const BoxConstraints(
          minHeight: 180,
          maxHeight: 460,
        ),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(18),
        ),
        padding: const EdgeInsets.all(10),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(12),
          child: Image.network(
            imageUrl,
            fit: BoxFit.contain,
            alignment: Alignment.center,
            loadingBuilder: (context, child, loadingProgress) {
              if (loadingProgress == null) {
                return child;
              }

              return const SizedBox(
                height: 220,
                child: Center(
                  child: CircularProgressIndicator(
                    color: Color(0xFF123B67),
                  ),
                ),
              );
            },
            errorBuilder: (context, error, stackTrace) {
              return SizedBox(
                height: 220,
                child: Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(
                        Icons.broken_image_outlined,
                        size: 42,
                        color: Color(0xFF94A3B8),
                      ),
                      const SizedBox(height: 10),
                      Text(
                        'Gagal memuat flyer',
                        style: Theme.of(context).textTheme.titleSmall?.copyWith(
                              fontWeight: FontWeight.w700,
                              color: const Color(0xFF334155),
                            ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        title,
                        textAlign: TextAlign.center,
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                              color: const Color(0xFF64748B),
                            ),
                      ),
                    ],
                  ),
                ),
              );
            },
          ),
        ),
      ),
    );
  }
}

class _ScrollableMessage extends StatelessWidget {
  const _ScrollableMessage({
    required this.message,
    required this.maxHeight,
    required this.textColor,
    required this.textSize,
  });

  final String message;
  final double maxHeight;
  final Color textColor;
  final double textSize;

  @override
  Widget build(BuildContext context) {
    if (message.trim().isEmpty) {
      return const SizedBox.shrink();
    }

    return Scrollbar(
      thumbVisibility: true,
      radius: const Radius.circular(999),
      child: ConstrainedBox(
        constraints: BoxConstraints(maxHeight: maxHeight),
        child: SingleChildScrollView(
          child: Text(
            message,
            textAlign: TextAlign.left,
            style: TextStyle(
              fontSize: textSize,
              height: 1.75,
              fontWeight: FontWeight.w500,
              color: textColor,
            ),
          ),
        ),
      ),
    );
  }
}

class _PopupFooter extends StatelessWidget {
  const _PopupFooter({
    required this.dismissLabel,
    required this.ctaLabel,
    required this.onDismiss,
    required this.onCtaTap,
  });

  final String dismissLabel;
  final String? ctaLabel;
  final VoidCallback onDismiss;
  final VoidCallback onCtaTap;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(20, 16, 20, 18),
      decoration: const BoxDecoration(
        color: Colors.white,
        border: Border(
          top: BorderSide(color: Color(0xFFF1F5F9)),
        ),
      ),
      child: Row(
        children: [
          TextButton(
            onPressed: onDismiss,
            style: TextButton.styleFrom(
              foregroundColor: const Color(0xFF047857),
              textStyle: const TextStyle(
                fontWeight: FontWeight.w800,
              ),
            ),
            child: Text(dismissLabel),
          ),
          const Spacer(),
          if (ctaLabel != null && ctaLabel!.trim().isNotEmpty)
            FilledButton.icon(
              onPressed: onCtaTap,
              style: FilledButton.styleFrom(
                backgroundColor: const Color(0xFF0F172A),
                foregroundColor: Colors.white,
                padding:
                    const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(16),
                ),
              ),
              icon: const Icon(Icons.open_in_new_rounded, size: 18),
              label: Text(
                ctaLabel!,
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
            ),
        ],
      ),
    );
  }
}
