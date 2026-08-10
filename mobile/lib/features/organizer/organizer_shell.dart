import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

class OrganizerShell extends StatelessWidget {
  const OrganizerShell({super.key, required this.shell});
  final StatefulNavigationShell shell;
  @override
  Widget build(BuildContext context) => Scaffold(
        body: shell,
        bottomNavigationBar: NavigationBar(
          selectedIndex: shell.currentIndex,
          onDestinationSelected: (index) => shell.goBranch(index,
              initialLocation: index == shell.currentIndex),
          destinations: const [
            NavigationDestination(
                icon: Icon(Icons.dashboard_outlined),
                selectedIcon: Icon(Icons.dashboard_rounded),
                label: 'Dashboard'),
            NavigationDestination(
                icon: Icon(Icons.local_activity_outlined),
                selectedIcon: Icon(Icons.local_activity_rounded),
                label: 'Eventi'),
            NavigationDestination(
                icon: Icon(Icons.groups_outlined),
                selectedIcon: Icon(Icons.groups_rounded),
                label: 'Prenotazioni'),
            NavigationDestination(
                icon: Icon(Icons.storefront_outlined),
                selectedIcon: Icon(Icons.storefront_rounded),
                label: 'Profilo'),
          ],
        ),
      );
}
