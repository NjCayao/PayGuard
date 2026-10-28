class AppConfig {
  static const String defaultBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://payguard.micaleth.com',
  );

  static const String appVersion = '1.0.0';

  static String normalizeBaseUrl(String value) {
    var normalized = value.trim();
    while (normalized.endsWith('/')) {
      normalized = normalized.substring(0, normalized.length - 1);
    }
    return normalized;
  }

  static bool isValidBaseUrl(String value) {
    final Uri? uri = Uri.tryParse(normalizeBaseUrl(value));
    if (uri == null || !uri.hasScheme || uri.host.isEmpty) {
      return false;
    }
    return uri.scheme == 'http' || uri.scheme == 'https';
  }

  static String formatDateTime(DateTime value) {
    String two(int part) => part.toString().padLeft(2, '0');
    return '${two(value.day)}/${two(value.month)}/${value.year} '
        '${two(value.hour)}:${two(value.minute)}';
  }

  static String formatTime(DateTime value) {
    final int hour12 = value.hour % 12 == 0 ? 12 : value.hour % 12;
    final String minutes = value.minute.toString().padLeft(2, '0');
    final String suffix = value.hour < 12 ? 'a. m.' : 'p. m.';
    return '$hour12:$minutes $suffix';
  }

  static String formatRelative(DateTime value, DateTime now) {
    final Duration diff = now.difference(value);
    if (diff.inSeconds < 60) {
      return 'Ahora mismo';
    }
    if (diff.inMinutes < 60) {
      return 'Hace ${diff.inMinutes} min';
    }
    if (diff.inHours < 24) {
      return 'Hace ${diff.inHours} h';
    }
    return 'Hace ${diff.inDays} d';
  }

  static String formatMoney(String? amount) {
    if (amount == null || amount.isEmpty) {
      return '—';
    }
    return 'S/ $amount';
  }
}
