import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'providers.dart';
import 'strings_ru.dart';

/// Interface languages. Uzbek Latin is the source text in the code; Cyrillic
/// is produced by transliteration and Russian comes from [ruStrings].
enum AppLanguage {
  uzLatn('uz', "O'zbekcha"),
  uzCyrl('uz_Cyrl', 'Ўзбекча'),
  ru('ru', 'Русский');

  const AppLanguage(this.code, this.label);

  final String code;
  final String label;

  static AppLanguage parse(String? code) => values.firstWhere((language) => language.code == code, orElse: () => uzLatn);
}

/// The language [tr] uses; kept in sync by [LanguageController].
AppLanguage currentLanguage = AppLanguage.uzLatn;

/// Translate an interface text written in Uzbek Latin. `{name}` placeholders
/// are filled from [args] after translation, so names and numbers are never
/// transliterated.
String tr(String uz, [Map<String, Object?> args = const {}]) {
  var text = switch (currentLanguage) {
    AppLanguage.uzLatn => uz,
    AppLanguage.uzCyrl => toCyrillic(uz),
    AppLanguage.ru => ruStrings[uz] ?? uz,
  };
  args.forEach((key, value) => text = text.replaceAll('{$key}', '${value ?? ''}'));
  return text;
}

/// Laravel's own validation texts are English; they are shown as they are.
final _english = RegExp(r"\b(the|field|must|least|characters|required|invalid|already|selected)\b", caseSensitive: false);

/// A message that came from the server (Uzbek Latin, or English from Laravel).
String trServer(String message) => _english.hasMatch(message) ? message : tr(message);

/* ---------- Uzbek Latin → Cyrillic ---------- */

const _apostrophes = "'ʻʼ‘’`";

const _single = {
  'a': 'а', 'b': 'б', 'c': 'с', 'd': 'д', 'e': 'е', 'f': 'ф', 'g': 'г', 'h': 'ҳ', 'i': 'и', 'j': 'ж', 'k': 'к', 'l': 'л', 'm': 'м', //
  'n': 'н', 'o': 'о', 'p': 'п', 'q': 'қ', 'r': 'р', 's': 'с', 't': 'т', 'u': 'у', 'v': 'в', 'x': 'х', 'y': 'й', 'z': 'з',
};

const _iotated = {'o': 'ё', 'u': 'ю', 'a': 'я', 'e': 'е'};

/// Brand names, abbreviations, links and `{placeholders}` stay as they are.
final _protected = RegExp(r"\{[a-zA-Z_]+\}|https?://\S+|LessonLoop|Wi-Fi|\b[A-Z]{2,}\b|\b[A-D]\b(?=[\s,.)–-]|$)");

bool _isLetter(String? char) => char != null && RegExp(r'[a-zA-Z]').hasMatch(char);

bool _isApostrophe(String? char) => char != null && _apostrophes.contains(char);

/// Uzbek Latin text in the official Cyrillic alphabet.
String toCyrillic(String text) {
  final out = StringBuffer();
  var i = 0;
  while (i < text.length) {
    final protected = _protected.matchAsPrefix(text, i);
    if (protected != null) {
      out.write(protected[0]);
      i = protected.end;
      continue;
    }

    final char = text[i];
    final lower = char.toLowerCase();
    final upper = char != lower;
    final next = i + 1 < text.length ? text[i + 1] : null;
    final nextLower = next?.toLowerCase();
    final afterNext = i + 2 < text.length ? text[i + 2] : null;
    String cased(String cyrillic) => upper ? cyrillic.toUpperCase() : cyrillic;

    if ((lower == 'o' || lower == 'g') && _isApostrophe(next)) {
      out.write(cased(lower == 'o' ? 'ў' : 'ғ'));
      i += 2;
    } else if (lower == 's' && nextLower == 'h') {
      out.write(cased('ш'));
      i += 2;
    } else if (lower == 'c' && nextLower == 'h') {
      out.write(cased('ч'));
      i += 2;
    } else if (lower == 'y' && nextLower != null && _iotated.containsKey(nextLower) && !(nextLower == 'o' && _isApostrophe(afterNext))) {
      out.write(cased(_iotated[nextLower]!));
      i += 2;
    } else if (lower == 'e') {
      // Э at the start of a word, Е inside it.
      out.write(cased(_isLetter(i > 0 ? text[i - 1] : null) ? 'е' : 'э'));
      i += 1;
    } else if (_isApostrophe(char) && _isLetter(i > 0 ? text[i - 1] : null) && _isLetter(next)) {
      // Tutuq belgisi: ma'lumot → маълумот.
      out.write('ъ');
      i += 1;
    } else {
      out.write(_single.containsKey(lower) ? cased(_single[lower]!) : char);
      i += 1;
    }
  }
  return out.toString();
}

/* ---------- Setting ---------- */

class LanguageController extends Notifier<AppLanguage> {
  static const _key = 'language';

  @override
  AppLanguage build() => currentLanguage = AppLanguage.parse(ref.watch(prefsProvider).getString(_key));

  Future<void> set(AppLanguage language) async {
    await ref.read(prefsProvider).setString(_key, language.code);
    currentLanguage = language;
    state = language;
  }
}

final languageProvider = NotifierProvider<LanguageController, AppLanguage>(LanguageController.new);

/// Texts are looked up while widgets build, so a language change rebuilds
/// every widget below [context] once.
void rebuildAll(BuildContext context) {
  void mark(Element element) {
    element.markNeedsBuild();
    element.visitChildren(mark);
  }

  (context as Element).visitChildren(mark);
}
