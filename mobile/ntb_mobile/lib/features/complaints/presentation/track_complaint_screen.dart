import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/app_strings.dart';
import 'complaint_detail_screen.dart';

class TrackComplaintScreen extends ConsumerStatefulWidget {
  const TrackComplaintScreen({super.key});

  @override
  ConsumerState<TrackComplaintScreen> createState() => _TrackComplaintScreenState();
}

class _TrackComplaintScreenState extends ConsumerState<TrackComplaintScreen> {
  final _code = TextEditingController();

  @override
  void dispose() {
    _code.dispose();
    super.dispose();
  }

  void _lookup() {
    final code = _code.text.trim().toUpperCase();
    if (code.isEmpty) return;
    Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => ComplaintDetailScreen(code: code, publicTrack: true)),
    );
  }

  @override
  Widget build(BuildContext context) {
    final s = ref.watch(stringsProvider);
    return Scaffold(
      appBar: AppBar(title: Text(s.t('track'))),
      body: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(s.t('caseCode'), style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 12),
            TextField(
              controller: _code,
              textCapitalization: TextCapitalization.characters,
              decoration: InputDecoration(hintText: s.t('enterCaseCode')),
              onSubmitted: (_) => _lookup(),
            ),
            const SizedBox(height: 16),
            ElevatedButton(onPressed: _lookup, child: Text(s.t('lookup'))),
            const SizedBox(height: 24),
            OutlinedButton(
              onPressed: () => context.go('/login'),
              child: Text(s.t('login')),
            ),
          ],
        ),
      ),
    );
  }
}
