import 'package:flutter/material.dart';

import 'core/strings/strings.dart';
import 'core/theme/app_theme.dart';
import 'features/login/login_screen.dart';
import 'features/status/status_screen.dart';
import 'services/native_bridge.dart';
import 'services/session.dart';

void main() {
  runApp(const PayGuardApp());
}

class PayGuardApp extends StatefulWidget {
  const PayGuardApp({super.key});

  @override
  State<PayGuardApp> createState() => _PayGuardAppState();
}

class _PayGuardAppState extends State<PayGuardApp> {
  final NativeBridge _bridge = NativeBridge();
  late final SessionManager _session = SessionManager(bridge: _bridge);
  late final Future<bool> _hasSession = _loadSession();

  Future<bool> _loadSession() async {
    await _session.syncBaseUrl();
    await _bridge.triggerSync();
    final String? token = await _session.token();
    return token != null && token.isNotEmpty;
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: Strings.appName,
      theme: buildAppTheme(),
      home: FutureBuilder<bool>(
        future: _hasSession,
        builder: (BuildContext context, AsyncSnapshot<bool> snapshot) {
          if (!snapshot.hasData) {
            return const Scaffold(
              body: SafeArea(
                child: Center(child: CircularProgressIndicator()),
              ),
            );
          }
          if (snapshot.data ?? false) {
            return StatusScreen(session: _session, bridge: _bridge);
          }
          return LoginScreen(session: _session, bridge: _bridge);
        },
      ),
    );
  }
}
