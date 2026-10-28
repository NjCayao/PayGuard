import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;

import '../../core/config.dart';
import '../../core/strings/strings.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_spacing.dart';
import '../../core/theme/app_text_styles.dart';
import '../../core/widgets/app_logo.dart';
import '../../core/widgets/app_scaffold.dart';
import '../../core/widgets/info_box.dart';
import '../../core/widgets/notice_banner.dart';
import '../../core/widgets/payment_row.dart';
import '../../core/widgets/stat_box.dart';
import '../../core/widgets/status_header.dart';
import '../../core/widgets/step_row.dart';
import '../../services/api_client.dart';
import '../../services/native_bridge.dart';
import '../../services/session.dart';
import '../account/account_screen.dart';
import '../history/history_screen.dart';
import '../login/login_screen.dart';

class _StatusData {
  const _StatusData({
    required this.listenerEnabled,
    required this.sdkInt,
    required this.batteryExempt,
    required this.token,
    required this.businessName,
    required this.sessionInvalid,
    required this.pending,
    required this.lastContact,
  });

  final bool listenerEnabled;
  final int sdkInt;
  final bool batteryExempt;
  final String? token;
  final String businessName;
  final bool sessionInvalid;
  final int pending;
  final DateTime? lastContact;

  bool get sessionOk => token != null && !sessionInvalid;
  bool get ready => listenerEnabled && batteryExempt && sessionOk;

  int get missingCount =>
      [!listenerEnabled, !batteryExempt, !sessionOk]
          .where((bool missing) => missing)
          .length;
}

class StatusScreen extends StatefulWidget {
  const StatusScreen({
    super.key,
    required this.session,
    required this.bridge,
  });

  final SessionManager session;
  final NativeBridge bridge;

  @override
  State<StatusScreen> createState() => _StatusScreenState();
}

