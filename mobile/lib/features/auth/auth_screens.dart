import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api.dart';
import '../../core/i18n.dart';
import '../../core/models.dart';
import '../../core/providers.dart';
import '../../core/theme.dart';
import '../../ui/widgets.dart';

class LogoMark extends StatelessWidget {
  const LogoMark({super.key, this.size = 56});

  final double size;

  /// Built from mobile/assets/icon by deploy/make-icons.php.
  static const asset = 'assets/images/logo.png';

  @override
  Widget build(BuildContext context) {
    final pixels = (size * MediaQuery.devicePixelRatioOf(context)).round();
    return Image.asset(asset, width: size, height: size, cacheWidth: pixels, filterQuality: FilterQuality.medium, semanticLabel: 'LessonLoop');
  }
}

/// M1 · 1 — shown while the saved token is checked. When that takes long
/// (the address changed with the network) the address and a way to change
/// it appear instead of a bare spinner.
class SplashScreen extends ConsumerStatefulWidget {
  const SplashScreen({super.key});

  static const slowAfter = Duration(seconds: 3);

  @override
  ConsumerState<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends ConsumerState<SplashScreen> {
  late final Timer _timer;
  bool _slow = false;

  @override
  void initState() {
    super.initState();
    _timer = Timer(SplashScreen.slowAfter, () => setState(() => _slow = true));
  }

  @override
  void dispose() {
    _timer.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Scaffold(
      body: Center(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const LogoMark(size: 88),
              const SizedBox(height: 18),
              const Text('LessonLoop', style: TextStyle(fontSize: 28, fontWeight: FontWeight.w800, letterSpacing: -0.5)),
              const SizedBox(height: 6),
              Text(tr('Har dars — takrorlash'), style: TextStyle(color: c.ink2)),
              const SizedBox(height: 40),
              const CircularProgressIndicator(),
              if (_slow) ...[
                const SizedBox(height: 20),
                Text(tr('Ulanmoqda…'), style: TextStyle(color: c.ink2)),
                const SizedBox(height: 4),
                Text(ref.watch(settingsProvider).serverUrl, textAlign: TextAlign.center, style: TextStyle(color: c.ink2, fontSize: 13)),
                TextButton(onPressed: () => showServerDialog(context), child: Text(tr("Server manzilini o'zgartirish"))),
              ],
            ],
          ),
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

  static List<(IconData, String, String)> get _slides => [
    (Icons.casino_outlined, tr('Adolatli random'), tr("Har talaba 4 darsda bir marta so'raladi. 60 baldan past olganlar keyingi darsda yana navbatga tushadi.")),
    (
      Icons.fact_check_outlined,
      tr("To'g'ri javob doim qo'lingizda"),
      tr("Talaba javob bergach, namuna javobni oching va 0–100 ball qo'ying. Baholar avtomatik saqlanadi."),
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
              child: TextButton(onPressed: () => ref.read(settingsProvider.notifier).finishOnboarding(), child: Text(tr("O'tkazib yuborish"))),
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
                  FilledButton(onPressed: _next, child: Text(last ? tr('Boshlash') : tr('Keyingi'))),
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
          Text(tr("Til va ko'rinish"), style: Theme.of(context).textTheme.headlineSmall),
          const SizedBox(height: 6),
          Text(tr("Keyin Sozlamalarda o'zgartirish mumkin"), style: TextStyle(color: context.colors.ink2)),
          const SizedBox(height: 20),
          const LanguageSelector(),
          const SizedBox(height: 12),
          ThemeModeSelector(mode: mode, onChanged: (value) => ref.read(settingsProvider.notifier).setThemeMode(value)),
        ],
      ),
    );
  }
}

