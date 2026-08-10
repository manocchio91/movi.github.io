import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/auth/auth_controller.dart';
import '../role_data/role_api_repository.dart';
import '../shared/ui/real_components.dart';

class CustomerProfileScreen extends ConsumerStatefulWidget {
  const CustomerProfileScreen({super.key});
  @override
  ConsumerState<CustomerProfileScreen> createState() =>
      _CustomerProfileScreenState();
}

class _CustomerProfileScreenState extends ConsumerState<CustomerProfileScreen> {
  late Future<Map<String, dynamic>> future =
      ref.read(roleApiRepositoryProvider).get('/me/profile').then(_data);

  @override
  Widget build(BuildContext context) => ScreenScaffold(
        title: 'Profilo',
        children: [
          FutureBuilder<Map<String, dynamic>>(
            future: future,
            builder: (context, snapshot) {
              if (snapshot.connectionState != ConnectionState.done) {
                return const LoadingSkeleton();
              }
              if (snapshot.hasError) {
                return EmptyState(
                    title: 'Profilo non disponibile',
                    message: '${snapshot.error}',
                    icon: Icons.cloud_off_rounded);
              }
              final profile = snapshot.data ?? {};
              return Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Container(
                    padding: const EdgeInsets.all(22),
                    decoration: BoxDecoration(
                      color: Theme.of(context).colorScheme.primaryContainer,
                      borderRadius: BorderRadius.circular(24),
                    ),
                    child: Row(children: [
                      const CircleAvatar(
                          radius: 30,
                          child: Icon(Icons.person_rounded, size: 32)),
                      const SizedBox(width: 16),
                      Expanded(
                          child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                            Text('${profile['name'] ?? ''}',
                                style: Theme.of(context).textTheme.titleLarge),
                            Text('${profile['email'] ?? ''}'),
                            if ('${profile['phone'] ?? ''}'.isNotEmpty)
                              Text('${profile['phone']}'),
                          ])),
                    ]),
                  ),
                  const SectionTitle('La tua attività'),
                  LayoutBuilder(
                    builder: (_, constraints) => GridView.count(
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      crossAxisCount: constraints.maxWidth > 540 ? 3 : 2,
                      childAspectRatio: 1.35,
                      crossAxisSpacing: 12,
                      mainAxisSpacing: 12,
                      children: [
                        MetricCard(
                            label: 'Prenotazioni',
                            value: '${profile['bookings_count'] ?? 0}',
                            icon: Icons.confirmation_number_rounded),
                        MetricCard(
                            label: 'Preferiti',
                            value: '${profile['favorites_count'] ?? 0}',
                            icon: Icons.favorite_rounded),
                        MetricCard(
                            label: 'Recensioni',
                            value: '${profile['reviews_count'] ?? 0}',
                            icon: Icons.star_rounded),
                      ],
                    ),
                  ),
                  const SizedBox(height: 20),
                  FilledButton.tonalIcon(
                    onPressed: () => _edit(profile),
                    icon: const Icon(Icons.edit_rounded),
                    label: const Text('Modifica profilo'),
                  ),
                ],
              );
            },
          ),
          const SizedBox(height: 14),
          OutlinedButton.icon(
            onPressed: () async {
              await ref.read(authControllerProvider.notifier).logout();
              if (context.mounted) context.go('/login');
            },
            icon: const Icon(Icons.logout_rounded),
            label: const Text('Esci dall’account'),
          ),
        ],
      );

  Future<void> _edit(Map<String, dynamic> profile) async {
    final name = TextEditingController(text: '${profile['name'] ?? ''}');
    final phone = TextEditingController(text: '${profile['phone'] ?? ''}');
    final save = await showDialog<bool>(
          context: context,
          builder: (dialogContext) => AlertDialog(
            title: const Text('Modifica profilo'),
            content: Column(mainAxisSize: MainAxisSize.min, children: [
              TextField(
                  controller: name,
                  decoration: const InputDecoration(labelText: 'Nome')),
              const SizedBox(height: 12),
              TextField(
                  controller: phone,
                  keyboardType: TextInputType.phone,
                  decoration: const InputDecoration(labelText: 'Telefono')),
            ]),
            actions: [
              TextButton(
                  onPressed: () => Navigator.pop(dialogContext, false),
                  child: const Text('Annulla')),
              FilledButton(
                  onPressed: () => Navigator.pop(dialogContext, true),
                  child: const Text('Salva')),
            ],
          ),
        ) ??
        false;
    if (save) {
      try {
        await ref.read(roleApiRepositoryProvider).patch('/me/profile', {
          'name': name.text.trim(),
          'phone': phone.text.trim(),
        });
        if (mounted) {
          setState(() => future = ref
              .read(roleApiRepositoryProvider)
              .get('/me/profile')
              .then(_data));
        }
      } catch (error) {
        if (mounted) {
          ScaffoldMessenger.of(context)
              .showSnackBar(SnackBar(content: Text('$error')));
        }
      }
    }
    name.dispose();
    phone.dispose();
  }
}

Map<String, dynamic> _data(Map<String, dynamic> body) {
  final value = body['data'];
  return value is Map<String, dynamic> ? value : <String, dynamic>{};
}