class _StatusScreenState extends State<StatusScreen>
    with WidgetsBindingObserver {
  _StatusData? _data;
  PaymentList? _payments;
  bool _loading = true;
  String? _error;
  String? _paymentsError;

  static const int _recentLimit = 5;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _load();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _load();
    }
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
      _paymentsError = null;
    });
    try {
      final bool listener = await widget.bridge.isListenerEnabled();
      final int sdk = await widget.bridge.sdkInt();
      final bool battery =
          await widget.bridge.isIgnoringBatteryOptimizations();
      final String? token = await widget.session.token();
      final String business = await widget.session.businessName() ?? '';
      final bool invalid = await widget.bridge.isSessionInvalid();
      final int pending = await widget.bridge.pendingCount();
      final DateTime? contact = await widget.bridge.lastContactAt();

      PaymentList? payments;
      if (token != null && !invalid) {
        try {
          final String baseUrl = await widget.session.baseUrl();
          final ApiClient api =
              ApiClient(client: http.Client(), baseUrl: baseUrl);
          payments = await api.fetchPayments(
            token: token,
            limit: _recentLimit,
          );
        } on ApiException catch (error) {
          _paymentsError = error.message;
          if (error.unauthorized) {
            await widget.bridge.triggerSync();
          }
        }
      }

      if (!mounted) {
        return;
      }
      setState(() {
        _data = _StatusData(
          listenerEnabled: listener,
          sdkInt: sdk,
          batteryExempt: battery,
          token: token,
          businessName: business,
          sessionInvalid: invalid,
          pending: pending,
          lastContact: contact,
        );
        _payments = payments;
        _loading = false;
      });
    } catch (_) {
      if (!mounted) {
        return;
      }
      setState(() {
        _error = Strings.genericError;
        _loading = false;
      });
    }
  }

  void _goToLogin() {
    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute(
        builder: (_) => LoginScreen(
          session: widget.session,
          bridge: widget.bridge,
        ),
      ),
      (_) => false,
    );
  }

  void _openAccount() {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => AccountScreen(
          session: widget.session,
          bridge: widget.bridge,
        ),
      ),
    );
  }

  void _openHistory() {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => HistoryScreen(session: widget.session),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final _StatusData? data = _data;

    return AppScaffold(
      onRefresh: _load,
      child: _loading
          ? const Center(child: CircularProgressIndicator())
          : data == null
              ? Column(
                  children: [
                    NoticeBanner.error(_error ?? Strings.genericError),
                    const SizedBox(height: AppSpacing.sm),
                    TextButton(
                      onPressed: _load,
                      child: const Text(
                        Strings.retry,
                        style: AppTextStyles.action,
                      ),
                    ),
                  ],
                )
              : Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _header(data),
                    const SizedBox(height: AppSpacing.md),
                    _statusBlock(data),
                    const SizedBox(height: AppSpacing.md),
                    if (data.ready) ...[
                      _stats(data),
                      const SizedBox(height: AppSpacing.md),
                      _recent(),
                    ] else ...[
                      _checklist(data),
                    ],
                  ],
                ),
    );
  }

  Widget _header(_StatusData data) {
    return Row(
      children: [
        const AppLogo(size: 28),
        const SizedBox(width: AppSpacing.xs),
        Expanded(
          child: Text(
            data.businessName.isEmpty
                ? Strings.appName
                : data.businessName,
            style: AppTextStyles.bodyBold,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
        ),
        IconButton(
          icon: const Icon(Icons.account_circle_outlined),
          color: AppColors.muted,
          onPressed: _openAccount,
        ),
      ],
    );
  }

  Widget _statusBlock(_StatusData data) {
    if (data.ready) {
      return const StatusHeader(
        dotColor: AppColors.successDot,
        label: Strings.readyLabel,
        labelColor: AppColors.primaryDark,
        title: Strings.readyTitle,
        subtitle: Strings.readySubtitle,
      );
    }
    return StatusHeader(
      dotColor: AppColors.warningDot,
      label: Strings.incompleteLabel,
      labelColor: AppColors.warningText,
      title: Strings.incompleteTitle(data.missingCount),
      subtitle: Strings.incompleteSubtitle,
    );
  }

  Widget _checklist(_StatusData data) {
    return Column(
      children: [
        StepRow(
          icon: Icons.notifications_outlined,
          title: Strings.rowNotifications,
          detail: Strings.rowNotificationsDetail,
          done: data.listenerEnabled,
          actionLabel:
              data.listenerEnabled ? null : Strings.actionActivate,
          onAction: data.listenerEnabled
              ? null
              : widget.bridge.openNotificationListenerSettings,
        ),
        const Divider(),
        StepRow(
          icon: Icons.bolt_outlined,
          title: Strings.rowAlwaysOn,
          detail: Strings.rowAlwaysOnDetail,
          done: data.batteryExempt,
          actionLabel:
              data.batteryExempt ? null : Strings.actionAllow,
          onAction: data.batteryExempt
              ? null
              : widget.bridge.requestIgnoreBatteryOptimizations,
        ),
        const Divider(),
        StepRow(
          icon: Icons.cloud_outlined,
          title: Strings.rowConnected,
          detail: _connectionDetail(data),
          done: data.sessionOk,
          actionLabel: data.sessionOk ? null : Strings.actionLogin,
          onAction: data.sessionOk ? null : _goToLogin,
        ),
        if (!data.listenerEnabled && data.sdkInt >= 33) ...[
          const SizedBox(height: AppSpacing.sm),
          const InfoBox(
            title: Strings.restrictedTitle,
            body: Strings.restrictedSteps,
          ),
        ],
      ],
    );
  }

  String _connectionDetail(_StatusData data) {
    if (!data.sessionOk) {
      return Strings.rowSessionExpired;
    }
    if (data.lastContact == null) {
      return Strings.neverContacted;
    }
    return AppConfig.formatRelative(
      data.lastContact!.toLocal(),
      DateTime.now(),
    );
  }

  Widget _stats(_StatusData data) {
    final String connection = data.lastContact == null
        ? Strings.neverContacted
        : AppConfig.formatRelative(
            data.lastContact!.toLocal(),
            DateTime.now(),
          );
    return Row(
      children: [
        Expanded(
          child: StatBox(
            label: Strings.statToday,
            value: Strings.paymentsToday(_payments?.todayCount ?? 0),
          ),
        ),
        const SizedBox(width: AppSpacing.xs),
        Expanded(
          child: StatBox(
            label: Strings.statPending,
            value: '${data.pending}',
          ),
        ),
        const SizedBox(width: AppSpacing.xs),
        Expanded(
          child: StatBox(
            label: Strings.statConnection,
            value: connection,
          ),
        ),
      ],
    );
  }

  Widget _recent() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            const Expanded(
              child: Text(
                Strings.recentTitle,
                style: AppTextStyles.bodyBold,
              ),
            ),
            TextButton(
              onPressed: _openHistory,
              child: const Text(
                Strings.seeAll,
                style: AppTextStyles.action,
              ),
            ),
          ],
        ),
        if (_paymentsError != null) ...[
          NoticeBanner.error(_paymentsError!),
          TextButton(
            onPressed: _load,
            child: const Text(
              Strings.retry,
              style: AppTextStyles.action,
            ),
          ),
        ] else if (_payments == null || _payments!.payments.isEmpty) ...[
          const Text(
            Strings.recentEmpty,
            style: AppTextStyles.detail,
          ),
        ] else ...[
          for (int i = 0; i < _payments!.payments.length; i++) ...[
            if (i > 0) const Divider(),
            _paymentRow(_payments!.payments[i]),
          ],
        ],
      ],
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
