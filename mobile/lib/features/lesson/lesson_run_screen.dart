import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api.dart';
import '../../core/models.dart';
import '../../core/outbox.dart';
import '../../core/providers.dart';
import '../../core/theme.dart';
import '../../ui/widgets.dart';
import '../board/board_controller.dart';
import '../board/pair_screen.dart';

/// M6–M9 — draw a student, show the question, grade, repeat, finish.
/// With a paired board every step is mirrored there (D3–D8).
class LessonRunScreen extends ConsumerStatefulWidget {
  const LessonRunScreen({super.key, required this.lessonId});

  final int lessonId;

  @override
  ConsumerState<LessonRunScreen> createState() => _LessonRunScreenState();
}

enum _Phase { loading, idle, drawing, question, finished }

class _LessonRunScreenState extends ConsumerState<LessonRunScreen> {
  _Phase _phase = _Phase.loading;
  Lesson? _lesson;
  Answer? _current;
  Answer? _last;
  bool _revealed = false;
  bool _busy = false;
  Object? _loadError;

  ApiClient get _api => ref.read(apiProvider);
  BoardController get _board => ref.read(boardProvider.notifier);
  bool get _hasBoard => ref.read(boardProvider) != null;
  OutboxController get _outbox => ref.read(outboxProvider.notifier);
  int get _passScore => ref.read(rulesProvider).value?.passScore ?? 60;

  /// Grades saved on the phone must reach the server before the next draw,
  /// redraw or finish: those need the server anyway, and finishing would
  /// drop a still-pending answer.
  Future<void> _sendQueued() async {
    await _outbox.flush();
    if (ref.read(outboxProvider).hasLesson(widget.lessonId)) {
      throw ApiException(0, "Telefonda saqlangan baholar hali yuborilmadi. Internet ulanishini tekshiring.");
    }
  }
  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<Lesson> _fetch() async {
    final response = await _api.get('/lessons/${widget.lessonId}');
    return Lesson.fromJson(response['data'] as Json);
  }

  Future<void> _load() async {
    setState(() {
      _phase = _Phase.loading;
      _loadError = null;
    });
    try {
      await _outbox.flush();
      final lesson = await _fetch();
      final queued = ref.read(outboxProvider).pending.map((operation) => operation.answerId).toSet();
      setState(() {
        _lesson = lesson;
        // An answer graded offline is still pending on the server.
        _current = queued.contains(lesson.pending?.id) ? null : lesson.pending;
        _phase = lesson.isFinished ? _Phase.finished : (_current == null ? _Phase.idle : _Phase.question);
      });
      // The board follows the lesson opened on the phone.
      final board = ref.read(boardProvider);
      if (!lesson.isFinished && board != null && board.lessonId != lesson.id) {
        await _board.attach(lesson.id);
      }
    } catch (error) {
      setState(() => _loadError = error);
    }
  }

