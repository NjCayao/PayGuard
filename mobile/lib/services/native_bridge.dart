import 'package:flutter/services.dart';

class NativeBridge {
  static const MethodChannel _channel =
      MethodChannel('app.payguard.mobile/native');

  static const String keyToken = 'auth_token';
  static const String keyBaseUrl = 'base_url';
  static const String keyBaseUrlOverride = 'base_url_override';
  static const String keyInstallId = 'install_id';
  static const String keyDeviceName = 'device_name';
  static const String keyBusinessName = 'business_name';

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

  Future<DateTime?> lastContactAt() async {
    final int? ms = await _channel.invokeMethod<int>('lastContactAt');
    if (ms == null || ms <= 0) {
      return null;
    }
    return DateTime.fromMillisecondsSinceEpoch(ms);
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

  Future<bool> voiceEnabled() async {
    return (await _channel.invokeMethod<bool>('voiceEnabled')) ?? true;
  }

  Future<void> setVoiceEnabled(bool value) {
    return _channel.invokeMethod('setVoiceEnabled', {'value': value});
  }

  Future<bool> voiceAlarmStream() async {
    return (await _channel.invokeMethod<bool>('voiceAlarmStream')) ?? false;
  }

  Future<void> setVoiceAlarmStream(bool value) {
    return _channel.invokeMethod('setVoiceAlarmStream', {'value': value});
  }

  Future<void> testVoice() {
    return _channel.invokeMethod('voiceTest');
  }

  Future<VoiceStatus> voiceStatus() async {
    final Map<dynamic, dynamic>? raw =
        await _channel.invokeMethod<Map<dynamic, dynamic>>('voiceStatus');
    if (raw == null) {
      return const VoiceStatus(enabled: true, alarmStream: false);
    }
    return VoiceStatus(
      enabled: raw['enabled'] as bool? ?? true,
      alarmStream: raw['alarmStream'] as bool? ?? false,
      spanishAvailable: raw['spanishAvailable'] as bool?,
      locale: raw['locale'] as String?,
    );
  }

  Future<void> openTtsSettings() {
    return _channel.invokeMethod('openTtsSettings');
  }
}

class VoiceStatus {
  const VoiceStatus({
    required this.enabled,
    required this.alarmStream,
    this.spanishAvailable,
    this.locale,
  });

  final bool enabled;
  final bool alarmStream;
  final bool? spanishAvailable;
  final String? locale;
}
