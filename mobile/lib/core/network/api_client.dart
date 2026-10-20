import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;

import 'analytics_session.dart';

class ApiResponse {
  const ApiResponse({required this.statusCode, required this.body});
  final int statusCode;
  final Map<String, dynamic> body;
}

class ApiClient {
  ApiClient({http.Client? client, String? baseUrl})
      : baseUrl = baseUrl ?? _defaultBaseUrl,
        _client = client ?? http.Client(),
        analyticsSession = buildAnalyticsSession();

  static String get _defaultBaseUrl {
    const configured = String.fromEnvironment('API_BASE_URL');
    if (configured.isNotEmpty) return configured;
    if (!kIsWeb && defaultTargetPlatform == TargetPlatform.android) {
      return 'http://10.0.2.2:8080/api/v1';
    }
    return 'http://localhost:8080/api/v1';
  }

  final String baseUrl;
  final http.Client _client;
  final String analyticsSession;

  Future<ApiResponse> post(String path,
          {Map<String, dynamic>? body,
          String? token,
          Map<String, String>? headers}) =>
      _send('POST', path, body: body, token: token, extraHeaders: headers);
  Future<ApiResponse> get(String path, {String? token}) =>
      _send('GET', path, token: token);
  Future<ApiResponse> put(String path,
          {Map<String, dynamic>? body, String? token}) =>
      _send('PUT', path, body: body, token: token);
  Future<ApiResponse> patch(String path,
          {Map<String, dynamic>? body, String? token}) =>
      _send('PATCH', path, body: body, token: token);
  Future<ApiResponse> delete(String path, {String? token}) =>
      _send('DELETE', path, token: token);

  Future<ApiResponse> _send(String method, String path,
      {Map<String, dynamic>? body,
      String? token,
      Map<String, String>? extraHeaders}) async {
    final headers = <String, String>{
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'X-Analytics-Session': analyticsSession,
      if (token != null) 'Authorization': 'Bearer $token',
      ...?extraHeaders,
    };
    final uri = Uri.parse('$baseUrl$path');
    final encoded = body == null ? null : jsonEncode(body);
    final response = switch (method) {
      'GET' => await _client.get(uri, headers: headers),
      'PUT' => await _client.put(uri, headers: headers, body: encoded),
      'PATCH' => await _client.patch(uri, headers: headers, body: encoded),
      'DELETE' => await _client.delete(uri, headers: headers),
      _ => await _client.post(uri, headers: headers, body: encoded),
    };
    Map<String, dynamic> decoded = const {};
    if (response.body.isNotEmpty) {
      final value = jsonDecode(response.body);
      if (value is Map<String, dynamic>) decoded = value;
    }
    return ApiResponse(statusCode: response.statusCode, body: decoded);
  }
}