  /// Run an action; flow errors (R6 and friends) become dialogs or messages.
  Future<void> _run(Future<void> Function() action) async {
    if (_busy) {
      return;
    }
    setState(() => _busy = true);
    try {
      await action();
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }
      if (error.code == 'no_students_left' || error.code == 'question_limit_reached' || error.code == 'no_questions_left') {
        await _offerFinish(error.userMessage);
      } else {
        showMessage(context, error.userMessage, error: true);
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _draw() => _run(() async {
    await _sendQueued();
    setState(() {
      _phase = _Phase.drawing;
      _revealed = false;
    });
    final animation = Future<void>.delayed(const Duration(milliseconds: 1500));
    Answer answer;
    try {
      if (_hasBoard) {
        await _board.command('draw');
        final lesson = await _fetch();
        _lesson = lesson;
        answer = lesson.pending!;
      } else {
        final response = await _api.post('/lessons/${widget.lessonId}/draw');
        answer = Answer.fromJson(response['data'] as Json);
      }
    } catch (_) {
      setState(() => _phase = _Phase.idle);
      rethrow;
    }
    await animation;
    HapticFeedback.mediumImpact();
    setState(() {
      _current = answer;
      _phase = _Phase.question;
    });
    if (_hasBoard) {
      await _board.command('show_question');
    }
  });

  Future<void> _toggleAnswer() => _run(() async {
    final reveal = !_revealed;
    setState(() => _revealed = reveal);
    if (_hasBoard) {
      await _board.command(reveal ? 'reveal_answer' : 'hide_answer');
    }
  });

  Future<void> _grade({int? score, String? option, String? comment}) => _run(() async {
    final operation = _operation('grade', score: score, chosenOption: option, comment: comment);
    final graded = await _send('/answers/${operation.answerId}/grade', operation);
    if (graded == null) {
      return;
    }
    if (_hasBoard) {
      await _board.command('show_score');
    }
    await _afterAnswer(graded);
  });

  Future<void> _skip(String reason) => _run(() async {
    final skipped = await _send('/answers/${_current!.id}/skip', _operation('skip', reason: reason));
    if (skipped == null) {
      return;
    }
    if (_hasBoard) {
      // The skipped student's question leaves the board.
      await _board.command('show_lobby');
    }
    await _afterAnswer(skipped);
  });

  PendingOperation _operation(String type, {int? score, String? chosenOption, String? comment, String? reason}) {
    final answer = _current!;
    return PendingOperation(
      clientUuid: uuidV4(),
      type: type,
      answerId: answer.id,
      lessonId: widget.lessonId,
      performedAt: DateTime.now(),
      score: score,
      chosenOption: chosenOption,
      comment: comment,
      reason: reason,
      studentName: answer.studentName,
    );
  }

  /// Grade or skip online. Without a connection the operation is kept on the
  /// phone (null is returned) and sent later with the same client_uuid.
  Future<Answer?> _send(String path, PendingOperation operation) async {
    final body = Map<String, dynamic>.of(operation.toRequest())..removeWhere((key, _) => key == 'type' || key == 'answer_id' || key == 'performed_at');
    try {
      final response = await _api.post(path, body);
      return Answer.fromJson(response['data'] as Json);
    } on ApiException catch (error) {
      if (!error.isOffline) {
        rethrow;
      }
    }
    await _outbox.add(operation);
    HapticFeedback.mediumImpact();
    setState(() {
      _last = operation.toLocalAnswer(passScore: _passScore);
      _current = null;
      _revealed = false;
      _phase = _Phase.idle;
    });
    if (mounted) {
      showMessage(context, "Internet yo'q. Baho telefonda saqlandi va aloqa tiklanganda o'zi yuboriladi.");
    }
    return null;
  }

  Future<void> _redraw() => _run(() async {
    await _sendQueued();
    setState(() {
      _phase = _Phase.drawing;
      _revealed = false;
    });
    final animation = Future<void>.delayed(const Duration(milliseconds: 1200));
    Answer answer;
    try {
      if (_hasBoard) {
        await _board.command('redraw');
        answer = (await _fetch()).pending!;
      } else {
        final response = await _api.post('/answers/${_current!.id}/redraw');
        answer = Answer.fromJson(response['data'] as Json);
      }
    } catch (_) {
      setState(() => _phase = _Phase.question);
      rethrow;
    }
    await animation;
    setState(() {
      _current = answer;
      _phase = _Phase.question;
    });
    if (_hasBoard) {
      await _board.command('show_question');
    }
  });

  Future<void> _afterAnswer(Answer answer) async {
    final lesson = await _fetch();
    setState(() {
      _lesson = lesson;
      _last = answer;
      _current = null;
      _revealed = false;
      _phase = _Phase.idle;
    });
  }

  Future<void> _finish() => _run(() async {
    await _sendQueued();
    if (_hasBoard) {
      await _board.command('finish');
    } else {
      await _api.post('/lessons/${widget.lessonId}/finish');
    }
    final lesson = await _fetch();
    ref.invalidate(dashboardProvider);
    setState(() {
      _lesson = lesson;
      _current = null;
      _phase = _Phase.finished;
    });
  });

  Future<void> _offerFinish(String reason) async {
    final finish = await confirm(context, title: 'Davom etib bo\'lmaydi', text: reason, action: 'Darsni yakunlash');
    if (finish) {
      await _finish();
    }
  }

  Future<void> _askFinish() async {
    final lesson = _lesson!;
    final left = lesson.questionCount - lesson.scored.length;
    final text = _current != null
        ? 'Javob kutilayotgan savol bekor qilinadi. ${lesson.scored.length} ta baho saqlangan.'
        : (left > 0 ? 'Yana $left ta savol qoldi. Baholar saqlangan.' : 'Barcha savollar berildi.');
    if (await confirm(context, title: 'Darsni yakunlaysizmi?', text: text, action: 'Yakunlash')) {
      await _finish();
    }
  }

  @override
  Widget build(BuildContext context) {
    final lesson = _lesson;
    final board = ref.watch(boardProvider);

    return Scaffold(
      appBar: AppBar(
        title: lesson == null
            ? const Text('Dars')
            : Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('${lesson.group ?? ''} · ${lesson.sequence}-dars', overflow: TextOverflow.ellipsis),
                  Text(lesson.subject ?? '', style: Theme.of(context).textTheme.bodySmall, overflow: TextOverflow.ellipsis),
                ],
              ),
        actions: [
          if (_phase != _Phase.finished && _phase != _Phase.loading) ...[
            _BoardButton(board: board, busy: _busy, run: _run),
            IconButton(tooltip: 'Darsni yakunlash', onPressed: _busy ? null : _askFinish, icon: const Icon(Icons.stop_circle_outlined)),
          ],
        ],
      ),
      body: switch (_phase) {
        _Phase.loading => _loadError == null ? const Loading() : ErrorView(error: _loadError!, onRetry: _load),
        _Phase.finished => _Summary(lesson: lesson!),
        _ => _running(lesson!),
      },
    );
  }