/// O'zbekcha (lotin) · Ўзбекча (кирилл) · Русский.
class LanguageSelector extends ConsumerWidget {
  const LanguageSelector({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final language = ref.watch(languageProvider);
    return SegmentedButton<AppLanguage>(
      segments: [for (final value in AppLanguage.values) ButtonSegment(value: value, label: Text(value.label))],
      selected: {language},
      showSelectedIcon: false,
      onSelectionChanged: (selection) => ref.read(languageProvider.notifier).set(selection.first),
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
      segments: [
        // No icons: the labels need the width in Cyrillic and Russian.
        ButtonSegment(value: ThemeMode.light, label: Text(tr("Yorug'"))),
        ButtonSegment(value: ThemeMode.dark, label: Text(tr("Qorong'i"))),
        ButtonSegment(value: ThemeMode.system, label: Text(tr('Tizim'))),
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
            Text(tr('Xush kelibsiz'), style: Theme.of(context).textTheme.headlineSmall),
            const SizedBox(height: 6),
            Text(tr('Admin bergan login va parol bilan kiring'), style: TextStyle(color: c.ink2)),
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
              decoration: InputDecoration(labelText: tr('Login'), hintText: tr('masalan, sh.mahkamov')),
            ),
            const SizedBox(height: 16),
            TextField(
              controller: _password,
              enabled: !_busy,
              obscureText: _hidden,
              autofillHints: const [AutofillHints.password],
              onSubmitted: (_) => ready ? _submit() : null,
              decoration: InputDecoration(
                labelText: tr('Parol'),
                errorText: passwordError,
                suffixIcon: IconButton(
                  tooltip: _hidden ? tr("Parolni ko'rsatish") : tr('Parolni yashirish'),
                  icon: Icon(_hidden ? Icons.visibility_outlined : Icons.visibility_off_outlined),
                  onPressed: () => setState(() => _hidden = !_hidden),
                ),
              ),
            ),
            const SizedBox(height: 8),
            Text(tr("Parolni unutgan bo'lsangiz, admin yangi vaqtinchalik parol beradi."), style: Theme.of(context).textTheme.bodySmall),
            const SizedBox(height: 24),
            FilledButton(
              onPressed: ready ? _submit : null,
              child: _busy
                  ? Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2.5, color: c.onAccent)),
                        const SizedBox(width: 10),
                        Text(tr('Tekshirilmoqda…')),
                      ],
                    )
                  : Text(tr('Kirish')),
            ),
            const SizedBox(height: 12),
            Center(
              child: TextButton.icon(
                onPressed: _busy ? null : () => showServerDialog(context),
                icon: const Icon(Icons.dns_outlined, size: 18),
                label: Text(tr('Server: {url}', {'url': ref.watch(settingsProvider).serverUrl}), overflow: TextOverflow.ellipsis),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// The API address: the teacher's computer on the same Wi-Fi.
///
/// The screen that opens it is replaced once the address is saved, so the
/// providers are reached through the container, not through that screen.
Future<void> showServerDialog(BuildContext context) async {
  final container = ProviderScope.containerOf(context, listen: false);
  final value = await showDialog<String>(
    context: context,
    builder: (context) => _ServerDialog(initial: container.read(settingsProvider).serverUrl),
  );
  if (value != null && value.trim().isNotEmpty) {
    await container.read(settingsProvider.notifier).setServerUrl(value);
    container.invalidate(authProvider);
  }
}

/// Owns its text controller: the field is still on screen while the dialog
/// closes, so the controller may only go away together with the dialog.
class _ServerDialog extends StatefulWidget {
  const _ServerDialog({required this.initial});

  final String initial;

  @override
  State<_ServerDialog> createState() => _ServerDialogState();
}

class _ServerDialogState extends State<_ServerDialog> {
  late final _controller = TextEditingController(text: widget.initial);

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(tr('Server manzili')),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          TextField(
            controller: _controller,
            autofocus: true,
            keyboardType: TextInputType.url,
            decoration: const InputDecoration(hintText: 'http://192.168.1.10:8000/api'),
            onSubmitted: (value) => Navigator.pop(context, value),
          ),
          const SizedBox(height: 8),
          Text(tr("Kompyuter IP manzili va port. Telefon va kompyuter bitta Wi-Fi'da bo'lishi kerak."), style: Theme.of(context).textTheme.bodySmall),
        ],
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context), child: Text(tr('Bekor'))),
        FilledButton(onPressed: () => Navigator.pop(context, _controller.text), child: Text(tr('Saqlash'))),
      ],
    );
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
        showMessage(context, tr("Parol o'zgartirildi"));
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
        title: Text(tr('Yangi parol')),
        automaticallyImplyLeading: !widget.required,
        actions: [if (widget.required) TextButton(onPressed: () => ref.read(authProvider.notifier).logout(), child: Text(tr('Chiqish')))],
      ),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          if (widget.required) ...[
            Banner2(tr("Siz vaqtinchalik parol bilan kirdingiz. Davom etish uchun o'z parolingizni o'rnating.")),
            const SizedBox(height: 16),
          ],
          if (_error != null && _error!.errors.isEmpty) ...[Banner2(_error!.userMessage, tone: Tone.danger), const SizedBox(height: 16)],
          TextField(
            controller: _current,
            obscureText: true,
            decoration: InputDecoration(labelText: tr('Joriy parol'), errorText: _error?.fieldError('current_password')),
          ),
          const SizedBox(height: 14),
          TextField(
            controller: _password,
            obscureText: true,
            decoration: InputDecoration(labelText: tr('Yangi parol'), helperText: tr('Kamida 8 belgi'), errorText: _error?.fieldError('password')),
          ),
          const SizedBox(height: 14),
          TextField(
            controller: _confirm,
            obscureText: true,
            decoration: InputDecoration(labelText: tr('Yangi parolni takrorlang')),
          ),
          const SizedBox(height: 24),
          FilledButton(onPressed: _busy ? null : _submit, child: Text(_busy ? tr('Saqlanmoqda…') : tr('Saqlash'))),
        ],
      ),
    );
  }
}
