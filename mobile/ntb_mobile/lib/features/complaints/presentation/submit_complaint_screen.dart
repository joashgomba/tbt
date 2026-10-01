import 'dart:async';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../core/l10n/app_strings.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/network/connectivity_service.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/widgets/searchable_select_field.dart';
import '../application/lookups_provider.dart';
import '../data/complaint_draft_storage.dart';
import '../data/complaints_repository.dart';
import '../domain/complaint_models.dart';

class SubmitComplaintScreen extends ConsumerStatefulWidget {
  const SubmitComplaintScreen({super.key});

  @override
  ConsumerState<SubmitComplaintScreen> createState() => _SubmitComplaintScreenState();
}

class _SubmitComplaintScreenState extends ConsumerState<SubmitComplaintScreen> {
  int _step = 0;
  bool _submitting = false;

  int? _categoryId;
  final _description = TextEditingController();
  DateTime? _occurrenceDate;
  String? _countryAffected;
  String? _countryOrigin;
  String? _countryDestination;
  int? _corridorId;
  int? _locationId;
  final _productDescription = TextEditingController();
  String? _productOrigin;
  String? _currency;
  final _valueAmount = TextEditingController();
  List<PlatformFile> _files = [];
  String? _draftId;
  Timer? _saveDebounce;
  bool _draftLoaded = false;

  @override
  void initState() {
    super.initState();
    _description.addListener(_scheduleDraftSave);
    _productDescription.addListener(_scheduleDraftSave);
    _valueAmount.addListener(_scheduleDraftSave);
    WidgetsBinding.instance.addPostFrameCallback((_) => _maybeRestoreDraft());
  }

  @override
  void dispose() {
    _saveDebounce?.cancel();
    _description.dispose();
    _productDescription.dispose();
    _valueAmount.dispose();
    super.dispose();
  }

  void _scheduleDraftSave() {
    _saveDebounce?.cancel();
    _saveDebounce = Timer(const Duration(milliseconds: 600), _persistDraft);
  }

  Map<String, dynamic> _draftFields() {
    return {
      'category_id': _categoryId,
      'description': _description.text,
      'occurrence_date': _occurrenceDate?.toIso8601String(),
      'country_affected': _countryAffected,
      'country_origin': _countryOrigin,
      'country_destination': _countryDestination,
      'corridor_id': _corridorId,
      'location_id': _locationId,
      'product_description': _productDescription.text,
      'product_origin': _productOrigin,
      'currency': _currency,
      'value_amount': _valueAmount.text,
    };
  }

  Future<void> _persistDraft() async {
    if (!_draftLoaded) return;
    await ref.read(complaintDraftStorageProvider).save(
          existingId: _draftId,
          step: _step,
          fields: _draftFields(),
        );
  }

