import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/config/providers.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/widgets/djibouti_header.dart';
import '../../auth/application/auth_controller.dart';

class SplashScreen extends ConsumerStatefulWidget {
  const SplashScreen({super.key});

  @override
  ConsumerState<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends ConsumerState<SplashScreen> {
  Timer? _failsafe;

  @override
  void initState() {
    super.initState();
    _failsafe = Timer(const Duration(seconds: 11), _goLogin);
    WidgetsBinding.instance.addPostFrameCallback((_) => _maybeLeaveSplash());
  }

  @override
  void dispose() {
    _failsafe?.cancel();
    super.dispose();
  }

  void _goLogin() {
    if (!mounted) return;
    context.go('/login');
  }

  void _maybeLeaveSplash() {
    final auth = ref.read(authControllerProvider);
    if (!auth.isLoading) {
      _failsafe?.cancel();
      final user = auth.valueOrNull;
      context.go(user != null ? '/home' : '/login');
    }
  }

  @override
  Widget build(BuildContext context) {
    final config = ref.watch(appConfigProvider);
    final s = ref.watch(stringsProvider);

    ref.listen(authControllerProvider, (previous, next) {
      if (next.isLoading) return;
      _failsafe?.cancel();
      if (!context.mounted) return;
      final user = next.valueOrNull;
      context.go(user != null ? '/home' : '/login');
    });

    return Scaffold(
      body: Column(
        children: [
          DjiboutiHeader(title: config.appName, subtitle: s.t('appTagline')),
          Expanded(
            child: Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const CircularProgressIndicator(color: AppColors.green),
                  const SizedBox(height: 16),
                  Text(s.t('loading'), style: const TextStyle(color: AppColors.inkMuted)),
                  if (!config.isProduction) ...[
                    const SizedBox(height: 12),
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 24),
                      child: Text(
                        config.apiBaseUrl,
                        textAlign: TextAlign.center,
                        style: const TextStyle(fontSize: 11, color: AppColors.inkMuted),
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
