import 'dart:convert';

import 'package:http/http.dart' as http;

class ApiResponse {
  const ApiResponse({required this.statusCode, required this.body});
  final int statusCode;
  final Map<String, dynamic> body;
}

class ApiClient {
  ApiClient({http.Client? client}) : _client = client ?? http.Client();
  static const baseUrl = 'http://localhost:8080/api/v1';
  final http.Client _client;

  Future<ApiResponse> post(String path,
          {Map<String, dynamic>? body, String? token}) =>
      _send('POST', path, body: body, token: token);
  Future<ApiResponse> get(String path, {String? token}) =>
      _send('GET', path, token: token);

  Future<ApiResponse> _send(String method, String path,
      {Map<String, dynamic>? body, String? token}) async {
    final headers = <String, String>{
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      if (token != null) 'Authorization': 'Bearer $token',
    };
    final uri = Uri.parse('$baseUrl$path');
    final response = method == 'GET'
        ? await _client.get(uri, headers: headers)
        : await _client.post(uri,
            headers: headers, body: body == null ? null : jsonEncode(body));
    Map<String, dynamic> decoded = const {};
    if (response.body.isNotEmpty) {
      final value = jsonDecode(response.body);
      if (value is Map<String, dynamic>) decoded = value;
    }
    return ApiResponse(statusCode: response.statusCode, body: decoded);
  }
}
