import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;

import '../../core/config.dart';
import '../../core/strings/strings.dart';
import '../../core/theme/app_spacing.dart';
import '../../core/theme/app_text_styles.dart';
import '../../core/widgets/app_scaffold.dart';
import '../../core/widgets/notice_banner.dart';
import '../../core/widgets/payment_row.dart';
import '../../services/api_client.dart';
import '../../services/session.dart';

class HistoryScreen extends StatefulWidget {
  const HistoryScreen({super.key, required this.session});

  final SessionManager session;

  @override
  State<HistoryScreen> createState() => _HistoryScreenState();
}

class _HistoryScreenState extends State<HistoryScreen> {
  List<DevicePayment>? _items;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _error = null);
    try {
      final String? token = await widget.session.token();
      if (token == null) {
        throw ApiException(Strings.sessionExpiredForHistory);
      }
      final String baseUrl = await widget.session.baseUrl();
      final ApiClient api =
          ApiClient(client: http.Client(), baseUrl: baseUrl);
      final PaymentList list = await api.fetchPayments(
        token: token,
        limit: 50,
      );
      if (!mounted) {
        return;
      }
      setState(() => _items = list.payments);
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }
      setState(() {
        _items = const [];
        _error = error.message;
      });
    } catch (_) {
      if (!mounted) {
        return;
      }
      setState(() {
        _items = const [];
        _error = Strings.genericError;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final List<DevicePayment>? items = _items;

    return AppScaffold(
      title: Strings.historyTitle,
      child: items == null
          ? const Center(child: CircularProgressIndicator())
          : Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (_error != null) ...[
                  NoticeBanner.error(_error!),
                  const SizedBox(height: AppSpacing.xs),
                  TextButton(
                    onPressed: _load,
                    child: const Text(
                      Strings.retry,
                      style: AppTextStyles.action,
                    ),
                  ),
                ] else if (items.isEmpty) ...[
                  const Text(
                    Strings.historyEmpty,
                    style: AppTextStyles.detail,
                  ),
                ] else ...[
                  for (int i = 0; i < items.length; i++) ...[
                    if (i > 0) const Divider(),
                    _paymentRow(items[i]),
                  ],
                ],
              ],
            ),
    );
  }

  Widget _paymentRow(DevicePayment payment) {
    final String time =
        AppConfig.formatTime(payment.receivedAt.toLocal());
    final String detail = payment.securityCode == null
        ? time
        : '$time · ${Strings.codeLabel} ${payment.securityCode}';
    return PaymentRow(
      name: payment.payerName ?? Strings.unparsedPayment,
      detail: detail,
      amount: AppConfig.formatMoney(payment.amount),
    );
  }
}
