import 'package:flutter/material.dart';

import '../theme/app_colors.dart';

/// Djibouti national flag (blue / green bands, white hoist triangle, red star).
class DjiboutiFlag extends StatelessWidget {
  const DjiboutiFlag({
    super.key,
    this.width = 88,
    this.height = 56,
    this.borderRadius = 8,
  });

  final double width;
  final double height;
  final double borderRadius;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: width,
      height: height,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(borderRadius),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.12),
            blurRadius: 12,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: Stack(
        fit: StackFit.expand,
        children: [
          Column(
            children: [
              Expanded(child: Container(color: AppColors.skyBlue)),
              Expanded(child: Container(color: AppColors.green)),
            ],
          ),
          Align(
            alignment: Alignment.centerLeft,
            child: ClipPath(
              clipper: _HoistTriangleClipper(),
              child: SizedBox(
                width: width * 0.52,
                height: height,
                child: ColoredBox(
                  color: AppColors.white,
                  child: Center(
                    child: Padding(
                      padding: EdgeInsets.only(right: width * 0.08),
                      child: Icon(
                        Icons.star,
                        color: AppColors.danger,
                        size: height * 0.38,
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _HoistTriangleClipper extends CustomClipper<Path> {
  @override
  Path getClip(Size size) {
    return Path()
      ..moveTo(0, 0)
      ..lineTo(size.width, size.height / 2)
      ..lineTo(0, size.height)
      ..close();
  }

  @override
  bool shouldReclip(covariant CustomClipper<Path> oldClipper) => false;
}
