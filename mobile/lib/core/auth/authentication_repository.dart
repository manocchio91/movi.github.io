import 'dart:async';

import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import '../models/app_role.dart';
import '../network/api_client.dart';

class AuthUser {
  const AuthUser({required this.name, required this.email, required this.role});

  factory AuthUser.fromJson(Map<String, dynamic> json) {
    final role = AppRoleLabel.fromApi(json['role'] as String?);
    if (role == null) {
      throw const AuthenticationException(
          'Questo ruolo non è supportato dall’app mobile.');
    }
    return AuthUser(
      name: json['name'] as String? ?? '',
      email: json['email'] as String? ?? '',
      role: role,
    );
  }

  final String name;
  final String email;
  final AppRole role;
}

class AuthenticationException implements Exception {
  const AuthenticationException(this.message);
  final String message;
}

class AuthenticationRepository {
  AuthenticationRepository(this._client);
  static const _tokenKey = 'authentication_token';
  final ApiClient _client;

  Future<AuthUser> login({required String email, required String password}) async {
    try {
      final response = await _client.post('/auth/login', body: {
        'email': email.trim(),
        'password': password,
      });
      if (response.statusCode < 200 || response.statusCode >= 300) {
        throw AuthenticationException(_messageFor(response));
      }
      final token = response.body['token'] as String?;
      final userJson = response.body['user'];
      if (token == null || userJson is! Map<String, dynamic>) {
        throw const AuthenticationException('Risposta di accesso non valida.');
      }
      final user = AuthUser.fromJson(userJson);
      await _saveToken(token);
      return user;
    } on AuthenticationException {
      rethrow;
    } on TimeoutException {
      throw const AuthenticationException('Il server non risponde. Riprova.');
    } on http.ClientException {
      throw const AuthenticationException('Impossibile raggiungere il server.');
    } on FormatException {
      throw const AuthenticationException('Risposta del server non valida.');
    } catch (_) {
      throw const AuthenticationException('Impossibile completare l’accesso.');
    }
  }

  Future<AuthUser?> currentUser() async {
    final token = await _readToken();
    if (token == null) return null;
    try {
      final response = await _client.get('/me', token: token);
      if (response.statusCode == 401 || response.statusCode == 403) {
        await _deleteToken();
        return null;
      }
      if (response.statusCode < 200 || response.statusCode >= 300) return null;
      final userJson = response.body['user'];
      return userJson is Map<String, dynamic> ? AuthUser.fromJson(userJson) : null;
    } catch (_) {
      return null;
    }
  }

  Future<void> logout() async {
    final token = await _readToken();
    try {
      if (token != null) await _client.post('/auth/logout', token: token);
    } finally {
      await _deleteToken();
    }
  }

  Future<String?> accessToken() => _readToken();

  Future<void> _saveToken(String token) async =>
      (await SharedPreferences.getInstance()).setString(_tokenKey, token);
  Future<String?> _readToken() async =>
      (await SharedPreferences.getInstance()).getString(_tokenKey);
  Future<void> _deleteToken() async =>
      (await SharedPreferences.getInstance()).remove(_tokenKey);

  String _messageFor(ApiResponse response) {
    final serverMessage = response.body['message'] as String?;
    return switch (response.statusCode) {
      401 => 'Sessione non valida. Accedi di nuovo.',
      403 => 'Non hai i permessi per accedere.',
      422 => serverMessage ?? 'Controlla email e password.',
      >= 500 => 'Il server ha riscontrato un errore. Riprova più tardi.',
      _ => serverMessage ?? 'Accesso non riuscito. Riprova.',
    };
  }
}
