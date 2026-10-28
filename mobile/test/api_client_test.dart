import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:mobile/services/api_client.dart';

void main() {
  test('login parses token, business and device', () async {
    final ApiClient api = ApiClient(
      client: MockClient((http.Request request) async {
        return http.Response(
          jsonEncode({
            'data': {
              'token': 'tok-1',
              'business_name': 'Bodega Central',
              'device': {'id': 7, 'name': 'Xiaomi Redmi 12'},
            },
          }),
          200,
        );
      }),
      baseUrl: 'https://example.test',
    );

    final LoginResult result = await api.login(
      email: 'owner@example.test',
      password: 'secret',
      brand: 'Xiaomi',
      model: 'Redmi 12',
      installId: 'install-1',
    );

    expect(result.token, 'tok-1');
    expect(result.businessName, 'Bodega Central');
    expect(result.deviceId, 7);
    expect(result.deviceName, 'Xiaomi Redmi 12');
  });

  test('login sends brand, model and install id', () async {
    Map<String, dynamic>? sent;
    final ApiClient api = ApiClient(
      client: MockClient((http.Request request) async {
        sent = jsonDecode(request.body) as Map<String, dynamic>;
        return http.Response(
          jsonEncode({
            'data': {
              'token': 'tok-1',
              'device': {'id': 1, 'name': 'n'},
            },
          }),
          200,
        );
      }),
      baseUrl: 'https://example.test',
    );

    await api.login(
      email: 'owner@example.test',
      password: 'secret',
      brand: 'Xiaomi',
      model: 'Redmi 12',
      installId: 'install-1',
    );

    expect(sent?['brand'], 'Xiaomi');
    expect(sent?['model'], 'Redmi 12');
    expect(sent?['install_id'], 'install-1');
  });

  test('login with bad credentials throws server message', () async {
    final ApiClient api = ApiClient(
      client: MockClient((_) async {
        return http.Response(
          jsonEncode({'message': 'El correo o la contraseña no son correctos.'}),
          401,
        );
      }),
      baseUrl: 'https://example.test',
    );

    try {
      await api.login(
        email: 'owner@example.test',
        password: 'wrong',
        brand: 'b',
        model: 'm',
        installId: 'i',
      );
      fail('expected ApiException');
    } on ApiException catch (error) {
      expect(error.unauthorized, isTrue);
      expect(error.message, 'El correo o la contraseña no son correctos.');
    }
  });

  test('login with unexpected shape throws', () async {
    final ApiClient api = ApiClient(
      client: MockClient((_) async {
        return http.Response(jsonEncode({'data': 'weird'}), 200);
      }),
      baseUrl: 'https://example.test',
    );

    expect(
      () => api.login(
        email: 'e',
        password: 'p',
        brand: 'b',
        model: 'm',
        installId: 'i',
      ),
      throwsA(isA<ApiException>()),
    );
  });

  test('heartbeat sends bearer token', () async {
    String? authorization;
    final ApiClient api = ApiClient(
      client: MockClient((http.Request request) async {
        authorization = request.headers['Authorization'];
        return http.Response(jsonEncode({'data': {'ok': true}}), 200);
      }),
      baseUrl: 'https://example.test',
    );

    await api.heartbeat(token: 'tok-1');

    expect(authorization, 'Bearer tok-1');
  });

  test('heartbeat with revoked token throws unauthorized', () async {
    final ApiClient api = ApiClient(
      client: MockClient((_) async {
        return http.Response('{}', 401);
      }),
      baseUrl: 'https://example.test',
    );

    try {
      await api.heartbeat(token: 'dead');
      fail('expected ApiException');
    } on ApiException catch (error) {
      expect(error.unauthorized, isTrue);
    }
  });

  test('fetchPayments parses list and today count', () async {
    String? path;
    final ApiClient api = ApiClient(
      client: MockClient((http.Request request) async {
        path = '${request.url.path}?${request.url.query}';
        return http.Response(
          jsonEncode({
            'data': [
              {
                'id': 3,
                'amount': '50.00',
                'payer_name': 'JUAN PEREZ',
                'security_code': '482',
                'received_at': '2026-10-27T15:44:00.000Z',
              },
              {
                'id': 2,
                'amount': null,
                'payer_name': null,
                'security_code': null,
                'received_at': '2026-10-27T14:00:00.000Z',
              },
            ],
            'meta': {'today_count': 4},
          }),
          200,
        );
      }),
      baseUrl: 'https://example.test',
    );

    final PaymentList list =
        await api.fetchPayments(token: 'tok-1', limit: 5);

    expect(path, '/api/device/payments?limit=5');
    expect(list.todayCount, 4);
    expect(list.payments.length, 2);
    expect(list.payments[0].payerName, 'JUAN PEREZ');
    expect(list.payments[0].securityCode, '482');
    expect(list.payments[0].amount, '50.00');
    expect(list.payments[1].payerName, isNull);
  });

  test('fetchPayments with revoked token throws unauthorized', () async {
    final ApiClient api = ApiClient(
      client: MockClient((_) async {
        return http.Response('{}', 401);
      }),
      baseUrl: 'https://example.test',
    );

    try {
      await api.fetchPayments(token: 'dead');
      fail('expected ApiException');
    } on ApiException catch (error) {
      expect(error.unauthorized, isTrue);
    }
  });

  test('logout returns true on success and false on failure', () async {
    final ApiClient ok = ApiClient(
      client: MockClient((_) async {
        return http.Response(jsonEncode({'data': true}), 200);
      }),
      baseUrl: 'https://example.test',
    );
    expect(await ok.logout(token: 'tok-1'), isTrue);

    final ApiClient broken = ApiClient(
      client: MockClient((_) async {
        return http.Response('error', 500);
      }),
      baseUrl: 'https://example.test',
    );
    expect(await broken.logout(token: 'tok-1'), isFalse);

    final ApiClient offline = ApiClient(
      client: MockClient((_) async {
        throw http.ClientException('no network');
      }),
      baseUrl: 'https://example.test',
    );
    expect(await offline.logout(token: 'tok-1'), isFalse);
  });
}