  Widget _running(Lesson lesson) {
    final outbox = ref.watch(outboxProvider);
    final scoredIds = lesson.scored.map((answer) => answer.id).toSet();
    final queued = outbox.pending.where((operation) => operation.lessonId == lesson.id && !scoredIds.contains(operation.answerId)).toList();
    final used = lesson.scored.length + queued.where((operation) => operation.reason != 'absent').length;
    final c = context.colors;
    final rules = ref.watch(rulesProvider).value;

    return Column(
      children: [
        if (queued.isNotEmpty)
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 4),
            child: Banner2(
              "${queued.length} ta baho telefonda saqlangan, hali yuborilmagan",
              tone: Tone.warn,
              icon: Icons.cloud_off_outlined,
              action: 'Yuborish',
              onAction: () => _run(() async {
                if (await _outbox.flush() && mounted) {
                  showMessage(context, 'Baholar yuborildi');
                }
              }),
            ),
          ),
        if (outbox.rejected.isNotEmpty)
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 4),
            child: Banner2("Saqlanmadi — ${outbox.rejected.join('; ')}", tone: Tone.danger, action: 'Yopish', onAction: _outbox.dismissRejected),
          ),
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
          child: Row(
            children: [
              Expanded(
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(4),
                  child: LinearProgressIndicator(value: lesson.questionCount == 0 ? 0 : used / lesson.questionCount, minHeight: 8, backgroundColor: c.line),
                ),
              ),
              const SizedBox(width: 12),
              Text('$used / ${lesson.questionCount}', style: const TextStyle(fontWeight: FontWeight.w800)),
            ],
          ),
        ),
        Expanded(
          child: AnimatedSwitcher(
            duration: const Duration(milliseconds: 250),
            child: switch (_phase) {
              _Phase.drawing => const _Drawing(key: ValueKey('drawing')),
              _Phase.question => _QuestionView(
                key: ValueKey(_current!.id),
                answer: _current!,
                revealed: _revealed,
                busy: _busy,
                passScore: rules?.passScore ?? 60,
                onToggle: _toggleAnswer,
                onGrade: _grade,
                onSkip: _skipSheet,
              ),
              _ => _Idle(
                key: const ValueKey('idle'),
                lesson: lesson,
                last: _last,
                busy: _busy,
                passScore: rules?.passScore ?? 60,
                onDraw: _draw,
                onFinish: _askFinish,
              ),
            },
          ),
        ),
      ],
    );
  }

  Future<void> _skipSheet() async {
    final choice = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      builder: (context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Padding(
              padding: EdgeInsets.fromLTRB(20, 0, 20, 8),
              child: Text("O'tkazish sababi", style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
            ),
            ListTile(
              leading: const Icon(Icons.voice_over_off_outlined),
              title: const Text('Javob bermadi'),
              subtitle: const Text("0 ball, talaba qarzdor bo'ladi"),
              onTap: () => Navigator.pop(context, 'no_answer'),
            ),
            ListTile(
              leading: const Icon(Icons.person_off_outlined),
              title: const Text("Darsda yo'q ekan"),
              subtitle: const Text('Baho qo\'yilmaydi, navbatdagi o\'rni saqlanadi'),
              onTap: () => Navigator.pop(context, 'absent'),
            ),
            ListTile(
              leading: const Icon(Icons.shuffle),
              title: const Text('Boshqa talaba'),
              subtitle: const Text('Shu savol boshqa talabaga beriladi'),
              onTap: () => Navigator.pop(context, 'redraw'),
            ),
            const SizedBox(height: 8),
          ],
        ),
      ),
    );
    switch (choice) {
      case 'redraw':
        await _redraw();
      case 'no_answer' || 'absent':
        await _skip(choice!);
    }
  }
}