  Future<void> _maybeRestoreDraft() async {
    final draft = await ref.read(complaintDraftStorageProvider).load();
    if (draft == null || !mounted) {
      setState(() => _draftLoaded = true);
      return;
    }
    final s = ref.read(stringsProvider);
    final restore = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(s.t('draftRestoreTitle')),
        content: Text(s.t('draftRestoreBody')),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: Text(s.t('draftDiscard'))),
          ElevatedButton(onPressed: () => Navigator.pop(ctx, true), child: Text(s.t('draftContinue'))),
        ],
      ),
    );
    if (restore != true) {
      await ref.read(complaintDraftStorageProvider).clear();
      setState(() => _draftLoaded = true);
      return;
    }
    final f = draft.fields;
    setState(() {
      _draftId = draft.id;
      _step = draft.step;
      _categoryId = f['category_id'] as int?;
      _description.text = f['description'] as String? ?? '';
      _occurrenceDate = f['occurrence_date'] != null ? DateTime.tryParse(f['occurrence_date'] as String) : null;
      _countryAffected = f['country_affected'] as String?;
      _countryOrigin = f['country_origin'] as String?;
      _countryDestination = f['country_destination'] as String?;
      _corridorId = f['corridor_id'] as int?;
      _locationId = f['location_id'] as int?;
      _productDescription.text = f['product_description'] as String? ?? '';
      _productOrigin = f['product_origin'] as String?;
      _currency = f['currency'] as String?;
      _valueAmount.text = f['value_amount'] as String? ?? '';
      _draftLoaded = true;
    });
  }

  bool get fr => ref.read(localeProvider) == AppLocale.fr;

  Future<void> _pickDate() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: _occurrenceDate ?? now,
      firstDate: DateTime(now.year - 5),
      lastDate: now,
    );
    if (picked != null) setState(() => _occurrenceDate = picked);
  }

  Future<void> _pickFiles(LookupsBundle lookups) async {
    final result = await FilePicker.platform.pickFiles(
      allowMultiple: true,
      type: FileType.custom,
      allowedExtensions: const ['pdf', 'jpg', 'jpeg', 'png', 'docx'],
    );
    if (result == null) return;
    setState(() {
      _files = result.files.take(lookups.maxAttachments).toList();
    });
  }

  Map<String, dynamic> _payload() {
    return {
      'description': _description.text.trim(),
      'occurrence_date': DateFormat('yyyy-MM-dd').format(_occurrenceDate!),
      'country_affected': _countryAffected,
      'ntb_category_id': _categoryId,
      'country_origin': _countryOrigin,
      'country_destination': _countryDestination,
      'corridor_id': _corridorId,
      'location_id': _locationId,
      if (_productDescription.text.trim().isNotEmpty) 'product_description': _productDescription.text.trim(),
      if (_productOrigin != null && _productOrigin!.isNotEmpty) 'product_country_origin': _productOrigin,
      if (_currency != null && _currency!.isNotEmpty) 'product_value_currency': _currency,
      if (_valueAmount.text.trim().isNotEmpty) 'product_value_amount': double.tryParse(_valueAmount.text.trim()),
    };
  }

  Future<void> _submit({bool confirmDuplicate = false}) async {
    final online = ref.read(isOnlineProvider);
    final s = ref.read(stringsProvider);
    if (!online) {
      await _persistDraft();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(s.t('draftSaved'))));
      }
      return;
    }
    setState(() => _submitting = true);
    try {
      final detail = await ref.read(complaintsRepositoryProvider).submit(
            payload: _payload(),
            files: _files,
            confirmDuplicate: confirmDuplicate,
          );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('${s.t('submit')}: ${detail.complaintCode}')),
      );
      await ref.read(complaintDraftStorageProvider).clear();
      ref.invalidate(myComplaintsProvider);
      if (!mounted) return;
      final code = detail.complaintCode;
      context.go('/cases');
      context.push('/complaints/$code');
    } on ApiException catch (e) {
      if (e.isDuplicateWarning && mounted) {
        final ok = await showDialog<bool>(
          context: context,
          builder: (ctx) => AlertDialog(
            title: Text(s.t('duplicateTitle')),
            content: Text(s.t('duplicateBody')),
            actions: [
              TextButton(onPressed: () => Navigator.pop(ctx, false), child: Text(s.t('cancel'))),
              ElevatedButton(onPressed: () => Navigator.pop(ctx, true), child: Text(s.t('submitAnyway'))),
            ],
          ),
        );
        if (ok == true) await _submit(confirmDuplicate: true);
      } else if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(s.t('errorGeneric'))));
      }
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  bool _validateStep(int step, LookupsBundle lookups) {
    switch (step) {
      case 0:
        return _categoryId != null && _description.text.trim().length >= 10 && _occurrenceDate != null && _countryAffected != null;
      case 1:
        return _countryOrigin != null && _countryDestination != null && _corridorId != null && _locationId != null;
      default:
        return true;
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = ref.watch(stringsProvider);
    final lookupsAsync = ref.watch(lookupsProvider);

    return Scaffold(
      appBar: AppBar(title: Text(s.t('report')), elevation: 0),
      body: lookupsAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(child: Text('$e')),
        data: (lookups) {
          final countryNames = lookups.countries.map((c) => c.label(fr)).toList();
          final locations = lookups.locations
              .where((l) => _corridorId == null || l.corridorId == _corridorId)
              .toList();

          final searchHint = s.t('searchHint');

          String labelForCategory(int? id) {
            if (id == null) return '';
            for (final c in lookups.categories) {
              if (c.id == id) return c.label(fr);
            }
            return '$id';
          }

          String labelForCorridor(int? id) {
            if (id == null) return '';
            for (final c in lookups.corridors) {
              if (c.id == id) return c.label(fr);
            }
            return '$id';
          }

          String labelForLocation(int? id) {
            if (id == null) return '';
            for (final l in lookups.locations) {
              if (l.id == id) return l.label(fr);
            }
            return '$id';
          }

          Widget stepTitle(String text) => Text(text, maxLines: 2, overflow: TextOverflow.ellipsis);

          return SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
            child: Stepper(
            type: StepperType.vertical,
            currentStep: _step,
            onStepContinue: () {
              if (!_validateStep(_step, lookups)) {
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(content: Text(s.t('errorGeneric'))),
                );
                return;
              }
              if (_step < 3) {
                setState(() => _step++);
                _scheduleDraftSave();
              } else {
                _submit();
              }
            },
            onStepCancel: () {
              if (_step > 0) {
                setState(() => _step--);
                _scheduleDraftSave();
              }
            },
            controlsBuilder: (context, details) {
              return Padding(
                padding: const EdgeInsets.only(top: 12),
                child: Row(
                  children: [
                    ElevatedButton(
                      onPressed: _submitting ? null : details.onStepContinue,
                      child: Text(_step == 3 ? s.t('submit') : s.t('next')),
                    ),
                    if (_step > 0) ...[
                      const SizedBox(width: 12),
                      OutlinedButton(onPressed: details.onStepCancel, child: Text(s.t('back'))),
                    ],
                  ],
                ),
              );
            },
            steps: [
              Step(
                title: stepTitle(s.t('category')),
                isActive: _step >= 0,
                state: _step > 0 ? StepState.complete : StepState.indexed,
                content: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    SearchableSelectField<int>(
                      label: s.t('category'),
                      searchHint: searchHint,
                      value: _categoryId,
                      options: lookups.categories
                          .map((c) => SelectOption(value: c.id, label: c.label(fr)))
                          .toList(),
                      onChanged: (v) {
                        setState(() => _categoryId = v);
                        _scheduleDraftSave();
                      },
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: _description,
                      maxLines: 5,
                      decoration: InputDecoration(labelText: s.t('description')),
                    ),
                    const SizedBox(height: 12),
                    Material(
                      color: AppColors.white,
                      borderRadius: BorderRadius.circular(12),
                      child: ListTile(
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                          side: BorderSide(color: AppColors.inkMuted.withOpacity(0.25)),
                        ),
                        title: Text(s.t('occurrenceDate')),
                        subtitle: Text(_occurrenceDate == null ? '—' : DateFormat.yMMMd().format(_occurrenceDate!)),
                        trailing: IconButton(
                          icon: const Icon(Icons.calendar_today, color: AppColors.green),
                          onPressed: _pickDate,
                        ),
                      ),
                    ),
                    const SizedBox(height: 12),
                    SearchableSelectField<String>(
                      label: s.t('countryAffected'),
                      searchHint: searchHint,
                      value: _countryAffected,
                      options: countryNames.map((c) => SelectOption(value: c, label: c)).toList(),
                      onChanged: (v) {
                        setState(() => _countryAffected = v);
                        _scheduleDraftSave();
                      },
                    ),
                  ],
                ),
              ),
              Step(
                title: stepTitle(s.t('corridor')),
                isActive: _step >= 1,
                state: _step > 1 ? StepState.complete : StepState.indexed,
                content: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    SearchableSelectField<String>(
                      label: s.t('origin'),
                      searchHint: searchHint,
                      value: _countryOrigin,
                      options: countryNames.map((c) => SelectOption(value: c, label: c)).toList(),
                      onChanged: (v) {
                        setState(() => _countryOrigin = v);
                        _scheduleDraftSave();
                      },
                    ),
                    const SizedBox(height: 12),
                    SearchableSelectField<String>(
                      label: s.t('destination'),
                      searchHint: searchHint,
                      value: _countryDestination,
                      options: countryNames.map((c) => SelectOption(value: c, label: c)).toList(),
                      onChanged: (v) {
                        setState(() => _countryDestination = v);
                        _scheduleDraftSave();
                      },
                    ),
                    const SizedBox(height: 12),
                    SearchableSelectField<int>(
                      label: s.t('corridor'),
                      searchHint: searchHint,
                      value: _corridorId,
                      options: lookups.corridors
                          .map((c) => SelectOption(value: c.id, label: c.label(fr)))
                          .toList(),
                      onChanged: (v) {
                        setState(() {
                          _corridorId = v;
                          _locationId = null;
                        });
                        _scheduleDraftSave();
                      },
                    ),
                    const SizedBox(height: 12),
                    SearchableSelectField<int>(
                      label: s.t('location'),
                      searchHint: searchHint,
                      value: _locationId,
                      options: locations.map((l) => SelectOption(value: l.id, label: l.label(fr))).toList(),
                      onChanged: (v) {
                        setState(() => _locationId = v);
                        _scheduleDraftSave();
                      },
                    ),
                  ],
                ),
              ),
              Step(
                title: stepTitle(s.t('productOptional')),
                isActive: _step >= 2,
                state: _step > 2 ? StepState.complete : StepState.indexed,
                content: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    TextField(
                      controller: _productDescription,
                      maxLines: 3,
                      decoration: InputDecoration(labelText: s.t('productDescription')),
                    ),
                    const SizedBox(height: 12),
                    SearchableSelectField<String>(
                      label: s.t('productOrigin'),
                      searchHint: searchHint,
                      value: (_productOrigin == null || _productOrigin!.isEmpty) ? null : _productOrigin,
                      options: countryNames.map((c) => SelectOption(value: c, label: c)).toList(),
                      onChanged: (v) {
                        setState(() => _productOrigin = v);
                        _scheduleDraftSave();
                      },
                    ),
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        Expanded(
                          child: SearchableSelectField<String>(
                            label: s.t('currency'),
                            searchHint: searchHint,
                            value: (_currency == null || _currency!.isEmpty) ? null : _currency,
                            options: lookups.currencies.map((c) => SelectOption(value: c, label: c)).toList(),
                            onChanged: (v) {
                              setState(() => _currency = v);
                              _scheduleDraftSave();
                            },
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: TextField(
                            controller: _valueAmount,
                            keyboardType: TextInputType.number,
                            decoration: InputDecoration(labelText: s.t('valueAmount')),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    OutlinedButton.icon(
                      onPressed: () => _pickFiles(lookups),
                      icon: const Icon(Icons.attach_file),
                      label: Text('${s.t('attachments')} (${_files.length})'),
                    ),
                  ],
                ),
              ),
              Step(
                title: stepTitle(s.t('review')),
                isActive: _step >= 3,
                content: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(_description.text.trim(), style: const TextStyle(fontWeight: FontWeight.w600)),
                    const SizedBox(height: 8),
                    Text('${s.t('category')}: ${labelForCategory(_categoryId)}'),
                    Text('${s.t('countryAffected')}: ${_countryAffected ?? '—'}'),
                    Text('${s.t('corridor')}: ${labelForCorridor(_corridorId)} · ${s.t('location')}: ${labelForLocation(_locationId)}'),
                    if (_files.isNotEmpty) Text('${s.t('attachments')}: ${_files.map((f) => f.name).join(', ')}'),
                    if (_submitting) const Padding(padding: EdgeInsets.only(top: 16), child: LinearProgressIndicator(color: AppColors.green)),
                  ],
                ),
              ),
            ],
            ),
          );
        },
      ),
    );
  }
}
