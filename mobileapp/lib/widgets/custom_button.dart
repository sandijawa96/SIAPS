import 'package:flutter/material.dart';
import 'package:flutter_spinkit/flutter_spinkit.dart';

class CustomButton extends StatelessWidget {
  final String text;
  final VoidCallback? onPressed;
  final bool isLoading;
  final bool isEnabled;
  final ButtonType type;
  final ButtonSize size;
  final Widget? icon;
  final Color? backgroundColor;
  final Color? textColor;
  final double? width;
  final EdgeInsetsGeometry? padding;

  const CustomButton({
    Key? key,
    required this.text,
    this.onPressed,
    this.isLoading = false,
    this.isEnabled = true,
    this.type = ButtonType.primary,
    this.size = ButtonSize.medium,
    this.icon,
    this.backgroundColor,
    this.textColor,
    this.width,
    this.padding,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;

    final bool enabled = isEnabled && !isLoading && onPressed != null;

    return SizedBox(
      width: width,
      height: _getHeight(),
      child: _buildButton(context, theme, colorScheme, enabled),
    );
  }

  Widget _buildButton(
    BuildContext context,
    ThemeData theme,
    ColorScheme colorScheme,
    bool enabled,
  ) {
    switch (type) {
      case ButtonType.primary:
        return ElevatedButton(
          onPressed: enabled ? onPressed : null,
          style: _getElevatedButtonStyle(theme, colorScheme),
          child: _buildButtonContent(context, colorScheme),
        );

      case ButtonType.secondary:
        return OutlinedButton(
          onPressed: enabled ? onPressed : null,
          style: _getOutlinedButtonStyle(theme, colorScheme),
          child: _buildButtonContent(context, colorScheme),
        );

      case ButtonType.text:
        return TextButton(
          onPressed: enabled ? onPressed : null,
          style: _getTextButtonStyle(theme, colorScheme),
          child: _buildButtonContent(context, colorScheme),
        );
    }
  }

  Widget _buildButtonContent(BuildContext context, ColorScheme colorScheme) {
    if (isLoading) {
      return Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          SpinKitThreeBounce(
            color: _getLoadingColor(colorScheme),
            size: _getLoadingSize(),
          ),
          const SizedBox(width: 8),
          Text('Memuat...', style: _getTextStyle(context, colorScheme)),
        ],
      );
    }

    if (icon != null) {
      return Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          icon!,
          const SizedBox(width: 8),
          Text(text, style: _getTextStyle(context, colorScheme)),
        ],
      );
    }

    return Text(text, style: _getTextStyle(context, colorScheme));
  }

  ButtonStyle _getElevatedButtonStyle(
    ThemeData theme,
    ColorScheme colorScheme,
  ) {
    return ElevatedButton.styleFrom(
      backgroundColor: backgroundColor ?? colorScheme.primary,
      foregroundColor: textColor ?? colorScheme.onPrimary,
      disabledBackgroundColor: colorScheme.onSurface.withOpacity(0.12),
      disabledForegroundColor: colorScheme.onSurface.withOpacity(0.38),
      elevation: 2,
      shadowColor: colorScheme.shadow,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      padding: padding ?? _getPadding(),
    );
  }

  ButtonStyle _getOutlinedButtonStyle(
    ThemeData theme,
    ColorScheme colorScheme,
  ) {
    return OutlinedButton.styleFrom(
      foregroundColor: textColor ?? colorScheme.primary,
      disabledForegroundColor: colorScheme.onSurface.withOpacity(0.38),
      side: BorderSide(
        color: backgroundColor ?? colorScheme.primary,
        width: 1.5,
      ),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      padding: padding ?? _getPadding(),
    );
  }

  ButtonStyle _getTextButtonStyle(ThemeData theme, ColorScheme colorScheme) {
    return TextButton.styleFrom(
      foregroundColor: textColor ?? colorScheme.primary,
      disabledForegroundColor: colorScheme.onSurface.withOpacity(0.38),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      padding: padding ?? _getPadding(),
    );
  }

  TextStyle _getTextStyle(BuildContext context, ColorScheme colorScheme) {
    final theme = Theme.of(context);

    switch (size) {
      case ButtonSize.small:
        return theme.textTheme.labelMedium?.copyWith(
              fontWeight: FontWeight.w600,
            ) ??
            const TextStyle();

      case ButtonSize.medium:
        return theme.textTheme.labelLarge?.copyWith(
              fontWeight: FontWeight.w600,
            ) ??
            const TextStyle();

      case ButtonSize.large:
        return theme.textTheme.titleMedium?.copyWith(
              fontWeight: FontWeight.w600,
            ) ??
            const TextStyle();
    }
  }

  EdgeInsetsGeometry _getPadding() {
    switch (size) {
      case ButtonSize.small:
        return const EdgeInsets.symmetric(horizontal: 16, vertical: 8);
      case ButtonSize.medium:
        return const EdgeInsets.symmetric(horizontal: 24, vertical: 12);
      case ButtonSize.large:
        return const EdgeInsets.symmetric(horizontal: 32, vertical: 16);
    }
  }

  double _getHeight() {
    switch (size) {
      case ButtonSize.small:
        return 36;
      case ButtonSize.medium:
        return 48;
      case ButtonSize.large:
        return 56;
    }
  }

  Color _getLoadingColor(ColorScheme colorScheme) {
    switch (type) {
      case ButtonType.primary:
        return textColor ?? colorScheme.onPrimary;
      case ButtonType.secondary:
      case ButtonType.text:
        return textColor ?? colorScheme.primary;
    }
  }

  double _getLoadingSize() {
    switch (size) {
      case ButtonSize.small:
        return 12;
      case ButtonSize.medium:
        return 16;
      case ButtonSize.large:
        return 20;
    }
  }
}

enum ButtonType { primary, secondary, text }

enum ButtonSize { small, medium, large }

// Specialized button widgets
class LoginButton extends StatelessWidget {
  final String text;
  final VoidCallback? onPressed;
  final bool isLoading;

  const LoginButton({
    Key? key,
    required this.text,
    this.onPressed,
    this.isLoading = false,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return CustomButton(
      text: text,
      onPressed: onPressed,
      isLoading: isLoading,
      type: ButtonType.primary,
      size: ButtonSize.large,
      width: double.infinity,
    );
  }
}

class SecondaryButton extends StatelessWidget {
  final String text;
  final VoidCallback? onPressed;
  final bool isLoading;
  final Widget? icon;

  const SecondaryButton({
    Key? key,
    required this.text,
    this.onPressed,
    this.isLoading = false,
    this.icon,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return CustomButton(
      text: text,
      onPressed: onPressed,
      isLoading: isLoading,
      type: ButtonType.secondary,
      size: ButtonSize.medium,
      icon: icon,
      width: double.infinity,
    );
  }
}

class IconTextButton extends StatelessWidget {
  final String text;
  final IconData iconData;
  final VoidCallback? onPressed;
  final bool isLoading;
  final ButtonType type;

  const IconTextButton({
    Key? key,
    required this.text,
    required this.iconData,
    this.onPressed,
    this.isLoading = false,
    this.type = ButtonType.text,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return CustomButton(
      text: text,
      onPressed: onPressed,
      isLoading: isLoading,
      type: type,
      size: ButtonSize.medium,
      icon: Icon(iconData),
    );
  }
}
