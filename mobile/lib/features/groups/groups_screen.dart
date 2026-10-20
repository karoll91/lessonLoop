import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api.dart';
import '../../core/models.dart';
import '../../core/providers.dart';
import '../../core/theme.dart';
import '../../ui/widgets.dart';
import '../lesson/lesson_setup_screen.dart';

/// M4 — the teacher's courses, grouped by study group.
class GroupsScreen extends ConsumerStatefulWidget {
  const GroupsScreen({super.key});

  @override
  ConsumerState<GroupsScreen> createState() => _GroupsScreenState();
}

class _GroupsScreenState extends ConsumerState<GroupsScreen> {
  String _search = '';

  @override
  Widget build(BuildContext context) {
    final courses = ref.watch(coursesProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Guruhlar')),
      body: courses.when(
        loading: () => const Loading(),
        error: (error, _) => ErrorView(error: error, onRetry: () => ref.invalidate(coursesProvider)),
        data: (list) {
          final filtered = list.where((course) => course.label.toLowerCase().contains(_search.toLowerCase())).toList();
          return RefreshIndicator(
            onRefresh: () => ref.refresh(coursesProvider.future),
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
              children: [
                TextField(
                  onChanged: (value) => setState(() => _search = value),
                  decoration: const InputDecoration(prefixIcon: Icon(Icons.search), hintText: 'Guruh yoki fan'),
                ),
                const SizedBox(height: 12),
                if (list.isEmpty)
                  const EmptyState(
                    icon: Icons.groups_outlined,
                    title: "Hali guruh yo'q",
                    text: "Guruh va fanlarni admin web panelda biriktiradi. Keyin ular shu yerda chiqadi.",
                  )
                else if (filtered.isEmpty)
                  EmptyState(icon: Icons.search_off, title: '«$_search» topilmadi', text: 'Guruh yoki fan nomini tekshiring.')
                else
                  for (final course in filtered) ...[
                    Card(
                      child: ListTile(
                        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
                        leading: Avatar(course.groupName),
                        title: Text(course.groupName, style: Theme.of(context).textTheme.titleMedium),
                        subtitle: Text('${course.subjectName} · ${course.lessonsCount} dars'),
                        trailing: const Icon(Icons.chevron_right),
                        onTap: () => Navigator.push(context, MaterialPageRoute<void>(builder: (_) => CourseScreen(course: course))),
                      ),
                    ),
                    const SizedBox(height: 10),
                  ],
              ],
            ),
          );
        },
      ),
    );
  }
}

/// M4 · 2 + M10 — students of a course with their queue state.
class CourseScreen extends ConsumerStatefulWidget {
  const CourseScreen({super.key, required this.course});

  final Course course;

  @override
  ConsumerState<CourseScreen> createState() => _CourseScreenState();
}

class _CourseScreenState extends ConsumerState<CourseScreen> {
  QueueState? _filter;

  @override
  Widget build(BuildContext context) {
    final course = widget.course;
    final key = (course.groupId, course.id);
    final students = ref.watch(courseStudentsProvider(key));

    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(course.groupName),
            Text(course.subjectName, style: Theme.of(context).textTheme.bodySmall),
          ],
        ),
      ),
      bottomNavigationBar: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
          child: FilledButton.icon(
            onPressed: () => Navigator.push(context, MaterialPageRoute<void>(builder: (_) => LessonSetupScreen(course: course))),
            icon: const Icon(Icons.play_arrow_rounded),
            label: const Text('Shu guruh bilan dars boshlash'),
          ),
        ),
      ),
      body: students.when(
        loading: () => const Loading(),
        error: (error, _) => ErrorView(error: error, onRetry: () => ref.invalidate(courseStudentsProvider(key))),
        data: (list) {
          final counts = {for (final state in QueueState.values) state: list.where((student) => student.queue?.state == state).length};
          final shown = _filter == null ? list : list.where((student) => student.queue?.state == _filter).toList();
          return RefreshIndicator(
            onRefresh: () => ref.refresh(courseStudentsProvider(key).future),
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
              children: [
                SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  child: Row(
                    children: [
                      ChoiceChip(label: Text('Hammasi ${list.length}'), selected: _filter == null, onSelected: (_) => setState(() => _filter = null)),
                      for (final state in QueueState.values) ...[
                        const SizedBox(width: 8),
                        ChoiceChip(
                          label: Text('${state.label} ${counts[state]}'),
                          selected: _filter == state,
                          onSelected: (_) => setState(() => _filter = state),
                        ),
                      ],
                    ],
                  ),
                ),
                const SizedBox(height: 12),
                if (list.isEmpty)
                  const EmptyState(icon: Icons.person_outline, title: "Guruhda talaba yo'q", text: "Talabalarni web panelda import qiling.")
                else if (shown.isEmpty)
                  EmptyState(icon: Icons.filter_alt_off_outlined, title: "«${_filter!.label}» holatida talaba yo'q")
                else
                  Section(
                    children: [
                      for (final student in shown)
                        ListTile(
                          leading: Avatar(student.fullName, size: 36, tone: _tone(student.queue?.state)),
                          title: Text(student.fullName, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
                          subtitle: Text(_subtitle(student.queue)),
                          trailing: QueueChip(student.queue),
                          onTap: () => showStudentCard(context, course, student),
                        ),
                    ],
                  ),
              ],
            ),
          );
        },
      ),
    );
  }
}

