import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:lessonloop/core/api.dart';
import 'package:lessonloop/core/models.dart';
import 'package:lessonloop/features/board/board_controller.dart';

/// Answers every request with one canned status and JSON body, or fails like a dead server.
class FakeAdapter implements HttpClientAdapter {
  FakeAdapter(this.status, this.body, {this.offline = false});

  final int status;
  final Object body;
  final bool offline;
  RequestOptions? last;

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) async {
    last = options;
    if (offline) {
      throw DioException.connectionError(requestOptions: options, reason: 'refused');
    }
    return ResponseBody.fromString(
      jsonEncode(body),
      status,
      headers: {
        Headers.contentTypeHeader: ['application/json'],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}

Future<ApiException> failure(Future<Object?> call) async {
  try {
    await call;
  } on ApiException catch (error) {
    return error;
  }
  throw StateError('The call did not fail');
}

void main() {
  group('parsePairCode', () {
    test('reads the code from the board QR', () {
      expect(parsePairCode('lessonloop://pair?code=482913'), '482913');
    });

    test('accepts six typed digits, with spaces', () {
      expect(parsePairCode(' 482 913 '), '482913');
    });

    test('rejects anything else', () {
      expect(parsePairCode('48291'), isNull);
      expect(parsePairCode('https://example.com/?code=482913'), isNull);
      expect(parsePairCode('abcdef'), isNull);
    });
  });

  group('models', () {
    test('a lesson knows its pending answer and the average of scored answers', () {
      final lesson = Lesson.fromJson({
        'id': 1,
        'course_id': 2,
        'sequence': 3,
        'question_count': 5,
        'status': 'active',
        'answers': [
          {
            'id': 10,
            'status': 'graded',
            'score': 90,
            'student': {'id': 1, 'full_name': 'Sora Aliyeva'},
          },
          {'id': 11, 'status': 'no_answer', 'score': 0},
          {'id': 12, 'status': 'absent'},
          {
            'id': 13,
            'status': 'pending',
            'student': {'id': 4, 'full_name': 'Bobur Rahimov'},
          },
        ],
      });

      expect(lesson.scored.length, 2);
      expect(lesson.average, 45);
      expect(lesson.pending?.studentName, 'Bobur Rahimov');
    });

    test('an answer can be regraded only inside its edit window', () {
      final answer = Answer.fromJson({'id': 1, 'status': 'graded', 'score': 50, 'editable_until': '2026-10-21T10:00:00Z'});

      expect(answer.editableAt(DateTime(2026, 10, 20, 12)), isTrue);
      expect(answer.editableAt(DateTime(2026, 10, 22)), isFalse);
    });

    test('unknown queue states fall back to the plain queue', () {
      expect(QueueState.parse('debtor'), QueueState.debtor);
      expect(QueueState.parse('something'), QueueState.queue);
    });
  });

  group('ApiClient', () {
    ApiClient client(FakeAdapter adapter, {void Function()? onUnauthorized, String? token}) =>
        ApiClient(baseUrl: 'http://test/api', token: token, adapter: adapter, onUnauthorized: onUnauthorized);

    test('maps validation errors to fields and a readable message', () async {
      final adapter = FakeAdapter(422, {
        'message': 'The given data was invalid.',
        'errors': {
          'login': ["Login yoki parol noto'g'ri."],
        },
      });

      final error = await failure(client(adapter).post('/login', {'login': 'x'}));

      expect(error.status, 422);
      expect(error.fieldError('login'), "Login yoki parol noto'g'ri.");
      expect(error.userMessage, "Login yoki parol noto'g'ri.");
    });

    test('keeps the lesson flow code', () async {
      final adapter = FakeAdapter(422, {'message': 'Randomga talaba qolmadi.', 'code': 'no_students_left'});

      final error = await failure(client(adapter).post('/lessons/1/draw'));

      expect(error.code, 'no_students_left');
      expect(error.userMessage, 'Randomga talaba qolmadi.');
    });

    test('an unreachable server is status 0', () async {
      final error = await failure(client(FakeAdapter(0, {}, offline: true)).get('/me'));

      expect(error.isOffline, isTrue);
    });

    test('401 with a token signs out', () async {
      var signedOut = false;
      final adapter = FakeAdapter(401, {'message': 'Unauthenticated.'});

      await failure(client(adapter, token: 'abc', onUnauthorized: () => signedOut = true).get('/me'));

      expect(signedOut, isTrue);
      expect(adapter.last?.headers['Authorization'], 'Bearer abc');
    });

    test('drops empty query values', () async {
      final adapter = FakeAdapter(200, {'data': []});

      await client(adapter).get('/lessons', {'course_id': null, 'q': '', 'page': 2});

      expect(adapter.last?.queryParameters, {'page': 2});
    });
  });
}
