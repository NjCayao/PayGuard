import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;

import '../../core/config.dart';
import '../../core/strings/strings.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_spacing.dart';
import '../../core/theme/app_text_styles.dart';
import '../../core/widgets/app_scaffold.dart';
import '../../core/widgets/info_box.dart';
import '../../core/widgets/info_row.dart';
import '../../core/widgets/secondary_button.dart';
import '../../services/api_client.dart';
import '../../services/native_bridge.dart';
import '../../services/session.dart';
import '../history/history_screen.dart';
import '../login/login_screen.dart';

class AccountScreen extends StatefulWidget {
  const AccountScreen({
    super.key,
    required this.session,
    required this.bridge,
  });

  final SessionManager session;
  final NativeBridge bridge;

  @override
  State<AccountScreen> createState() => _AccountScreenState();
}

class _AccountScreenState extends State<AccountScreen> {
  String _business = '';
  String _device = '';
  bool _loggingOut = false;
  bool _voiceEnabled = true;
  bool _voiceAlarm = false;
  bool? _spanishAvailable;
  bool _testingVoice = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final String business = await widget.session.businessName() ?? '';
    final String device = await widget.session.deviceName() ?? '';
    VoiceStatus? voice;
    try {
      voice = await widget.bridge.voiceStatus();
    } catch (_) {
      voice = null;
    }
    if (!mounted) {
      return;
    }
    setState(() {
      _business = business;
      _device = device;
      if (voice != null) {
        _voiceEnabled = voice.enabled;
        _voiceAlarm = voice.alarmStream;
        _spanishAvailable = voice.spanishAvailable;
      }
    });
  }

  Future<void> _setVoiceEnabled(bool value) async {
    setState(() => _voiceEnabled = value);
    try {
      await widget.bridge.setVoiceEnabled(value);
    } catch (_) {
      if (mounted) {
        setState(() => _voiceEnabled = !value);
      }
    }
  }

  Future<void> _setVoiceAlarm(bool value) async {
    setState(() => _voiceAlarm = value);
    try {
      await widget.bridge.setVoiceAlarmStream(value);
    } catch (_) {
      if (mounted) {
        setState(() => _voiceAlarm = !value);
      }
    }
  }

  Future<void> _testVoice() async {
    setState(() => _testingVoice = true);
    try {
      await widget.bridge.testVoice();
      await Future<void>.delayed(const Duration(seconds: 2));
      final VoiceStatus voice = await widget.bridge.voiceStatus();
      if (mounted) {
        setState(() => _spanishAvailable = voice.spanishAvailable);
      }
    } catch (_) {
    } finally {
      if (mounted) {
        setState(() => _testingVoice = false);
      }
    }
  }

  void _openHistory() {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => HistoryScreen(session: widget.session),
      ),
    );
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

    setState(() => _loggingOut = true);
    final String? token = await widget.session.token();
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

  @override
  Widget build(BuildContext context) {
    return AppScaffold(
      title: Strings.accountTitle,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          InfoRow(label: Strings.accountBusiness, value: _business),
          const Divider(),
          InfoRow(label: Strings.accountDevice, value: _device),
          const Divider(),
          LinkRow(
            label: Strings.accountPayments,
            onTap: _openHistory,
          ),
          const Divider(),
          const Text(
            Strings.voiceTitle,
            style: AppTextStyles.bodyBold,
          ),
          _voiceSwitch(
            title: Strings.voiceAnnounce,
            detail: Strings.voiceAnnounceDetail,
            value: _voiceEnabled,
            onChanged: _setVoiceEnabled,
          ),
          const Divider(),
          _voiceSwitch(
            title: Strings.voiceAlarm,
            detail: Strings.voiceAlarmDetail,
            value: _voiceAlarm,
            onChanged: _setVoiceAlarm,
          ),
          if (_spanishAvailable == false) ...[
            const SizedBox(height: AppSpacing.sm),
            const InfoBox(
              title: Strings.voiceMissingTitle,
              body: Strings.voiceMissingBody,
            ),
            TextButton(
              onPressed: widget.bridge.openTtsSettings,
              child: const Text(
                Strings.voiceMissingAction,
                style: AppTextStyles.action,
              ),
            ),
          ],
          const SizedBox(height: AppSpacing.sm),
          SecondaryButton(
            label: Strings.voiceTest,
            loading: _testingVoice,
            onPressed: _testVoice,
          ),
          const SizedBox(height: AppSpacing.sm),
          const InfoBox(
            title: Strings.autostartTitle,
            body: Strings.autostartBody,
          ),
          const SizedBox(height: AppSpacing.md),
          SecondaryButton(
            label: Strings.logoutButton,
            loading: _loggingOut,
            onPressed: _confirmLogout,
          ),
          const SizedBox(height: AppSpacing.sm),
          Center(
            child: Text(
              'v${AppConfig.appVersion}',
              style: AppTextStyles.detail,
            ),
          ),
        ],
      ),
    );
  }

  Widget _voiceSwitch({
    required String title,
    required String detail,
    required bool value,
    required ValueChanged<bool> onChanged,
  }) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: AppSpacing.sm),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: AppTextStyles.body),
                Text(detail, style: AppTextStyles.detail),
              ],
            ),
          ),
          Switch(
            value: value,
            activeThumbColor: AppColors.primary,
            onChanged: onChanged,
          ),
        ],
      ),
    );
  }
}
