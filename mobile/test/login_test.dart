import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:lessonloop/core/api.dart';
import 'package:lessonloop/core/models.dart';
import 'package:lessonloop/core/providers.dart';
import 'package:lessonloop/core/theme.dart';
import 'package:lessonloop/features/auth/auth_screens.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Rejects every login like the server does for a wrong password.
class RejectingAuth extends AuthController {
  int attempts = 0;

  @override
  Future<User?> build() async => null;

  @override
  Future<void> login(String login, String password) async {
    attempts++;
    throw ApiException(
      422,
      'invalid',
      errors: {
        'login': ["Login yoki parol noto'g'ri."],
      },
    );
  }
}

void main() {
  testWidgets('login stays disabled until both fields are filled, then shows the server error', (tester) async {
    SharedPreferences.setMockInitialValues({});
    final prefs = await SharedPreferences.getInstance();
    final auth = RejectingAuth();

    await tester.pumpWidget(
      ProviderScope(
        overrides: [prefsProvider.overrideWithValue(prefs), authProvider.overrideWith(() => auth)],
        child: MaterialApp(theme: buildTheme(Brightness.light), home: const LoginScreen()),
      ),
    );

    final button = find.widgetWithText(FilledButton, 'Kirish');
    expect(tester.widget<FilledButton>(button).onPressed, isNull);

    await tester.enterText(find.widgetWithText(TextField, 'Login'), 'admin');
    await tester.enterText(find.widgetWithText(TextField, 'Parol'), 'wrong-pass');
    await tester.pump();
    expect(tester.widget<FilledButton>(button).onPressed, isNotNull);

    await tester.tap(button);
    await tester.pumpAndSettle();

    expect(auth.attempts, 1);
    expect(find.text("Login yoki parol noto'g'ri."), findsOneWidget);
  });
}
