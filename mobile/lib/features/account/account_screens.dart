import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/auth/auth_controller.dart';
import '../shared/ui/real_components.dart';

class CustomerProfileScreen extends ConsumerWidget {
  const CustomerProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(authControllerProvider).user!;
    return ScreenScaffold(title: 'Profilo', children: [
      ListTile(contentPadding: EdgeInsets.zero, leading: const CircleAvatar(child: Icon(Icons.person_rounded)), title: Text(user.name), subtitle: Text(user.email)),
      const SizedBox(height: 16),
      OutlinedButton.icon(onPressed: () async { await ref.read(authControllerProvider.notifier).logout(); if (context.mounted) context.go('/login'); }, icon: const Icon(Icons.logout_rounded), label: const Text('Logout')),
    ]);
  }
}

class OrganizerWebOnlyScreen extends ConsumerWidget {
  const OrganizerWebOnlyScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(authControllerProvider).user!;
    return ScreenScaffold(title: 'Account Organizer', children: [
      ListTile(contentPadding: EdgeInsets.zero, leading: const CircleAvatar(child: Icon(Icons.storefront_rounded)), title: Text(user.name), subtitle: Text(user.email)),
      const EmptyState(title: 'Area Organizer', message: 'Le funzioni Organizer sono disponibili nella versione web.', icon: Icons.language_rounded),
      const SizedBox(height: 16),
      OutlinedButton.icon(onPressed: () async { await ref.read(authControllerProvider.notifier).logout(); if (context.mounted) context.go('/login'); }, icon: const Icon(Icons.logout_rounded), label: const Text('Logout')),
    ]);
  }
}
