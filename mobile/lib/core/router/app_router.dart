import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/account/account_screens.dart';
import '../../features/admin/admin_screens.dart';
import '../../features/admin/admin_shell.dart';
import '../../features/auth/login_screen.dart';
import '../../features/customer/booking/booking_flow_screen.dart';
import '../../features/customer/booking/booking_list_screen.dart';
import '../../features/customer/customer_shell.dart';
import '../../features/customer/screens/customer_api_screens.dart';
import '../../features/organizer/organizer_event_form_screen.dart';
import '../../features/organizer/organizer_screens.dart';
import '../../features/organizer/organizer_shell.dart';
import '../auth/auth_controller.dart';
import '../models/app_role.dart';

final _rootKey = GlobalKey<NavigatorState>();
final _customerShellKey = GlobalKey<NavigatorState>();

final appRouterProvider = Provider<GoRouter>((ref) {
  final auth = ref.watch(authControllerProvider);
  return GoRouter(
    navigatorKey: _rootKey,
    initialLocation: '/login',
    refreshListenable: _RouterRefresh(ref),
    redirect: (context, state) {
      final user = auth.user;
      final path = state.uri.path;
      if (user == null) return path == '/login' ? null : '/login';
      if (path == '/login') return user.role.homePath;
      if (path.startsWith('/customer') && user.role != AppRole.customer) {
        return user.role.homePath;
      }
      if (path.startsWith('/organizer') && user.role != AppRole.organizer) {
        return user.role.homePath;
      }
      if (path.startsWith('/admin') && user.role != AppRole.admin) {
        return user.role.homePath;
      }
      return null;
    },
    routes: [
      GoRoute(path: '/login', builder: (_, __) => const LoginScreen()),
      StatefulShellRoute.indexedStack(
        builder: (_, __, shell) => CustomerShell(shell: shell),
        branches: [
          StatefulShellBranch(navigatorKey: _customerShellKey, routes: [
            GoRoute(
                path: '/customer',
                builder: (_, __) => const CustomerApiHomeScreen(),
                routes: [
                  GoRoute(
                      path: 'events/:id',
                      parentNavigatorKey: _rootKey,
                      builder: (_, s) => CustomerApiEventDetailScreen(
                          id: s.pathParameters['id']!)),
                  GoRoute(
                      path: 'events/:id/book',
                      parentNavigatorKey: _rootKey,
                      builder: (_, s) =>
                          BookingFlowScreen(id: s.pathParameters['id']!)),
                  GoRoute(
                      path: 'following',
                      parentNavigatorKey: _rootKey,
                      builder: (_, __) => const CustomerFollowingScreen()),
                ])
          ]),
          StatefulShellBranch(routes: [
            GoRoute(
                path: '/customer/all-events',
                builder: (_, __) => const CustomerApiAllEventsScreen())
          ]),
          StatefulShellBranch(routes: [
            GoRoute(
                path: '/customer/success',
                builder: (_, __) => const CustomerApiSuccessEventsScreen())
          ]),
          StatefulShellBranch(routes: [
            GoRoute(
                path: '/customer/bookings',
                builder: (_, __) => const CustomerBookingsScreen(),
                routes: [
                  GoRoute(
                      path: ':id',
                      parentNavigatorKey: _rootKey,
                      builder: (_, s) => CustomerBookingDetailScreen(
                          bookingUlid: s.pathParameters['id']!))
                ])
          ]),
          StatefulShellBranch(routes: [
            GoRoute(
                path: '/customer/profile',
                builder: (_, __) => const CustomerProfileScreen())
          ]),
        ],
      ),
      StatefulShellRoute.indexedStack(
        builder: (_, __, shell) => OrganizerShell(shell: shell),
        branches: [
          StatefulShellBranch(routes: [
            GoRoute(
                path: '/organizer',
                builder: (_, __) => const OrganizerDashboardScreen())
          ]),
          StatefulShellBranch(routes: [
            GoRoute(
                path: '/organizer/events',
                builder: (_, __) => const OrganizerEventsScreen(),
                routes: [
                  GoRoute(
                      path: 'create',
                      parentNavigatorKey: _rootKey,
                      builder: (_, __) => const OrganizerEventFormScreen()),
                  GoRoute(
                      path: ':id/edit',
                      parentNavigatorKey: _rootKey,
                      builder: (_, s) => OrganizerEventFormScreen(
                          eventId: s.pathParameters['id']!)),
                  GoRoute(
                      path: ':id',
                      parentNavigatorKey: _rootKey,
                      builder: (_, s) => OrganizerEventDetailScreen(
                          id: s.pathParameters['id']!),
                      routes: [
                        GoRoute(
                            path: 'participants',
                            builder: (_, s) => OrganizerParticipantsScreen(
                                eventId: s.pathParameters['id']!))
                      ]),
                ])
          ]),
          StatefulShellBranch(routes: [
            GoRoute(
                path: '/organizer/bookings',
                builder: (_, __) => const OrganizerBookingsScreen(),
                routes: [
                  GoRoute(
                      path: ':id',
                      parentNavigatorKey: _rootKey,
                      builder: (_, s) => OrganizerBookingDetailScreen(
                          id: s.pathParameters['id']!))
                ])
          ]),
          StatefulShellBranch(routes: [
            GoRoute(
                path: '/organizer/profile',
                builder: (_, __) => const OrganizerProfileScreen())
          ]),
        ],
      ),
      StatefulShellRoute.indexedStack(
        builder: (_, __, shell) => AdminShell(shell: shell),
        branches: [
          StatefulShellBranch(routes: [
            GoRoute(
                path: '/admin',
                builder: (_, __) => const AdminDashboardScreen())
          ]),
          StatefulShellBranch(routes: [
            GoRoute(
                path: '/admin/search',
                builder: (_, __) => const AdminSearchScreen())
          ]),
          StatefulShellBranch(routes: [
            GoRoute(
                path: '/admin/management',
                builder: (_, __) => const AdminManagementScreen(),
                routes: [
                  GoRoute(
                      path: 'organizers/:id',
                      parentNavigatorKey: _rootKey,
                      builder: (_, s) => AdminOrganizerDetailScreen(
                          id: s.pathParameters['id']!)),
                  GoRoute(
                      path: 'customers/:id',
                      parentNavigatorKey: _rootKey,
                      builder: (_, s) => AdminCustomerDetailScreen(
                          id: s.pathParameters['id']!)),
                  GoRoute(
                      path: 'events/:id',
                      parentNavigatorKey: _rootKey,
                      builder: (_, s) =>
                          AdminEventDetailScreen(id: s.pathParameters['id']!)),
                ])
          ]),
          StatefulShellBranch(routes: [
            GoRoute(
                path: '/admin/plans',
                builder: (_, __) => const AdminPlansScreen())
          ]),
          StatefulShellBranch(routes: [
            GoRoute(
                path: '/admin/profile',
                builder: (_, __) => const AdminProfileScreen())
          ]),
        ],
      ),
      GoRoute(
          path: '/admin/organizers/:id',
          redirect: (_, s) =>
              '/admin/management/organizers/${s.pathParameters['id']}'),
      GoRoute(
          path: '/admin/customers/:id',
          redirect: (_, s) =>
              '/admin/management/customers/${s.pathParameters['id']}'),
      GoRoute(
          path: '/admin/events/:id',
          redirect: (_, s) =>
              '/admin/management/events/${s.pathParameters['id']}'),
    ],
  );
});

class _RouterRefresh extends ChangeNotifier {
  _RouterRefresh(this.ref) {
    ref.listen(authControllerProvider, (_, __) => notifyListeners());
  }
  final Ref ref;
}
