import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;

import '../../core/config.dart';
import '../../core/strings/strings.dart';
import '../../core/theme/app_spacing.dart';
import '../../core/theme/app_text_styles.dart';
import '../../core/widgets/app_scaffold.dart';
import '../../core/widgets/notice_banner.dart';
import '../../core/widgets/primary_button.dart';
import '../../services/api_client.dart';
import '../../services/native_bridge.dart';
import '../../services/session.dart';
import '../status/status_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({
    super.key,
    required this.session,
    required this.bridge,
  });

  final SessionManager session;
  final NativeBridge bridge;

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final TextEditingController _emailController = TextEditingController();
  final TextEditingController _passwordController = TextEditingController();
  final TextEditingController _serverController = TextEditingController();

  bool _loading = false;
  bool _obscure = true;
  bool _showServer = false;
  int _titleTaps = 0;
  String? _error;

  static const int _tapsToRevealServer = 5;

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    _serverController.dispose();
    super.dispose();
  }

  void _onTitleTap() {
    _titleTaps += 1;
    if (_titleTaps >= _tapsToRevealServer && !_showServer) {
      setState(() => _showServer = true);
    }
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) {
      return;
    }

    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      if (_showServer) {
        final String server = _serverController.text.trim();
        await widget.session
            .setBaseUrlOverride(server.isEmpty ? null : server);
      }

      final String baseUrl = await widget.session.baseUrl();
      final String installId = await widget.session.installId();
      final String brand = await widget.bridge.deviceBrand();
      final String model = await widget.bridge.deviceModel();

      final ApiClient api =
          ApiClient(client: http.Client(), baseUrl: baseUrl);
      final LoginResult result = await api.login(
        email: _emailController.text.trim(),
        password: _passwordController.text,
        brand: brand,
        model: model,
        installId: installId,
      );

      await widget.session.saveLogin(
        token: result.token,
        deviceName: result.deviceName,
      );
      await widget.bridge.triggerSync();

      if (!mounted) {
        return;
      }
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(
          builder: (_) => StatusScreen(
            session: widget.session,
            bridge: widget.bridge,
          ),
        ),
      );
    } on ApiException catch (error) {
      setState(() => _error = error.message);
    } catch (_) {
      setState(() => _error = Strings.genericError);
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return AppScaffold(
      title: Strings.appName,
      child: Form(
        key: _formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            GestureDetector(
              onTap: _onTitleTap,
              child: const Text(
                Strings.loginTitle,
                style: AppTextStyles.title,
              ),
            ),
            const SizedBox(height: AppSpacing.xs),
            const Text(Strings.loginSubtitle, style: AppTextStyles.muted),
            const SizedBox(height: AppSpacing.lg),
            if (_error != null) ...[
              NoticeBanner.error(_error!),
              const SizedBox(height: AppSpacing.md),
            ],
            TextFormField(
              controller: _emailController,
              keyboardType: TextInputType.emailAddress,
              autocorrect: false,
              decoration: const InputDecoration(
                labelText: Strings.emailLabel,
                hintText: Strings.emailHint,
              ),
              validator: (String? value) {
                final String text = (value ?? '').trim();
                if (text.isEmpty) {
                  return Strings.emailRequired;
                }
                if (!text.contains('@') || !text.contains('.')) {
                  return Strings.emailInvalid;
                }
                return null;
              },
            ),
            const SizedBox(height: AppSpacing.md),
            TextFormField(
              controller: _passwordController,
              obscureText: _obscure,
              decoration: InputDecoration(
                labelText: Strings.passwordLabel,
                suffixIcon: IconButton(
                  icon: Icon(
                    _obscure ? Icons.visibility : Icons.visibility_off,
                  ),
                  onPressed: () => setState(() => _obscure = !_obscure),
                ),
              ),
              validator: (String? value) {
                if ((value ?? '').isEmpty) {
                  return Strings.passwordRequired;
                }
                return null;
              },
            ),
            if (_showServer) ...[
              const SizedBox(height: AppSpacing.md),
              TextFormField(
                controller: _serverController,
                keyboardType: TextInputType.url,
                autocorrect: false,
                decoration: const InputDecoration(
                  labelText: Strings.serverLabel,
                  hintText: AppConfig.defaultBaseUrl,
                ),
                validator: (String? value) {
                  final String text = (value ?? '').trim();
                  if (text.isEmpty) {
                    return null;
                  }
                  if (!AppConfig.isValidBaseUrl(text)) {
                    return Strings.serverInvalid;
                  }
                  return null;
                },
              ),
            ],
            const SizedBox(height: AppSpacing.lg),
            PrimaryButton(
              label: Strings.loginButton,
              loading: _loading,
              onPressed: _submit,
            ),
          ],
        ),
      ),
    );
  }
}
