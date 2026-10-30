import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'api.dart';
import 'models.dart';

/// Replaced in main() with the loaded instance.
final prefsProvider = Provider<SharedPreferences>((ref) => throw UnimplementedError('prefsProvider is overridden in main()'));

/// The API on the teacher's computer. A phone on the same Wi-Fi uses the
/// computer's IP; the Android emulator reaches the host as 10.0.2.2.
String defaultServerUrl() {
  if (!kIsWeb && defaultTargetPlatform == TargetPlatform.android) {
    return 'http://10.0.2.2:8000/api';
  }
  return 'http://localhost:8000/api';
}

/* ---------- Device settings ---------- */

@immutable
class AppSettings {
  const AppSettings({required this.serverUrl, required this.themeMode, required this.onboardingSeen});

  final String serverUrl;
  final ThemeMode themeMode;
  final bool onboardingSeen;

  AppSettings copyWith({String? serverUrl, ThemeMode? themeMode, bool? onboardingSeen}) =>
      AppSettings(serverUrl: serverUrl ?? this.serverUrl, themeMode: themeMode ?? this.themeMode, onboardingSeen: onboardingSeen ?? this.onboardingSeen);
}

class SettingsController extends Notifier<AppSettings> {
  static const _server = 'server_url';
  static const _theme = 'theme_mode';
  static const _onboarding = 'onboarding_seen';

  SharedPreferences get _prefs => ref.read(prefsProvider);

  @override
  AppSettings build() {
    final prefs = ref.watch(prefsProvider);
    return AppSettings(
      serverUrl: prefs.getString(_server) ?? defaultServerUrl(),
      themeMode: ThemeMode.values.firstWhere((mode) => mode.name == prefs.getString(_theme), orElse: () => ThemeMode.system),
      onboardingSeen: prefs.getBool(_onboarding) ?? false,
    );
  }

  Future<void> setServerUrl(String url) async {
    var value = url.trim();
    while (value.endsWith('/')) {
      value = value.substring(0, value.length - 1);
    }
    if (!value.startsWith('http')) {
      value = 'http://$value';
    }
    if (!value.endsWith('/api')) {
      value = '$value/api';
    }
    await _prefs.setString(_server, value);
    state = state.copyWith(serverUrl: value);
  }

  Future<void> setThemeMode(ThemeMode mode) async {
    await _prefs.setString(_theme, mode.name);
    state = state.copyWith(themeMode: mode);
  }

  Future<void> finishOnboarding() async {
    await _prefs.setBool(_onboarding, true);
    state = state.copyWith(onboardingSeen: true);
  }
}

final settingsProvider = NotifierProvider<SettingsController, AppSettings>(SettingsController.new);

/* ---------- Token, API, signed-in user ---------- */

class TokenController extends Notifier<String?> {
  static const _key = 'auth_token';

  @override
  String? build() => ref.watch(prefsProvider).getString(_key);

  Future<void> set(String? token) async {
    final prefs = ref.read(prefsProvider);
    if (token == null) {
      await prefs.remove(_key);
    } else {
      await prefs.setString(_key, token);
    }
    state = token;
  }
}

final tokenProvider = NotifierProvider<TokenController, String?>(TokenController.new);

final apiProvider = Provider<ApiClient>((ref) {
  final url = ref.watch(settingsProvider.select((settings) => settings.serverUrl));
  final token = ref.watch(tokenProvider);
  return ApiClient(
    baseUrl: url,
    token: token,
    // An expired or revoked token signs the teacher out.
    onUnauthorized: () => Future.microtask(() => ref.read(tokenProvider.notifier).set(null)),
  );
});

class AuthController extends AsyncNotifier<User?> {
  /// The start screen waits at most this long: after a network change the
  /// saved address may lead nowhere, and the «server unreachable» screen is
  /// where the teacher fixes it.
  static const startTimeout = Duration(seconds: 10);

  @override
  Future<User?> build() async {
    final token = ref.watch(tokenProvider);
    if (token == null) {
      return null;
    }
    final response = await ref
        .read(apiProvider)
        .get('/me')
        .timeout(startTimeout, onTimeout: () => throw ApiException(0, "Serverga ulanib bo'lmadi. Internet yoki server manzilini tekshiring."));
    return User.fromJson(response['user'] as Json);
  }

  Future<void> login(String login, String password) async {
    final api = ApiClient(baseUrl: ref.read(settingsProvider).serverUrl);
    final response = await api.post('/login', {'login': login.trim(), 'password': password, 'device_name': 'mobile'});
    await ref.read(tokenProvider.notifier).set(response['token'] as String);
  }

  Future<void> logout() async {
    try {
      await ref.read(apiProvider).post('/logout');
    } on ApiException {
      // Signed out on the phone even if the server cannot be reached.
    }
    await ref.read(tokenProvider.notifier).set(null);
  }

  void updateUser(User user) => state = AsyncData(user);
}

/// Not retried automatically: Riverpod would keep the start screen loading
/// through its retries, hiding the «server unreachable» screen for minutes.
final authProvider = AsyncNotifierProvider<AuthController, User?>(AuthController.new, retry: (_, _) => null);

/* ---------- Shared data ---------- */

final dashboardProvider = FutureProvider.autoDispose<Dashboard>((ref) async {
  final response = await ref.watch(apiProvider).get('/dashboard');
  return Dashboard.fromJson(response['data'] as Json);
});

final coursesProvider = FutureProvider.autoDispose<List<Course>>((ref) async {
  final response = await ref.watch(apiProvider).get('/courses', {'per_page': 100});
  return (response['data'] as List).cast<Json>().map(Course.fromJson).toList();
});

final topicsProvider = FutureProvider.autoDispose.family<List<Topic>, int>((ref, subjectId) async {
  final response = await ref.watch(apiProvider).get('/subjects/$subjectId/topics');
  return (response['data'] as List).cast<Json>().map(Topic.fromJson).toList()..sort((a, b) => a.position.compareTo(b.position));
});

/// Students of a course's group with their queue state in that course.
/// Keyed by (groupId, courseId) so equal keys share one request.
final courseStudentsProvider = FutureProvider.autoDispose.family<List<Student>, (int, int)>((ref, key) async {
  final (groupId, courseId) = key;
  final response = await ref.watch(apiProvider).get('/groups/$groupId/students', {'course_id': courseId});
  return (response['data'] as List).cast<Json>().map(Student.fromJson).toList();
});

final rulesProvider = FutureProvider.autoDispose<Rules>((ref) async {
  return Rules.fromJson(await ref.watch(apiProvider).get('/settings'));
});
