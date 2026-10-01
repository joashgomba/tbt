import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/auth/application/auth_controller.dart';
import '../../features/auth/presentation/login_screen.dart';
import '../../features/auth/presentation/register_screen.dart';
import '../../features/complaints/presentation/complaint_detail_screen.dart';
import '../../features/complaints/presentation/my_complaints_screen.dart';
import '../../features/complaints/presentation/submit_complaint_screen.dart';
import '../../features/complaints/presentation/track_complaint_screen.dart';
import '../../features/home/presentation/help_screen.dart';
import '../../features/home/presentation/home_screen.dart';
import '../../features/home/presentation/splash_screen.dart';
import '../../features/profile/presentation/profile_screen.dart';
import '../../features/transparency/presentation/transparency_screen.dart';
import '../widgets/app_shell.dart';

final _rootNavigatorKey = GlobalKey<NavigatorState>(debugLabel: 'root');
final appRouterProvider = Provider<GoRouter>((ref) {
  final auth = ref.watch(authControllerProvider);

  return GoRouter(
    navigatorKey: _rootNavigatorKey,
    initialLocation: '/login',
    refreshListenable: _RouterRefresh(ref),
    redirect: (context, state) {
      final path = state.matchedLocation;
      final isSplash = path == '/splash';
      final isAuthRoute = path == '/login' || path == '/register';

      // Never block on splash — open login while session restore runs in background.
      if (isSplash && !auth.isLoading) {
        final user = auth.valueOrNull;
        return user != null ? '/home' : '/login';
      }

      if (auth.hasError && !isAuthRoute) return '/login';

      final user = auth.valueOrNull;
      if (!auth.isLoading && user == null && !isAuthRoute && path != '/track' && path != '/insights') {
        return '/login';
      }
      if (!auth.isLoading && user != null && (isAuthRoute || isSplash)) {
        return '/home';
      }
      return null;
    },
    routes: [
      GoRoute(path: '/splash', builder: (_, __) => const SplashScreen()),
      GoRoute(path: '/login', builder: (_, __) => const LoginScreen()),
      GoRoute(path: '/register', builder: (_, __) => const RegisterScreen()),
      GoRoute(
        path: '/complaints/:code',
        parentNavigatorKey: _rootNavigatorKey,
        builder: (_, state) => ComplaintDetailScreen(code: state.pathParameters['code']!),
      ),
      GoRoute(
        path: '/help',
        parentNavigatorKey: _rootNavigatorKey,
        builder: (_, __) => const HelpScreen(),
      ),
      StatefulShellRoute.indexedStack(
        builder: (_, __, navigationShell) => AppShell(navigationShell: navigationShell),
        branches: [
          StatefulShellBranch(
            routes: [
              GoRoute(path: '/home', builder: (_, __) => const HomeScreen()),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(path: '/report', builder: (_, __) => const SubmitComplaintScreen()),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(path: '/cases', builder: (_, __) => const MyComplaintsScreen()),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(path: '/insights', builder: (_, __) => const TransparencyScreen()),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(path: '/track', builder: (_, __) => const TrackComplaintScreen()),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(path: '/profile', builder: (_, __) => const ProfileScreen()),
            ],
          ),
        ],
      ),
    ],
  );
});

class _RouterRefresh extends ChangeNotifier {
  _RouterRefresh(this.ref) {
    ref.listen(authControllerProvider, (_, __) => notifyListeners());
  }

  final Ref ref;
}
