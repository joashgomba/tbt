import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/config/local_api_hint.dart';
import '../../../core/config/providers.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/widgets/auth_form_card.dart';
import '../../../core/widgets/language_toggle.dart';
import '../application/auth_controller.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _email = TextEditingController();
  final _password = TextEditingController();
  bool _busy = false;
  bool _obscure = true;
  String? _error;
  String? _errorHint;

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() {
      _busy = true;
      _error = null;
      _errorHint = null;
    });
    try {
      await ref.read(authControllerProvider.notifier).login(_email.text.trim(), _password.text);
      if (mounted) context.go('/home');
    } catch (e) {
      final strings = ref.read(stringsProvider);
      setState(() {
        if (e is ApiException) {
          _error = e.message;
          if (e.kind == ApiErrorKind.timeout || e.kind == ApiErrorKind.connection) {
            _errorHint = strings.t('networkTimeoutHint');
          }
        } else {
          _error = ref.read(authControllerProvider.notifier).errorMessage(e);
        }
      });
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = ref.watch(stringsProvider);
    final config = ref.watch(appConfigProvider);
    final auth = ref.watch(authControllerProvider);
    final checkingSession = auth.isLoading;
    final physicalApiHint = ref.watch(showPhysicalDeviceApiHintProvider);

    ref.listen(authControllerProvider, (previous, next) {
      if (next.isLoading) return;
      final user = next.valueOrNull;
      if (user != null && context.mounted) {
        context.go('/home');
      }
    });

    return Scaffold(
      body: DecoratedBox(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [
              AppColors.skyBlue.withOpacity(0.18),
              AppColors.sand,
              AppColors.sand,
            ],
          ),
        ),
        child: SafeArea(
          child: Column(
            children: [
              const Align(
                alignment: Alignment.centerRight,
                child: Padding(
                  padding: EdgeInsets.fromLTRB(24, 8, 24, 0),
                  child: LanguageToggle(),
                ),
              ),
              Expanded(
                child: Center(
                  child: SingleChildScrollView(
                    padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 24),
                    child: ConstrainedBox(
                      constraints: const BoxConstraints(maxWidth: 420),
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                    AuthBrandMark(title: config.appName, subtitle: s.t('appTagline')),
                    const SizedBox(height: 16),
                    physicalApiHint.when(
                      data: (show) => show
                          ? Padding(
                              padding: const EdgeInsets.only(bottom: 12),
                              child: _InfoBanner(text: s.t('physicalDeviceApiHint')),
                            )
                          : const SizedBox.shrink(),
                      loading: () => const SizedBox.shrink(),
                      error: (_, __) => const SizedBox.shrink(),
                    ),
                    const SizedBox(height: 12),
                    AuthFormCard(
                      child: Form(
                        key: _formKey,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            Text(
                              s.t('login'),
                              style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w600),
                            ),
                            const SizedBox(height: 20),
                            if (_error != null)
                              Container(
                                margin: const EdgeInsets.only(bottom: 16),
                                padding: const EdgeInsets.all(12),
                                decoration: BoxDecoration(
                                  color: AppColors.danger.withOpacity(0.08),
                                  borderRadius: BorderRadius.circular(12),
                                  border: Border.all(color: AppColors.danger.withOpacity(0.25)),
                                ),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(_error!, style: const TextStyle(color: AppColors.danger, fontSize: 14)),
                                    if (_errorHint != null) ...[
                                      const SizedBox(height: 8),
                                      Text(
                                        _errorHint!,
                                        style: TextStyle(color: AppColors.danger.withOpacity(0.85), fontSize: 12),
                                      ),
                                    ],
                                  ],
                                ),
                              ),
                            TextFormField(
                              controller: _email,
                              keyboardType: TextInputType.emailAddress,
                              textInputAction: TextInputAction.next,
                              decoration: InputDecoration(
                                labelText: s.t('email'),
                                prefixIcon: const Icon(Icons.mail_outline, color: AppColors.green),
                              ),
                              validator: (v) => (v == null || !v.contains('@')) ? s.t('email') : null,
                            ),
                            const SizedBox(height: 14),
                            TextFormField(
                              controller: _password,
                              obscureText: _obscure,
                              textInputAction: TextInputAction.done,
                              onFieldSubmitted: (_) => _submit(),
                              decoration: InputDecoration(
                                labelText: s.t('password'),
                                prefixIcon: const Icon(Icons.lock_outline, color: AppColors.green),
                                suffixIcon: IconButton(
                                  icon: Icon(_obscure ? Icons.visibility_outlined : Icons.visibility_off_outlined),
                                  onPressed: () => setState(() => _obscure = !_obscure),
                                ),
                              ),
                              validator: (v) => (v == null || v.length < 8) ? s.t('password') : null,
                            ),
                            const SizedBox(height: 24),
                            SizedBox(
                              height: 50,
                              child: ElevatedButton(
                                onPressed: (_busy || checkingSession) ? null : _submit,
                                child: (_busy || checkingSession)
                                    ? const SizedBox(
                                        height: 22,
                                        width: 22,
                                        child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                                      )
                                    : Text(s.t('login')),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 20),
                    Wrap(
                      alignment: WrapAlignment.center,
                      spacing: 4,
                      runSpacing: 0,
                      children: [
                        TextButton(onPressed: () => context.push('/register'), child: Text(s.t('register'))),
                        TextButton(onPressed: () => context.go('/track'), child: Text(s.t('track'))),
                        TextButton(onPressed: () => context.go('/insights'), child: Text(s.t('insights'))),
                      ],
                    ),
                    if (!config.isProduction)
                      Padding(
                        padding: const EdgeInsets.only(top: 8),
                        child: Text(
                          config.apiBaseUrl,
                          style: const TextStyle(fontSize: 11, color: AppColors.inkMuted),
                          textAlign: TextAlign.center,
                        ),
                      ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _InfoBanner extends StatelessWidget {
  const _InfoBanner({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.warning.withOpacity(0.15),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.warning.withOpacity(0.45)),
      ),
      child: Text(text, style: const TextStyle(fontSize: 12, height: 1.35, color: AppColors.ink)),
    );
  }
}
