import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api.dart';
import '../../core/models.dart';
import '../../core/providers.dart';
import '../../core/theme.dart';
import '../../ui/widgets.dart';

class LogoMark extends StatelessWidget {
  const LogoMark({super.key, this.size = 56});

  final double size;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(color: c.accent, borderRadius: BorderRadius.circular(size * 0.29)),
      child: Icon(Icons.repeat_rounded, color: c.onAccent, size: size * 0.52),
    );
  }
}

/// M1 · 1 — shown while the saved token is checked.
class SplashScreen extends StatelessWidget {
  const SplashScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Scaffold(
      body: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const LogoMark(size: 88),
            const SizedBox(height: 18),
            const Text('LessonLoop', style: TextStyle(fontSize: 28, fontWeight: FontWeight.w800, letterSpacing: -0.5)),
            const SizedBox(height: 6),
            Text('Har dars — takrorlash', style: TextStyle(color: c.ink2)),
            const SizedBox(height: 40),
            const CircularProgressIndicator(),
          ],
        ),
      ),
    );
  }
}

/// M1 · 2–4 — two slides and the theme choice, once per install.
class OnboardingScreen extends ConsumerStatefulWidget {
  const OnboardingScreen({super.key});

  @override
  ConsumerState<OnboardingScreen> createState() => _OnboardingScreenState();
}

class _OnboardingScreenState extends ConsumerState<OnboardingScreen> {
  final _pages = PageController();
  int _page = 0;

  static const _slides = [
    (Icons.casino_outlined, 'Adolatli random', "Har talaba 4 darsda bir marta so'raladi. 60 baldan past olganlar keyingi darsda yana navbatga tushadi."),
    (
      Icons.fact_check_outlined,
      "To'g'ri javob doim qo'lingizda",
      "Talaba javob bergach, namuna javobni oching va 0–100 ball qo'ying. Baholar avtomatik saqlanadi.",
    ),
  ];

  @override
  void dispose() {
    _pages.dispose();
    super.dispose();
  }

  void _next() {
    if (_page < _slides.length) {
      _pages.nextPage(duration: const Duration(milliseconds: 280), curve: Curves.easeOut);
    } else {
      ref.read(settingsProvider.notifier).finishOnboarding();
    }
  }

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final last = _page == _slides.length;

    return Scaffold(
      body: SafeArea(
        child: Column(
          children: [
            Align(
              alignment: Alignment.centerRight,
              child: TextButton(onPressed: () => ref.read(settingsProvider.notifier).finishOnboarding(), child: const Text("O'tkazib yuborish")),
            ),
            Expanded(
              child: PageView(
                controller: _pages,
                onPageChanged: (page) => setState(() => _page = page),
                children: [
                  for (final (icon, title, text) in _slides)
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 28),
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Container(
                            height: 220,
                            width: double.infinity,
                            decoration: BoxDecoration(color: c.accentSoft, borderRadius: BorderRadius.circular(28)),
                            child: Icon(icon, size: 96, color: c.accent),
                          ),
                          const SizedBox(height: 28),
                          Text(title, style: Theme.of(context).textTheme.headlineSmall),
                          const SizedBox(height: 10),
                          Text(text, style: TextStyle(fontSize: 16, color: c.ink2, height: 1.4)),
                        ],
                      ),
                    ),
                  const _ThemeChoice(),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(28, 0, 28, 24),
              child: Row(
                children: [
                  for (var index = 0; index <= _slides.length; index++)
                    AnimatedContainer(
                      duration: const Duration(milliseconds: 200),
                      margin: const EdgeInsets.only(right: 6),
                      width: index == _page ? 24 : 8,
                      height: 8,
                      decoration: BoxDecoration(color: index == _page ? c.accent : c.line, borderRadius: BorderRadius.circular(4)),
                    ),
                  const Spacer(),
                  FilledButton(onPressed: _next, child: Text(last ? 'Boshlash' : 'Keyingi')),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ThemeChoice extends ConsumerWidget {
  const _ThemeChoice();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final mode = ref.watch(settingsProvider.select((settings) => settings.themeMode));
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 28),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text("Ko'rinish", style: Theme.of(context).textTheme.headlineSmall),
          const SizedBox(height: 6),
          Text("Keyin Sozlamalarda o'zgartirish mumkin", style: TextStyle(color: context.colors.ink2)),
          const SizedBox(height: 20),
          ThemeModeSelector(mode: mode, onChanged: (value) => ref.read(settingsProvider.notifier).setThemeMode(value)),
        ],
      ),
    );
  }
}

class ThemeModeSelector extends StatelessWidget {
  const ThemeModeSelector({super.key, required this.mode, required this.onChanged});

