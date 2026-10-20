/// Shapes of the backend API resources (backend/app/Http/Resources).
library;

typedef Json = Map<String, dynamic>;

List<T> _list<T>(Object? raw, T Function(Json) parse) => raw is List ? raw.whereType<Json>().map(parse).toList() : <T>[];

int? _int(Object? value) => value is num ? value.toInt() : (value is String ? int.tryParse(value) : null);

DateTime? _date(Object? value) => value is String ? DateTime.tryParse(value)?.toLocal() : null;

class User {
  const User({required this.id, required this.name, required this.login, required this.role, required this.mustChangePassword});

  factory User.fromJson(Json json) => User(
    id: _int(json['id'])!,
    name: json['name'] as String? ?? '',
    login: json['login'] as String? ?? '',
    role: json['role'] as String? ?? 'teacher',
    mustChangePassword: json['must_change_password'] == true,
  );

  final int id;
  final String name;
  final String login;
  final String role;
  final bool mustChangePassword;

  bool get isAdmin => role == 'admin';

  String get initials {
    final parts = name.trim().split(RegExp(r'\s+')).where((part) => part.isNotEmpty).toList();
    return parts.take(2).map((part) => part[0].toUpperCase()).join();
  }
}

class Course {
  const Course({
    required this.id,
    required this.groupId,
    required this.groupName,
    required this.subjectId,
    required this.subjectName,
    this.subjectCode,
    this.teacherName,
    this.lessonsCount = 0,
  });

  factory Course.fromJson(Json json) => Course(
    id: _int(json['id'])!,
    groupId: _int(json['study_group_id'])!,
    groupName: (json['group'] as Json?)?['name'] as String? ?? '?',
    subjectId: _int(json['subject_id'])!,
    subjectName: (json['subject'] as Json?)?['name'] as String? ?? '?',
    subjectCode: (json['subject'] as Json?)?['code'] as String?,
    teacherName: (json['teacher'] as Json?)?['name'] as String?,
    lessonsCount: _int(json['lessons_count']) ?? 0,
  );

  final int id;
  final int groupId;
  final String groupName;
  final int subjectId;
  final String subjectName;
  final String? subjectCode;
  final String? teacherName;
  final int lessonsCount;

  String get label => '$groupName · $subjectName';
}

class Topic {
  const Topic({required this.id, required this.title, required this.position, this.questionsCount = 0});

  factory Topic.fromJson(Json json) => Topic(
    id: _int(json['id'])!,
    title: json['title'] as String? ?? '',
    position: _int(json['position']) ?? 0,
    questionsCount: _int(json['questions_count']) ?? 0,
  );

  final int id;
  final String title;
  final int position;
  final int questionsCount;
}

/// Where a student stands in the random queue of one course (rules R1–R8).
enum QueueState {
  neverAsked('never_asked', "So'ralmagan"),
  queue('queue', 'Navbatda'),
  debtor('debtor', 'Qarzdor'),
  resting('resting', 'Dam olmoqda');

  const QueueState(this.value, this.label);

  final String value;
  final String label;

  static QueueState parse(String? value) => QueueState.values.firstWhere((state) => state.value == value, orElse: () => QueueState.queue);
}

class QueueInfo {
  const QueueInfo({required this.state, required this.askedCount, this.lastScore, this.lastSequence, this.restLessonsLeft = 0});

  factory QueueInfo.fromJson(Json json) => QueueInfo(
    state: QueueState.parse(json['state'] as String?),
    askedCount: _int(json['asked_count']) ?? 0,
    lastScore: _int(json['last_score']),
    lastSequence: _int(json['last_sequence']),
    restLessonsLeft: _int(json['rest_lessons_left']) ?? 0,
  );

  final QueueState state;
  final int askedCount;
  final int? lastScore;
  final int? lastSequence;
  final int restLessonsLeft;
}

class Student {
  const Student({required this.id, required this.fullName, this.phone, this.isActive = true, this.queue});

  factory Student.fromJson(Json json) => Student(
    id: _int(json['id'])!,
    fullName: json['full_name'] as String? ?? '',
    phone: json['phone'] as String?,
    isActive: json['is_active'] != false,
    queue: json['queue'] is Json ? QueueInfo.fromJson(json['queue'] as Json) : null,
  );

  final int id;
  final String fullName;
  final String? phone;
  final bool isActive;
  final QueueInfo? queue;
}

class QuestionOption {
  const QuestionOption({required this.label, required this.text, required this.isCorrect});