/// Board controls in the app bar: pair, or pause / blackout / lobby.
class _BoardButton extends ConsumerWidget {
  const _BoardButton({required this.board, required this.busy, required this.run});

  final BoardState? board;
  final bool busy;
  final Future<void> Function(Future<void> Function()) run;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = board;
    if (state == null) {
      return IconButton(tooltip: 'Doskaga ulash', onPressed: () => openPairing(context), icon: const Icon(Icons.cast_outlined));
    }
    final suspended = state.screen == 'pause' || state.screen == 'blackout';
    final controller = ref.read(boardProvider.notifier);
    return PopupMenuButton<String>(
      tooltip: 'Doska',
      enabled: !busy,
      icon: Icon(Icons.cast_connected, color: context.colors.accent),
      onSelected: (value) => run(() async {
        if (value.startsWith('opt:')) {
          final key = value.substring(4);
          await controller.setOption(key, !state.option(key));
        } else {
          await controller.command(value);
        }
      }),
      itemBuilder: (context) => [
        if (suspended)
          const PopupMenuItem(
            value: 'resume',
            child: ListTile(leading: Icon(Icons.play_arrow), title: Text('Doskani davom ettirish')),
          )
        else ...[
          const PopupMenuItem(
            value: 'pause',
            child: ListTile(leading: Icon(Icons.pause), title: Text('Tanaffus')),
          ),
          const PopupMenuItem(
            value: 'blackout',
            child: ListTile(leading: Icon(Icons.visibility_off_outlined), title: Text('Qora ekran')),
          ),
        ],
        const PopupMenuDivider(),
        CheckedPopupMenuItem(value: 'opt:show_student_name', checked: state.option('show_student_name'), child: const Text('Talaba ismini ko\'rsatish')),
        CheckedPopupMenuItem(value: 'opt:show_answer', checked: state.option('show_answer'), child: const Text("To'g'ri javobni ko'rsatish")),
        CheckedPopupMenuItem(value: 'opt:show_score', checked: state.option('show_score'), child: const Text("Bahoni ko'rsatish")),
      ],
    );
  }
}

/// M6 · 1 — the drum while the server picks.
class _Drawing extends StatefulWidget {
  const _Drawing({super.key});

  @override
  State<_Drawing> createState() => _DrawingState();
}

class _DrawingState extends State<_Drawing> with SingleTickerProviderStateMixin {
  late final AnimationController _spin = AnimationController(vsync: this, duration: const Duration(milliseconds: 600))..repeat();

  @override
  void dispose() {
    _spin.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          RotationTransition(
            turns: _spin,
            child: Container(
              width: 120,
              height: 120,
              decoration: BoxDecoration(color: c.accentSoft, borderRadius: BorderRadius.circular(32)),
              child: Icon(Icons.casino_outlined, size: 64, color: c.accent),
            ),
          ),
          const SizedBox(height: 24),
          Text('Tanlanmoqda…', style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 6),
          Text("Navbat, qarzdorlik va dam olish qoidalari bo'yicha", style: TextStyle(color: c.ink2)),
        ],
      ),
    );
  }
}

class _Idle extends StatelessWidget {
  const _Idle({super.key, required this.lesson, required this.last, required this.busy, required this.passScore, required this.onDraw, required this.onFinish});

