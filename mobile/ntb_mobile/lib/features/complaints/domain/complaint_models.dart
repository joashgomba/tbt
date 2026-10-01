class LookupsBundle {
  LookupsBundle({
    required this.categories,
    required this.corridors,
    required this.locations,
    required this.countries,
    required this.currencies,
    required this.maxAttachments,
    required this.maxAttachmentSizeKb,
  });

  final List<LookupCategory> categories;
  final List<LookupCorridor> corridors;
  final List<LookupLocation> locations;
  final List<CountryOption> countries;
  final List<String> currencies;
  final int maxAttachments;
  final int maxAttachmentSizeKb;

  factory LookupsBundle.fromJson(Map<String, dynamic> json) {
    final settings = json['settings'] as Map<String, dynamic>? ?? {};
    return LookupsBundle(
      categories: (json['categories'] as List<dynamic>? ?? [])
          .map((e) => LookupCategory.fromJson(e as Map<String, dynamic>))
          .toList(),
      corridors: (json['corridors'] as List<dynamic>? ?? [])
          .map((e) => LookupCorridor.fromJson(e as Map<String, dynamic>))
          .toList(),
      locations: (json['locations'] as List<dynamic>? ?? [])
          .map((e) => LookupLocation.fromJson(e as Map<String, dynamic>))
          .toList(),
      countries: (json['countries'] as List<dynamic>? ?? [])
          .map((e) => CountryOption.fromJson(e as Map<String, dynamic>))
          .toList(),
      currencies: (json['currencies'] as List<dynamic>? ?? []).map((e) => e.toString()).toList(),
      maxAttachments: settings['max_attachments'] as int? ?? 5,
      maxAttachmentSizeKb: settings['max_attachment_size_kb'] as int? ?? 5120,
    );
  }
}

class CountryOption {
  CountryOption({required this.nameEn, required this.nameFr});

  final String nameEn;
  final String nameFr;

  factory CountryOption.fromJson(Map<String, dynamic> json) {
    return CountryOption(
      nameEn: json['name_en'] as String? ?? json['code'] as String? ?? '',
      nameFr: json['name_fr'] as String? ?? json['name_en'] as String? ?? '',
    );
  }

  String label(bool fr) => fr ? nameFr : nameEn;
}

class LookupCategory {
  LookupCategory({required this.id, required this.code, required this.nameEn, required this.nameFr});

  final int id;
  final String code;
  final String nameEn;
  final String nameFr;

  factory LookupCategory.fromJson(Map<String, dynamic> json) {
    return LookupCategory(
      id: json['id'] as int,
      code: json['code'] as String? ?? '',
      nameEn: json['name_en'] as String? ?? json['name'] as String? ?? '',
      nameFr: json['name_fr'] as String? ?? json['name_en'] as String? ?? '',
    );
  }

  String label(bool fr) => fr ? nameFr : nameEn;
}

class LookupCorridor {
  LookupCorridor({required this.id, required this.code, required this.nameEn, required this.nameFr});

  final int id;
  final String code;
  final String nameEn;
  final String nameFr;

  factory LookupCorridor.fromJson(Map<String, dynamic> json) {
    return LookupCorridor(
      id: json['id'] as int,
      code: json['corridor_code'] as String? ?? '',
      nameEn: json['name_en'] as String? ?? json['corridor_name'] as String? ?? '',
      nameFr: json['name_fr'] as String? ?? json['name_en'] as String? ?? '',
    );
  }

  String label(bool fr) => fr ? nameFr : nameEn;
}

class LookupLocation {
  LookupLocation({
    required this.id,
    required this.nameEn,
    required this.nameFr,
    required this.corridorId,
  });

  final int id;
  final String nameEn;
  final String nameFr;
  final int? corridorId;

  factory LookupLocation.fromJson(Map<String, dynamic> json) {
    return LookupLocation(
      id: json['id'] as int,
      nameEn: json['name_en'] as String? ?? json['location_name'] as String? ?? '',
      nameFr: json['name_fr'] as String? ?? json['name_en'] as String? ?? '',
      corridorId: json['corridor_id'] as int?,
    );
  }

  String label(bool fr) => fr ? nameFr : nameEn;
}

class ComplaintSummary {
  ComplaintSummary({
    required this.complaintCode,
    required this.title,
    required this.description,
    required this.createdAt,
    this.statusLabel,
  });

  final String complaintCode;
  final String title;
  final String description;
  final DateTime? createdAt;
  final String? statusLabel;

  factory ComplaintSummary.fromJson(Map<String, dynamic> json) {
    final status = json['status'];
    String? statusLabel;
    if (status is Map) {
      statusLabel = status['name_en'] as String? ?? status['code'] as String?;
    }
    return ComplaintSummary(
      complaintCode: json['complaint_code'] as String? ?? '',
      title: json['title'] as String? ?? '',
      description: json['description'] as String? ?? '',
      createdAt: DateTime.tryParse(json['created_at']?.toString() ?? ''),
      statusLabel: statusLabel,
    );
  }
}

class ComplaintDetail extends ComplaintSummary {
  ComplaintDetail({
    required super.complaintCode,
    required super.title,
    required super.description,
    required super.createdAt,
    super.statusLabel,
    this.occurrenceDate,
    this.histories = const [],
    this.categoryLabel,
    this.corridorLabel,
    this.locationLabel,
  });

  final String? occurrenceDate;
  final List<Map<String, dynamic>> histories;
  final String? categoryLabel;
  final String? corridorLabel;
  final String? locationLabel;

  factory ComplaintDetail.fromJson(Map<String, dynamic> json) {
    final base = ComplaintSummary.fromJson(json);
    String? cat;
    if (json['category'] is Map) {
      cat = (json['category'] as Map)['name_en'] as String?;
    }
    String? cor;
    if (json['corridor'] is Map) {
      cor = (json['corridor'] as Map)['name_en'] as String?;
    }
    String? loc;
    if (json['location'] is Map) {
      loc = (json['location'] as Map)['name_en'] as String?;
    }
    return ComplaintDetail(
      complaintCode: base.complaintCode,
      title: base.title,
      description: base.description,
      createdAt: base.createdAt,
      statusLabel: base.statusLabel,
      occurrenceDate: json['occurrence_date'] as String?,
      histories: (json['histories'] as List<dynamic>? ?? [])
          .map((e) => Map<String, dynamic>.from(e as Map))
          .toList(),
      categoryLabel: cat,
      corridorLabel: cor,
      locationLabel: loc,
    );
  }
}
