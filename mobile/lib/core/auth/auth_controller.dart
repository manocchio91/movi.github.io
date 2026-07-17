import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../network/api_client.dart';
import 'authentication_repository.dart';

class AuthState {
  const AuthState({this.user, this.error, this.isLoading = false});
  final AuthUser? user;
  final String? error;
  final bool isLoading;
  bool get isAuthenticated => user != null;
}

class AuthController extends Notifier<AuthState> {
  late final AuthenticationRepository _repository;

  @override
  AuthState build() {
    _repository = ref.read(authenticationRepositoryProvider);
    Future<void>.microtask(_restoreSession);
    return const AuthState(isLoading: true);
  }

  Future<void> _restoreSession() async {
    final user = await _repository.currentUser();
    state = AuthState(user: user);
  }

  Future<void> login({required String email, required String password}) async {
    if (email.trim().isEmpty || password.isEmpty) {
      state = const AuthState(error: 'Inserisci email e password.');
      return;
    }
    state = const AuthState(isLoading: true);
    try {
      final user = await _repository.login(email: email, password: password);
      state = AuthState(user: user);
    } on AuthenticationException catch (error) {
      state = AuthState(error: error.message);
    }
  }

  Future<void> logout() async {
    state = AuthState(user: state.user, isLoading: true);
    await _repository.logout();
    state = const AuthState();
  }
}

final apiClientProvider = Provider<ApiClient>((ref) => ApiClient());
final authenticationRepositoryProvider = Provider<AuthenticationRepository>(
  (ref) => AuthenticationRepository(ref.watch(apiClientProvider)),
);
final authControllerProvider =
    NotifierProvider<AuthController, AuthState>(AuthController.new);
