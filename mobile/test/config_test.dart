import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/core/config.dart';

void main() {
  test('normalizeBaseUrl trims and strips trailing slashes', () {
    expect(
      AppConfig.normalizeBaseUrl('  https://example.test// '),
      'https://example.test',
    );
  });

  test('isValidBaseUrl accepts http and https', () {
    expect(AppConfig.isValidBaseUrl('https://payguard.micaleth.com'), isTrue);
    expect(AppConfig.isValidBaseUrl('http://10.0.2.2:8000'), isTrue);
  });

  test('isValidBaseUrl rejects bad values', () {
    expect(AppConfig.isValidBaseUrl(''), isFalse);
    expect(AppConfig.isValidBaseUrl('not-a-url'), isFalse);
    expect(AppConfig.isValidBaseUrl('ftp://example.test'), isFalse);
    expect(AppConfig.isValidBaseUrl('https://'), isFalse);
  });

  test('formatDateTime renders day, month, year, hour and minute', () {
    expect(
      AppConfig.formatDateTime(DateTime(2026, 10, 27, 15, 4)),
      '27/10/2026 15:04',
    );
  });

  test('formatTime renders 12-hour time with suffix', () {
    expect(AppConfig.formatTime(DateTime(2026, 10, 27, 10, 44)), '10:44 a. m.');
    expect(AppConfig.formatTime(DateTime(2026, 10, 27, 15, 5)), '3:05 p. m.');
    expect(AppConfig.formatTime(DateTime(2026, 10, 27, 0, 1)), '12:01 a. m.');
  });

  test('formatRelative renders minutes, hours and days', () {
    final DateTime now = DateTime(2026, 10, 27, 15, 0);
    expect(
      AppConfig.formatRelative(now.subtract(const Duration(seconds: 30)), now),
      'Ahora mismo',
    );
    expect(
      AppConfig.formatRelative(now.subtract(const Duration(minutes: 5)), now),
      'Hace 5 min',
    );
    expect(
      AppConfig.formatRelative(now.subtract(const Duration(hours: 3)), now),
      'Hace 3 h',
    );
    expect(
      AppConfig.formatRelative(now.subtract(const Duration(days: 2)), now),
      'Hace 2 d',
    );
  });

  test('formatMoney renders soles or dash', () {
    expect(AppConfig.formatMoney('50.00'), 'S/ 50.00');
    expect(AppConfig.formatMoney(null), '—');
  });
}