  factory QuestionOption.fromJson(Json json) =>
      QuestionOption(label: json['label'] as String? ?? '', text: json['text'] as String? ?? '', isCorrect: json['is_correct'] == true);

  final String label;
  final String text;
  final bool isCorrect;

  Json toJson() => {'label': label, 'text': text, 'is_correct': isCorrect};
}

class Question {
  const Question({
    required this.id,
    required this.type,
    required this.difficulty,
    required this.text,
    this.topicId,
    this.answerText,
    this.options = const [],
    this.timesAsked,
    this.averageScore,
  });

  factory Question.fromJson(Json json) => Question(
    id: _int(json['id'])!,
    topicId: _int(json['topic_id']),
    type: json['type'] as String? ?? 'open',
    difficulty: json['difficulty'] as String? ?? 'medium',
    text: json['text'] as String? ?? '',
    answerText: json['answer_text'] as String?,
    options: _list(json['options'], QuestionOption.fromJson),
    timesAsked: _int(json['times_asked']),
    averageScore: _int(json['average_score']),
  );

  final int id;
  final int? topicId;
  final String type;
  final String difficulty;
  final String text;
  final String? answerText;
  final List<QuestionOption> options;
  final int? timesAsked;
  final int? averageScore;

  bool get isTest => type == 'test';

  bool get hasAnswer => isTest || (answerText?.trim().isNotEmpty ?? false);

  QuestionOption? get correctOption => options.where((option) => option.isCorrect).firstOrNull;
}

const difficultyLabels = {'easy': 'Oson', 'medium': "O'rta", 'hard': 'Qiyin'};

class AnswerEdit {
  const AnswerEdit({this.oldScore, this.newScore, required this.reason, this.by, this.at});

  factory AnswerEdit.fromJson(Json json) => AnswerEdit(
    oldScore: _int(json['old_score']),
    newScore: _int(json['new_score']),
    reason: json['reason'] as String? ?? '',
    by: json['by'] as String?,
    at: _date(json['at']),
  );

  final int? oldScore;
  final int? newScore;
  final String reason;
  final String? by;
  final DateTime? at;
}

class Answer {
  const Answer({
    required this.id,
    required this.status,
    this.score,
    this.passed,
    this.chosenOption,
    this.comment,
    this.pickReason,
    this.editableUntil,
    this.studentId,
    this.studentName = '',
    this.question,
    this.edits = const [],
  });

  factory Answer.fromJson(Json json) => Answer(
    id: _int(json['id'])!,
    status: json['status'] as String? ?? 'pending',
    score: _int(json['score']),
    passed: json['passed'] as bool?,
    chosenOption: json['chosen_option'] as String?,
    comment: json['comment'] as String?,
    pickReason: json['pick_reason'] as String?,
    editableUntil: _date(json['editable_until']),
    studentId: _int((json['student'] as Json?)?['id']),
    studentName: (json['student'] as Json?)?['full_name'] as String? ?? '',
    question: json['question'] is Json ? Question.fromJson(json['question'] as Json) : null,
    edits: _list(json['edits'], AnswerEdit.fromJson),
  );

  final int id;

  /// pending, graded, no_answer, absent.
  final String status;
  final int? score;
  final bool? passed;
  final String? chosenOption;
  final String? comment;
  final String? pickReason;
  final DateTime? editableUntil;
  final int? studentId;
  final String studentName;
  final Question? question;
  final List<AnswerEdit> edits;

  bool get isPending => status == 'pending';

  bool get isScored => status == 'graded' || status == 'no_answer';

  bool editableAt(DateTime now) => isScored && editableUntil != null && editableUntil!.isAfter(now);
}

const answerStatusLabels = {'pending': 'Javob kutilmoqda', 'graded': 'Baholangan', 'no_answer': 'Javob bermadi', 'absent': "Yo'q edi"};

class Lesson {
  const Lesson({
    required this.id,
    required this.courseId,
    required this.sequence,
    required this.questionCount,
    required this.status,
    this.startedAt,
    this.finishedAt,
    this.group,
    this.subject,
    this.askedCount,
    this.averageScore,
    this.answers = const [],
  });

  factory Lesson.fromJson(Json json) => Lesson(
    id: _int(json['id'])!,
    courseId: _int(json['course_id']) ?? 0,
    sequence: _int(json['sequence']) ?? 0,
    questionCount: _int(json['question_count']) ?? 0,
    status: json['status'] as String? ?? 'active',
    startedAt: _date(json['started_at']),
    finishedAt: _date(json['finished_at']),
    group: json['group'] as String?,
    subject: json['subject'] as String?,
    askedCount: _int(json['asked_count']),
    averageScore: _int(json['average_score']),
    answers: _list(json['answers'], Answer.fromJson),
  );

