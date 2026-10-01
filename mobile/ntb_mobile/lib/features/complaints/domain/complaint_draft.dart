import 'dart:convert';

class ComplaintDraft {
  ComplaintDraft({
    required this.id,
    required this.updatedAt,
    required this.step,
    required this.fields,
  });

  final String id;
  final DateTime updatedAt;
  final int step;
  final Map<String, dynamic> fields;

  Map<String, dynamic> toJson() => {
        'id': id,
        'updated_at': updatedAt.toIso8601String(),
        'step': step,
        'fields': fields,
      };

  factory ComplaintDraft.fromJson(Map<String, dynamic> json) {
    return ComplaintDraft(
      id: json['id'] as String,
      updatedAt: DateTime.parse(json['updated_at'] as String),
      step: json['step'] as int? ?? 0,
      fields: Map<String, dynamic>.from(json['fields'] as Map? ?? {}),
    );
  }

  static ComplaintDraft? decode(String? raw) {
    if (raw == null || raw.isEmpty) return null;
    try {
      return ComplaintDraft.fromJson(jsonDecode(raw) as Map<String, dynamic>);
    } catch (_) {
      return null;
    }
  }
}
