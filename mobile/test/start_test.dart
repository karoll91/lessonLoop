import 'dart:async';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:lessonloop/app.dart';
import 'package:lessonloop/core/api.dart';
import 'package:lessonloop/core/providers.dart';
import 'package:lessonloop/core/theme.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'support/routing_adapter.dart';

/// A server address that leads nowhere: the request is never answered.
class SilentAdapter implements HttpClientAdapter {
  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) =>
      Completer<ResponseBody>().future;

  @override
  void close({bool force = false}) {}
}

const oldServer = 'http://10.42.32.40:8000/api';
const newServer = 'http://192.168.31.94:8000/api';

Future<void> startApp(WidgetTester tester) async {
  SharedPreferences.setMockInitialValues({'onboarding_seen': true, 'auth_token': 'token', 'server_url': oldServer});
  final prefs = await SharedPreferences.getInstance();
  // The old address hangs; the new one answers that it is not reachable,
  // which is enough to show which address the app now uses.
  final silent = SilentAdapter();
  final refusing = RoutingAdapter({});

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        prefsProvider.overrideWithValue(prefs),
        apiProvider.overrideWith((ref) {
          final url = ref.watch(settingsProvider.select((settings) => settings.serverUrl));
          return ApiClient(baseUrl: url, token: ref.watch(tokenProvider), adapter: url == oldServer ? silent : refusing);
        }),
      ],
      child: MaterialApp(theme: buildTheme(Brightness.light), home: const AuthGate()),
    ),
  );
}

void main() {
  testWidgets('a silent server never keeps the start screen spinning', (tester) async {
    await startApp(tester);
    expect(find.text("Server manzilini o'zgartirish"), findsNothing);

    await tester.pump(const Duration(seconds: 3));
    expect(find.text(oldServer), findsOneWidget);
    expect(find.text("Server manzilini o'zgartirish"), findsOneWidget);

    await tester.pump(AuthController.startTimeout);
    await tester.pump();
    expect(find.text("Serverga ulanib bo'lmadi"), findsOneWidget);
  });

  testWidgets('the address can be changed from the start screen without a crash', (tester) async {
    await startApp(tester);
    await tester.pump(const Duration(seconds: 3));

    await tester.tap(find.text("Server manzilini o'zgartirish"));
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextField), '192.168.31.94:8000');
    await tester.tap(find.text('Saqlash'));
    await tester.pumpAndSettle();

    expect(tester.takeException(), isNull);
    expect(find.text("Serverga ulanib bo'lmadi"), findsOneWidget);
    expect(find.textContaining(newServer), findsOneWidget);

    // The request to the old address times out without effect.
    await tester.pump(AuthController.startTimeout);
  });
}
