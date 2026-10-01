import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../core/l10n/app_strings.dart';
import '../../../core/theme/app_colors.dart';
import '../application/lookups_provider.dart';

class MyComplaintsScreen extends ConsumerWidget {
  const MyComplaintsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final s = ref.watch(stringsProvider);
    final async = ref.watch(myComplaintsProvider);

    return Scaffold(
      appBar: AppBar(title: Text(s.t('myCases'))),
      body: async.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(child: Text('$e')),
        data: (items) {
          if (items.isEmpty) {
            return Center(child: Text(s.t('noCases'), style: const TextStyle(color: AppColors.inkMuted)));
          }
          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: items.length,
            separatorBuilder: (_, __) => const SizedBox(height: 10),
            itemBuilder: (_, i) {
              final c = items[i];
              final date = c.createdAt != null ? DateFormat.yMMMd().format(c.createdAt!) : '';
              return Card(
                child: ListTile(
                  title: Text(c.complaintCode, style: const TextStyle(fontWeight: FontWeight.w700)),
                  subtitle: Text('${c.statusLabel ?? '—'} · $date\n${c.title}'),
                  isThreeLine: true,
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => context.push('/complaints/${c.complaintCode}'),
                ),
              );
            },
          );
        },
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => context.go('/report'),
        backgroundColor: AppColors.green,
        icon: const Icon(Icons.add),
        label: Text(s.t('report')),
      ),
    );
  }
}
