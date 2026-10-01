import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/app_strings.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/widgets/djibouti_header.dart';
import '../../auth/application/auth_controller.dart';

class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final s = ref.watch(stringsProvider);
    final user = ref.watch(authControllerProvider).valueOrNull;
    return Scaffold(
      body: CustomScrollView(
        slivers: [
          SliverToBoxAdapter(
            child: DjiboutiHeader(
              title: s.t('welcomeBack'),
              subtitle: user?.name ?? '',
              compact: true,
            ),
          ),
          SliverPadding(
            padding: const EdgeInsets.all(20),
            sliver: SliverList(
              delegate: SliverChildListDelegate([
                Text(
                  s.t('quickActions'),
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w600),
                ),
                const SizedBox(height: 14),
                _ActionCard(
                  icon: Icons.edit_note,
                  color: AppColors.green,
                  title: s.t('report'),
                  onTap: () => context.go('/report'),
                ),
                const SizedBox(height: 10),
                _ActionCard(
                  icon: Icons.folder_open,
                  color: AppColors.skyBlueDark,
                  title: s.t('myCases'),
                  onTap: () => context.go('/cases'),
                ),
                const SizedBox(height: 10),
                _ActionCard(
                  icon: Icons.analytics,
                  color: AppColors.skyBlueDark,
                  title: s.t('insights'),
                  onTap: () => context.go('/insights'),
                ),
                const SizedBox(height: 10),
                _ActionCard(
                  icon: Icons.search,
                  color: AppColors.ink,
                  title: s.t('track'),
                  onTap: () => context.go('/track'),
                ),
                const SizedBox(height: 24),
                Container(
                  decoration: BoxDecoration(
                    color: AppColors.white,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: AppColors.skyBlue.withOpacity(0.35)),
                    boxShadow: [
                      BoxShadow(
                        color: AppColors.greenDark.withOpacity(0.06),
                        blurRadius: 16,
                        offset: const Offset(0, 4),
                      ),
                    ],
                  ),
                  child: ListTile(
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                    leading: const Icon(Icons.info_outline, color: AppColors.green),
                    title: Text(s.t('learnMore'), style: const TextStyle(fontWeight: FontWeight.w600)),
                    subtitle: Text(s.t('learnBody')),
                    onTap: () => context.push('/help'),
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  s.t('homeFooter'),
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(color: AppColors.inkMuted),
                  textAlign: TextAlign.center,
                ),
              ]),
            ),
          ),
        ],
      ),
    );
  }
}

class _ActionCard extends StatelessWidget {
  const _ActionCard({
    required this.icon,
    required this.color,
    required this.title,
    required this.onTap,
  });

  final IconData icon;
  final Color color;
  final String title;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.white,
      elevation: 0,
      shadowColor: AppColors.greenDark.withOpacity(0.08),
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: onTap,
        child: Ink(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: AppColors.inkMuted.withOpacity(0.12)),
            boxShadow: [
              BoxShadow(
                color: AppColors.greenDark.withOpacity(0.05),
                blurRadius: 12,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Row(
              children: [
                CircleAvatar(
                  radius: 24,
                  backgroundColor: color.withOpacity(0.14),
                  foregroundColor: color,
                  child: Icon(icon),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: Text(title, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 16)),
                ),
                Icon(Icons.chevron_right, color: AppColors.inkMuted.withOpacity(0.7)),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