  final Lesson lesson;
  final Answer? last;
  final bool busy;
  final int passScore;
  final VoidCallback onDraw;
  final VoidCallback onFinish;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final done = lesson.scored.length >= lesson.questionCount;
    final result = last;

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        if (result != null) ...[
          Card(
            color: result.isScored ? ((result.score ?? 0) >= passScore ? c.passSoft : c.warnSoft) : c.surface2,
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Row(
                children: [
                  Avatar(result.studentName, tone: result.isScored && (result.score ?? 0) >= passScore ? Tone.pass : Tone.warn),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(result.studentName, style: const TextStyle(fontWeight: FontWeight.w700)),
                        Text(switch (result.status) {
                          'absent' => "Yo'q ekan — navbatdagi o'rni saqlandi",
                          'no_answer' => "Javob bermadi — qarzdor",
                          _ => (result.score ?? 0) >= passScore ? "O'tdi — dam olish navbatiga" : "Qoniqarsiz — qayta navbatga",
                        }, style: Theme.of(context).textTheme.bodySmall),
                      ],
                    ),
                  ),
                  if (result.isScored) ScoreText(result.score, size: 28, passScore: passScore),
                ],
              ),
            ),
          ),
          const SizedBox(height: 24),
        ],
        const SizedBox(height: 24),
        Icon(done ? Icons.flag_outlined : Icons.casino_outlined, size: 72, color: c.accent),
        const SizedBox(height: 16),
        Text(
          done ? 'Barcha savollar berildi' : (lesson.scored.isEmpty ? 'Birinchi talabani tanlang' : 'Keyingi talaba'),
          textAlign: TextAlign.center,
          style: Theme.of(context).textTheme.titleLarge,
        ),
        const SizedBox(height: 6),
        Text(
          done ? 'Darsni yakunlab natijalarni ko\'ring.' : 'Random adolatli: kim so\'ralmagan va qarzdor bo\'lsa, ehtimoli yuqori.',
          textAlign: TextAlign.center,
          style: TextStyle(color: c.ink2),
        ),
        const SizedBox(height: 28),
        if (done)
          FilledButton.icon(onPressed: busy ? null : onFinish, icon: const Icon(Icons.flag), label: const Text('Darsni yakunlash'))
        else ...[
          FilledButton.icon(
            onPressed: busy ? null : onDraw,
            style: FilledButton.styleFrom(
              minimumSize: const Size.fromHeight(64),
              textStyle: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
            ),
            icon: const Icon(Icons.casino),
            label: const Text('Random'),
          ),
          const SizedBox(height: 10),
          OutlinedButton(onPressed: busy ? null : onFinish, child: const Text('Darsni yakunlash')),
        ],
      ],
    );
  }
}

/// M7 + M8 — the student, the question (answer hidden by default) and grading.
class _QuestionView extends StatefulWidget {
  const _QuestionView({
    super.key,
    required this.answer,
    required this.revealed,
    required this.busy,
    required this.passScore,
    required this.onToggle,
    required this.onGrade,
    required this.onSkip,
  });

  final Answer answer;
  final bool revealed;
  final bool busy;
  final int passScore;
  final VoidCallback onToggle;
  final Future<void> Function({int? score, String? option, String? comment}) onGrade;
  final VoidCallback onSkip;

  @override
  State<_QuestionView> createState() => _QuestionViewState();
}

class _QuestionViewState extends State<_QuestionView> {
  double _score = 60;
  bool _touched = false;
  String? _option;
  final _scoreText = TextEditingController(text: '60');

  @override
  void dispose() {
    _scoreText.dispose();
    super.dispose();
  }

