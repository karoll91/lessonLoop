import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api.dart';
import '../../core/models.dart';
import '../../core/providers.dart';

/// The classroom board this phone controls, if any (D1–D10 phone side).
class BoardController extends Notifier<BoardState?> {
  @override
  BoardState? build() {
    // Signing out drops the board.
    ref.watch(tokenProvider);
    return null;
  }

  ApiClient get _api => ref.read(apiProvider);

  /// Pair with the 6-digit code (typed, or read from the QR `lessonloop://pair?code=…`).
  Future<BoardState> pair(String code) async {
    final response = await _api.post('/boards/pair', {'code': code});
    return state = BoardState.fromJson(response['state'] as Json);
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
      state = null;
    }
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