Tone _tone(QueueState? state) => switch (state) {
  QueueState.debtor => Tone.warn,
  QueueState.resting => Tone.info,
  QueueState.neverAsked => Tone.accent,
  _ => Tone.neutral,
};

String _subtitle(QueueInfo? queue) {
  if (queue == null || queue.askedCount == 0) {
    return "Hech so'ralmagan";
  }
  return 'Oxirgi: ${queue.lastScore ?? '—'} · ${queue.lastSequence ?? '?'}-dars · ${queue.askedCount} marta';
}

/// M10 · 2–3 — one student's queue position and scores in this course.
Future<void> showStudentCard(BuildContext context, Course course, Student student) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    builder: (context) => _StudentCard(course: course, student: student),
  );
}

class _StudentCard extends ConsumerWidget {
  const _StudentCard({required this.course, required this.student});

  final Course course;
  final Student student;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final c = context.colors;
    final queue = student.queue;
    final rules = ref.watch(rulesProvider).value;
    final cooldown = rules?.cooldownLessons ?? 4;
    final journal = ref.watch(_journalProvider(course.id));

    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Avatar(student.fullName, size: 52, tone: _tone(queue?.state)),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(student.fullName, style: Theme.of(context).textTheme.titleLarge),
                      Text(course.label, style: Theme.of(context).textTheme.bodySmall),
                    ],
                  ),
                ),
                QueueChip(queue),
              ],
            ),
            const SizedBox(height: 16),
            if (queue?.state == QueueState.resting) ...[
              Text('Dam olish: ${cooldown - queue!.restLessonsLeft} / $cooldown dars o\'tdi', style: const TextStyle(fontWeight: FontWeight.w700)),
              const SizedBox(height: 8),
              ClipRRect(
                borderRadius: BorderRadius.circular(4),
                child: LinearProgressIndicator(value: (cooldown - queue.restLessonsLeft) / cooldown, minHeight: 8, backgroundColor: c.line, color: c.info),
              ),
              const SizedBox(height: 6),
              Text('Yana ${queue.restLessonsLeft} darsdan keyin randomga qaytadi.', style: Theme.of(context).textTheme.bodySmall),
            ] else if (queue?.state == QueueState.debtor)
              Banner2('Oxirgi baho ${queue!.lastScore ?? 0} — keyingi darslarda tanlanish ehtimoli yuqori.', tone: Tone.warn)
            else if (queue?.state == QueueState.neverAsked)
              const Banner2("Hali so'ralmagan — tanlanish ehtimoli yuqori.", tone: Tone.accent),
            const SizedBox(height: 16),
            Text('Baholar', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 8),
            journal.when(
              loading: () => const Loading(),
              error: (error, _) => Text(errorText(error), style: TextStyle(color: c.danger)),
              data: (data) {
                final row = (data['rows'] as List).cast<Json>().where((row) => row['student_id'] == student.id).firstOrNull;
                final lessons = (data['lessons'] as List).cast<Json>();
                final cells = ((row?['cells'] as List?) ?? const []).cast<Json>().where((cell) => cell['status'] != 'not_asked').toList();
                if (cells.isEmpty) {
                  return Text("Bu fandan hali baho yo'q.", style: TextStyle(color: c.ink2));
                }
                return Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    for (final cell in cells)
                      Chip2(
                        '${lessons.firstWhere((lesson) => lesson['id'] == cell['lesson_id'], orElse: () => {'sequence': '?'})['sequence']}-dars: '
                        '${cell['status'] == 'absent' ? "yo'q" : (cell['score'] ?? '—')}',
                        tone: cell['status'] == 'absent' ? Tone.neutral : ((cell['score'] as int? ?? 0) >= (rules?.passScore ?? 60) ? Tone.pass : Tone.warn),
                      ),
                  ],
                );
              },
            ),
          ],
        ),
      ),
    );
  }
}

final _journalProvider = FutureProvider.autoDispose.family<Json, int>((ref, courseId) async {
  final response = await ref.watch(apiProvider).get('/courses/$courseId/journal');
  return response['data'] as Json;
});
