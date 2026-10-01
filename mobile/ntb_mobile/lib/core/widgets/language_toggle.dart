import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../l10n/app_strings.dart';
import '../theme/app_colors.dart';

/// Compact EN / FR switch for auth screens and toolbars.
class LanguageToggle extends ConsumerWidget {
  const LanguageToggle({super.key, this.compact = false});

  final bool compact;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final locale = ref.watch(localeProvider);
    final s = ref.watch(stringsProvider);

    return DecoratedBox(
      decoration: BoxDecoration(
        color: AppColors.white.withOpacity(0.92),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: AppColors.skyBlue.withOpacity(0.45)),
        boxShadow: [
          BoxShadow(
            color: AppColors.greenDark.withOpacity(0.06),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.all(3),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            _LocaleChip(
              label: compact ? 'EN' : s.t('languageEn'),
              selected: locale == AppLocale.en,
              onTap: () => ref.read(localeProvider.notifier).setLocale(AppLocale.en),
            ),
            _LocaleChip(
              label: compact ? 'FR' : s.t('languageFr'),
              selected: locale == AppLocale.fr,
              onTap: () => ref.read(localeProvider.notifier).setLocale(AppLocale.fr),
            ),
          ],
        ),
      ),
    );
  }
}

class _LocaleChip extends StatelessWidget {
  const _LocaleChip({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: selected ? AppColors.green : Colors.transparent,
      borderRadius: BorderRadius.circular(20),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(20),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
          child: Text(
            label,
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w600,
              color: selected ? AppColors.white : AppColors.inkMuted,
            ),
          ),
        ),
      ),
    );
  }
}
