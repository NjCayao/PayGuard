import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;

import '../../core/config.dart';
import '../../core/strings/strings.dart';
import '../../core/theme/app_spacing.dart';
import '../../core/theme/app_text_styles.dart';
import '../../core/widgets/app_logo.dart';
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
  int _brandTaps = 0;
  String? _error;

  static const int _tapsToRevealServer = 5;

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    _serverController.dispose();
    super.dispose();
  }

  void _onBrandTap() {
    _brandTaps += 1;
    if (_brandTaps >= _tapsToRevealServer && !_showServer) {
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
        businessName: result.businessName,
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
      centered: true,
      child: Form(
        key: _formKey,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Center(child: AppLogo(size: 64)),
            const SizedBox(height: AppSpacing.sm),
            Center(
              child: GestureDetector(
                onTap: _onBrandTap,
                child: const BrandName(),
              ),
            ),
            const SizedBox(height: AppSpacing.xs),
            const Center(
              child: Text(
                Strings.brandSlogan,
                style: AppTextStyles.bodyMuted,
              ),
            ),
            const SizedBox(height: AppSpacing.lg),
            const Text(Strings.emailLabel, style: AppTextStyles.label),
            const SizedBox(height: AppSpacing.xs),
            SizedBox(
              height: 48,
              child: TextFormField(
                controller: _emailController,
                keyboardType: TextInputType.emailAddress,
                autocorrect: false,
                decoration: const InputDecoration(
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
            ),
            const SizedBox(height: AppSpacing.sm),
            const Text(Strings.passwordLabel, style: AppTextStyles.label),
            const SizedBox(height: AppSpacing.xs),
            SizedBox(
              height: 48,
              child: TextFormField(
                controller: _passwordController,
                obscureText: _obscure,
                decoration: InputDecoration(
                  hintText: Strings.passwordHint,
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
            ),
            if (_showServer) ...[
              const SizedBox(height: AppSpacing.sm),
              const Text(Strings.serverLabel, style: AppTextStyles.label),
              const SizedBox(height: AppSpacing.xs),
              SizedBox(
                height: 48,
                child: TextFormField(
                  controller: _serverController,
                  keyboardType: TextInputType.url,
                  autocorrect: false,
                  decoration: const InputDecoration(
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
              ),
            ],
            if (_error != null) ...[
              const SizedBox(height: AppSpacing.sm),
              NoticeBanner.error(_error!),
            ],
            const SizedBox(height: AppSpacing.md),
            PrimaryButton(
              label: Strings.loginButton,
              loading: _loading,
              onPressed: _submit,
            ),
            const SizedBox(height: AppSpacing.md),
            Center(
              child: Text(
                '${Strings.loginBottom} · v${AppConfig.appVersion}',
                style: AppTextStyles.detail,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
