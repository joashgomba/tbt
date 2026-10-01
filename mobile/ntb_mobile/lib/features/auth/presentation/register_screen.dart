import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/config/providers.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/widgets/auth_form_card.dart';
import '../../../core/widgets/language_toggle.dart';
import '../application/auth_controller.dart';

class RegisterScreen extends ConsumerStatefulWidget {
  const RegisterScreen({super.key});

  @override
  ConsumerState<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends ConsumerState<RegisterScreen> {
  final _formKey = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController(text: '+253');
  final _org = TextEditingController();
  final _country = TextEditingController(text: 'Djibouti');
  final _password = TextEditingController();
  final _password2 = TextEditingController();
  bool _acceptTerms = false;
  bool _busy = false;
  bool _obscure1 = true;
  bool _obscure2 = true;
  String? _error;

  @override
  void dispose() {
    for (final c in [_name, _email, _phone, _org, _country, _password, _password2]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate() || !_acceptTerms) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    final locale = ref.read(localeProvider);
    try {
      await ref.read(authControllerProvider.notifier).register(
            fullName: _name.text.trim(),
            email: _email.text.trim(),
            password: _password.text,
            passwordConfirmation: _password2.text,
            phoneNumber: _phone.text.trim(),
            organizationName: _org.text.trim(),
            country: _country.text.trim(),
            acceptTerms: _acceptTerms,
            preferredLanguage: locale.name,
          );
      if (mounted) context.go('/home');
    } catch (e) {
      setState(() {
        _error = e is ApiException ? e.message : e.toString();
      });
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = ref.watch(stringsProvider);
    final config = ref.watch(appConfigProvider);

    return Scaffold(
      appBar: AppBar(
        title: Text(s.t('register')),
        backgroundColor: Colors.transparent,
        foregroundColor: AppColors.ink,
        elevation: 0,
      ),
      extendBodyBehindAppBar: true,
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
                    padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 16),
                    child: ConstrainedBox(
                      constraints: const BoxConstraints(maxWidth: 420),
                      child: Column(
                        children: [
                    AuthBrandMark(title: config.appName, subtitle: s.t('appTagline')),
                    const SizedBox(height: 20),
                    AuthFormCard(
                      child: Form(
                        key: _formKey,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            Text(
                              s.t('register'),
                              style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w600),
                            ),
                            const SizedBox(height: 16),
                            if (_error != null)
                              Container(
                                margin: const EdgeInsets.only(bottom: 12),
                                padding: const EdgeInsets.all(12),
                                decoration: BoxDecoration(
                                  color: AppColors.danger.withOpacity(0.08),
                                  borderRadius: BorderRadius.circular(12),
                                  border: Border.all(color: AppColors.danger.withOpacity(0.25)),
                                ),
                                child: Text(_error!, style: const TextStyle(color: AppColors.danger, fontSize: 14)),
                              ),
                            TextFormField(
                              controller: _name,
                              textInputAction: TextInputAction.next,
                              decoration: InputDecoration(
                                labelText: s.t('fullName'),
                                prefixIcon: const Icon(Icons.person_outline, color: AppColors.green),
                              ),
                              validator: _req,
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _email,
                              keyboardType: TextInputType.emailAddress,
                              textInputAction: TextInputAction.next,
                              decoration: InputDecoration(
                                labelText: s.t('email'),
                                prefixIcon: const Icon(Icons.mail_outline, color: AppColors.green),
                              ),
                              validator: _emailVal,
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _phone,
                              keyboardType: TextInputType.phone,
                              textInputAction: TextInputAction.next,
                              decoration: InputDecoration(
                                labelText: s.t('phone'),
                                prefixIcon: const Icon(Icons.phone_outlined, color: AppColors.green),
                              ),
                              validator: _req,
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _org,
                              textInputAction: TextInputAction.next,
                              decoration: InputDecoration(
                                labelText: s.t('organization'),
                                prefixIcon: const Icon(Icons.business_outlined, color: AppColors.green),
                              ),
                              validator: _req,
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _country,
                              textInputAction: TextInputAction.next,
                              decoration: InputDecoration(
                                labelText: s.t('country'),
                                prefixIcon: const Icon(Icons.public, color: AppColors.green),
                              ),
                              validator: _req,
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _password,
                              obscureText: _obscure1,
                              textInputAction: TextInputAction.next,
                              decoration: InputDecoration(
                                labelText: s.t('password'),
                                prefixIcon: const Icon(Icons.lock_outline, color: AppColors.green),
                                suffixIcon: IconButton(
                                  icon: Icon(_obscure1 ? Icons.visibility_outlined : Icons.visibility_off_outlined),
                                  onPressed: () => setState(() => _obscure1 = !_obscure1),
                                ),
                              ),
                              validator: _pass,
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _password2,
                              obscureText: _obscure2,
                              textInputAction: TextInputAction.done,
                              decoration: InputDecoration(
                                labelText: s.t('passwordConfirm'),
                                prefixIcon: const Icon(Icons.lock_outline, color: AppColors.green),
                                suffixIcon: IconButton(
                                  icon: Icon(_obscure2 ? Icons.visibility_outlined : Icons.visibility_off_outlined),
                                  onPressed: () => setState(() => _obscure2 = !_obscure2),
                                ),
                              ),
                              validator: (v) => v != _password.text ? '!' : null,
                            ),
                            CheckboxListTile(
                              value: _acceptTerms,
                              onChanged: (v) => setState(() => _acceptTerms = v ?? false),
                              title: Text(s.t('acceptTerms'), style: const TextStyle(fontSize: 14)),
                              controlAffinity: ListTileControlAffinity.leading,
                              contentPadding: EdgeInsets.zero,
                              activeColor: AppColors.green,
                            ),
                            const SizedBox(height: 8),
                            SizedBox(
                              height: 50,
                              child: ElevatedButton(
                                onPressed: (_busy || !_acceptTerms) ? null : _submit,
                                child: _busy
                                    ? const SizedBox(
                                        height: 22,
                                        width: 22,
                                        child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                                      )
                                    : Text(s.t('register')),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 16),
                    TextButton(onPressed: () => context.pop(), child: Text(s.t('login'))),
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

  String? _req(String? v) => (v == null || v.trim().isEmpty) ? '!' : null;
  String? _emailVal(String? v) => (v == null || !v.contains('@')) ? '!' : null;
  String? _pass(String? v) => (v == null || v.length < 8) ? '!' : null;
}
