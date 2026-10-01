import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/config/providers.dart';
import 'core/l10n/app_strings.dart';
import 'core/network/connectivity_service.dart';
import 'core/push/push_service.dart';
import 'core/router/app_router.dart';
import 'core/theme/app_colors.dart';
import 'core/theme/app_theme.dart';
import 'features/auth/application/auth_controller.dart';

class NtbApp extends ConsumerStatefulWidget {
  const NtbApp({super.key});

  @override
  ConsumerState<NtbApp> createState() => _NtbAppState();
}

class _NtbAppState extends ConsumerState<NtbApp> {
  @override
  void initState() {
    super.initState();
    Future.microtask(() => ref.read(pushServiceProvider).initialize());
  }

  @override
  Widget build(BuildContext context) {
    final config = ref.watch(appConfigProvider);
    final router = ref.watch(appRouterProvider);
    final locale = ref.watch(localeProvider);
    final online = ref.watch(isOnlineProvider);
    final s = ref.watch(stringsProvider);

    ref.listen(authControllerProvider, (previous, next) {
      next.whenData((user) {
        if (user != null) {
          ref.read(pushServiceProvider).syncTokenAfterLogin();
        }
      });
    });

    return MaterialApp.router(
      title: config.appName,
      theme: AppTheme.light(),
      routerConfig: router,
      locale: locale == AppLocale.fr ? const Locale('fr') : const Locale('en'),
      supportedLocales: const [Locale('en'), Locale('fr')],
      localizationsDelegates: const [
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      debugShowCheckedModeBanner: !config.isProduction,
      builder: (context, child) {
        return Column(
          children: [
            if (!online)
              Material(
                color: AppColors.warning.withOpacity(0.35),
                child: SafeArea(
                  bottom: false,
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                    child: Text(s.t('offlineBanner'), style: const TextStyle(fontSize: 13)),
                  ),
                ),
              ),
            Expanded(child: child ?? const SizedBox.shrink()),
          ],
        );
      },
    );
  }
}
