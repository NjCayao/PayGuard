import 'dart:async';
import 'dart:convert';

import 'package:http/http.dart' as http;

import '../core/strings/strings.dart';

class ApiException implements Exception {
  ApiException(this.message, {this.statusCode});

  final String message;
  final int? statusCode;

  bool get unauthorized => statusCode == 401;

  @override
  String toString() => message;
}

class LoginResult {
  const LoginResult({
    required this.token,
    required this.deviceId,
    required this.deviceName,
  });

  final String token;
  final int deviceId;
  final String deviceName;
}

class ApiClient {
  ApiClient({required http.Client client, required String baseUrl})
      : _client = client,
        _baseUrl = baseUrl;

  final http.Client _client;
  final String _baseUrl;

  static const Duration timeout = Duration(seconds: 15);

  Future<LoginResult> login({
    required String email,
    required String password,
    required String brand,
    required String model,
    required String installId,
  }) async {
    final http.Response response = await _post(
      '/api/device/login',
      body: {
        'email': email,
        'password': password,
        'brand': brand,
        'model': model,
        'install_id': installId,
      },
    );

    if (response.statusCode != 200) {
      throw ApiException(_messageOf(response), statusCode: response.statusCode);
    }

    final Map<String, dynamic> data = _dataOf(response);
    final Object? device = data['device'];
    final Object? token = data['token'];
    if (token is! String || device is! Map<String, dynamic>) {
      throw ApiException(
        Strings.unexpectedResponse,
        statusCode: response.statusCode,
      );
    }

    return LoginResult(
      token: token,
      deviceId: (device['id'] as num?)?.toInt() ?? 0,
      deviceName: (device['name'] as String?) ?? '',
    );
  }

  Future<void> heartbeat({required String token}) async {
    final http.Response response =
        await _post('/api/device/heartbeat', token: token);

    if (response.statusCode != 200) {
      throw ApiException(_messageOf(response), statusCode: response.statusCode);
    }
  }

  Future<bool> logout({required String token}) async {
    try {
      final http.Response response =
          await _post('/api/device/logout', token: token);
      return response.statusCode == 200;
    } on ApiException {
      return false;
    }
  }

  Future<http.Response> _post(
    String path, {
    String? token,
    Map<String, dynamic>? body,
  }) async {
    try {
      final Map<String, String> headers = {'Accept': 'application/json'};
      if (token != null) {
        headers['Authorization'] = 'Bearer $token';
      }
      if (body != null) {
        headers['Content-Type'] = 'application/json';
      }
      return await _client
          .post(
            Uri.parse('$_baseUrl$path'),
            headers: headers,
            body: body == null ? null : jsonEncode(body),
          )
          .timeout(timeout);
    } on TimeoutException {
      throw ApiException(Strings.noConnection);
    } on http.ClientException {
      throw ApiException(Strings.noConnection);
    }
  }

  Map<String, dynamic> _dataOf(http.Response response) {
    final Map<String, dynamic> decoded =
        jsonDecode(response.body) as Map<String, dynamic>;
    final Object? data = decoded['data'];
    if (data is! Map<String, dynamic>) {
      throw ApiException(
        Strings.unexpectedResponse,
        statusCode: response.statusCode,
      );
    }
    return data;
  }

  String _messageOf(http.Response response) {
    try {
      final Map<String, dynamic> decoded =
          jsonDecode(response.body) as Map<String, dynamic>;
      final Object? message = decoded['message'];
      if (message is String && message.isNotEmpty) {
        return message;
      }
    } on FormatException {
      // Fall through to the generic message below.
    }
    return Strings.genericError;
  }
}
