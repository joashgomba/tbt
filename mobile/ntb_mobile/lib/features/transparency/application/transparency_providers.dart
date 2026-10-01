import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/transparency_repository.dart';
import '../domain/transparency_models.dart';

class TransparencyFilters {
  const TransparencyFilters({this.year});

  final int? year;

  Map<String, dynamic> toQuery() {
    if (year == null) return {};
    return {'year': year};
  }

  TransparencyFilters copyWith({int? year}) => TransparencyFilters(year: year ?? this.year);
}

final transparencyFiltersProvider = StateProvider<TransparencyFilters>((ref) {
  return const TransparencyFilters();
});

final transparencyStatsProvider = FutureProvider.autoDispose<TransparencyStats>((ref) async {
  final filters = ref.watch(transparencyFiltersProvider);
  return ref.watch(transparencyRepositoryProvider).stats(filters: filters.toQuery());
});

final transparencyMapProvider = FutureProvider.autoDispose<List<MapPoint>>((ref) async {
  final filters = ref.watch(transparencyFiltersProvider);
  return ref.watch(transparencyRepositoryProvider).mapPoints(filters: filters.toQuery());
});

final transparencyYearsProvider = FutureProvider<TransparencyLookups>((ref) async {
  return ref.watch(transparencyRepositoryProvider).lookups();
});
