import 'package:flutter/services.dart';

class SentPayment {
  const SentPayment({
    required this.text,
    required this.postedAt,
    required this.sentAt,
  });

  final String text;
  final DateTime postedAt;
  final DateTime sentAt;

  static SentPayment fromMap(Map<Object?, Object?> map) {
    final int postedMs = (map['postedMs'] as int?) ?? 0;
    final int sentMs = (map['sentMs'] as int?) ?? 0;
    return SentPayment(
      text: (map['text'] as String?) ?? '',
      postedAt: DateTime.fromMillisecondsSinceEpoch(postedMs),
      sentAt: DateTime.fromMillisecondsSinceEpoch(sentMs),
    );
  }
}

class NativeBridge {
  static const MethodChannel _channel =
      MethodChannel('app.payguard.mobile/native');

  static const String keyToken = 'auth_token';
  static const String keyBaseUrl = 'base_url';
  static const String keyBaseUrlOverride = 'base_url_override';
  static const String keyInstallId = 'install_id';
  static const String keyDeviceName = 'device_name';

  Future<String?> secureGet(String key) {
    return _channel.invokeMethod<String>('secureGet', {'key': key});
  }

  Future<void> secureSet(String key, String value) {
    return _channel.invokeMethod('secureSet', {'key': key, 'value': value});
  }

  Future<void> secureClear() {
    return _channel.invokeMethod('secureClear');
  }

  Future<String> deviceBrand() async {
    return (await _channel.invokeMethod<String>('deviceBrand')) ?? '';
  }

  Future<String> deviceModel() async {
    return (await _channel.invokeMethod<String>('deviceModel')) ?? '';
  }

  Future<int> sdkInt() async {
    return (await _channel.invokeMethod<int>('sdkInt')) ?? 0;
  }

  Future<bool> isListenerEnabled() async {
    return (await _channel.invokeMethod<bool>('isListenerEnabled')) ?? false;
  }

  Future<void> openNotificationListenerSettings() {
    return _channel.invokeMethod('openNotificationListenerSettings');
  }

  Future<bool> isIgnoringBatteryOptimizations() async {
    return (await _channel.invokeMethod<bool>(
            'isIgnoringBatteryOptimizations')) ??
        false;
  }

  Future<void> requestIgnoreBatteryOptimizations() {
    return _channel.invokeMethod('requestIgnoreBatteryOptimizations');
  }

  Future<void> openAppSettings() {
    return _channel.invokeMethod('openAppSettings');
  }

  Future<int> pendingCount() async {
    return (await _channel.invokeMethod<int>('pendingCount')) ?? 0;
  }

  Future<DateTime?> lastSentAt() async {
    final int? ms = await _channel.invokeMethod<int>('lastSentAt');
    if (ms == null || ms <= 0) {
      return null;
    }
    return DateTime.fromMillisecondsSinceEpoch(ms);
  }

  Future<List<SentPayment>> sentHistory() async {
    final List<Object?>? raw =
        await _channel.invokeMethod<List<Object?>>('sentHistory');
    if (raw == null) {
      return const [];
    }
    return raw
        .whereType<Map<Object?, Object?>>()
        .map(SentPayment.fromMap)
        .toList();
  }

  Future<void> clearQueue() {
    return _channel.invokeMethod('clearQueue');
  }

  Future<void> triggerSync() {
    return _channel.invokeMethod('triggerSync');
  }

  Future<bool> isSessionInvalid() async {
    return (await _channel.invokeMethod<bool>('isSessionInvalid')) ?? false;
  }

  Future<void> clearSessionInvalid() {
    return _channel.invokeMethod('clearSessionInvalid');
  }
}
