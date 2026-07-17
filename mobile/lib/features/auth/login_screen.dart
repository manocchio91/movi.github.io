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
  final _email = TextEditingController(text: 'customer@demo.it');
  final _password = TextEditingController(text: 'password');

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
      context.go(user.role == AppRole.customer ? '/customer' : '/organizer');
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
                      'Eventi Locali\nMolise',
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
            Text('Accedi alla preview mobile',
                style: Theme.of(context)
                    .textTheme
                    .headlineSmall
                    ?.copyWith(fontWeight: FontWeight.w800)),
            const SizedBox(height: 8),
            const Text(
                'Account demo per navigare Customer App e Organizer App. Il selettore è solo per sviluppo.'),
            const SizedBox(height: 24),
            SegmentedButton<AppRole>(
              segments: const [
                ButtonSegment(
                    value: AppRole.customer,
                    label: Text('Customer'),
                    icon: Icon(Icons.person_rounded)),
                ButtonSegment(
                    value: AppRole.organizer,
                    label: Text('Organizer'),
                    icon: Icon(Icons.storefront_rounded)),
              ],
              selected: {
                _email.text.contains('organizer')
                    ? AppRole.organizer
                    : AppRole.customer
              },
              onSelectionChanged: (value) {
                final role = value.first;
                setState(() {
                  _email.text = role == AppRole.customer
                      ? 'customer@demo.it'
                      : 'organizer@demo.it';
                });
              },
            ),
            const SizedBox(height: 16),
            TextField(
              controller: _email,
              keyboardType: TextInputType.emailAddress,
              decoration: const InputDecoration(
                  labelText: 'Email demo',
                  prefixIcon: Icon(Icons.mail_rounded)),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _password,
              obscureText: true,
              decoration: const InputDecoration(
                  labelText: 'Password', prefixIcon: Icon(Icons.lock_rounded)),
            ),
            if (auth.error != null) ...[
              const SizedBox(height: 12),
              Text(auth.error!, style: TextStyle(color: scheme.error)),
            ],
            const SizedBox(height: 20),
            FilledButton.icon(
              onPressed: () => ref.read(authControllerProvider.notifier).login(
                    email: _email.text,
                    password: _password.text,
                  ),
              icon: const Icon(Icons.login_rounded),
              label: const Text('Entra'),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: () => ref
                        .read(authControllerProvider.notifier)
                        .loginAs(AppRole.customer),
                    child: const Text('Customer demo'),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: OutlinedButton(
                    onPressed: () => ref
                        .read(authControllerProvider.notifier)
                        .loginAs(AppRole.organizer),
                    child: const Text('Organizer demo'),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
