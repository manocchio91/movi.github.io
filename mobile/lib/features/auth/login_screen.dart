import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/auth/auth_controller.dart';
import '../../core/models/app_role.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _email = TextEditingController();
  final _password = TextEditingController();

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final auth = ref.watch(authControllerProvider);
    final scheme = Theme.of(context).colorScheme;

    ref.listen(authControllerProvider, (_, next) {
      final user = next.user;
      if (user == null) return;
      context.go(user.role.homePath);
    });

    return Scaffold(
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(24, 28, 24, 32),
          keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
          children: [
            Container(
              height: 190,
              decoration: BoxDecoration(
                color: scheme.primaryContainer,
                borderRadius: BorderRadius.circular(30),
              ),
              child: Stack(
                children: [
                  Positioned(
                    right: -20,
                    top: -26,
                    child: Icon(Icons.celebration_rounded,
                        size: 150,
                        color: scheme.primary.withValues(alpha: .18)),
                  ),
                  Positioned(
                    left: 22,
                    bottom: 22,
                    child: Text(
                      'MòVi\nVivi quello che succede.',
                      style:
                          Theme.of(context).textTheme.headlineMedium?.copyWith(
                                fontWeight: FontWeight.w900,
                                color: scheme.onPrimaryContainer,
                              ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 28),
            Text('Accedi',
                style: Theme.of(context)
                    .textTheme
                    .headlineSmall
                    ?.copyWith(fontWeight: FontWeight.w800)),
            const SizedBox(height: 8),
            const Text('Inserisci le credenziali del tuo account.'),
            const SizedBox(height: 24),
            TextField(
              controller: _email,
              enabled: !auth.isLoading,
              keyboardType: TextInputType.emailAddress,
              autofillHints: const [AutofillHints.email],
              decoration: const InputDecoration(
                  labelText: 'Email', prefixIcon: Icon(Icons.mail_rounded)),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _password,
              enabled: !auth.isLoading,
              obscureText: true,
              autofillHints: const [AutofillHints.password],
              onSubmitted: auth.isLoading ? null : (_) => _login(),
              decoration: const InputDecoration(
                  labelText: 'Password', prefixIcon: Icon(Icons.lock_rounded)),
            ),
            if (auth.error != null) ...[
              const SizedBox(height: 12),
              Semantics(
                liveRegion: true,
                child: Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: scheme.errorContainer,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Text(auth.error!,
                      style: TextStyle(color: scheme.onErrorContainer)),
                ),
              ),
            ],
            const SizedBox(height: 20),
            FilledButton.icon(
              onPressed: auth.isLoading ? null : _login,
              icon: auth.isLoading
                  ? const SizedBox.square(
                      dimension: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.login_rounded),
              label: Text(auth.isLoading ? 'Accesso...' : 'Entra'),
            ),
          ],
        ),
      ),
    );
  }

  void _login() {
    ref.read(authControllerProvider.notifier).login(
          email: _email.text,
          password: _password.text,
        );
  }
}
