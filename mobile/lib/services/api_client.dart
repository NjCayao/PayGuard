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
    required this.businessName,
    required this.deviceId,
    required this.deviceName,
  });

  final String token;
  final String businessName;
  final int deviceId;
  final String deviceName;
}

class DevicePayment {
  const DevicePayment({
    required this.id,
    required this.amount,
    required this.payerName,
    required this.securityCode,
    required this.receivedAt,
  });

  final int id;
  final String? amount;
  final String? payerName;
  final String? securityCode;
  final DateTime receivedAt;

  static DevicePayment fromJson(Map<String, dynamic> json) {
    return DevicePayment(
      id: (json['id'] as num).toInt(),
      amount: json['amount'] as String?,
      payerName: json['payer_name'] as String?,
      securityCode: json['security_code'] as String?,
      receivedAt: DateTime.parse(json['received_at'] as String),
    );
  }
}

class PaymentList {
  const PaymentList({required this.payments, required this.todayCount});

  final List<DevicePayment> payments;
  final int todayCount;
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
      businessName: (data['business_name'] as String?) ?? '',
      deviceId: (device['id'] as num?)?.toInt() ?? 0,
      deviceName: (device['name'] as String?) ?? '',
    );
  }

  Future<PaymentList> fetchPayments({
    required String token,
    int limit = 20,
  }) async {
    final http.Response response = await _get(
      '/api/device/payments?limit=$limit',
      token: token,
    );

    if (response.statusCode != 200) {
      throw ApiException(_messageOf(response), statusCode: response.statusCode);
    }

    final Map<String, dynamic> decoded =
        jsonDecode(response.body) as Map<String, dynamic>;
    final Object? data = decoded['data'];
    final Object? meta = decoded['meta'];
    if (data is! List || meta is! Map<String, dynamic>) {
      throw ApiException(
        Strings.unexpectedResponse,
        statusCode: response.statusCode,
      );
    }

    return PaymentList(
      payments: data
          .whereType<Map<String, dynamic>>()
          .map(DevicePayment.fromJson)
          .toList(),
      todayCount: (meta['today_count'] as num?)?.toInt() ?? 0,
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

  Future<http.Response> _get(String path, {required String token}) async {
    try {
      return await _client
          .get(
            Uri.parse('$_baseUrl$path'),
            headers: {
              'Accept': 'application/json',
              'Authorization': 'Bearer $token',
            },
          )
          .timeout(timeout);
    } on TimeoutException {
      throw ApiException(Strings.noConnection);
    } on http.ClientException {
      throw ApiException(Strings.noConnection);
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
