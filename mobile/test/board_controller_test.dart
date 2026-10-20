import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:lessonloop/core/api.dart';
import 'package:lessonloop/core/providers.dart';
import 'package:lessonloop/features/board/board_controller.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'support/routing_adapter.dart';

const boardJson = {
  'state': {
    'board_id': 5,
    'name': '214-xona',
    'screen': 'lobby',
    'is_paired': true,
    'lesson': {'id': 9},
    'options': <String, Object?>{},
  },
};

Future<(ProviderContainer, SharedPreferences)> setUp(RoutingAdapter adapter, Map<String, Object> saved) async {
  SharedPreferences.setMockInitialValues({'auth_token': 'abc', ...saved});
  final prefs = await SharedPreferences.getInstance();
  final container = ProviderContainer(
    overrides: [
      prefsProvider.overrideWithValue(prefs),
      apiProvider.overrideWithValue(ApiClient(baseUrl: 'http://test/api', token: 'abc', adapter: adapter)),
    ],
  );
  addTearDown(container.dispose);
  return (container, prefs);
}

/// Lets the microtask that restores the board and its request finish.
Future<void> settle() => Future<void>.delayed(const Duration(milliseconds: 20));

void main() {
  group('BoardController', () {
    test('remembers the paired board and gets it back after a restart', () async {
      final adapter = RoutingAdapter({'POST /boards/pair': (200, boardJson), 'GET /boards/5/controller-state': (200, boardJson)});
      final (first, prefs) = await setUp(adapter, {});

      await first.read(boardProvider.notifier).pair('482913');
      await settle();
      expect(prefs.getInt('board_id'), 5);
      first.dispose();

      // A new app start with the same device storage.
      final restarted = ProviderContainer(
        overrides: [
          prefsProvider.overrideWithValue(prefs),
          apiProvider.overrideWithValue(ApiClient(baseUrl: 'http://test/api', token: 'abc', adapter: adapter)),
        ],
      );
      addTearDown(restarted.dispose);

      expect(restarted.read(boardProvider), isNull);
      await settle();

      expect(restarted.read(boardProvider)?.boardId, 5);
      expect(restarted.read(boardProvider)?.lessonId, 9);
      expect(adapter.calls, contains('GET /boards/5/controller-state'));
    });

    test('forgets a saved board that another phone took over', () async {
      final adapter = RoutingAdapter({
        'GET /boards/5/controller-state': (403, {'message': 'This action is unauthorized.'}),
      });
      final (container, prefs) = await setUp(adapter, {'board_id': 5});

      container.read(boardProvider);
      await settle();

      expect(container.read(boardProvider), isNull);
      expect(prefs.getInt('board_id'), isNull);
    });

    test('keeps the saved board while the server cannot be reached', () async {
      final (container, prefs) = await setUp(RoutingAdapter({}), {'board_id': 5});

      container.read(boardProvider);
      await settle();

      expect(container.read(boardProvider), isNull);
      expect(prefs.getInt('board_id'), 5);
    });

    test('sends heartbeats while paired and stops after the board is released', () async {
      BoardController.heartbeatInterval = const Duration(milliseconds: 30);
      addTearDown(() => BoardController.heartbeatInterval = const Duration(seconds: 15));
      final adapter = RoutingAdapter({
        'POST /boards/pair': (200, boardJson),
        'POST /boards/5/heartbeat': (200, boardJson),
        'DELETE /boards/5/controller': (200, {'released': true}),
      });
      final (container, prefs) = await setUp(adapter, {});

      await container.read(boardProvider.notifier).pair('482913');
      await Future<void>.delayed(const Duration(milliseconds: 100));
      final beats = adapter.calls.where((call) => call == 'POST /boards/5/heartbeat').length;
      expect(beats, greaterThanOrEqualTo(2));

      await container.read(boardProvider.notifier).release();
      await Future<void>.delayed(const Duration(milliseconds: 100));

      expect(adapter.calls.where((call) => call == 'POST /boards/5/heartbeat').length, beats);
      expect(container.read(boardProvider), isNull);
      expect(prefs.getInt('board_id'), isNull);
    });

    test('a heartbeat refused with 403 drops the board', () async {
      final adapter = RoutingAdapter({
        'POST /boards/pair': (200, boardJson),
        'POST /boards/5/heartbeat': (403, {'message': 'This action is unauthorized.'}),
      });
      final (container, prefs) = await setUp(adapter, {});

      await container.read(boardProvider.notifier).pair('482913');
      await container.read(boardProvider.notifier).ping();

      expect(container.read(boardProvider), isNull);
      expect(prefs.getInt('board_id'), isNull);
    });
  });
}
