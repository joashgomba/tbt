import 'package:flutter/material.dart';

import '../theme/app_colors.dart';

class SelectOption<T> {
  const SelectOption({required this.value, required this.label});

  final T value;
  final String label;
}

/// Tap field that opens a searchable bottom sheet — avoids dropdown overflow on long labels.
class SearchableSelectField<T> extends StatelessWidget {
  const SearchableSelectField({
    super.key,
    required this.label,
    required this.options,
    required this.value,
    required this.onChanged,
    this.searchHint = 'Search…',
    this.enabled = true,
  });

  final String label;
  final List<SelectOption<T>> options;
  final T? value;
  final ValueChanged<T?> onChanged;
  final String searchHint;
  final bool enabled;

  String? get _displayLabel {
    if (value == null) return null;
    for (final o in options) {
      if (o.value == value) return o.label;
    }
    return null;
  }

  Future<void> _open(BuildContext context) async {
    if (!enabled) return;
    final picked = await showModalBottomSheet<T>(
      context: context,
      isScrollControlled: true,
      backgroundColor: AppColors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) => _SearchSheet<T>(
        title: label,
        options: options,
        searchHint: searchHint,
        selected: value,
      ),
    );
    if (picked != null) {
      onChanged(picked);
    }
  }

  @override
  Widget build(BuildContext context) {
    final display = _displayLabel;
    return Material(
      color: AppColors.white,
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: enabled ? () => _open(context) : null,
        child: InputDecorator(
          decoration: InputDecoration(
            labelText: label,
            suffixIcon: const Icon(Icons.expand_more, color: AppColors.inkMuted),
            enabled: enabled,
          ),
          child: Text(
            display ?? '—',
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              fontSize: 16,
              color: display == null ? AppColors.inkMuted : AppColors.ink,
              fontWeight: display != null ? FontWeight.w500 : FontWeight.normal,
            ),
          ),
        ),
      ),
    );
  }
}

class _SearchSheet<T> extends StatefulWidget {
  const _SearchSheet({
    required this.title,
    required this.options,
    required this.searchHint,
    this.selected,
  });

  final String title;
  final List<SelectOption<T>> options;
  final String searchHint;
  final T? selected;

  @override
  State<_SearchSheet<T>> createState() => _SearchSheetState<T>();
}

class _SearchSheetState<T> extends State<_SearchSheet<T>> {
  final _query = TextEditingController();
  List<SelectOption<T>> _filtered = [];

  @override
  void initState() {
    super.initState();
    _filtered = widget.options;
    _query.addListener(_filter);
  }

  @override
  void dispose() {
    _query.dispose();
    super.dispose();
  }

  void _filter() {
    final q = _query.text.trim().toLowerCase();
    setState(() {
      _filtered = q.isEmpty
          ? widget.options
          : widget.options.where((o) => o.label.toLowerCase().contains(q)).toList();
    });
  }

  @override
  Widget build(BuildContext context) {
    final maxH = MediaQuery.sizeOf(context).height * 0.75;
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
      child: SizedBox(
        height: maxH,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 16, 12, 8),
              child: Row(
                children: [
                  Expanded(
                    child: Text(widget.title, style: Theme.of(context).textTheme.titleMedium),
                  ),
                  IconButton(onPressed: () => Navigator.pop(context), icon: const Icon(Icons.close)),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: TextField(
                controller: _query,
                autofocus: true,
                decoration: InputDecoration(
                  hintText: widget.searchHint,
                  prefixIcon: const Icon(Icons.search, color: AppColors.green),
                ),
              ),
            ),
            const SizedBox(height: 8),
            Expanded(
              child: _filtered.isEmpty
                  ? Center(child: Text(widget.searchHint, style: const TextStyle(color: AppColors.inkMuted)))
                  : ListView.separated(
                      itemCount: _filtered.length,
                      separatorBuilder: (_, __) => Divider(height: 1, color: AppColors.inkMuted.withOpacity(0.12)),
                      itemBuilder: (context, i) {
                        final opt = _filtered[i];
                        final selected = opt.value == widget.selected;
                        return ListTile(
                          title: Text(opt.label, maxLines: 3, overflow: TextOverflow.ellipsis),
                          trailing: selected ? const Icon(Icons.check_circle, color: AppColors.green) : null,
                          onTap: () => Navigator.pop(context, opt.value),
                        );
                      },
                    ),
            ),
          ],
        ),
      ),
    );
  }
}
