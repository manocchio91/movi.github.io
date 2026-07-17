import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/app_role.dart';

class DemoUser {
  const DemoUser({
    required this.email,
    required this.name,
    required this.role,
    required this.area,
  });

  final String email;
  final String name;
  final AppRole role;
  final String area;
}

class AuthState {
  const AuthState({this.user, this.error});

  final DemoUser? user;
  final String? error;

  bool get isAuthenticated => user != null;
}

class AuthController extends Notifier<AuthState> {
  static const demoPassword = 'password';

  @override
  AuthState build() => const AuthState();

  void login({required String email, required String password}) {
    if (password != demoPassword) {
      state = const AuthState(error: 'Credenziali demo non valide.');
      return;
    }

    final normalized = email.trim().toLowerCase();
    if (normalized == 'customer@demo.it') {
      state = const AuthState(
        user: DemoUser(
          email: 'customer@demo.it',
          name: 'Giulia',
          role: AppRole.customer,
          area: 'Campobasso e dintorni',
        ),
      );
      return;
    }
    if (normalized == 'organizer@demo.it') {
      state = const AuthState(
        user: DemoUser(
          email: 'organizer@demo.it',
          name: 'Masseria Collevento',
          role: AppRole.organizer,
          area: 'Termoli',
        ),
      );
      return;
    }

    state = const AuthState(error: 'Usa uno degli account demo disponibili.');
  }

  void loginAs(AppRole role) {
    switch (role) {
      case AppRole.customer:
        login(email: 'customer@demo.it', password: demoPassword);
      case AppRole.organizer:
        login(email: 'organizer@demo.it', password: demoPassword);
    }
  }

  void logout() => state = const AuthState();
}

final authControllerProvider =
    NotifierProvider<AuthController, AuthState>(AuthController.new);
