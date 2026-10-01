import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../../core/l10n/app_strings.dart';
import '../../../core/theme/app_colors.dart';
import '../data/complaints_repository.dart';
import '../domain/complaint_models.dart';

class ComplaintDetailScreen extends ConsumerStatefulWidget {
  const ComplaintDetailScreen({super.key, required this.code, this.publicTrack = false});

  final String code;
  final bool publicTrack;

  @override
  ConsumerState<ComplaintDetailScreen> createState() => _ComplaintDetailScreenState();
}

class _ComplaintDetailScreenState extends ConsumerState<ComplaintDetailScreen> {
  ComplaintDetail? _detail;
  Object? _error;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final repo = ref.read(complaintsRepositoryProvider);
      final detail = widget.publicTrack
          ? await repo.trackPublic(widget.code)
          : await repo.getMine(widget.code);
      setState(() => _detail = detail);
    } catch (e) {
      setState(() => _error = e);
    } finally {
      setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = ref.watch(stringsProvider);
    return Scaffold(
      appBar: AppBar(title: Text(widget.code)),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text('$_error'))
              : ListView(
                  padding: const EdgeInsets.all(20),
                  children: [
                    _chip(s.t('status'), _detail!.statusLabel ?? '—'),
                    if (_detail!.categoryLabel != null) _chip(s.t('category'), _detail!.categoryLabel!),
                    if (_detail!.corridorLabel != null) _chip(s.t('corridor'), _detail!.corridorLabel!),
                    if (_detail!.locationLabel != null) _chip(s.t('location'), _detail!.locationLabel!),
                    const SizedBox(height: 16),
                    Text(_detail!.title, style: Theme.of(context).textTheme.titleMedium),
                    const SizedBox(height: 8),
                    Text(_detail!.description),
                    const SizedBox(height: 24),
                    Text(s.t('timeline'), style: Theme.of(context).textTheme.titleSmall),
                    const SizedBox(height: 8),
                    if (_detail!.histories.isEmpty)
                      Text('—', style: TextStyle(color: AppColors.inkMuted.withOpacity(0.8)))
                    else
                      ..._detail!.histories.map((h) {
                        final when = h['action_date']?.toString();
                        final label = h['action_label'] ?? h['notes'] ?? h['action_type'];
                        return ListTile(
                          contentPadding: EdgeInsets.zero,
                          leading: const Icon(Icons.timeline, color: AppColors.green),
                          title: Text(label.toString()),
                          subtitle: when != null ? Text(DateFormat.yMMMd().add_jm().format(DateTime.parse(when))) : null,
                        );
                      }),
                  ],
                ),
    );
  }

  Widget _chip(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(width: 110, child: Text(label, style: const TextStyle(color: AppColors.inkMuted))),
          Expanded(child: Text(value, style: const TextStyle(fontWeight: FontWeight.w600))),
        ],
      ),
    );
  }
}