  final int id;
  final int courseId;
  final int sequence;
  final int questionCount;

  /// active, paused, finished.
  final String status;
  final DateTime? startedAt;
  final DateTime? finishedAt;
  final String? group;
  final String? subject;
  final int? askedCount;
  final int? averageScore;
  final List<Answer> answers;

  bool get isFinished => status == 'finished';

  List<Answer> get scored => answers.where((answer) => answer.isScored).toList();

  /// Questions used so far: graded and skipped answers, not absences.
  int get usedCount => askedCount ?? scored.length;

  Answer? get pending => answers.where((answer) => answer.isPending).firstOrNull;

  int? get average {
    final scores = scored.map((answer) => answer.score ?? 0).toList();
    return scores.isEmpty ? averageScore : (scores.reduce((a, b) => a + b) / scores.length).round();
  }
}

class DashboardLesson {
  const DashboardLesson({
    required this.id,
    required this.group,
    required this.subject,
    required this.sequence,
    required this.status,
    required this.questionCount,
    required this.askedCount,
    this.startedAt,
    this.averageScore,
  });

  factory DashboardLesson.fromJson(Json json) => DashboardLesson(
    id: _int(json['id'])!,
    group: json['group'] as String? ?? '',
    subject: json['subject'] as String? ?? '',
    sequence: _int(json['sequence']) ?? 0,
    status: json['status'] as String? ?? '',
    questionCount: _int(json['question_count']) ?? 0,
    askedCount: _int(json['asked_count']) ?? 0,
    startedAt: _date(json['started_at']),
    averageScore: _int(json['average_score']),
  );

  final int id;
  final String group;
  final String subject;
  final int sequence;
  final String status;
  final int questionCount;
  final int askedCount;
  final DateTime? startedAt;
  final int? averageScore;
}

class Dashboard {
  const Dashboard({required this.neverAsked, required this.debtors, required this.groups, this.runningLesson, this.todayLessons = const []});

  factory Dashboard.fromJson(Json json) {
    final counts = json['counts'] as Json? ?? const {};
    return Dashboard(
      groups: _int(counts['groups']) ?? 0,
      neverAsked: _int(counts['never_asked']) ?? 0,
      debtors: (json['debtors'] as List?)?.length ?? 0,
      runningLesson: json['running_lesson'] is Json ? DashboardLesson.fromJson(json['running_lesson'] as Json) : null,
      todayLessons: _list(json['today_lessons'], DashboardLesson.fromJson),
    );
  }

  final int groups;
  final int neverAsked;
  final int debtors;
  final DashboardLesson? runningLesson;
  final List<DashboardLesson> todayLessons;
}

/// What the classroom board shows, as the phone sees it after each command.
class BoardState {
  const BoardState({required this.boardId, required this.screen, this.name, this.lessonId, this.options = const {}});

  factory BoardState.fromJson(Json json) => BoardState(
    boardId: _int(json['board_id'])!,
    screen: json['screen'] as String? ?? 'lobby',
    name: json['name'] as String?,
    lessonId: _int((json['lesson'] as Json?)?['id']),
    options: json['options'] as Json? ?? const {},
  );

  final int boardId;

  /// pairing, lobby, drawing, question, answer, score, summary, blackout, pause.
  final String screen;
  final String? name;
  final int? lessonId;
  final Json options;

  bool option(String key) => options[key] != false;
}

/// Random-picker rules from GET /settings.
class Rules {
  const Rules({required this.values, required this.defaults, required this.limits});

  factory Rules.fromJson(Json json) {
    Map<String, int> ints(Object? raw) => raw is Map ? raw.map((key, value) => MapEntry('$key', _int(value) ?? 0)) : <String, int>{};
    final limits = <String, (int, int)>{};
    final rawLimits = json['limits'];
    if (rawLimits is Map) {
      rawLimits.forEach((key, value) {
        if (value is Map) {
          limits['$key'] = (_int(value['min']) ?? 0, _int(value['max']) ?? 100);
        }
      });
    }
    return Rules(values: ints(json['data']), defaults: ints(json['defaults']), limits: limits);
  }

  final Map<String, int> values;
  final Map<String, int> defaults;
  final Map<String, (int, int)> limits;

  int get passScore => values['pass_score'] ?? 60;
  int get cooldownLessons => values['cooldown_lessons'] ?? 4;
  int get defaultQuestionCount => values['default_question_count'] ?? 8;
}