  void _setScore(int value) {
    setState(() {
      _score = value.toDouble();
      _touched = true;
      _scoreText.text = '$value';
    });
  }

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final answer = widget.answer;
    final question = answer.question;
    final score = _score.round();
    final passed = score >= widget.passScore;
    final typed = int.tryParse(_scoreText.text);
    final invalid = typed == null || typed < 0 || typed > 100;

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
      children: [
        Row(
          children: [
            Avatar(answer.studentName, size: 52),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(answer.studentName, style: Theme.of(context).textTheme.titleLarge),
                  if (answer.pickReason != null) Text('Random: ${QueueState.parse(answer.pickReason).label}', style: Theme.of(context).textTheme.bodySmall),
                ],
              ),
            ),
          ],
        ),
        const SizedBox(height: 16),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Wrap(
                  spacing: 6,
                  children: [
                    Chip2(question?.isTest == true ? 'Test' : 'Ochiq savol', tone: question?.isTest == true ? Tone.info : Tone.neutral),
                    Chip2(difficultyLabels[question?.difficulty] ?? ''),
                  ],
                ),
                const SizedBox(height: 12),
                Text(question?.text ?? '', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700, height: 1.35)),
                if (question?.isTest == true) ...[
                  const SizedBox(height: 12),
                  for (final option in question!.options)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: _OptionTile(
                        option: option,
                        revealed: widget.revealed,
                        chosen: _option == option.label,
                        onTap: widget.busy ? null : () => setState(() => _option = _option == option.label ? null : option.label),
                      ),
                    ),
                ],
                const SizedBox(height: 8),
                const Divider(),
                const SizedBox(height: 8),
                if (widget.revealed && question?.isTest != true)
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(color: c.passSoft, borderRadius: BorderRadius.circular(12)),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          "To'g'ri javob",
                          style: TextStyle(color: c.pass, fontWeight: FontWeight.w800),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          question?.hasAnswer == true ? question!.answerText! : "Bu savolga namuna javob kiritilmagan.",
                          style: const TextStyle(height: 1.4),
                        ),
                      ],
                    ),
                  ),
                TextButton.icon(
                  onPressed: widget.busy ? null : widget.onToggle,
                  icon: Icon(widget.revealed ? Icons.visibility_off_outlined : Icons.visibility_outlined),
                  label: Text(widget.revealed ? 'Javobni yashirish' : "To'g'ri javobni ko'rsatish"),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 20),
        if (question?.isTest == true && _option != null) ...[
          Banner2(
            _option == question!.correctOption?.label ? "To'g'ri variant — 100 ball" : "Noto'g'ri variant — 0 ball",
            tone: _option == question.correctOption?.label ? Tone.pass : Tone.warn,
          ),
          const SizedBox(height: 12),
          FilledButton(
            onPressed: widget.busy ? null : () => widget.onGrade(option: _option),
            child: const Text('Saqlash'),
          ),
        ] else ...[
          Row(
            children: [
              Text('Baho', style: Theme.of(context).textTheme.titleMedium),
              const Spacer(),
              SizedBox(
                width: 88,
                child: TextField(
                  controller: _scoreText,
                  keyboardType: TextInputType.number,
                  textAlign: TextAlign.center,
                  inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(3)],
                  style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800, color: invalid ? c.danger : (passed ? c.pass : c.warn)),
                  decoration: InputDecoration(contentPadding: const EdgeInsets.symmetric(vertical: 8), errorText: invalid ? '0–100' : null),
                  onChanged: (value) {
                    final parsed = int.tryParse(value);
                    setState(() {
                      _touched = true;
                      if (parsed != null && parsed >= 0 && parsed <= 100) {
                        _score = parsed.toDouble();
                      }
                    });
                  },
                ),
              ),
            ],
          ),
          Stack(
            alignment: Alignment.center,
            children: [
              Slider(
                value: _score,
                max: 100,
                divisions: 100,
                label: '$score',
                activeColor: passed ? c.pass : c.warn,
                thumbColor: passed ? c.pass : c.warn,
                onChanged: widget.busy ? null : (value) => _setScore(value.round()),
              ),
            ],
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 24),
            child: Row(
              children: [
                Text('0', style: Theme.of(context).textTheme.bodySmall),
                const Spacer(),
                Text("${widget.passScore} — o'tish chegarasi", style: Theme.of(context).textTheme.bodySmall),
                const Spacer(),
                Text('100', style: Theme.of(context).textTheme.bodySmall),
              ],
            ),
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final preset in [0, 40, 60, 75, 90, 100])
                ChoiceChip(label: Text('$preset'), selected: _touched && score == preset, onSelected: widget.busy ? null : (_) => _setScore(preset)),
            ],
          ),
          const SizedBox(height: 12),
          if (_touched && !invalid)
            Text(
              passed ? "O'tdi — talaba dam olish navbatiga o'tadi" : "Qoniqarsiz — talaba qarzdor bo'lib, randomga qaytadi",
              style: TextStyle(color: passed ? c.pass : c.warn, fontWeight: FontWeight.w600),
            ),
          const SizedBox(height: 16),
          FilledButton(
            onPressed: widget.busy || invalid || !_touched ? null : () => widget.onGrade(score: score),
            child: Text(widget.busy ? 'Saqlanmoqda…' : (_touched ? '$score ball qo\'yish' : 'Bahoni tanlang')),
          ),
        ],
        const SizedBox(height: 8),
        OutlinedButton.icon(onPressed: widget.busy ? null : widget.onSkip, icon: const Icon(Icons.skip_next_outlined), label: const Text("O'tkazish")),
      ],
    );
  }
}

