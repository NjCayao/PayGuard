import 'package:uuid/uuid.dart';

import '../core/config.dart';
import 'native_bridge.dart';

class SessionManager {
  SessionManager({required NativeBridge bridge, Uuid? uuid})
      : _bridge = bridge,
        _uuid = uuid ?? const Uuid();

  final NativeBridge _bridge;
  final Uuid _uuid;

  Future<String> installId() async {
    final String? stored =
        await _bridge.secureGet(NativeBridge.keyInstallId);
    if (stored != null && stored.isNotEmpty) {
      return stored;
    }
    final String created = _uuid.v4();
    await _bridge.secureSet(NativeBridge.keyInstallId, created);
    return created;
  }

  Future<String> baseUrl() async {
    final String? override =
        await _bridge.secureGet(NativeBridge.keyBaseUrlOverride);
    if (override != null && AppConfig.isValidBaseUrl(override)) {
      return AppConfig.normalizeBaseUrl(override);
    }
    return AppConfig.defaultBaseUrl;
  }

  Future<void> setBaseUrlOverride(String? value) async {
    if (value == null || value.trim().isEmpty) {
      await _bridge.secureSet(NativeBridge.keyBaseUrlOverride, '');
    } else {
      await _bridge.secureSet(
        NativeBridge.keyBaseUrlOverride,
        AppConfig.normalizeBaseUrl(value),
      );
    }
    await syncBaseUrl();
  }

  Future<void> syncBaseUrl() async {
    await _bridge.secureSet(NativeBridge.keyBaseUrl, await baseUrl());
  }

  Future<String?> token() => _bridge.secureGet(NativeBridge.keyToken);

  Future<String?> deviceName() =>
      _bridge.secureGet(NativeBridge.keyDeviceName);

  Future<void> saveLogin({
    required String token,
    required String deviceName,
  }) async {
    await _bridge.secureSet(NativeBridge.keyToken, token);
    await _bridge.secureSet(NativeBridge.keyDeviceName, deviceName);
    await _bridge.clearSessionInvalid();
    await syncBaseUrl();
  }

  Future<void> logout() async {
    final String? keptInstallId =
        await _bridge.secureGet(NativeBridge.keyInstallId);
    final String? keptOverride =
        await _bridge.secureGet(NativeBridge.keyBaseUrlOverride);
    await _bridge.clearQueue();
    await _bridge.clearSessionInvalid();
    await _bridge.secureClear();
    if (keptInstallId != null && keptInstallId.isNotEmpty) {
      await _bridge.secureSet(NativeBridge.keyInstallId, keptInstallId);
    }
    if (keptOverride != null && keptOverride.isNotEmpty) {
      await _bridge.secureSet(NativeBridge.keyBaseUrlOverride, keptOverride);
    }
    await syncBaseUrl();
  }
}
