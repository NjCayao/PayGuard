import 'package:flutter/material.dart';

import '../strings/strings.dart';
import '../theme/app_colors.dart';
import '../theme/app_text_styles.dart';

class AppLogo extends StatelessWidget {
  const AppLogo({super.key, this.size = 48});

  final double size;

  @override
  Widget build(BuildContext context) {
    return CustomPaint(
      size: Size(size, size),
      painter: _LogoPainter(),
    );
  }
}

class _LogoPainter extends CustomPainter {
  static const double _grid = 48;

  @override
  void paint(Canvas canvas, Size size) {
    final double scale = size.width / _grid;
    canvas.save();
    canvas.scale(scale);

    final Path shield = Path()
      ..moveTo(24, 4)
      ..lineTo(40, 10)
      ..lineTo(40, 22)
      ..cubicTo(40, 32, 33, 40, 24, 44)
      ..cubicTo(15, 40, 8, 32, 8, 22)
      ..lineTo(8, 10)
      ..close();
    canvas.drawPath(
      shield,
      Paint()
        ..color = AppColors.primary
        ..style = PaintingStyle.fill,
    );

    final Path check = Path()
      ..moveTo(16.5, 24)
      ..lineTo(21.5, 29)
      ..lineTo(31.5, 18.5);
    canvas.drawPath(
      check,
      Paint()
        ..color = Colors.white
        ..style = PaintingStyle.stroke
        ..strokeWidth = 3.5
        ..strokeCap = StrokeCap.round
        ..strokeJoin = StrokeJoin.round,
    );

    canvas.restore();
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

class BrandName extends StatelessWidget {
  const BrandName({super.key, this.fontSize = 28});

  final double fontSize;

  @override
  Widget build(BuildContext context) {
    final TextStyle style =
        AppTextStyles.brand.copyWith(fontSize: fontSize);
    return Text.rich(
      TextSpan(children: [
        TextSpan(
          text: Strings.brandPay,
          style: style.copyWith(color: AppColors.text),
        ),
        TextSpan(
          text: Strings.brandGuard,
          style: style.copyWith(color: AppColors.primary),
        ),
      ]),
    );
  }
}
