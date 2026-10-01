class TransparencyStats {
  TransparencyStats({
    required this.totalReceived,
    required this.resolved,
    required this.resolutionRate,
    this.avgResolutionDays,
    required this.byStatusGroup,
    required this.byCategory,
  });

  final int totalReceived;
  final int resolved;
  final double resolutionRate;
  final double? avgResolutionDays;
  final Map<String, int> byStatusGroup;
  final List<CategoryCount> byCategory;

  factory TransparencyStats.fromJson(Map<String, dynamic> json) {
    final summary = json['summary'] as Map<String, dynamic>? ?? {};
    final groups = json['by_status_group'] as Map<String, dynamic>? ?? {};
    return TransparencyStats(
      totalReceived: summary['total_received'] as int? ?? 0,
      resolved: summary['resolved'] as int? ?? 0,
      resolutionRate: (summary['resolution_rate'] as num?)?.toDouble() ?? 0,
      avgResolutionDays: (summary['avg_resolution_days'] as num?)?.toDouble(),
      byStatusGroup: groups.map((k, v) => MapEntry(k, v as int? ?? 0)),
      byCategory: (json['by_category'] as List<dynamic>? ?? [])
          .map((e) => CategoryCount.fromJson(e as Map<String, dynamic>))
          .toList(),
    );
  }
}

class CategoryCount {
  CategoryCount({required this.label, required this.count});

  final String label;
  final int count;

  factory CategoryCount.fromJson(Map<String, dynamic> json) {
    final cat = json['category'];
    var label = '—';
    if (cat is Map) {
      label = cat['name_en'] as String? ?? cat['code'] as String? ?? '—';
    }
    return CategoryCount(label: label, count: json['count'] as int? ?? 0);
  }
}

class MapPoint {
  MapPoint({
    required this.label,
    required this.count,
    required this.latitude,
    required this.longitude,
  });

  final String label;
  final int count;
  final double latitude;
  final double longitude;

  factory MapPoint.fromJson(Map<String, dynamic> json) {
    return MapPoint(
      label: json['label'] as String? ?? '',
      count: json['count'] as int? ?? 0,
      latitude: (json['latitude'] as num?)?.toDouble() ?? 0,
      longitude: (json['longitude'] as num?)?.toDouble() ?? 0,
    );
  }
}

class TransparencyLookups {
  TransparencyLookups({required this.years});

  final List<int> years;

  factory TransparencyLookups.fromJson(Map<String, dynamic> json) {
    return TransparencyLookups(
      years: (json['years'] as List<dynamic>? ?? []).map((e) => e as int).toList(),
    );
  }
}
