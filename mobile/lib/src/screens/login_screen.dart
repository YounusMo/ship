import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/api_exceptions.dart';
import '../push/push_service.dart';
import '../state/auth_provider.dart';

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
          _errorMessage = _humanize(state.error);
        });
      } else {
        // Successful login. Register this device with the backend so push
        // fan-outs reach us. Fire-and-forget — router redirect already kicked in.
        unawaited(PushService.instance.registerWithBackend());
      }
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  String _humanize(Object? err) {
    if (err is ApiException) {
      return switch (err.code) {
        ApiErrorCode.unauthorized => 'Invalid identifier or password.',
        ApiErrorCode.rateLimited  => 'Too many attempts. Try again shortly.',
        ApiErrorCode.network      => 'No connection to the server.',
        _                          => err.message,
      };
    }
    return 'Sign-in failed. Try again.';
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
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
                    Text('ShipFlow',
                      style: theme.textTheme.headlineMedium?.copyWith(fontWeight: FontWeight.w700),
                    ),
                    const SizedBox(height: 4),
                    Text('Client portal', style: theme.textTheme.bodyMedium?.copyWith(color: Colors.black54)),
                    const SizedBox(height: 32),

                    TextFormField(
                      controller: _idCtrl,
                      autocorrect: false,
                      enableSuggestions: false,
                      textInputAction: TextInputAction.next,
                      decoration: const InputDecoration(
                        labelText: 'Email or code',
                        prefixIcon: Icon(Icons.person_outline),
                      ),
                      validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
                    ),
                    const SizedBox(height: 12),

                    TextFormField(
                      controller: _pwCtrl,
                      obscureText: _obscure,
                      textInputAction: TextInputAction.done,
                      onFieldSubmitted: (_) => _submit(),
                      decoration: InputDecoration(
                        labelText: 'Password',
                        prefixIcon: const Icon(Icons.lock_outline),
                        suffixIcon: IconButton(
                          icon: Icon(_obscure ? Icons.visibility : Icons.visibility_off),
                          onPressed: () => setState(() => _obscure = !_obscure),
                        ),
                      ),
                      validator: (v) => (v == null || v.isEmpty) ? 'Required' : null,
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
                        : const Text('Sign in'),
                    ),

                    const SizedBox(height: 24),
                    Text(
                      'Contact your branch administrator for credentials or a password reset.',
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
