import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:latlong2/latlong.dart';

import '../../../core/l10n/app_strings.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/widgets/djibouti_header.dart';
import '../application/transparency_providers.dart';
import '../domain/transparency_models.dart';

class TransparencyScreen extends ConsumerWidget {
  const TransparencyScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final s = ref.watch(stringsProvider);
    final statsAsync = ref.watch(transparencyStatsProvider);
    final mapAsync = ref.watch(transparencyMapProvider);
    final yearsAsync = ref.watch(transparencyYearsProvider);
    final filters = ref.watch(transparencyFiltersProvider);

    return DefaultTabController(
      length: 2,
      child: Scaffold(
        body: NestedScrollView(
          headerSliverBuilder: (_, __) => [
            SliverToBoxAdapter(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  DjiboutiHeader(title: s.t('transparencyTitle'), subtitle: s.t('transparencySub'), compact: true),
                  yearsAsync.when(
                    loading: () => const SizedBox.shrink(),
                    error: (_, __) => const SizedBox.shrink(),
                    data: (lookups) {
                      if (lookups.years.isEmpty) return const SizedBox.shrink();
                      return Padding(
                        padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                        child: DropdownButtonFormField<int>(
                          value: filters.year ?? lookups.years.first,
                          decoration: InputDecoration(labelText: s.t('transparencyYear')),
                          items: lookups.years
                              .map((y) => DropdownMenuItem(value: y, child: Text('$y')))
                              .toList(),
                          onChanged: (y) => ref.read(transparencyFiltersProvider.notifier).state =
                              TransparencyFilters(year: y),
                        ),
                      );
                    },
                  ),
                  Material(
                    color: AppColors.white,
                    child: TabBar(
                      labelColor: AppColors.greenDark,
                      indicatorColor: AppColors.green,
                      tabs: [
                        Tab(text: s.t('transparencyStats')),
                        Tab(text: s.t('transparencyMap')),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ],
          body: TabBarView(
            children: [
              statsAsync.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (e, _) => Center(child: Text('$e')),
                data: (stats) => _StatsTab(stats: stats, s: s),
              ),
              mapAsync.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (e, _) => Center(child: Text('$e')),
                data: (points) => _MapTab(points: points),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _StatsTab extends StatelessWidget {
  const _StatsTab({required this.stats, required this.s});

  final TransparencyStats stats;
  final S s;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Row(
          children: [
            Expanded(child: _StatCard(label: s.t('transparencyTotal'), value: '${stats.totalReceived}')),
            const SizedBox(width: 10),
            Expanded(child: _StatCard(label: s.t('transparencyResolved'), value: '${stats.resolved}')),
          ],
        ),
        const SizedBox(height: 10),
        _StatCard(
          label: s.t('transparencyRate'),
          value: '${stats.resolutionRate.toStringAsFixed(1)}%',
          wide: true,
        ),
        if (stats.avgResolutionDays != null) ...[
          const SizedBox(height: 10),
          _StatCard(
            label: s.t('transparencyAvgDays'),
            value: stats.avgResolutionDays!.toStringAsFixed(1),
            wide: true,
          ),
        ],
        const SizedBox(height: 20),
        Text(s.t('transparencyByStatus'), style: Theme.of(context).textTheme.titleSmall),
        const SizedBox(height: 8),
        ...stats.byStatusGroup.entries.map(
          (e) => Card(
            child: ListTile(
              title: Text(e.key.replaceAll('_', ' ')),
              trailing: Text('${e.value}', style: const TextStyle(fontWeight: FontWeight.bold)),
            ),
          ),
        ),
        const SizedBox(height: 12),
        Text(s.t('transparencyByCategory'), style: Theme.of(context).textTheme.titleSmall),
        ...stats.byCategory.map(
          (c) => Card(
            child: ListTile(
              title: Text(c.label),
              trailing: Text('${c.count}', style: const TextStyle(fontWeight: FontWeight.bold)),
            ),
          ),
        ),
      ],
    );
  }
}

class _StatCard extends StatelessWidget {
  const _StatCard({required this.label, required this.value, this.wide = false});

  final String label;
  final String value;
  final bool wide;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(label, style: const TextStyle(color: AppColors.inkMuted, fontSize: 13)),
            const SizedBox(height: 6),
            Text(value, style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w700, color: AppColors.greenDark)),
          ],
        ),
      ),
    );
  }
}

class _MapTab extends StatelessWidget {
  const _MapTab({required this.points});

  final List<MapPoint> points;

  @override
  Widget build(BuildContext context) {
    if (points.isEmpty) {
      return const Center(child: Text('No map data for selected filters'));
    }
    final center = LatLng(points.first.latitude, points.first.longitude);

    return FlutterMap(
      options: MapOptions(initialCenter: center, initialZoom: 5.5),
      children: [
        TileLayer(
          urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
          userAgentPackageName: 'dj.ntb.portal.ntb_mobile',
        ),
        MarkerLayer(
          markers: points.map<Marker>((p) {
            return Marker(
              point: LatLng(p.latitude, p.longitude),
              width: 48,
              height: 48,
              child: Tooltip(
                message: '${p.label} (${p.count})',
                child: CircleAvatar(
                  backgroundColor: AppColors.green,
                  child: Text('${p.count}', style: const TextStyle(color: Colors.white, fontSize: 12)),
                ),
              ),
            );
          }).toList(),
        ),
      ],
    );
  }
}
