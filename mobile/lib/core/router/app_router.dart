import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/auth/login_screen.dart';
import '../../features/customer/customer_shell.dart';
import '../../features/customer/screens/customer_screens.dart';
import '../../features/customer/screens/customer_api_screens.dart';
import '../../features/organizer/organizer_shell.dart';
import '../../features/organizer/screens/organizer_screens.dart';
import '../auth/auth_controller.dart';
import '../models/app_role.dart';

final _rootKey = GlobalKey<NavigatorState>();
final _customerShellKey = GlobalKey<NavigatorState>();
final _organizerShellKey = GlobalKey<NavigatorState>();

final appRouterProvider = Provider<GoRouter>((ref) {
  final auth = ref.watch(authControllerProvider);

  return GoRouter(
    navigatorKey: _rootKey,
    initialLocation: '/login',
    refreshListenable: _RouterRefresh(ref),
    redirect: (context, state) {
      final user = auth.user;
      final path = state.uri.path;
      if (user == null) {
        return path == '/login' ? null : '/login';
      }
      if (path == '/login') {
        return user.role == AppRole.customer ? '/customer' : '/organizer';
      }
      if (path.startsWith('/customer') && user.role != AppRole.customer) {
        return '/organizer';
      }
      if (path.startsWith('/organizer') && user.role != AppRole.organizer) {
        return '/customer';
      }
      return null;
    },
    routes: [
      GoRoute(path: '/login', builder: (_, __) => const LoginScreen()),
      StatefulShellRoute.indexedStack(
        builder: (_, state, shell) => CustomerShell(shell: shell),
        branches: [
          StatefulShellBranch(
            navigatorKey: _customerShellKey,
            routes: [
              GoRoute(
                path: '/customer',
                builder: (_, __) => const CustomerApiHomeScreen(),
                routes: [
                  GoRoute(
                    path: 'events/:id',
                    parentNavigatorKey: _rootKey,
                    builder: (_, state) => CustomerApiEventDetailScreen(
                        id: state.pathParameters['id']!),
                  ),
                  GoRoute(
                    path: 'events/:id/book',
                    parentNavigatorKey: _rootKey,
                    builder: (_, state) =>
                        BookingFlowScreen(id: state.pathParameters['id']!),
                  ),
                  GoRoute(
                    path: 'preferences',
                    parentNavigatorKey: _rootKey,
                    builder: (_, __) => const PreferencesScreen(),
                  ),
                  GoRoute(
                    path: 'review/:id',
                    parentNavigatorKey: _rootKey,
                    builder: (_, state) =>
                        ReviewScreen(id: state.pathParameters['id']!),
                  ),
                ],
              ),
            ],
          ),
          StatefulShellBranch(routes: [
            GoRoute(
              path: '/customer/all-events',
              builder: (_, __) => const CustomerApiAllEventsScreen(),
            ),
          ]),
          StatefulShellBranch(routes: [
            GoRoute(
              path: '/customer/success',
              builder: (_, __) => const CustomerApiSuccessEventsScreen(),
            ),
          ]),
          StatefulShellBranch(routes: [
            GoRoute(
              path: '/customer/bookings',
              builder: (_, __) => const BookingsScreen(),
            ),
          ]),
          StatefulShellBranch(routes: [
            GoRoute(
              path: '/customer/profile',
              builder: (_, __) => const CustomerProfileScreen(),
            ),
          ]),
        ],
      ),
      StatefulShellRoute.indexedStack(
        builder: (_, state, shell) => OrganizerShell(shell: shell),
        branches: [
          StatefulShellBranch(
            navigatorKey: _organizerShellKey,
            routes: [
              GoRoute(
                path: '/organizer',
                builder: (_, __) => const OrganizerDashboardScreen(),
                routes: [
                  GoRoute(
                    path: 'calendar',
                    parentNavigatorKey: _rootKey,
                    builder: (_, __) => const SmartCalendarScreen(),
                  ),
                  GoRoute(
                    path: 'bookings',
                    parentNavigatorKey: _rootKey,
                    builder: (_, __) => const OrganizerBookingsScreen(),
                  ),
                  GoRoute(
                    path: 'reviews',
                    parentNavigatorKey: _rootKey,
                    builder: (_, __) => const OrganizerReviewsScreen(),
                  ),
                ],
              ),
            ],
          ),
          StatefulShellBranch(routes: [
            GoRoute(
              path: '/organizer/events',
              builder: (_, __) => const MyEventsScreen(),
            ),
          ]),
          StatefulShellBranch(routes: [
            GoRoute(
              path: '/organizer/create',
              builder: (_, __) => const CreateEventWizardScreen(),
            ),
          ]),
          StatefulShellBranch(routes: [
            GoRoute(
              path: '/organizer/opportunities',
              builder: (_, __) => const OpportunitiesScreen(),
            ),
          ]),
          StatefulShellBranch(routes: [
            GoRoute(
              path: '/organizer/profile',
              builder: (_, __) => const OrganizerProfileScreen(),
            ),
          ]),
        ],
      ),
    ],
  );
});

class _RouterRefresh extends ChangeNotifier {
  _RouterRefresh(this.ref) {
    ref.listen(authControllerProvider, (_, __) => notifyListeners());
  }

  final Ref ref;
}
