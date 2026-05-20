import 'dart:async';

import 'package:flutter/material.dart';
import 'package:shipflow_client/l10n/app_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/api_exceptions.dart';
import '../push/push_service.dart';
import '../state/auth_provider.dart';
import '../state/biometric_provider.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _idCtrl  = TextEditingController();
  final _pwCtrl  = TextEditingController();
  bool _submitting = false;
  bool _obscure    = true;
  String? _errorMessage;

  @override
  void dispose() {
    _idCtrl.dispose();
    _pwCtrl.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_submitting) return;
    if (!(_formKey.currentState?.validate() ?? false)) return;
    setState(() { _submitting = true; _errorMessage = null; });

    try {
      await ref.read(authProvider.notifier).login(
        identifier: _idCtrl.text.trim(),
        password  : _pwCtrl.text,
      );
      // Login put the auth state in either data() or error() — branch on it.
      final state = ref.read(authProvider);
      if (state.hasError) {
        setState(() {
          _errorMessage = _humanize(context, state.error);
        });
      } else {
        // Successful login. Register this device with the backend so push
        // fan-outs reach us. Fire-and-forget — router redirect already kicked in.
        unawaited(PushService.instance.registerWithBackend());
        // Cache the localized biometric prompt string. The next cold start
        // reads it from secure storage so AuthNotifier (which has no
        // BuildContext) still surfaces a localized prompt.
        if (mounted) {
          await ref.read(biometricControllerProvider)
              .cacheLocalizedReason(AppLocalizations.of(context)!.biometricUnlockReason);
        }
      }
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  String _humanize(BuildContext context, Object? err) {
    final l = AppLocalizations.of(context)!;
    if (err is ApiException) {
      return switch (err.code) {
        ApiErrorCode.unauthorized => l.invalidCredentials,
        ApiErrorCode.rateLimited  => l.rateLimited,
        ApiErrorCode.network      => l.noConnection,
        _                          => err.message,
      };
    }
    return l.signInFailed;
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final l = AppLocalizations.of(context)!;
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 420),
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Form(
                key: _formKey,
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: <Widget>[
                    Text(l.appTitle,
                      style: theme.textTheme.headlineMedium?.copyWith(fontWeight: FontWeight.w700),
                    ),
                    const SizedBox(height: 4),
                    Text(l.appSubtitle, style: theme.textTheme.bodyMedium?.copyWith(color: Colors.black54)),
                    const SizedBox(height: 32),

                    TextFormField(
                      controller: _idCtrl,
                      autocorrect: false,
                      enableSuggestions: false,
                      textInputAction: TextInputAction.next,
                      decoration: InputDecoration(
                        labelText: l.emailOrCode,
                        prefixIcon: const Icon(Icons.person_outline),
                      ),
                      validator: (v) => (v == null || v.trim().isEmpty) ? l.required : null,
                    ),
                    const SizedBox(height: 12),

                    TextFormField(
                      controller: _pwCtrl,
                      obscureText: _obscure,
                      textInputAction: TextInputAction.done,
                      onFieldSubmitted: (_) => _submit(),
                      decoration: InputDecoration(
                        labelText: l.password,
                        prefixIcon: const Icon(Icons.lock_outline),
                        suffixIcon: IconButton(
                          icon: Icon(_obscure ? Icons.visibility : Icons.visibility_off),
                          onPressed: () => setState(() => _obscure = !_obscure),
                        ),
                      ),
                      validator: (v) => (v == null || v.isEmpty) ? l.required : null,
                    ),
                    if (_errorMessage != null) ...<Widget>[
                      const SizedBox(height: 16),
                      Text(_errorMessage!, style: const TextStyle(color: Colors.red)),
                    ],

                    const SizedBox(height: 24),
                    ElevatedButton(
                      onPressed: _submitting ? null : _submit,
                      child: _submitting
                        ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                        : Text(l.signIn),
                    ),

                    const SizedBox(height: 24),
                    Text(
                      l.needHelpContact,
                      style: theme.textTheme.bodySmall?.copyWith(color: Colors.black54),
                      textAlign: TextAlign.center,
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
