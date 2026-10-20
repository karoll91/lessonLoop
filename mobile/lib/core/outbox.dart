import 'dart:async';
import 'dart:convert';
import 'dart:math';

import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'api.dart';
import 'models.dart';
import 'providers.dart';

/// A random UUID v4 for client_uuid.
String uuidV4([Random? random]) {
  final rng = random ?? Random.secure();
  final bytes = List<int>.generate(16, (_) => rng.nextInt(256));
  bytes[6] = (bytes[6] & 0x0f) | 0x40;
  bytes[8] = (bytes[8] & 0x3f) | 0x80;
  final hex = bytes.map((byte) => byte.toRadixString(16).padLeft(2, '0')).join();
  return '${hex.substring(0, 8)}-${hex.substring(8, 12)}-${hex.substring(12, 16)}-${hex.substring(16, 20)}-${hex.substring(20)}';
}

/// A grade or skip made while the server could not be reached.
@immutable
class PendingOperation {
  const PendingOperation({
    required this.clientUuid,
    required this.type,
    required this.answerId,
    required this.lessonId,
    required this.performedAt,
    this.score,
    this.chosenOption,
    this.comment,
    this.reason,
    this.studentName = '',
  });

  factory PendingOperation.fromJson(Json json) => PendingOperation(
    clientUuid: json['client_uuid'] as String,
    type: json['type'] as String,
    answerId: (json['answer_id'] as num).toInt(),
    lessonId: (json['lesson_id'] as num).toInt(),
    performedAt: DateTime.parse(json['performed_at'] as String),
    score: (json['score'] as num?)?.toInt(),
    chosenOption: json['chosen_option'] as String?,
    comment: json['comment'] as String?,
    reason: json['reason'] as String?,
    studentName: json['student_name'] as String? ?? '',
  );

  final String clientUuid;

  /// grade or skip.
  final String type;
  final int answerId;
  final int lessonId;
  final DateTime performedAt;
  final int? score;
  final String? chosenOption;
  final String? comment;

  /// no_answer or absent, for skip.
  final String? reason;
  final String studentName;

  Json toJson() => {
    'client_uuid': clientUuid,
    'type': type,
    'answer_id': answerId,
    'lesson_id': lessonId,
    'performed_at': performedAt.toUtc().toIso8601String(),
    'score': ?score,
    'chosen_option': ?chosenOption,
    'comment': ?comment,
    'reason': ?reason,
    'student_name': studentName,
  };

  /// The body of POST /sync for this operation (or of the online call).
  Json toRequest() => {
    'client_uuid': clientUuid,
    'type': type,
    'answer_id': answerId,
    'performed_at': performedAt.toUtc().toIso8601String(),
    'score': ?score,
    'chosen_option': ?chosenOption,
    if (comment != null && comment!.isNotEmpty) 'comment': comment,
    'reason': ?reason,
  };

  /// How the answer looks on the phone until the server confirms it.
  Answer toLocalAnswer({required int passScore}) => Answer(
    id: answerId,
    status: type == 'grade' ? 'graded' : (reason ?? 'no_answer'),
    score: type == 'grade' ? score : (reason == 'absent' ? null : 0),
    passed: type == 'grade' && score != null ? score! >= passScore : (reason == 'absent' ? null : false),
    chosenOption: chosenOption,
    comment: comment,
    studentName: studentName,
  );
}

/// Operations waiting to be sent, and the ones the server refused.
@immutable
class OutboxState {
  const OutboxState({this.pending = const [], this.rejected = const []});

  final List<PendingOperation> pending;

  /// Server messages for queued operations it could not apply.
  final List<String> rejected;

  bool hasLesson(int lessonId) => pending.any((operation) => operation.lessonId == lessonId);

  OutboxState copyWith({List<PendingOperation>? pending, List<String>? rejected}) =>
      OutboxState(pending: pending ?? this.pending, rejected: rejected ?? this.rejected);
}

/// Grades and skips made without a connection (PRD stage 2). They are kept on
/// the device and sent with POST /sync as soon as the server answers; each has
/// a client_uuid, so sending one twice never grades twice.
class OutboxController extends Notifier<OutboxState> {
  static const _key = 'outbox';

  /// How often a non-empty outbox tries to reach the server.
  static Duration retryInterval = const Duration(seconds: 15);

  Timer? _timer;
  Future<bool>? _flushing;

  @override
  OutboxState build() {
    ref.onDispose(() => _timer?.cancel());
    // Signed out: nothing can be sent. Settings clears the outbox on logout,
    // so another teacher never sends this one's grades.
    if (ref.watch(tokenProvider) == null) {
      return const OutboxState();
    }
    final raw = ref.read(prefsProvider).getString(_key);
    final pending = <PendingOperation>[];
    if (raw != null) {
      try {
        pending.addAll((jsonDecode(raw) as List).cast<Json>().map(PendingOperation.fromJson));
      } on Object {
        // A damaged outbox is dropped rather than blocking the app.
      }
    }
    _schedule(pending);
    return OutboxState(pending: pending);
  }

  ApiClient get _api => ref.read(apiProvider);

  Future<void> add(PendingOperation operation) async {
    await _save([...state.pending, operation]);
  }

  /// Forget everything queued (used when the teacher signs out).
  Future<void> clear() async {
    await _save(const []);
    state = const OutboxState();
  }

  void dismissRejected() => state = state.copyWith(rejected: const []);

  /// Send everything queued. Returns true when nothing is left.
  Future<bool> flush() => _flushing ??= _flush().whenComplete(() => _flushing = null);

  Future<bool> _flush() async {
    final queued = state.pending;
    if (queued.isEmpty) {
      return true;
    }
    try {
      final response = await _api.post('/sync', {'operations': queued.map((operation) => operation.toRequest()).toList()});
      final results = (response['results'] as List? ?? const []).cast<Json>();
      final done = results.map((result) => result['client_uuid'] as String).toSet();
      final rejected = [
        for (final result in results)
          if (result['status'] == 'failed') _rejection(queued, result),
      ];
      // Operations added while the request was in flight stay queued.
      await _save(state.pending.where((operation) => !done.contains(operation.clientUuid)).toList());
      if (rejected.isNotEmpty) {
        state = state.copyWith(rejected: [...state.rejected, ...rejected]);
      }
      return state.pending.isEmpty;
    } on ApiException catch (error) {
      if (error.status == 422) {
        // The server will never accept this batch as it is; keep the app usable.
        state = state.copyWith(rejected: [...state.rejected, error.userMessage]);
        await _save(const []);
      }
      return false;
    }
  }

  String _rejection(List<PendingOperation> queued, Json result) {
    final operation = queued.where((item) => item.clientUuid == result['client_uuid']).firstOrNull;
    final who = operation == null || operation.studentName.isEmpty ? '' : '${operation.studentName}: ';
    return '$who${result['message'] ?? 'saqlanmadi'}';
  }

  Future<void> _save(List<PendingOperation> pending) async {
    final prefs = ref.read(prefsProvider);
    if (pending.isEmpty) {
      await prefs.remove(_key);
    } else {
      await prefs.setString(_key, jsonEncode(pending.map((operation) => operation.toJson()).toList()));
    }
    state = state.copyWith(pending: pending);
    _schedule(pending);
  }

  void _schedule(List<PendingOperation> pending) {
    if (pending.isEmpty) {
      _timer?.cancel();
      _timer = null;
    } else {
      _timer ??= Timer.periodic(retryInterval, (_) => flush());
    }
  }
}

final outboxProvider = NotifierProvider<OutboxController, OutboxState>(OutboxController.new);
