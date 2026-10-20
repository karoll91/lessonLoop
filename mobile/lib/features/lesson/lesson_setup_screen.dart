import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api.dart';
import '../../core/i18n.dart';
import '../../core/models.dart';
import '../../core/providers.dart';
import '../../core/theme.dart';
import '../../ui/widgets.dart';
import '../board/board_controller.dart';
import 'lesson_run_screen.dart';

/// M5 — 1) course and topics, 2) question count, 3) attendance → start.
class LessonSetupScreen extends ConsumerStatefulWidget {
  const LessonSetupScreen({super.key, this.course});

  final Course? course;

  @override
  ConsumerState<LessonSetupScreen> createState() => _LessonSetupScreenState();
}

class _LessonSetupScreenState extends ConsumerState<LessonSetupScreen> {
  int _step = 0;
  Course? _course;
  final Set<int> _topics = {};
  int? _count;
  final Set<int> _absent = {};
  bool _busy = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _course = widget.course;
  }

  int _available(List<Topic> topics) => topics.where((topic) => _topics.contains(topic.id)).fold(0, (sum, topic) => sum + topic.questionsCount);

  Future<void> _start(List<Student> students) async {
    final course = _course!;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final response = await ref.read(apiProvider).post('/courses/${course.id}/lessons', {
        'topic_ids': _topics.toList(),
        'question_count': _count,
        'absent_student_ids': _absent.toList(),
      });
      final lessonId = (response['data'] as Json)['id'] as int;
      if (ref.read(boardProvider) != null) {
        try {
          await ref.read(boardProvider.notifier).attach(lessonId);
        } on ApiException catch (error) {
          if (mounted) {
            showMessage(context, tr('Dars boshlandi, lekin doskaga ulanmadi: {e}', {'e': error.userMessage}), error: true);
          }
        }
      }
      ref.invalidate(dashboardProvider);
      if (mounted) {
        Navigator.pushReplacement(context, MaterialPageRoute<void>(builder: (_) => LessonRunScreen(lessonId: lessonId)));
      }
    } on ApiException catch (error) {
      setState(() => _error = error.userMessage);
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final titles = [tr('Fan va mavzular'), tr('Savollar soni'), tr('Kim keldi?')];
    return PopScope(
      canPop: _step == 0,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) {
          setState(() => _step--);
        }
      },
      child: Scaffold(
        appBar: AppBar(
          leading: IconButton(
            tooltip: _step == 0 ? tr('Yopish') : tr('Orqaga'),
            icon: Icon(_step == 0 ? Icons.close : Icons.arrow_back),
            onPressed: () => _step == 0 ? Navigator.pop(context) : setState(() => _step--),
          ),
          title: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(titles[_step]),
              Text(tr('{n}-qadam / 3', {'n': _step + 1}), style: Theme.of(context).textTheme.bodySmall),
            ],
          ),
          bottom: PreferredSize(
            preferredSize: const Size.fromHeight(8),
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 4),
              child: Row(
                children: [
                  for (var index = 0; index < 3; index++)
                    Expanded(
                      child: Container(
                        height: 4,
                        margin: EdgeInsets.only(right: index < 2 ? 4 : 0),
                        decoration: BoxDecoration(color: index <= _step ? context.colors.accent : context.colors.line, borderRadius: BorderRadius.circular(2)),
                      ),
                    ),
                ],
              ),
            ),
          ),
        ),
        body: switch (_step) {
          0 => _courseStep(),
          1 => _countStep(),
          _ => _attendanceStep(),
        },
      ),
    );
  }

  Widget _footer(String label, VoidCallback? onPressed) => SafeArea(
    child: Padding(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
      child: SizedBox(
        width: double.infinity,
        child: FilledButton(onPressed: onPressed, child: Text(label)),
      ),
    ),
  );

  Widget _courseStep() {
    final courses = ref.watch(coursesProvider);
    return courses.when(
      loading: () => const Loading(),
      error: (error, _) => ErrorView(error: error, onRetry: () => ref.invalidate(coursesProvider)),
      data: (list) {
        if (list.isEmpty) {
          return Center(
            child: EmptyState(icon: Icons.groups_outlined, title: tr('Kurs biriktirilmagan'), text: tr("Admin sizga guruh va fan biriktirgach dars boshlay olasiz.")),
          );
        }
        final course = _course == null ? null : list.where((item) => item.id == _course!.id).firstOrNull;
        final topics = course == null ? null : ref.watch(topicsProvider(course.subjectId));
        return Column(
          children: [
            Expanded(
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  DropdownButtonFormField<int>(
                    initialValue: course?.id,
                    isExpanded: true,
                    decoration: InputDecoration(
                      labelText: tr('Guruh va fan'),
                      helperText: course == null ? null : tr("Bu kurs bo'yicha {n}-dars bo'ladi", {'n': course.lessonsCount + 1}),
                    ),
                    items: [
                      for (final item in list)
                        DropdownMenuItem(
                          value: item.id,
                          child: Text(item.label, overflow: TextOverflow.ellipsis),
                        ),
                    ],
                    onChanged: (id) => setState(() {
                      _course = list.firstWhere((item) => item.id == id);
                      _topics.clear();
                      _absent.clear();
                    }),
                  ),
                  const SizedBox(height: 20),
                  if (topics != null)
                    ...topics.when(
                      loading: () => [const Loading()],
                      error: (error, _) => [ErrorView(error: error, onRetry: () => ref.invalidate(topicsProvider(course!.subjectId)))],
                      data: (items) => [
                        Row(
                          children: [
                            Text(tr('Mavzular'), style: Theme.of(context).textTheme.titleMedium),
                            const Spacer(),
                            Text(tr('{a} tanlandi · {b} savol', {'a': _topics.length, 'b': _available(items)}), style: Theme.of(context).textTheme.bodySmall),
                          ],
                        ),
                        const SizedBox(height: 8),
                        if (items.isEmpty)
                          Banner2(tr("Bu fanda mavzu yo'q. Mavzu va savollarni web panelda qo'shing."), tone: Tone.warn)
                        else
                          Section(
                            children: [
                              for (final topic in items)
                                CheckboxListTile(
                                  value: _topics.contains(topic.id),
                                  enabled: topic.questionsCount > 0,
                                  controlAffinity: ListTileControlAffinity.leading,
                                  title: Text('${topic.position}. ${topic.title}'),
                                  secondary: Chip2('${topic.questionsCount}', tone: _topics.contains(topic.id) ? Tone.accent : Tone.neutral),
                                  onChanged: (checked) => setState(() => checked == true ? _topics.add(topic.id) : _topics.remove(topic.id)),
                                ),
                            ],
                          ),
                      ],
                    ),
                ],
              ),
            ),
            _footer(tr('Keyingi'), course != null && _topics.isNotEmpty ? () => setState(() => _step = 1) : null),
          ],
        );
      },
    );
  }

  Widget _countStep() {
    final course = _course!;
    final topics = ref.watch(topicsProvider(course.subjectId)).value ?? const [];
    final rules = ref.watch(rulesProvider).value;
    final available = _available(topics);
    final count = _count ?? (rules?.defaultQuestionCount ?? 8).clamp(1, available == 0 ? 1 : available);
    final tooMany = count > available;
    final c = context.colors;

    return Column(
      children: [
        Expanded(
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Text(tr('Bu darsda nechta savol beriladi?'), style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 16),
              Container(
                decoration: BoxDecoration(
                  color: c.surface,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: tooMany ? c.danger : c.line, width: tooMany ? 2 : 1),
                ),
                child: Row(
                  children: [
                    IconButton(
                      iconSize: 32,
                      tooltip: tr('Kamaytirish'),
                      onPressed: count > 1 ? () => setState(() => _count = count - 1) : null,
                      icon: const Icon(Icons.remove),
                    ),
                    Expanded(
                      child: Text(
                        '$count',
                        textAlign: TextAlign.center,
                        style: TextStyle(fontSize: 40, fontWeight: FontWeight.w800, color: tooMany ? c.danger : c.ink),
                      ),
                    ),
                    IconButton(
                      iconSize: 32,
                      tooltip: tr("Ko'paytirish"),
                      onPressed: count < available ? () => setState(() => _count = count + 1) : null,
                      icon: const Icon(Icons.add),
                    ),
                  ],
                ),
              ),
              if (tooMany) ...[
                const SizedBox(height: 8),
                Text(tr("Tanlangan mavzularda faqat {n} ta savol bor. Mavzu qo'shing yoki sonni kamaytiring.", {'n': available}), style: TextStyle(color: c.danger)),
              ],
              const SizedBox(height: 16),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  for (final preset in {5, 8, 10, 15}.where((value) => value <= available))
                    ChoiceChip(label: Text('$preset'), selected: count == preset, onSelected: (_) => setState(() => _count = preset)),
                  ChoiceChip(label: Text(tr('Hammasi ({n})', {'n': available})), selected: count == available, onSelected: (_) => setState(() => _count = available)),
                ],
              ),
              const SizedBox(height: 20),
              Text(tr('Oxirgi {n} darsda berilgan savollar oxiriga suriladi.', {'n': rules?.cooldownLessons ?? 4}), style: Theme.of(context).textTheme.bodySmall),
            ],
          ),
        ),
        _footer(
          tr('Keyingi'),
          tooMany || available == 0
              ? null
              : () => setState(() {
                  _count = count;
                  _step = 2;
                }),
        ),
      ],
    );
  }

  Widget _attendanceStep() {
    final course = _course!;
    final students = ref.watch(courseStudentsProvider((course.groupId, course.id)));

    return students.when(
      loading: () => const Loading(),
      error: (error, _) => ErrorView(error: error, onRetry: () => ref.invalidate(courseStudentsProvider((course.groupId, course.id)))),
      data: (list) {
        final present = list.where((student) => !_absent.contains(student.id)).toList();
        final inPool = present.where((student) => student.queue?.state != QueueState.resting).length;
        return Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
              child: Row(
                children: [
                  Text(tr('{a} / {b} keldi', {'a': present.length, 'b': list.length}), style: const TextStyle(fontWeight: FontWeight.w700)),
                  const Spacer(),
                  TextButton(onPressed: () => setState(_absent.clear), child: Text(tr('Hammasi keldi'))),
                ],
              ),
            ),
            if (_error != null)
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Banner2(_error!, tone: Tone.danger),
              ),
            if (inPool < (_count ?? 0))
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
                child: Banner2(tr("Randomga {a} ta talaba tushadi, savol {b} ta. Dam olish muddati eng oldin tugaydiganlar qo'shiladi.", {'a': inPool, 'b': _count}), tone: Tone.warn),
              ),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  Section(
                    children: [
                      for (final student in list)
                        CheckboxListTile(
                          value: !_absent.contains(student.id),
                          controlAffinity: ListTileControlAffinity.leading,
                          tileColor: _absent.contains(student.id) ? context.colors.dangerSoft : null,
                          title: Text(student.fullName, style: const TextStyle(fontWeight: FontWeight.w600)),
                          secondary: _absent.contains(student.id) ? Chip2(tr('Kelmagan'), tone: Tone.danger) : QueueChip(student.queue),
                          onChanged: (checked) => setState(() => checked == true ? _absent.remove(student.id) : _absent.add(student.id)),
                        ),
                    ],
                  ),
                ],
              ),
            ),
            _footer(_busy ? tr('Boshlanmoqda…') : tr('Darsni boshlash'), _busy || present.isEmpty ? null : () => _start(list)),
          ],
        );
      },
    );
  }
}

class QueueChip extends StatelessWidget {
  const QueueChip(this.queue, {super.key});

  final QueueInfo? queue;

  @override
  Widget build(BuildContext context) {
    final info = queue;
    if (info == null) {
      return const SizedBox.shrink();
    }
    return switch (info.state) {
      QueueState.neverAsked => Chip2(tr("So'ralmagan ×2"), tone: Tone.accent),
      QueueState.debtor => Chip2(tr('Qarzdor ×2'), tone: Tone.warn),
      QueueState.resting => Chip2(tr('Dam · {n} dars', {'n': info.restLessonsLeft}), tone: Tone.info),
      QueueState.queue => Chip2(tr('Navbatda')),
    };
  }
}
