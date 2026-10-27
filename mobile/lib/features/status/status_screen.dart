import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;

import '../../core/config.dart';
import '../../core/strings/strings.dart';
import '../../core/theme/app_spacing.dart';
import '../../core/theme/app_text_styles.dart';
import '../../core/widgets/app_scaffold.dart';
import '../../core/widgets/checklist_item.dart';
import '../../core/widgets/notice_banner.dart';
import '../../services/api_client.dart';
import '../../services/native_bridge.dart';
import '../../services/session.dart';
import '../history/history_screen.dart';
import '../login/login_screen.dart';

class _StatusData {
  const _StatusData({
    required this.listenerEnabled,
    required this.sdkInt,
    required this.batteryExempt,
    required this.token,
    required this.deviceName,
    required this.sessionInvalid,
    required this.pending,
    required this.lastSentAt,
  });

  final bool listenerEnabled;
  final int sdkInt;
  final bool batteryExempt;
  final String? token;
  final String deviceName;
  final bool sessionInvalid;
  final int pending;
  final DateTime? lastSentAt;

  bool get sessionOk => token != null && !sessionInvalid;
  bool get ready => listenerEnabled && batteryExempt && sessionOk;
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

class _StatusScreenState extends State<StatusScreen> with WidgetsBindingObserver {
  _StatusData? _data;
  bool _loading = true;
  String? _error;
  String? _connectionMessage;
  bool _checkingConnection = false;
  bool _showRestrictedGuide = false;

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
    });
    try {
      final bool listener = await widget.bridge.isListenerEnabled();
      final int sdk = await widget.bridge.sdkInt();
      final bool battery =
          await widget.bridge.isIgnoringBatteryOptimizations();
      final String? token = await widget.session.token();
      final String device = await widget.session.deviceName() ?? '';
      final bool invalid = await widget.bridge.isSessionInvalid();
      final int pending = await widget.bridge.pendingCount();
      final DateTime? lastSent = await widget.bridge.lastSentAt();

      if (!mounted) {
        return;
      }
      setState(() {
        _data = _StatusData(
          listenerEnabled: listener,
          sdkInt: sdk,
          batteryExempt: battery,
          token: token,
          deviceName: device,
          sessionInvalid: invalid,
          pending: pending,
          lastSentAt: lastSent,
        );
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

  Future<void> _checkConnection() async {
    final String? token = _data?.token;
    if (token == null) {
      return;
    }
    setState(() {
      _checkingConnection = true;
      _connectionMessage = null;
    });
    try {
      final String baseUrl = await widget.session.baseUrl();
      final ApiClient api = ApiClient(client: http.Client(), baseUrl: baseUrl);
      await api.heartbeat(token: token);
      await widget.bridge.clearSessionInvalid();
      if (!mounted) {
        return;
      }
      setState(() => _connectionMessage = Strings.connectionOk);
      await _load();
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }
      setState(() => _connectionMessage = error.message);
      if (error.unauthorized) {
        await _load();
      }
    } finally {
      if (mounted) {
        setState(() => _checkingConnection = false);
      }
    }
  }

  Future<void> _sendNow() async {
    await widget.bridge.triggerSync();
    await _load();
  }

  Future<void> _confirmLogout() async {
    final bool? confirmed = await showDialog<bool>(
      context: context,
      builder: (BuildContext context) {
        return AlertDialog(
          title: const Text(Strings.logoutTitle),
          content: const Text(Strings.logoutConfirm),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(context).pop(false),
              child: const Text(Strings.logoutCancel),
            ),
            TextButton(
              onPressed: () => Navigator.of(context).pop(true),
              child: const Text(Strings.logoutConfirmButton),
            ),
          ],
        );
      },
    );
    if (confirmed != true || !mounted) {
      return;
    }

    final String? token = _data?.token;
    if (token != null) {
      try {
        final String baseUrl = await widget.session.baseUrl();
        final ApiClient api =
            ApiClient(client: http.Client(), baseUrl: baseUrl);
        await api.logout(token: token);
      } catch (_) {
        // Best effort: the local data is cleared below either way.
      }
    }
    await widget.session.logout();

    if (!mounted) {
      return;
    }
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

  void _openHistory() {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => HistoryScreen(bridge: widget.bridge),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final _StatusData? data = _data;

    return AppScaffold(
      title: Strings.statusTitle,
      actions: [
        IconButton(
          icon: const Icon(Icons.history),
          tooltip: Strings.historyTitle,
          onPressed: _openHistory,
        ),
        IconButton(
          icon: const Icon(Icons.logout),
          tooltip: Strings.logoutTitle,
          onPressed: _confirmLogout,
        ),
      ],
      child: _loading
          ? const Center(child: CircularProgressIndicator())
          : data == null
              ? Column(
                  children: [
                    NoticeBanner.error(_error ?? Strings.genericError),
                    const SizedBox(height: AppSpacing.md),
                    OutlinedButton(
                      onPressed: _load,
                      child: const Text(Strings.retry),
                    ),
                  ],
                )
              : Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (data.ready)
                      const NoticeBanner.ok(Strings.statusReady)
                    else
                      const NoticeBanner.warning(Strings.statusNotReady),
                    const SizedBox(height: AppSpacing.md),
                    ChecklistItem(
                      title: Strings.notificationsTitle,
                      description: data.listenerEnabled
                          ? Strings.notificationsOk
                          : Strings.notificationsMissing,
                      status: data.listenerEnabled
                          ? ChecklistStatus.ok
                          : ChecklistStatus.pending,
                      actionLabel: data.listenerEnabled
                          ? null
                          : Strings.notificationsButton,
                      onAction: data.listenerEnabled
                          ? null
                          : widget.bridge.openNotificationListenerSettings,
                      extra: !data.listenerEnabled && data.sdkInt >= 33
                          ? Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                TextButton(
                                  onPressed: () => setState(() =>
                                      _showRestrictedGuide =
                                          !_showRestrictedGuide),
                                  child: const Text(Strings.restrictedTitle),
                                ),
                                if (_showRestrictedGuide)
                                  const Text(
                                    Strings.restrictedSteps,
                                    style: AppTextStyles.small,
                                  ),
                              ],
                            )
                          : null,
                    ),
                    const SizedBox(height: AppSpacing.sm),
                    ChecklistItem(
                      title: Strings.batteryTitle,
                      description: data.batteryExempt
                          ? Strings.batteryOk
                          : Strings.batteryMissing,
                      status: data.batteryExempt
                          ? ChecklistStatus.ok
                          : ChecklistStatus.pending,
                      actionLabel:
                          data.batteryExempt ? null : Strings.batteryButton,
                      onAction: data.batteryExempt
                          ? null
                          : widget.bridge.requestIgnoreBatteryOptimizations,
                    ),
                    const SizedBox(height: AppSpacing.sm),
                    ChecklistItem(
                      title: Strings.sessionTitle,
                      description: _sessionDescription(data),
                      status: data.sessionOk
                          ? ChecklistStatus.ok
                          : ChecklistStatus.pending,
                      actionLabel: data.sessionOk
                          ? Strings.connectionButton
                          : Strings.sessionButton,
                      onAction: data.sessionOk
                          ? (_checkingConnection ? null : _checkConnection)
                          : _goToLogin,
                      extra: _connectionMessage == null
                          ? null
                          : Text(
                              _connectionMessage!,
                              style: AppTextStyles.small,
                            ),
                    ),
                    const SizedBox(height: AppSpacing.sm),
                    ChecklistItem(
                      title: Strings.queueTitle,
                      description: _queueDescription(data),
                      status: data.pending == 0
                          ? ChecklistStatus.ok
                          : ChecklistStatus.pending,
                      actionLabel: Strings.queueSendNow,
                      onAction: _sendNow,
                      extra: Align(
                        alignment: Alignment.centerRight,
                        child: TextButton(
                          onPressed: _openHistory,
                          child: const Text(Strings.queueSeeAll),
                        ),
                      ),
                    ),
                    const SizedBox(height: AppSpacing.sm),
                    ChecklistItem(
                      title: Strings.autostartTitle,
                      description: Strings.autostartBody,
                      status: ChecklistStatus.ok,
                      actionLabel: Strings.autostartButton,
                      onAction: widget.bridge.openAppSettings,
                    ),
                    const SizedBox(height: AppSpacing.md),
                    Center(
                      child: Text(
                        '${Strings.appName} ${AppConfig.appVersion}',
                        style: AppTextStyles.small,
                      ),
                    ),
                  ],
                ),
    );
  }

  String _sessionDescription(_StatusData data) {
    if (data.token == null) {
      return Strings.sessionMissing;
    }
    if (data.sessionInvalid) {
      return Strings.sessionInvalid;
    }
    if (data.deviceName.isEmpty) {
      return Strings.sessionActive;
    }
    return data.deviceName;
  }

  String _queueDescription(_StatusData data) {
    final String last = data.lastSentAt == null
        ? Strings.queueNever
        : AppConfig.formatDateTime(data.lastSentAt!.toLocal());
    if (data.pending == 0 && data.lastSentAt == null) {
      return Strings.queueEmpty;
    }
    return '${Strings.queueLastSent}: $last\n${data.pending} ${Strings.queuePending}';
  }
}
