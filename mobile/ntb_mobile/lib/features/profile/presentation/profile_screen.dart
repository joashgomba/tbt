import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/config/providers.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/push/push_service.dart';
import '../../auth/application/auth_controller.dart';

class ProfileScreen extends ConsumerStatefulWidget {
  const ProfileScreen({super.key});

  @override
  ConsumerState<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends ConsumerState<ProfileScreen> {
  bool _pushEnabled = true;

  @override
  Widget build(BuildContext context) {
    final s = ref.watch(stringsProvider);
    final config = ref.watch(appConfigProvider);
    final user = ref.watch(authControllerProvider).valueOrNull;
    final locale = ref.watch(localeProvider);

    return Scaffold(
      appBar: AppBar(title: Text(s.t('profile'))),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          if (user != null) ...[
            ListTile(
              title: Text(user.name, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 18)),
              subtitle: Text('${user.email}\n${user.organizationName ?? ''}'),
              isThreeLine: true,
            ),
            const Divider(),
          ],
          ListTile(
            title: Text(s.t('language')),
            trailing: DropdownButton<AppLocale>(
              value: locale,
              items: [
                DropdownMenuItem(value: AppLocale.en, child: Text(s.t('languageEn'))),
                DropdownMenuItem(value: AppLocale.fr, child: Text(s.t('languageFr'))),
              ],
              onChanged: (v) {
                if (v != null) ref.read(localeProvider.notifier).setLocale(v);
              },
            ),
          ),
          ListTile(
            title: Text(s.t('environment')),
            subtitle: Text('${config.environment.name}\n${config.apiBaseUrl}'),
            isThreeLine: true,
          ),
          SwitchListTile(
            title: Text(s.t('pushNotifications')),
            subtitle: Text(s.t('pushNotificationsHint'), style: const TextStyle(fontSize: 12)),
            value: _pushEnabled,
            onChanged: (v) async {
              setState(() => _pushEnabled = v);
              await ref.read(pushServiceProvider).setCaseUpdatesEnabled(v);
            },
          ),
          const SizedBox(height: 24),
          ElevatedButton(
            onPressed: () async {
              await ref.read(authControllerProvider.notifier).logout();
              if (context.mounted) context.go('/login');
            },
            child: Text(s.t('logout')),
          ),
        ],
      ),
    );
  }
}
