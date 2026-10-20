import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:lessonloop/core/i18n.dart';
import 'package:lessonloop/core/strings_ru.dart';

void main() {
  tearDown(() => currentLanguage = AppLanguage.uzLatn);

  group('toCyrillic', () {
    test('follows the official Uzbek Cyrillic alphabet', () {
      expect(toCyrillic("O'zbekcha"), 'Ўзбекча');
      expect(toCyrillic("Yo'q"), 'Йўқ');
      expect(toCyrillic("Serverga ulanib bo'lmadi"), 'Серверга уланиб бўлмади');
      expect(toCyrillic("Ma'lumot yuklanmadi"), 'Маълумот юкланмади');
      expect(toCyrillic('Chiqish'), 'Чиқиш');
      expect(toCyrillic("G'olib shoshilmaydi"), 'Ғолиб шошилмайди');
      expect(toCyrillic('Ekran va eslatma'), 'Экран ва эслатма');
      expect(toCyrillic('Yangi dars'), 'Янги дарс');
    });

    test('keeps placeholders, abbreviations, links and the brand name', () {
      expect(toCyrillic('{n}-dars'), '{n}-дарс');
      expect(toCyrillic('QR kod va Wi-Fi'), 'QR код ва Wi-Fi');
      expect(toCyrillic('LessonLoop: http://10.0.2.2:8000/api'), 'LessonLoop: http://10.0.2.2:8000/api');
      expect(toCyrillic('Test (A–D)'), 'Тест (A–D)');
    });
  });

  group('tr', () {
    test('fills placeholders after translating, in every language', () {
      expect(tr('Yana {n} ta savol qoldi. Baholar saqlangan.', {'n': 3}), 'Yana 3 ta savol qoldi. Baholar saqlangan.');

      currentLanguage = AppLanguage.uzCyrl;
      expect(tr('{n}-dars', {'n': 5}), '5-дарс');
      // Names stay as written.
      expect(tr('Doska #{id}', {'id': 'Shoxrux'}), 'Доска #Shoxrux');

      currentLanguage = AppLanguage.ru;
      expect(tr('Yana {n} ta savol qoldi. Baholar saqlangan.', {'n': 3}), 'Осталось вопросов: 3. Оценки сохранены.');
      expect(tr('Matn tarjimasiz'), 'Matn tarjimasiz');
    });

    test('server messages: Uzbek ones are translated, English ones from Laravel are left alone', () {
      currentLanguage = AppLanguage.ru;
      expect(trServer("Login yoki parol noto'g'ri."), 'Неверный логин или пароль.');

      currentLanguage = AppLanguage.uzCyrl;
      expect(trServer('Bu javob allaqachon baholangan.'), 'Бу жавоб аллақачон баҳоланган.');
      expect(trServer('The password field must be at least 8 characters.'), 'The password field must be at least 8 characters.');
    });
  });

  test('every tr() text in lib/ has a Russian translation', () {
    final call = RegExp(r'''\btr\(\s*(?:'((?:[^'\\]|\\.)*)'|"((?:[^"\\]|\\.)*)")''');
    final missing = <String>{};
    for (final file in Directory('lib').listSync(recursive: true).whereType<File>().where((file) => file.path.endsWith('.dart'))) {
      for (final match in call.allMatches(file.readAsStringSync())) {
        final key = (match.group(1) ?? match.group(2))!.replaceAll(r"\'", "'").replaceAll(r'\"', '"').replaceAll(r'\n', '\n');
        if (key.isNotEmpty && !ruStrings.containsKey(key)) {
          missing.add(key);
        }
      }
    }
    expect(missing, isEmpty);
  });
}
