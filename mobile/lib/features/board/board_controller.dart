import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api.dart';
import '../../core/models.dart';
import '../../core/providers.dart';

/// The classroom board this phone controls, if any (D1–D10 phone side).
///
/// The board id is kept on the device, so the phone gets its board back after
/// the app restarts. While paired it sends a heartbeat every
/// [heartbeatInterval]; the board shows «Pult uzildi» (D9) when they stop.
class BoardController extends Notifier<BoardState?> {
  static const _key = 'board_id';

  /// The board treats the phone as gone after 45 s, so three beats fit.
  static Duration heartbeatInterval = const Duration(seconds: 15);

  Timer? _heartbeat;
  Timer? _retry;

  @override
  BoardState? build() {
    ref.onDispose(_stopTimers);

    // Signing out drops the board.
    final token = ref.watch(tokenProvider);
    final saved = ref.read(prefsProvider).getInt(_key);
    if (token == null) {
      if (saved != null) {
        unawaited(ref.read(prefsProvider).remove(_key));
      }
    } else if (saved != null) {
      Future.microtask(() => restore(saved));
    }
    return null;
  }

  ApiClient get _api => ref.read(apiProvider);

  /// Re-read the saved board after an app restart. A board that another phone
  /// took over (403) or that no longer exists (404) is forgotten; without a
  /// network the phone tries again a bit later.
  Future<void> restore(int boardId) async {
    _retry?.cancel();
    try {
      final response = await _api.get('/boards/$boardId/controller-state');
      if (ref.read(prefsProvider).getInt(_key) == boardId) {
        _connected(BoardState.fromJson(response['state'] as Json));
      }
    } on ApiException catch (error) {
      if (error.status == 403 || error.status == 404) {
        await _forget();
      } else {
        _retry = Timer(heartbeatInterval, () => restore(boardId));
      }
    }
  }

  /// Tell the backend this phone still controls the board.
  Future<void> ping() async {
    final board = state;
    if (board == null) {
      return;
    }
    try {
      await _api.post('/boards/${board.boardId}/heartbeat');
    } on ApiException catch (error) {
      if (error.status == 403 || error.status == 404) {
        // Another phone paired with the board, or it was closed.
        await _forget();
      }
      // Offline: the board shows «Pult uzildi» until the next beat gets through.
    }
  }

  /// Pair with the 6-digit code (typed, or read from the QR `lessonloop://pair?code=…`).
  Future<BoardState> pair(String code) async {
    final response = await _api.post('/boards/pair', {'code': code});
    return _connected(BoardState.fromJson(response['state'] as Json));
  }

  Future<void> attach(int lessonId) async {
    final board = state;
    if (board == null) {
      return;
    }
    final response = await _api.post('/boards/${board.boardId}/lesson', {'lesson_id': lessonId});
    state = BoardState.fromJson(response['state'] as Json);
  }

  /// Send a command; returns the board's current answer id (set by draw/redraw).
  Future<int?> command(String command) async {
    final board = state;
    if (board == null) {
      return null;
    }
    final response = await _api.post('/boards/${board.boardId}/commands', {'command': command});
    state = BoardState.fromJson(response['state'] as Json);
    final answerId = response['answer_id'];
    return answerId is num ? answerId.toInt() : null;
  }

  Future<void> setOption(String key, Object? value) async {
    final board = state;
    if (board == null) {
      return;
    }
    final response = await _api.patch('/boards/${board.boardId}/options', {key: value});
    state = BoardState.fromJson(response['state'] as Json);
  }

  Future<void> release() async {
    final board = state;
    if (board == null) {
      return;
    }
    try {
      await _api.delete('/boards/${board.boardId}/controller');
    } finally {
      await _forget();
    }
  }

  BoardState _connected(BoardState board) {
    unawaited(ref.read(prefsProvider).setInt(_key, board.boardId));
    _heartbeat?.cancel();
    _heartbeat = Timer.periodic(heartbeatInterval, (_) => ping());
    return state = board;
  }

  Future<void> _forget() async {
    _stopTimers();
    state = null;
    await ref.read(prefsProvider).remove(_key);
  }

  void _stopTimers() {
    _heartbeat?.cancel();
    _retry?.cancel();
    _heartbeat = null;
    _retry = null;
  }
}

final boardProvider = NotifierProvider<BoardController, BoardState?>(BoardController.new);

/// The pairing code from a scanned QR (`lessonloop://pair?code=482913`) or typed digits.
String? parsePairCode(String raw) {
  final value = raw.trim();
  final uri = Uri.tryParse(value);
  final fromQr = uri?.scheme == 'lessonloop' ? uri?.queryParameters['code'] : null;
  final code = (fromQr ?? value).replaceAll(RegExp(r'\s'), '');
  return RegExp(r'^\d{6}$').hasMatch(code) ? code : null;
}
