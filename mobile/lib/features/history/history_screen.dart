import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api.dart';
import '../../core/models.dart';
import '../../core/providers.dart';
import '../../core/theme.dart';
import '../../ui/widgets.dart';
import '../lesson/lesson_run_screen.dart';

final _lessonsProvider = FutureProvider.autoDispose.family<List<Lesson>, int?>((ref, courseId) async {
  final response = await ref.watch(apiProvider).get('/lessons', {'course_id': courseId, 'per_page': 50});
  return (response['data'] as List).cast<Json>().map(Lesson.fromJson).toList();
});

final _lessonProvider = FutureProvider.autoDispose.family<Lesson, int>((ref, id) async {
  final response = await ref.watch(apiProvider).get('/lessons/$id');
  return Lesson.fromJson(response['data'] as Json);
});

/// M12 — past lessons, filtered by course.
class HistoryScreen extends ConsumerStatefulWidget {
  const HistoryScreen({super.key});

  @override
  ConsumerState<HistoryScreen> createState() => _HistoryScreenState();
}

class _HistoryScreenState extends ConsumerState<HistoryScreen> {
  int? _courseId;

  @override
  Widget build(BuildContext context) {
    final courses = ref.watch(coursesProvider).value ?? const <Course>[];
    final lessons = ref.watch(_lessonsProvider(_courseId));

    return Scaffold(
      appBar: AppBar(title: const Text('Darslar tarixi')),
      body: Column(
        children: [
          if (courses.length > 1)
            SizedBox(
              height: 48,
              child: ListView(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 16),
                children: [
                  ChoiceChip(label: const Text('Hammasi'), selected: _courseId == null, onSelected: (_) => setState(() => _courseId = null)),
                  for (final course in courses) ...[
                    const SizedBox(width: 8),
                    ChoiceChip(label: Text(course.label), selected: _courseId == course.id, onSelected: (_) => setState(() => _courseId = course.id)),
                  ],
                ],
              ),
            ),
          Expanded(
            child: lessons.when(
              loading: () => const Loading(),
              error: (error, _) => ErrorView(error: error, onRetry: () => ref.invalidate(_lessonsProvider(_courseId))),
              data: (items) {
                if (items.isEmpty) {
                  return Center(
                    child: EmptyState(
                      icon: Icons.history,
                      title: _courseId == null ? "Hali dars o'tilmagan" : "Bu kurs bo'yicha dars yo'q",
                      text: _courseId == null ? 'Birinchi darsni «Bugun» bo\'limidan boshlang.' : null,
                    ),
                  );
                }
                return RefreshIndicator(
                  onRefresh: () => ref.refresh(_lessonsProvider(_courseId).future),
                  child: ListView.separated(
                    padding: const EdgeInsets.all(16),
                    itemCount: items.length,
                    separatorBuilder: (_, _) => const SizedBox(height: 8),
                    itemBuilder: (context, index) {
                      final lesson = items[index];
                      return Card(
                        child: ListTile(
                          contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
                          title: Text('${lesson.group} · ${lesson.subject}', style: const TextStyle(fontWeight: FontWeight.w700)),
                          subtitle: Text(
                            '${formatDate(lesson.startedAt)} · ${lesson.sequence}-dars · ${lesson.askedCount ?? 0} / ${lesson.questionCount} savol',
                          ),
                          trailing: lesson.isFinished ? ScoreText(lesson.averageScore, size: 20) : const Chip2('Faol', tone: Tone.accent),
                          onTap: () async {
                            await Navigator.push(
                              context,
                              MaterialPageRoute<void>(
                                builder: (_) => lesson.isFinished ? LessonDetailScreen(lessonId: lesson.id) : LessonRunScreen(lessonId: lesson.id),
                              ),
                            );
                            ref.invalidate(_lessonsProvider);
                          },
                        ),
                      );
                    },
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

/// M12 · 2 — every answer of a finished lesson; regrade within the edit window.
class LessonDetailScreen extends ConsumerWidget {
  const LessonDetailScreen({super.key, required this.lessonId});

  final int lessonId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final lesson = ref.watch(_lessonProvider(lessonId));
    final passScore = ref.watch(rulesProvider).value?.passScore ?? 60;
    final now = DateTime.now();

    return Scaffold(
      appBar: AppBar(title: Text(lesson.value == null ? 'Dars' : '${lesson.value!.group} · ${lesson.value!.sequence}-dars')),
      body: lesson.when(
        loading: () => const Loading(),
        error: (error, _) => ErrorView(error: error, onRetry: () => ref.invalidate(_lessonProvider(lessonId))),
        data: (data) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text(
              '${data.subject} · ${formatDate(data.startedAt)} ${formatTime(data.startedAt)}–${formatTime(data.finishedAt)}',
              style: Theme.of(context).textTheme.bodySmall,
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: Kpi(label: 'Savollar', value: '${data.scored.length} / ${data.questionCount}'),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Kpi(
                    label: "O'rtacha",
                    value: data.average?.toString() ?? '—',
                    color: context.colors.score(data.average, passScore: passScore),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            if (data.answers.isEmpty)
              const Card(
                child: EmptyState(icon: Icons.inbox_outlined, title: "Bu darsda hech kim so'ralmagan"),
              )
            else
              Section(
                children: [
                  for (final answer in data.answers)
                    ListTile(
                      title: Text(answer.studentName, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
                      subtitle: Text(
                        '${answer.question?.text ?? ''}\n${answerStatusLabels[answer.status]}${answer.edits.isEmpty ? '' : ' · ${answer.edits.length} marta tuzatilgan'}',
                        maxLines: 3,
                        overflow: TextOverflow.ellipsis,
                      ),
                      isThreeLine: true,
                      trailing: answer.isScored ? ScoreText(answer.score, size: 18, passScore: passScore) : null,
                      onTap: answer.isScored ? () => _openAnswer(context, ref, answer, now) : null,
                    ),
                ],
              ),
          ],
        ),
      ),
    );
  }

  Future<void> _openAnswer(BuildContext context, WidgetRef ref, Answer answer, DateTime now) async {
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (_) => RegradeSheet(answer: answer, editable: answer.editableAt(now)),
    );
    if (saved == true) {
      ref.invalidate(_lessonProvider(lessonId));
      ref.invalidate(dashboardProvider);
    }
  }
}

class RegradeSheet extends ConsumerStatefulWidget {
  const RegradeSheet({super.key, required this.answer, required this.editable});

  final Answer answer;
  final bool editable;

  @override
  ConsumerState<RegradeSheet> createState() => _RegradeSheetState();
}

class _RegradeSheetState extends ConsumerState<RegradeSheet> {
  late final _score = TextEditingController(text: widget.answer.score?.toString() ?? '');
  final _reason = TextEditingController();
  bool _busy = false;
  ApiException? _error;

  @override
  void dispose() {
    _score.dispose();
    _reason.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await ref.read(apiProvider).patch('/answers/${widget.answer.id}', {'score': int.parse(_score.text), 'reason': _reason.text.trim()});
      if (mounted) {
        showMessage(context, 'Baho tuzatildi');
        Navigator.pop(context, true);
      }
    } on ApiException catch (error) {
      setState(() => _error = error);
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final answer = widget.answer;
    final value = int.tryParse(_score.text);
    final valid = value != null && value >= 0 && value <= 100 && _reason.text.trim().length >= 3;

    return Padding(
      padding: EdgeInsets.fromLTRB(20, 0, 20, 20 + MediaQuery.viewInsetsOf(context).bottom),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(answer.studentName, style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 4),
          Text(answer.question?.text ?? '', style: Theme.of(context).textTheme.bodySmall),
          const SizedBox(height: 12),
          if (answer.edits.isNotEmpty) ...[
            Text('Tuzatishlar tarixi', style: Theme.of(context).textTheme.titleSmall),
            for (final edit in answer.edits)
              Padding(
                padding: const EdgeInsets.only(top: 4),
                child: Text(
                  '${formatDateTime(edit.at)} · ${edit.by ?? ''}: ${edit.oldScore ?? '—'} → ${edit.newScore ?? '—'} «${edit.reason}»',
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ),
            const SizedBox(height: 12),
          ],
          if (!widget.editable)
            const Banner2("Tuzatish muddati tugagan. Kerak bo'lsa admin bilan bog'laning.", tone: Tone.neutral, icon: Icons.lock_outline)
          else ...[
            Text('${formatDateTime(answer.editableUntil)} gacha tuzatish mumkin', style: Theme.of(context).textTheme.bodySmall),
            const SizedBox(height: 12),
            TextField(
              controller: _score,
              keyboardType: TextInputType.number,
              inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(3)],
              onChanged: (_) => setState(() {}),
              decoration: InputDecoration(
                labelText: 'Yangi ball (0–100)',
                errorText: _error?.fieldError('score') ?? (value != null && value > 100 ? '0 dan 100 gacha' : null),
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _reason,
              onChanged: (_) => setState(() {}),
              decoration: InputDecoration(labelText: 'Sabab *', hintText: "Javobni qayta ko'rib chiqdim", errorText: _error?.fieldError('reason')),
            ),
            if (_error != null && _error!.errors.isEmpty) ...[const SizedBox(height: 12), Banner2(_error!.userMessage, tone: Tone.danger)],
            const SizedBox(height: 16),
            FilledButton(onPressed: valid && !_busy ? _save : null, child: Text(_busy ? 'Saqlanmoqda…' : 'Saqlash')),
          ],
        ],
      ),
    );
  }
}
