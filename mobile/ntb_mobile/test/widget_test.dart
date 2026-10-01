import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ntb_mobile/app.dart';
import 'package:ntb_mobile/core/config/app_config.dart';
import 'package:ntb_mobile/core/config/providers.dart';

void main() {
  testWidgets('App boots with local config', (tester) async {
    final config = AppConfig.load();
    await tester.pumpWidget(
      ProviderScope(
        overrides: [appConfigProvider.overrideWithValue(config)],
        child: const NtbApp(),
      ),
    );
    await tester.pump();
    expect(find.byType(NtbApp), findsOneWidget);
  });
}
