import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

enum AppLocale { en, fr }

const _localeStorageKey = 'app_locale';

final sharedPreferencesProvider = Provider<SharedPreferences>((ref) {
  throw UnimplementedError('SharedPreferences must be overridden in main()');
});

class LocaleController extends Notifier<AppLocale> {
  @override
  AppLocale build() {
    final prefs = ref.watch(sharedPreferencesProvider);
    final stored = prefs.getString(_localeStorageKey);
    if (stored == AppLocale.fr.name) return AppLocale.fr;
    return AppLocale.en;
  }

  Future<void> setLocale(AppLocale locale) async {
    if (state == locale) return;
    state = locale;
    await ref.read(sharedPreferencesProvider).setString(_localeStorageKey, locale.name);
  }
}

final localeProvider = NotifierProvider<LocaleController, AppLocale>(LocaleController.new);
