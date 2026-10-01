import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/l10n/app_strings.dart';

class HelpScreen extends ConsumerWidget {
  const HelpScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final s = ref.watch(stringsProvider);
    return Scaffold(
      appBar: AppBar(title: Text(s.t('help'))),
      body: Padding(
        padding: const EdgeInsets.all(20),
        child: Text(s.t('learnBody'), style: Theme.of(context).textTheme.bodyLarge),
      ),
    );
  }
}
