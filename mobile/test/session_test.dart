import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/core/config.dart';
import 'package:mobile/services/native_bridge.dart';
import 'package:mobile/services/session.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  const MethodChannel channel = MethodChannel('app.payguard.mobile/native');
  final Map<String, String> store = <String, String>{};

  setUp(() {
    store.clear();
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(channel, (MethodCall call) async {
      switch (call.method) {
        case 'secureGet':
          final Map<Object?, Object?> args =
              call.arguments as Map<Object?, Object?>;
          return store[args['key'] as String];
        case 'secureSet':
          final Map<Object?, Object?> args =
              call.arguments as Map<Object?, Object?>;
          store[args['key'] as String] = args['value'] as String;
          return null;
        case 'secureClear':
          store.clear();
          return null;
        case 'clearQueue':
        case 'clearSessionInvalid':
          return null;
        default:
          throw PlatformException(code: 'unmocked');
      }
    });
  });

  SessionManager session() => SessionManager(bridge: NativeBridge());

  test('installId is created once and then reused', () async {
    final SessionManager manager = session();

    final String first = await manager.installId();
    final String second = await manager.installId();

    expect(first, isNotEmpty);
    expect(second, first);
  });

  test('baseUrl prefers a valid override over the default', () async {
    final SessionManager manager = session();

    expect(await manager.baseUrl(), AppConfig.defaultBaseUrl);

    await manager.setBaseUrlOverride('http://10.0.2.2:8000/');
    expect(await manager.baseUrl(), 'http://10.0.2.2:8000');
    expect(store[NativeBridge.keyBaseUrl], 'http://10.0.2.2:8000');

    await manager.setBaseUrlOverride(null);
    expect(await manager.baseUrl(), AppConfig.defaultBaseUrl);
  });

  test('logout keeps install id and override', () async {
    final SessionManager manager = session();
    final String installId = await manager.installId();
    await manager.setBaseUrlOverride('http://10.0.2.2:8000');
    await manager.saveLogin(token: 'tok-1', deviceName: 'Phone');

    await manager.logout();

    expect(store[NativeBridge.keyToken], isNull);
    expect(store[NativeBridge.keyInstallId], installId);
    expect(
      store[NativeBridge.keyBaseUrlOverride],
      'http://10.0.2.2:8000',
    );
  });
}
