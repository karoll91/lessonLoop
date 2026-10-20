import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/api.dart';
import 'core/i18n.dart';
import 'core/outbox.dart';
import 'core/providers.dart';
import 'core/theme.dart';
import 'features/auth/auth_screens.dart';
import 'features/groups/groups_screen.dart';
import 'features/history/history_screen.dart';
import 'features/home/home_screen.dart';
import 'features/questions/questions_screen.dart';
import 'features/settings/settings_screen.dart';
import 'ui/widgets.dart';

class LessonLoopApp extends ConsumerWidget {
  const LessonLoopApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final themeMode = ref.watch(settingsProvider.select((settings) => settings.themeMode));
    ref.watch(languageProvider);
    // Texts are read while widgets build, so a new language redraws everything.
    ref.listen(languageProvider, (_, _) => rebuildAll(context));
    return MaterialApp(
      title: 'LessonLoop',
      debugShowCheckedModeBanner: false,
      theme: buildTheme(Brightness.light),
      darkTheme: buildTheme(Brightness.dark),
      themeMode: themeMode,
      home: const AuthGate(),
    );
  }
}

/// M1 → onboarding (first launch) → M2 login → password change → tabs.
class AuthGate extends ConsumerWidget {
  const AuthGate({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    // Keeps the offline outbox alive, so grades queued before a restart are
    // sent in the background as soon as the server answers.
    ref.listen(outboxProvider, (_, _) {});

    final settings = ref.watch(settingsProvider);
    if (!settings.onboardingSeen) {
      return const OnboardingScreen();
    }

    final auth = ref.watch(authProvider);
    return auth.when(
      loading: () => const SplashScreen(),
      error: (error, _) => error is ApiException && error.isOffline
          ? Scaffold(
              body: SafeArea(child: OfflineStart(error: error)),
            )
          : const LoginScreen(),
      data: (user) {
        if (user == null) {
          return const LoginScreen();
        }
        if (user.mustChangePassword) {
          return const ChangePasswordScreen(required: true);
        }
        return const HomeShell();
      },
    );
  }
}

/// The server could not be reached while checking the saved token.
class OfflineStart extends ConsumerWidget {
  const OfflineStart({super.key, required this.error});

  final Object error;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          EmptyState(
            icon: Icons.wifi_off,
            title: tr("Serverga ulanib bo'lmadi"),
            text: tr("Internet va server manzilini tekshiring:\n{url}", {'url': ref.watch(settingsProvider).serverUrl}),
            action: tr('Qayta urinish'),
            onAction: () => ref.invalidate(authProvider),
          ),
          TextButton(onPressed: () => showServerDialog(context, ref), child: Text(tr("Server manzilini o'zgartirish"))),
        ],
      ),
    );
  }
}

/// Bottom tabs: Bugun, Guruhlar, Savollar, Tarix, Sozlamalar.
class HomeShell extends StatefulWidget {
  const HomeShell({super.key});

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> {
  int _index = 0;

  static const _pages = [HomeScreen(), GroupsScreen(), QuestionsScreen(), HistoryScreen(), SettingsScreen()];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: IndexedStack(index: _index, children: _pages),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _index,
        onDestinationSelected: (index) => setState(() => _index = index),
        destinations: [
          NavigationDestination(icon: Icon(Icons.today_outlined), selectedIcon: Icon(Icons.today), label: tr('Bugun')),
          NavigationDestination(icon: Icon(Icons.groups_outlined), selectedIcon: Icon(Icons.groups), label: tr('Guruhlar')),
          NavigationDestination(icon: Icon(Icons.quiz_outlined), selectedIcon: Icon(Icons.quiz), label: tr('Savollar')),
          NavigationDestination(icon: Icon(Icons.history), label: tr('Tarix')),
          NavigationDestination(icon: Icon(Icons.settings_outlined), selectedIcon: Icon(Icons.settings), label: tr('Sozlamalar')),
        ],
      ),
    );
  }
}
