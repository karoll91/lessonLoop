import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api.dart';
import '../../core/models.dart';
import '../../core/providers.dart';
import '../../core/theme.dart';
import '../../ui/widgets.dart';

/// Subjects the teacher teaches, taken from their courses.
final _subjectsProvider = FutureProvider.autoDispose<List<(int, String)>>((ref) async {
  final courses = await ref.watch(coursesProvider.future);
  final seen = <int, String>{};
  for (final course in courses) {
    seen.putIfAbsent(course.subjectId, () => course.subjectName);
  }
  return seen.entries.map((entry) => (entry.key, entry.value)).toList();
});

typedef _QuestionQuery = ({int subjectId, int? topicId, String search});

final _questionsProvider = FutureProvider.autoDispose.family<List<Question>, _QuestionQuery>((ref, query) async {
  final api = ref.watch(apiProvider);
  final path = query.topicId == null ? '/subjects/${query.subjectId}/questions' : '/topics/${query.topicId}/questions';
  final response = await api.get(path, {'q': query.search, 'per_page': 100});
  return (response['data'] as List).cast<Json>().map(Question.fromJson).toList();
});

/// M11 — questions of a subject by topic, quick add on the phone.
class QuestionsScreen extends ConsumerStatefulWidget {
  const QuestionsScreen({super.key});

  @override
  ConsumerState<QuestionsScreen> createState() => _QuestionsScreenState();
}

class _QuestionsScreenState extends ConsumerState<QuestionsScreen> {
  int? _subjectId;
  int? _topicId;
  String _search = '';

