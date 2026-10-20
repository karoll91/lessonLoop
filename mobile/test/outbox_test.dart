import 'dart:math';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:lessonloop/core/api.dart';
import 'package:lessonloop/core/outbox.dart';
import 'package:lessonloop/core/providers.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'support/routing_adapter.dart';

PendingOperation grade(int answerId, int score, {String? uuid}) => PendingOperation(
  clientUuid: uuid ?? uuidV4(),
  type: 'grade',
  answerId: answerId,
  lessonId: 3,
  performedAt: DateTime.utc(2026, 10, 20, 9, 15),
  score: score,
  studentName: 'Sora Aliyeva',
);

ProviderContainer containerFor(SharedPreferences prefs, RoutingAdapter adapter) {
  final container = ProviderContainer(
    overrides: [
      prefsProvider.overrideWithValue(prefs),
      apiProvider.overrideWithValue(ApiClient(baseUrl: 'http://test/api', token: 'abc', adapter: adapter)),
    ],
  );
  addTearDown(container.dispose);
  return container;
}

void main() {
  late SharedPreferences prefs;

  setUp(() async {
    SharedPreferences.setMockInitialValues({'auth_token': 'abc'});
    prefs = await SharedPreferences.getInstance();
  });

  test('uuidV4 has the version and variant bits set', () {
    final uuid = uuidV4(Random(1));

    expect(uuid, matches(RegExp(r'^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$')));
    expect(uuidV4(), isNot(uuidV4()));
  });

  test('a grade made offline survives an app restart and is sent once the server answers', () async {
    final offline = RoutingAdapter({});
    final first = containerFor(prefs, offline);
    final operation = grade(41, 85);

    await first.read(outboxProvider.notifier).add(operation);
    expect(await first.read(outboxProvider.notifier).flush(), isFalse);
    expect(first.read(outboxProvider).hasLesson(3), isTrue);
    first.dispose();

    final online = RoutingAdapter({
      'POST /sync': (
        200,
        {
          'results': [
            {'client_uuid': operation.clientUuid, 'status': 'applied', 'duplicate': false},
          ],
        },
      ),
    });
    final restarted = containerFor(prefs, online);

    expect(restarted.read(outboxProvider).pending.single.score, 85);
    expect(await restarted.read(outboxProvider.notifier).flush(), isTrue);

    final sent = (online.bodies.single as Map)['operations'] as List;
    expect(sent.single, {
      'client_uuid': operation.clientUuid,
      'type': 'grade',
      'answer_id': 41,
      'performed_at': '2026-10-20T09:15:00.000Z',
      'score': 85,
    });
    expect(restarted.read(outboxProvider).pending, isEmpty);
    expect(prefs.getString('outbox'), isNull);
  });

  test('operations the server refuses are dropped and reported with the student name', () async {
    final operation = grade(41, 85);
    final adapter = RoutingAdapter({
      'POST /sync': (
        200,
        {
          'results': [
            {'client_uuid': operation.clientUuid, 'status': 'failed', 'code': 'answer_closed', 'message': 'Bu javob allaqachon baholangan.'},
          ],
        },
      ),
    });
    final container = containerFor(prefs, adapter);

    await container.read(outboxProvider.notifier).add(operation);
    expect(await container.read(outboxProvider.notifier).flush(), isTrue);

    expect(container.read(outboxProvider).rejected, ['Sora Aliyeva: Bu javob allaqachon baholangan.']);
    container.read(outboxProvider.notifier).dismissRejected();
    expect(container.read(outboxProvider).rejected, isEmpty);
  });

  test('a queued grade looks graded on the phone until it is sent', () {
    final local = grade(41, 55).toLocalAnswer(passScore: 60);

    expect(local.status, 'graded');
    expect(local.score, 55);
    expect(local.passed, isFalse);
    expect(local.isScored, isTrue);
  });

  test('signing out with clear() forgets the queue', () async {
    final container = containerFor(prefs, RoutingAdapter({}));

    await container.read(outboxProvider.notifier).add(grade(41, 85));
    await container.read(outboxProvider.notifier).clear();

    expect(container.read(outboxProvider).pending, isEmpty);
    expect(prefs.getString('outbox'), isNull);
  });
}
