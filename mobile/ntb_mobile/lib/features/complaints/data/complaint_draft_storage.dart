import 'dart:convert';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:uuid/uuid.dart';

import '../domain/complaint_draft.dart';

const _draftKey = 'ntb_complaint_draft_v1';

final complaintDraftStorageProvider = Provider<ComplaintDraftStorage>((ref) {
  return ComplaintDraftStorage();
});

class ComplaintDraftStorage {
  Future<ComplaintDraft?> load() async {
    final prefs = await SharedPreferences.getInstance();
    return ComplaintDraft.decode(prefs.getString(_draftKey));
  }

  Future<void> save({
    required String? existingId,
    required int step,
    required Map<String, dynamic> fields,
  }) async {
    final prefs = await SharedPreferences.getInstance();
    final draft = ComplaintDraft(
      id: existingId ?? const Uuid().v4(),
      updatedAt: DateTime.now(),
      step: step,
      fields: fields,
    );
    await prefs.setString(_draftKey, jsonEncode(draft.toJson()));
  }

  Future<void> clear() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_draftKey);
  }
}