  final ThemeMode mode;
  final ValueChanged<ThemeMode> onChanged;

  @override
  Widget build(BuildContext context) {
    return SegmentedButton<ThemeMode>(
      segments: const [
        ButtonSegment(value: ThemeMode.light, icon: Icon(Icons.light_mode_outlined), label: Text("Yorug'")),
        ButtonSegment(value: ThemeMode.dark, icon: Icon(Icons.dark_mode_outlined), label: Text("Qorong'i")),
        ButtonSegment(value: ThemeMode.system, label: Text('Tizim')),
      ],
      selected: {mode},
      showSelectedIcon: false,
      onSelectionChanged: (selection) => onChanged(selection.first),
    );
  }
}

/// M2 — login with the account the admin created.
class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _login = TextEditingController();
  final _password = TextEditingController();
  bool _hidden = true;
  bool _busy = false;
  ApiException? _error;

  @override
  void initState() {
    super.initState();
    _login.addListener(_refresh);
    _password.addListener(_refresh);
  }

  void _refresh() => setState(() {});

  @override
  void dispose() {
    _login.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await ref.read(authProvider.notifier).login(_login.text, _password.text);
    } on ApiException catch (error) {
      if (mounted) {
        setState(() => _error = error);
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final ready = _login.text.trim().isNotEmpty && _password.text.isNotEmpty && !_busy;
    final loginError = _error?.fieldError('login');
    final passwordError = _error?.fieldError('password');

    return Scaffold(
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(24, 48, 24, 24),
          children: [
            const Align(alignment: Alignment.centerLeft, child: LogoMark()),
            const SizedBox(height: 28),
            Text('Xush kelibsiz', style: Theme.of(context).textTheme.headlineSmall),
            const SizedBox(height: 6),
            Text('Admin bergan login va parol bilan kiring', style: TextStyle(color: c.ink2)),
            const SizedBox(height: 24),
            if (_error != null && (_error!.isOffline || (loginError == null && passwordError == null))) ...[
              Banner2(_error!.userMessage, tone: Tone.danger, icon: _error!.isOffline ? Icons.wifi_off : null),
              const SizedBox(height: 16),
            ],
            if (loginError != null) ...[Banner2(loginError, tone: Tone.danger), const SizedBox(height: 16)],
            TextField(
              controller: _login,
              enabled: !_busy,
              autocorrect: false,
              textInputAction: TextInputAction.next,
              autofillHints: const [AutofillHints.username],
              decoration: const InputDecoration(labelText: 'Login', hintText: 'masalan, sh.mahkamov'),
            ),
            const SizedBox(height: 16),
            TextField(
              controller: _password,
              enabled: !_busy,
              obscureText: _hidden,
              autofillHints: const [AutofillHints.password],
              onSubmitted: (_) => ready ? _submit() : null,
              decoration: InputDecoration(
                labelText: 'Parol',
                errorText: passwordError,
                suffixIcon: IconButton(
                  tooltip: _hidden ? "Parolni ko'rsatish" : 'Parolni yashirish',
                  icon: Icon(_hidden ? Icons.visibility_outlined : Icons.visibility_off_outlined),
                  onPressed: () => setState(() => _hidden = !_hidden),
                ),
              ),
            ),
            const SizedBox(height: 8),
            Text("Parolni unutgan bo'lsangiz, admin yangi vaqtinchalik parol beradi.", style: Theme.of(context).textTheme.bodySmall),
            const SizedBox(height: 24),
            FilledButton(
              onPressed: ready ? _submit : null,
              child: _busy
                  ? Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2.5, color: c.onAccent)),
                        const SizedBox(width: 10),
                        const Text('Tekshirilmoqda…'),
                      ],
                    )
                  : const Text('Kirish'),
            ),
            const SizedBox(height: 12),
            Center(
              child: TextButton.icon(
                onPressed: _busy ? null : () => showServerDialog(context, ref),
                icon: const Icon(Icons.dns_outlined, size: 18),
                label: Text('Server: ${ref.watch(settingsProvider).serverUrl}', overflow: TextOverflow.ellipsis),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// The API address: the teacher's computer on the same Wi-Fi.
Future<void> showServerDialog(BuildContext context, WidgetRef ref) async {
  final controller = TextEditingController(text: ref.read(settingsProvider).serverUrl);
  final value = await showDialog<String>(
    context: context,
    builder: (context) => AlertDialog(
      title: const Text('Server manzili'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          TextField(
            controller: controller,
            autofocus: true,
            keyboardType: TextInputType.url,
            decoration: const InputDecoration(hintText: 'http://192.168.1.10:8000/api'),
          ),
          const SizedBox(height: 8),
          Text("Kompyuter IP manzili va port. Telefon va kompyuter bitta Wi-Fi'da bo'lishi kerak.", style: Theme.of(context).textTheme.bodySmall),
        ],
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context), child: const Text('Bekor')),
        FilledButton(onPressed: () => Navigator.pop(context, controller.text), child: const Text('Saqlash')),
      ],
    ),
  );
  controller.dispose();
  if (value != null && value.trim().isNotEmpty) {
    await ref.read(settingsProvider.notifier).setServerUrl(value);
    ref.invalidate(authProvider);
  }
}

