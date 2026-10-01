import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/complaints_repository.dart';
import '../domain/complaint_models.dart';

final lookupsProvider = FutureProvider<LookupsBundle>((ref) async {
  return ref.watch(complaintsRepositoryProvider).fetchLookups();
});

final myComplaintsProvider = FutureProvider.autoDispose<List<ComplaintSummary>>((ref) async {
  return ref.watch(complaintsRepositoryProvider).listMine();
});