class _OptionTile extends StatelessWidget {
  const _OptionTile({required this.option, required this.revealed, required this.chosen, required this.onTap});

  final QuestionOption option;
  final bool revealed;
  final bool chosen;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final correct = revealed && option.isCorrect;
    final border = correct ? c.pass : (chosen ? c.accent : c.line);
    return Material(
      color: correct ? c.passSoft : (chosen ? c.accentSoft : Colors.transparent),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: BorderSide(color: border, width: chosen || correct ? 2 : 1),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Row(
            children: [
              Container(
                width: 28,
                height: 28,
                alignment: Alignment.center,
                decoration: BoxDecoration(color: correct ? c.pass : c.surface2, borderRadius: BorderRadius.circular(8)),
                child: Text(
                  option.label,
                  style: TextStyle(fontWeight: FontWeight.w800, color: correct ? c.onAccent : c.ink),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(child: Text(option.text)),
              if (chosen)
                Text(
                  'Talaba',
                  style: TextStyle(color: c.accent, fontWeight: FontWeight.w700, fontSize: 12),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

/// M9 — the lesson summary.
class _Summary extends ConsumerWidget {
  const _Summary({required this.lesson});

  final Lesson lesson;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final c = context.colors;
    final passScore = ref.watch(rulesProvider).value?.passScore ?? 60;
    final scored = [...lesson.scored]..sort((a, b) => (b.score ?? 0).compareTo(a.score ?? 0));
    final passed = scored.where((answer) => (answer.score ?? 0) >= passScore).length;

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Center(
          child: Container(
            width: 72,
            height: 72,
            decoration: BoxDecoration(color: c.passSoft, shape: BoxShape.circle),
            child: Icon(Icons.flag_rounded, color: c.pass, size: 40),
          ),
        ),
        const SizedBox(height: 12),
        Text('Dars yakunlandi', textAlign: TextAlign.center, style: Theme.of(context).textTheme.headlineSmall),
        Text(
          '${formatTime(lesson.startedAt)} — ${formatTime(lesson.finishedAt)} · ${scored.length} ta savol',
          textAlign: TextAlign.center,
          style: TextStyle(color: c.ink2),
        ),
        const SizedBox(height: 20),
        Row(
          children: [
            Expanded(
              child: Kpi(
                label: "O'rtacha",
                value: lesson.average?.toString() ?? '—',
                color: c.score(lesson.average, passScore: passScore),
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Kpi(label: "O'tdi", value: '$passed', color: c.pass),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Kpi(label: 'Qarzdor', value: '${scored.length - passed}', color: c.warn),
            ),
          ],
        ),
        const SizedBox(height: 20),
        if (scored.isEmpty)
          const Card(
            child: EmptyState(icon: Icons.inbox_outlined, title: "Bu darsda baho qo'yilmadi"),
          )
        else
          Section(
            children: [
              for (final answer in scored)
                ListTile(
                  leading: Avatar(answer.studentName, size: 36),
                  title: Text(answer.studentName, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
                  subtitle: Text(answer.question?.text ?? '', maxLines: 1, overflow: TextOverflow.ellipsis),
                  trailing: ScoreText(answer.score, size: 18, passScore: passScore),
                ),
            ],
          ),
        const SizedBox(height: 20),
        FilledButton(onPressed: () => Navigator.pop(context), child: const Text('Bosh sahifaga')),
      ],
    );
  }
}