/// Required after a temporary password; also opened from settings.
class ChangePasswordScreen extends ConsumerStatefulWidget {
  const ChangePasswordScreen({super.key, this.required = false});

  final bool required;

  @override
  ConsumerState<ChangePasswordScreen> createState() => _ChangePasswordScreenState();
}

class _ChangePasswordScreenState extends ConsumerState<ChangePasswordScreen> {
  final _current = TextEditingController();
  final _password = TextEditingController();
  final _confirm = TextEditingController();
  bool _busy = false;
  ApiException? _error;

  @override
  void dispose() {
    _current.dispose();
    _password.dispose();
    _confirm.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final response = await ref.read(apiProvider).post('/me/password', {
        'current_password': _current.text,
        'password': _password.text,
        'password_confirmation': _confirm.text,
      });
      ref.read(authProvider.notifier).updateUser(User.fromJson(response['user'] as Json));
      if (mounted && !widget.required) {
        showMessage(context, "Parol o'zgartirildi");
        Navigator.pop(context);
      }
    } on ApiException catch (error) {
      if (mounted) {
        setState(() => _error = error);
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Yangi parol'),
        automaticallyImplyLeading: !widget.required,
        actions: [if (widget.required) TextButton(onPressed: () => ref.read(authProvider.notifier).logout(), child: const Text('Chiqish'))],
      ),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          if (widget.required) ...[
            const Banner2("Siz vaqtinchalik parol bilan kirdingiz. Davom etish uchun o'z parolingizni o'rnating."),
            const SizedBox(height: 16),
          ],
          if (_error != null && _error!.errors.isEmpty) ...[Banner2(_error!.userMessage, tone: Tone.danger), const SizedBox(height: 16)],
          TextField(
            controller: _current,
            obscureText: true,
            decoration: InputDecoration(labelText: 'Joriy parol', errorText: _error?.fieldError('current_password')),
          ),
          const SizedBox(height: 14),
          TextField(
            controller: _password,
            obscureText: true,
            decoration: InputDecoration(labelText: 'Yangi parol', helperText: 'Kamida 8 belgi', errorText: _error?.fieldError('password')),
          ),
          const SizedBox(height: 14),
          TextField(
            controller: _confirm,
            obscureText: true,
            decoration: const InputDecoration(labelText: 'Yangi parolni takrorlang'),
          ),
          const SizedBox(height: 24),
          FilledButton(onPressed: _busy ? null : _submit, child: Text(_busy ? 'Saqlanmoqda…' : 'Saqlash')),
        ],
      ),
    );
  }
}
