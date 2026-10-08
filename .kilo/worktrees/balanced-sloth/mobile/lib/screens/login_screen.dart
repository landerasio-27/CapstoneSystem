import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import '../services/auth_service.dart';
import '../theme/app_theme.dart';
import '../widgets/wave_header.dart';
import 'main_scaffold.dart';
import 'server_settings_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> with SingleTickerProviderStateMixin {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _loading = false;
  bool _obscure = true;
  bool _remember = false;
  String? _error;

  late final AnimationController _anim =
      AnimationController(vsync: this, duration: const Duration(milliseconds: 550))..forward();
  late final Animation<double> _fade = CurvedAnimation(parent: _anim, curve: Curves.easeOut);
  late final Animation<Offset> _slide =
      Tween<Offset>(begin: const Offset(0, 0.06), end: Offset.zero).animate(CurvedAnimation(parent: _anim, curve: Curves.easeOut));

  @override
  void dispose() {
    _anim.dispose();
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_loading) return; // guard double submit
    if (!_formKey.currentState!.validate()) return;
    FocusScope.of(context).unfocus();

    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      await context.read<AuthService>().login(_emailController.text.trim(), _passwordController.text);
      if (!mounted) return;
      Navigator.of(context).pushReplacement(MaterialPageRoute(builder: (_) => const MainScaffold()));
    } catch (e) {
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _openServerSettings() {
    Navigator.of(context).push(MaterialPageRoute(builder: (_) => const ServerSettingsScreen()));
  }

  void _forgotPassword() {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(const SnackBar(
        content: Text('Please contact the Supply Office to reset your password.'),
      ));
  }

  @override
  Widget build(BuildContext context) {
    final headerHeight = (MediaQuery.of(context).size.height * 0.32).clamp(230.0, 300.0);

    return AnnotatedRegion<SystemUiOverlayStyle>(
      // Light status-bar icons over the green header.
      value: SystemUiOverlayStyle.light.copyWith(statusBarColor: Colors.transparent),
      child: Scaffold(
        backgroundColor: Colors.white,
        body: SingleChildScrollView(
          child: Column(
            children: [
              WaveHeader(height: headerHeight, onSettings: _openServerSettings),

              Padding(
                padding: const EdgeInsets.fromLTRB(28, 10, 28, 28),
                child: FadeTransition(
                  opacity: _fade,
                  child: SlideTransition(
                    position: _slide,
                    child: Form(
                      key: _formKey,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          const SizedBox(height: 6),
                          const Text(
                            'Welcome back !',
                            textAlign: TextAlign.center,
                            style: TextStyle(fontSize: 26, fontWeight: FontWeight.w600, color: AppColors.textPrimary),
                          ),
                          const SizedBox(height: 28),

                          _PillField(
                            controller: _emailController,
                            hint: 'Username',
                            icon: Icons.person_outline,
                            enabled: !_loading,
                            keyboardType: TextInputType.emailAddress,
                            textInputAction: TextInputAction.next,
                            validator: (v) => (v == null || v.trim().isEmpty) ? 'Username is required' : null,
                          ),
                          const SizedBox(height: 16),
                          _PillField(
                            controller: _passwordController,
                            hint: 'Password',
                            icon: Icons.lock_outline,
                            enabled: !_loading,
                            obscure: _obscure,
                            textInputAction: TextInputAction.done,
                            onSubmitted: (_) => _submit(),
                            suffix: IconButton(
                              icon: Icon(_obscure ? Icons.visibility_off_outlined : Icons.visibility_outlined,
                                  color: AppColors.textSecondary, size: 20),
                              onPressed: _loading ? null : () => setState(() => _obscure = !_obscure),
                            ),
                            validator: (v) => (v == null || v.isEmpty) ? 'Password is required' : null,
                          ),

                          if (_error != null) ...[
                            const SizedBox(height: 14),
                            Container(
                              padding: const EdgeInsets.all(12),
                              decoration: BoxDecoration(
                                color: AppColors.missing.withValues(alpha: 0.07),
                                borderRadius: BorderRadius.circular(14),
                                border: Border.all(color: AppColors.missing.withValues(alpha: 0.28)),
                              ),
                              child: Row(
                                children: [
                                  const Icon(Icons.error_outline, color: AppColors.missing, size: 18),
                                  const SizedBox(width: 8),
                                  Expanded(child: Text(_error!, style: TextStyle(color: AppColors.missing, fontSize: 13))),
                                ],
                              ),
                            ),
                          ],

                          const SizedBox(height: 6),
                          // Remember me + Forgot password
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              InkWell(
                                borderRadius: BorderRadius.circular(8),
                                onTap: _loading ? null : () => setState(() => _remember = !_remember),
                                child: Padding(
                                  padding: const EdgeInsets.symmetric(vertical: 4, horizontal: 2),
                                  child: Row(
                                    mainAxisSize: MainAxisSize.min,
                                    children: [
                                      SizedBox(
                                        height: 22,
                                        width: 22,
                                        child: Checkbox(
                                          value: _remember,
                                          onChanged: _loading ? null : (v) => setState(() => _remember = v ?? false),
                                          activeColor: AppColors.primary,
                                          shape: const CircleBorder(),
                                          materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                                          visualDensity: VisualDensity.compact,
                                        ),
                                      ),
                                      const SizedBox(width: 8),
                                      Text('Remember me',
                                          style: TextStyle(fontSize: 13, color: AppColors.textSecondary)),
                                    ],
                                  ),
                                ),
                              ),
                              TextButton(
                                onPressed: _loading ? null : _forgotPassword,
                                style: TextButton.styleFrom(
                                  foregroundColor: AppColors.textSecondary,
                                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 4),
                                  minimumSize: Size.zero,
                                  tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                                ),
                                child: const Text('Forgot password?', style: TextStyle(fontSize: 13)),
                              ),
                            ],
                          ),

                          const SizedBox(height: 22),
                          // Outlined pill Login button
                          SizedBox(
                            height: 54,
                            child: OutlinedButton(
                              onPressed: _loading ? null : _submit,
                              style: OutlinedButton.styleFrom(
                                backgroundColor: Colors.white,
                                foregroundColor: AppColors.primary,
                                side: BorderSide(
                                  color: _loading ? AppColors.primary.withValues(alpha: 0.5) : AppColors.primary,
                                  width: 1.5,
                                ),
                                shape: const StadiumBorder(),
                                textStyle: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
                              ),
                              child: _loading
                                  ? const SizedBox(
                                      height: 22,
                                      width: 22,
                                      child: CircularProgressIndicator(strokeWidth: 2.4, color: AppColors.primary),
                                    )
                                  : const Text('Login'),
                            ),
                          ),

                          const SizedBox(height: 26),
                          Text(
                            'Isabela State University – Cauayan Campus',
                            textAlign: TextAlign.center,
                            style: TextStyle(fontSize: 12, color: AppColors.textSecondary.withValues(alpha: 0.9)),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// A pill-shaped, gray-filled input with a soft green glow when focused.
class _PillField extends StatefulWidget {
  final TextEditingController controller;
  final String hint;
  final IconData icon;
  final bool obscure;
  final bool enabled;
  final TextInputType? keyboardType;
  final TextInputAction textInputAction;
  final Widget? suffix;
  final String? Function(String?)? validator;
  final void Function(String)? onSubmitted;

  const _PillField({
    required this.controller,
    required this.hint,
    required this.icon,
    this.obscure = false,
    this.enabled = true,
    this.keyboardType,
    this.textInputAction = TextInputAction.next,
    this.suffix,
    this.validator,
    this.onSubmitted,
  });

  @override
  State<_PillField> createState() => _PillFieldState();
}

class _PillFieldState extends State<_PillField> {
  final _focusNode = FocusNode();
  bool _focused = false;

  @override
  void initState() {
    super.initState();
    _focusNode.addListener(() => setState(() => _focused = _focusNode.hasFocus));
  }

  @override
  void dispose() {
    _focusNode.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    OutlineInputBorder border(Color color, [double width = 1]) => OutlineInputBorder(
          borderRadius: BorderRadius.circular(28),
          borderSide: BorderSide(color: color, width: width),
        );

    return AnimatedContainer(
      duration: const Duration(milliseconds: 180),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(28),
        boxShadow: _focused
            ? [BoxShadow(color: AppColors.primary.withValues(alpha: 0.16), blurRadius: 12, spreadRadius: 1)]
            : const [],
      ),
      child: TextFormField(
        controller: widget.controller,
        focusNode: _focusNode,
        enabled: widget.enabled,
        obscureText: widget.obscure,
        keyboardType: widget.keyboardType,
        textInputAction: widget.textInputAction,
        onFieldSubmitted: widget.onSubmitted,
        validator: widget.validator,
        style: const TextStyle(color: AppColors.textPrimary, fontSize: 15),
        cursorColor: AppColors.primary,
        decoration: InputDecoration(
          hintText: widget.hint,
          hintStyle: const TextStyle(color: AppColors.textSecondary),
          prefixIcon: Icon(widget.icon, color: AppColors.textSecondary, size: 20),
          suffixIcon: widget.suffix,
          filled: true,
          fillColor: const Color(0xFFF3F4F6),
          contentPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 17),
          enabledBorder: border(Colors.transparent),
          disabledBorder: border(Colors.transparent),
          focusedBorder: border(AppColors.primary, 1.5),
          errorBorder: border(AppColors.missing, 1.2),
          focusedErrorBorder: border(AppColors.missing, 1.5),
          errorStyle: TextStyle(color: AppColors.missing.withValues(alpha: 0.9), fontSize: 12.5),
        ),
      ),
    );
  }
}
