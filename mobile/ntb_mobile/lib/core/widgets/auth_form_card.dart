import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import 'djibouti_flag.dart';

/// Centered card shell for login/register with Djibouti accent.
class AuthFormCard extends StatelessWidget {
  const AuthFormCard({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: AppColors.greenDark.withOpacity(0.08),
            blurRadius: 24,
            offset: const Offset(0, 8),
          ),
        ],
        border: Border.all(color: AppColors.skyBlue.withOpacity(0.35)),
      ),
      padding: const EdgeInsets.fromLTRB(24, 28, 24, 24),
      child: child,
    );
  }
}

class AuthBrandMark extends StatelessWidget {
  const AuthBrandMark({super.key, required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        const DjiboutiFlag(width: 96, height: 60, borderRadius: 10),
        const SizedBox(height: 20),
        Text(
          title,
          textAlign: TextAlign.center,
          style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                fontWeight: FontWeight.w700,
                color: AppColors.ink,
              ),
        ),
        const SizedBox(height: 8),
        Text(
          subtitle,
          textAlign: TextAlign.center,
          style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: AppColors.inkMuted, height: 1.4),
        ),
      ],
    );
  }
}