  @override
  Widget build(BuildContext context) {
    final subjects = ref.watch(_subjectsProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Savollar')),
      body: subjects.when(
        loading: () => const Loading(),
        error: (error, _) => ErrorView(error: error, onRetry: () => ref.invalidate(coursesProvider)),
        data: (list) {
          if (list.isEmpty) {
            return const Center(
              child: EmptyState(icon: Icons.quiz_outlined, title: "Fan yo'q", text: 'Sizga fan biriktirilgach savollar shu yerda chiqadi.'),
            );
          }
          final subjectId = _subjectId ?? list.first.$1;
          final topics = ref.watch(topicsProvider(subjectId));
          final query = (subjectId: subjectId, topicId: _topicId, search: _search);
          final questions = ref.watch(_questionsProvider(query));
          final topicList = topics.value ?? const <Topic>[];

          return Scaffold(
            floatingActionButton: topicList.isEmpty
                ? null
                : FloatingActionButton(
                    tooltip: 'Yangi savol',
                    onPressed: () async {
                      final saved = await Navigator.push<bool>(
                        context,
                        MaterialPageRoute(
                          builder: (_) => QuestionFormScreen(topics: topicList, topicId: _topicId ?? topicList.first.id),
                        ),
                      );
                      if (saved == true) {
                        ref.invalidate(_questionsProvider);
                        ref.invalidate(topicsProvider(subjectId));
                      }
                    },
                    child: const Icon(Icons.add),
                  ),
            body: Column(
              children: [
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
                  child: Column(
                    children: [
                      if (list.length > 1)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 8),
                          child: DropdownButtonFormField<int>(
                            initialValue: subjectId,
                            isExpanded: true,
                            decoration: const InputDecoration(labelText: 'Fan'),
                            items: [for (final (id, name) in list) DropdownMenuItem(value: id, child: Text(name))],
                            onChanged: (id) => setState(() {
                              _subjectId = id;
                              _topicId = null;
                            }),
                          ),
                        ),
                      TextField(
                        onChanged: (value) => setState(() => _search = value.trim()),
                        decoration: const InputDecoration(prefixIcon: Icon(Icons.search), hintText: 'Savol matni'),
                      ),
                      const SizedBox(height: 8),
                      SingleChildScrollView(
                        scrollDirection: Axis.horizontal,
                        child: Row(
                          children: [
                            ChoiceChip(label: const Text('Barchasi'), selected: _topicId == null, onSelected: (_) => setState(() => _topicId = null)),
                            for (final topic in topicList) ...[
                              const SizedBox(width: 8),
                              ChoiceChip(
                                label: Text('${topic.position}. ${topic.title} · ${topic.questionsCount}'),
                                selected: _topicId == topic.id,
                                onSelected: (_) => setState(() => _topicId = topic.id),
                              ),
                            ],
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
                Expanded(
                  child: questions.when(
                    loading: () => const Loading(),
                    error: (error, _) => ErrorView(error: error, onRetry: () => ref.invalidate(_questionsProvider(query))),
                    data: (items) {
                      if (topicList.isEmpty && topics.hasValue) {
                        return const Center(
                          child: EmptyState(icon: Icons.topic_outlined, title: "Fanda mavzu yo'q", text: "Mavzularni web panelda qo'shing."),
                        );
                      }
                      if (items.isEmpty) {
                        return Center(
                          child: EmptyState(
                            icon: _search.isEmpty ? Icons.quiz_outlined : Icons.search_off,
                            title: _search.isEmpty ? "Hali savol yo'q" : '«$_search» topilmadi',
                            text: _search.isEmpty ? "Pastdagi + tugmasi bilan qo'shing yoki web panelda Excel'dan import qiling." : null,
                          ),
                        );
                      }
                      return RefreshIndicator(
                        onRefresh: () => ref.refresh(_questionsProvider(query).future),
                        child: ListView.separated(
                          padding: const EdgeInsets.fromLTRB(16, 4, 16, 96),
                          itemCount: items.length,
                          separatorBuilder: (_, _) => const SizedBox(height: 8),
                          itemBuilder: (context, index) => _QuestionCard(question: items[index], topics: topicList),
                        ),
                      );
                    },
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _QuestionCard extends StatelessWidget {
  const _QuestionCard({required this.question, required this.topics});

  final Question question;
  final List<Topic> topics;

  @override
  Widget build(BuildContext context) {
    final topic = topics.where((item) => item.id == question.topicId).firstOrNull;
    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: () => showModalBottomSheet<void>(
          context: context,
          isScrollControlled: true,
          builder: (_) => _QuestionDetail(question: question, topic: topic),
        ),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Wrap(
                spacing: 6,
                runSpacing: 6,
                children: [
                  if (topic != null) Chip2('${topic.position}-mavzu'),
                  Chip2(question.isTest ? 'Test' : 'Ochiq', tone: question.isTest ? Tone.info : Tone.neutral),
                  Chip2(difficultyLabels[question.difficulty] ?? ''),
                  if (!question.hasAnswer) const Chip2('Javobsiz', tone: Tone.warn),
                ],
              ),
              const SizedBox(height: 8),
              Text(question.text, style: const TextStyle(fontWeight: FontWeight.w600, height: 1.35)),
              const SizedBox(height: 6),
              Row(
                children: [
                  Text("${question.timesAsked ?? 0} marta so'ralgan", style: Theme.of(context).textTheme.bodySmall),
                  const Spacer(),
                  if (question.averageScore != null) ...[
                    Text("o'rtacha ", style: Theme.of(context).textTheme.bodySmall),
                    ScoreText(question.averageScore, size: 13),
                  ],
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _QuestionDetail extends StatelessWidget {
  const _QuestionDetail({required this.question, required this.topic});

  final Question question;
  final Topic? topic;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return SafeArea(
      child: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (topic != null) Text('${topic!.position}. ${topic!.title}', style: Theme.of(context).textTheme.bodySmall),
            const SizedBox(height: 6),
            Text(question.text, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700, height: 1.35)),
            const SizedBox(height: 16),
            if (question.isTest)
              for (final option in question.options)
                Padding(
                  padding: const EdgeInsets.only(bottom: 6),
                  child: Text(
                    '${option.label}) ${option.text}${option.isCorrect ? '  ✓' : ''}',
                    style: TextStyle(color: option.isCorrect ? c.pass : c.ink, fontWeight: option.isCorrect ? FontWeight.w700 : FontWeight.w400),
                  ),
                ),
            if (question.answerText?.isNotEmpty ?? false)
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(color: c.passSoft, borderRadius: BorderRadius.circular(12)),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      question.isTest ? 'Izoh' : "To'g'ri javob",
                      style: TextStyle(color: c.pass, fontWeight: FontWeight.w800),
                    ),
                    const SizedBox(height: 4),
                    Text(question.answerText!),
                  ],
                ),
              )
            else if (!question.isTest)
              const Banner2('Namuna javob kiritilmagan — darsda doskada ko\'rsatib bo\'lmaydi.', tone: Tone.warn),
            const SizedBox(height: 16),
            Row(
              children: [
                Expanded(
                  child: Kpi(label: "So'ralgan", value: '${question.timesAsked ?? 0}'),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Kpi(label: "O'rtacha ball", value: question.averageScore?.toString() ?? '—', color: c.score(question.averageScore)),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

/// M11 · 4 — a new question with validation (open or A–D test).
class QuestionFormScreen extends ConsumerStatefulWidget {
  const QuestionFormScreen({super.key, required this.topics, required this.topicId});

  final List<Topic> topics;
  final int topicId;

  @override
  ConsumerState<QuestionFormScreen> createState() => _QuestionFormScreenState();
}

class _QuestionFormScreenState extends ConsumerState<QuestionFormScreen> {
  late int _topicId = widget.topicId;
  String _type = 'open';
  String _difficulty = 'medium';
  final _text = TextEditingController();
  final _answer = TextEditingController();
  final _options = [for (var index = 0; index < 4; index++) TextEditingController()];
  int _correct = 0;
  bool _busy = false;
  ApiException? _error;

  @override
  void dispose() {
    _text.dispose();
    _answer.dispose();
    for (final controller in _options) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _save() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    const labels = ['A', 'B', 'C', 'D'];
    try {
      await ref.read(apiProvider).post('/topics/$_topicId/questions', {
        'type': _type,
        'difficulty': _difficulty,
        'text': _text.text.trim(),
        'answer_text': _answer.text.trim().isEmpty ? null : _answer.text.trim(),
        if (_type == 'test')
          'options': [
            for (var index = 0; index < 4; index++)
              if (_options[index].text.trim().isNotEmpty) {'label': labels[index], 'text': _options[index].text.trim(), 'is_correct': index == _correct},
          ],
      });
      if (mounted) {
        showMessage(context, "Savol qo'shildi");
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
    final optionsError =
        _error?.fieldError('options') ?? (_error?.errors.keys.any((key) => key.startsWith('options.')) == true ? "Kamida 2 ta variantni to'ldiring" : null);
    final known = ['text', 'answer_text', 'options', 'type', 'difficulty'];
    final otherError = _error != null && !known.any((key) => _error!.fieldError(key) != null) && optionsError == null;

    return Scaffold(
      appBar: AppBar(title: const Text('Yangi savol')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          DropdownButtonFormField<int>(
            initialValue: _topicId,
            isExpanded: true,
            decoration: const InputDecoration(labelText: 'Mavzu'),
            items: [for (final topic in widget.topics) DropdownMenuItem(value: topic.id, child: Text('${topic.position}. ${topic.title}'))],
            onChanged: (id) => setState(() => _topicId = id!),
          ),
          const SizedBox(height: 14),
          SegmentedButton<String>(
            segments: const [
              ButtonSegment(value: 'open', label: Text('Ochiq')),
              ButtonSegment(value: 'test', label: Text('Test (A–D)')),
            ],
            selected: {_type},
            showSelectedIcon: false,
            onSelectionChanged: (value) => setState(() => _type = value.first),
          ),
          const SizedBox(height: 10),
          SegmentedButton<String>(
            segments: [for (final entry in difficultyLabels.entries) ButtonSegment(value: entry.key, label: Text(entry.value))],
            selected: {_difficulty},
            showSelectedIcon: false,
            onSelectionChanged: (value) => setState(() => _difficulty = value.first),
          ),
          const SizedBox(height: 14),
          TextField(
            controller: _text,
            minLines: 2,
            maxLines: 5,
            onChanged: (_) => setState(() {}),
            decoration: InputDecoration(labelText: 'Savol matni *', errorText: _error?.fieldError('text')),
          ),
          if (_type == 'test') ...[
            const SizedBox(height: 14),
            Text("Variantlar — to'g'risini belgilang", style: Theme.of(context).textTheme.titleSmall),
            RadioGroup<int>(
              groupValue: _correct,
              onChanged: (value) => setState(() => _correct = value ?? 0),
              child: Column(
                children: [
                  for (var index = 0; index < 4; index++)
                    Padding(
                      padding: const EdgeInsets.only(top: 8),
                      child: Row(
                        children: [
                          Radio<int>(value: index),
                          Expanded(
                            child: TextField(
                              controller: _options[index],
                              decoration: InputDecoration(labelText: '${['A', 'B', 'C', 'D'][index]} varianti', hintText: index < 2 ? 'Majburiy' : 'Ixtiyoriy'),
                            ),
                          ),
                        ],
                      ),
                    ),
                ],
              ),
            ),
            if (optionsError != null)
              Padding(
                padding: const EdgeInsets.only(top: 6),
                child: Text(optionsError, style: TextStyle(color: context.colors.danger)),
              ),
          ],
          const SizedBox(height: 14),
          TextField(
            controller: _answer,
            minLines: 2,
            maxLines: 6,
            decoration: InputDecoration(
              labelText: _type == 'test' ? 'Izoh (ixtiyoriy)' : "To'g'ri javob",
              helperText: _type == 'open' ? "Darsda «Javobni ko'rsatish»da chiqadi" : null,
              errorText: _error?.fieldError('answer_text'),
            ),
          ),
          if (otherError) ...[const SizedBox(height: 12), Banner2(_error!.userMessage, tone: Tone.danger)],
          const SizedBox(height: 20),
          FilledButton(onPressed: _busy || _text.text.trim().isEmpty ? null : _save, child: Text(_busy ? 'Saqlanmoqda…' : 'Saqlash')),
        ],
      ),
    );
  }
}
