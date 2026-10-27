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
}
