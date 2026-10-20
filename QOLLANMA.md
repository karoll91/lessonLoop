# LessonLoop — foydalanish qo'llanmasi

LessonLoop har darsda o'tilgan mavzularni talabalar bilan takrorlash uchun: adolatli random bilan talaba tanlanadi, savol beriladi, o'qituvchi 0–100 ball qo'yadi. Savol va natija sinfdagi doskada (proyektor yoki katta ekran) ko'rinadi, o'qituvchi esa hammasini telefondan boshqaradi.

Tizim uch qismdan iborat:

| Qism | Kim ishlatadi | Qayerda |
| --- | --- | --- |
| **Admin panel** (web) | Admin va o'qituvchilar | Kompyuter brauzerida: `http://<server IP>:8000/admin/` |
| **Mobil ilova** | O'qituvchi (dars va pult) | Android telefon (APK) |
| **Doska** | Sinf (talabalar ko'radi) | Sinfdagi kompyuter brauzerida: `http://<server IP>:8000/board/` |

Hammasi bitta **server** kompyuterga ulanadi: shu kompyuterda baza (MySQL) va LessonLoop dasturi ishlaydi. Telefon, doska kompyuteri va server **bitta tarmoqda** (bitta Wi-Fi yoki LAN) bo'lishi kerak; internet shart emas.

---

## 1. Serverni o'rnatish (bir marta)

### 1.1. Kerakli dasturlar

Server kompyuterda (hozirgi kompyuterda hammasi bor):

| Dastur | Joyi | Izoh |
| --- | --- | --- |
| OpenServer 5 | `D:\OSPanel` | Faqat MySQL uchun (MySQL-8.0, port 3306) |
| PHP 8.3 | `D:\php83` | `php.exe` va `php-cgi.exe` |
| nginx | `D:\OSPanel\modules\http\Nginx_1.23` | OpenServer bilan birga keladi |
| Node.js | `C:\Program Files\nodejs` | Admin panel va doskani yig'ish uchun |
| Loyiha | `D:\OSPanel\domains\LessonLoop.loc` | Git'dan |

Boshqa kompyuterga o'rnatilsa, yo'llar `deploy\config.ps1` faylida o'zgartiriladi.

> Nima uchun OpenServer domenlari emas? LessonLoop (Laravel 13) PHP 8.3 talab qiladi, OpenServer 5 dagi Apache esa butun panel uchun bitta PHP (eng yangisi 8.1) ishlatadi. Uni almashtirsak, OpenServer'dagi boshqa saytlar (masalan, linuxquiz.uz) buzilardi. Shuning uchun LessonLoop o'zining alohida serveri (nginx + PHP 8.3) bilan ishlaydi, OpenServer'dan faqat MySQL olinadi.

### 1.2. Sozlamalar (`backend\.env`)

`deploy\env.production.example` faylida server uchun o'zgartiriladigan qiymatlar bor. `backend\.env` faylini Bloknotda oching va shularni yozing:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=http://192.168.31.94:8000      ← server kompyuter IP manzili
LOG_LEVEL=warning
DB_PASSWORD=                           ← MySQL paroli (qo'yilgan bo'lsa)
```

Server IP manzilini bilish: `deploy\start.bat` ishga tushganda o'zi ko'rsatadi, yoki `cmd` da `ipconfig` → «IPv4 Address».

> **Muhim:** IP o'zgarmasligi uchun Wi-Fi routerda server kompyuterga doimiy IP bering (DHCP reservation). Aks holda har safar telefondagi server manzilini o'zgartirishga to'g'ri keladi.

### 1.3. Admin panel va doskani yig'ish

`D:\OSPanel\domains\LessonLoop.loc\deploy\build.bat` ni ikki marta bosing (yoki terminalda ishga tushiring). 1–2 daqiqa davom etadi, oxirida «Tayyor» chiqadi. Buni faqat birinchi marta va har yangilanishdan keyin qilish kerak.

### 1.4. Brandmauer (firewall)

Telefon va doska kompyuteri serverga ulana olishi uchun portlar ochiladi. **Administrator** sifatida PowerShell oching (Pusk → PowerShell → o'ng tugma → «Запуск от имени администратора») va:

```bash
powershell -ExecutionPolicy Bypass -File D:\OSPanel\domains\LessonLoop.loc\deploy\firewall.ps1
```

8000 (sayt va API) va 8080 (real vaqt — doska yangilanishi) portlariga ruxsat beriladi. Windows'da Wi-Fi tarmog'i **«Частная» (Private)** bo'lishi kerak: Параметры → Сеть и Интернет → Wi-Fi → tarmoq → «Частная сеть».

### 1.5. Avtomatik ishga tushirish (ixtiyoriy)

Kompyuter yoqilganda LessonLoop o'zi ishga tushishi uchun:

1. OpenServer sozlamalarida «Автозапуск» yoqilgan bo'lsin (MySQL kerak).
2. Oddiy PowerShell'da:

```bash
powershell -ExecutionPolicy Bypass -File D:\OSPanel\domains\LessonLoop.loc\deploy\autostart.ps1
```

Windows'ga kirilgandan 1 daqiqa o'tib server o'zi yonadi. O'chirish: o'sha buyruq oxiriga `-Remove` qo'shiladi.

---

## 2. Serverni yoqish va o'chirish

1. **OpenServer**'ni yoqing (bayroqcha yashil bo'lsin) — MySQL ishlashi kerak.
2. `deploy\start.bat` ni ishga tushiring. Oyna ochiladi va manzillarni ko'rsatadi:

```
LessonLoop ishga tushdi:
  Admin panel:  http://192.168.31.94:8000/admin/
  Doska:        http://192.168.31.94:8000/board/
  Telefon (server manzili): http://192.168.31.94:8000/api
Kuzatilmoqda. To'xtatish: Ctrl+C yoki deploy\stop.bat
```

3. **Oynani yopmang** (kichraytirib qo'ying). U serverni kuzatib turadi: biror qism to'xtab qolsa, 5 soniya ichida o'zi qayta ishga tushiradi. Hodisalar `deploy\runtime\logs\supervisor.log` ga yoziladi.
4. O'chirish: oynada **Ctrl+C** yoki `deploy\stop.bat`.

Server ishga tushganda nima bo'ladi: baza yangilanadi (migratsiyalar), 4 ta PHP ishchi jarayon, Reverb (real vaqt, 8080), rejalashtiruvchi (eski doska sessiyalarini tozalash, kunlik zaxira nusxa) va nginx (8000) yonadi.

Port 8000 band bo'lsa (masalan, ishlab chiqish uchun `php artisan serve` ochiq qolgan) — start.bat xabar beradi; o'sha dasturni yoping yoki `deploy\config.ps1` da `HttpPort` ni o'zgartiring.

---

## 3. Birinchi sozlash (admin)

### 3.1. Kirish

`http://<server IP>:8000/admin/` → login `admin`. Demo bazada parol `password` — **darhol o'zgartiring** (Sozlamalar → Parol). Haqiqiy ishga tushirishda demo ma'lumotlarsiz baza bilan boshlash tavsiya etiladi.

### 3.2. Tartib

Admin panelda quyidagi tartibda to'ldiring:

1. **Foydalanuvchilar** — o'qituvchilarni qo'shing. Vaqtinchalik parol **bir marta** ko'rsatiladi — uni o'qituvchiga bering. O'qituvchi birinchi kirishda o'z parolini o'rnatadi. Ishdan ketgan o'qituvchini «bloklash» — barcha qurilmalardan chiqariladi.
2. **Fanlar** va har fanning **mavzulari** (tartibini sudrab o'zgartirish mumkin).
3. **Guruhlar** va har guruhga **kurs** biriktirish: kurs = guruh + fan + o'qituvchi. O'qituvchi faqat o'z kurslarini ko'radi.
4. **Talabalar** — qo'lda yoki Excel'dan import (3.3).
5. **Savollar banki** — har mavzuga savollar: qo'lda yoki import.
6. **Sozlamalar → Random qoidalari** (faqat admin; 4-bo'lim).

### 3.3. Excel'dan import

Talabalar (guruh sahifasida) va savollar (savollar banki) uchun «Import» → «Shablonni yuklab olish». Fayl turlari: xlsx, xls, csv (`,` yoki `;`), oddiy matn. Avval **tekshirish** bosqichi: qaysi qator qabul qilinadi, qaysi biri dublikat yoki xato — qator raqami bilan ko'rsatiladi. Keyin «Saqlash».

**Talabalar:** `F.I.Sh. | Telefon` (telefon ixtiyoriy).

**Savollar:** `Savol | To'g'ri javob | Qiyinlik | A | B | C | D | To'g'ri variant`

- Qiyinlik: `oson`, `o'rta` yoki `qiyin`.
- Ochiq savol: A–D bo'sh, «To'g'ri javob» — namuna javob (darsda o'qituvchiga va so'ralsa doskada ko'rsatiladi).
- Test savol: A–D (kamida 2 tasi) va «To'g'ri variant» — `A`, `B`, `C` yoki `D`.

---

## 4. Random qanday ishlaydi (qoidalar)

Har bir kurs (guruh + fan) uchun alohida hisoblanadi:

| Qoida | Standart qiymat |
| --- | --- |
| Bir darsda bir talabaga bitta savol | — |
| **O'tish bali**: shundan yuqori olgan talaba «dam oladi» | 60 |
| **Dam olish**: 60+ olgan talaba qayta so'ralishi uchun o'tishi kerak bo'lgan darslar | 4 (ya'ni 3 dars dam) |
| **Qarzdor** (60 dan past olgan) — keyingi darslarda tanlanish ehtimoli | ×2 |
| **Hech so'ralmagan** talaba — tanlanish ehtimoli | ×2 |
| Darsga kelmagan talaba (davomatda belgilangan) | tanlanmaydi |
| Hamma dam olayotgan bo'lsa | dam olishi eng oldin tugaydiganlar qaytadi |
| Savollar | faqat dars mavzularidan, darsda takrorlanmaydi, oxirgi 4 darsda berilganlari oxiriga suriladi |
| Bahoni tuzatish | o'qituvchi 24 soat ichida (sabab bilan), admin istalgan vaqtda |
| Standart savollar soni | 8 |

Qiymatlarni admin **Sozlamalar → Random qoidalari** da o'zgartiradi; keyingi tanlovdan boshlab amal qiladi.

Talaba holatlari (guruh sahifasida, telefonda «Guruhlar»): **Navbatda**, **Dam olmoqda** (yana necha dars), **Qarzdor**, **So'ralmagan**.

---

## 5. Dars o'tkazish (o'qituvchi)

### 5.1. Telefon ilovasini sozlash (bir marta)

1. APK'ni o'rnating (7-bo'lim).
2. Birinchi ochilishda til (O'zbekcha · Ўзбекча · Русский) va mavzu (yorug'/qorong'i) tanlanadi.
3. Kirish ekranida pastdagi **«Server: …»** → `http://<server IP>:8000/api` kiriting.
4. Admin bergan login va vaqtinchalik parol bilan kiring, o'z parolingizni o'rnating.

### 5.2. Doskani tayyorlash

1. Sinfdagi kompyuterda brauzerda `http://<server IP>:8000/board/` oching (admin paneldagi «Doska rejimi» havolasi ham shu yerga olib boradi).
2. **F** — to'liq ekran, **T** — yorug'/qorong'i mavzu.
3. Ekranda QR kod va 6 xonali kod chiqadi (kod 5 daqiqa amal qiladi, keyin o'zi yangilanadi).

Doska kompyuteriga login kerak emas. Brauzer yopilib qayta ochilsa, o'sha doska sessiyasi tiklanadi.

### 5.3. Telefonni doskaga ulash

Telefonda **Bugun → Doska ulanmagan → QR** tugmasi (yoki Sozlamalar → Doska) → doskadagi QR kodni skanerlang yoki 6 xonali kodni kiriting. Doskada «Pult ulandi» chiqadi.

- Telefon ulanishni eslab qoladi: ilova yopilib qayta ochilsa ham doska ulangan qoladi.
- Telefon har 15 soniyada doskaga «tirikman» signalini yuboradi. 45 soniya signal kelmasa (telefon o'chdi, Wi-Fi uzildi, ilova yopildi), doskada **«Pult uzildi»** ogohlantirishi chiqadi — oxirgi ekran o'z joyida qoladi. Telefon qayta ulanganda ogohlantirish o'zi yo'qoladi.
- Boshqa o'qituvchi o'sha doskaga ulansa, oldingi telefon doskani yo'qotadi.
- Doskani uzish: Bugun yoki Sozlamalar'dagi doska kartasi → uzish tugmasi. Doska yana QR ekraniga qaytadi.

### 5.4. Dars

1. **Bugun → Yangi dars**: kurs (guruh va fan) va mavzular → savollar soni → davomat (kim kelmagan).
2. **Random** tugmasi — baraban aylanadi, talaba tanlanadi. Doskada ham baraban, keyin talaba ismi va savol.
3. Talaba javob beradi. Telefonda **«To'g'ri javobni ko'rsatish»** — namuna javob ochiladi va doska ulangan bo'lsa doskada ham ko'rinadi. Javobni doskada umuman ko'rsatmaslik uchun doska menyusida «To'g'ri javobni ko'rsatish» ni o'chiring — shunda u faqat telefonda bo'ladi.
4. **Baho**: slayder yoki tez tugmalar (0–100). Test savolda talaba aytgan variantni bosing — ball avtomatik (to'g'ri 100, noto'g'ri 0). Baho doskada ko'rinadi.
5. **O'tkazish**: «Javob bermadi» (0 ball, qarzdor), «Darsda yo'q ekan» (baho yo'q, navbatdagi o'rni saqlanadi), «Boshqa talaba» (shu savol boshqasiga).
6. Keyingi talaba uchun yana **Random**. Savollar tugaganda yoki istalgan payt — **Darsni yakunlash**: doskada natijalar (o'rtacha, o'tganlar, qarzdorlar).

Dars paytida yuqoridagi doska menyusi: **Tanaffus** (savollar yashiriladi), **Qora ekran**, **Davom ettirish**, doskada ko'rsatish sozlamalari (talaba ismi, to'g'ri javob, baho).

### 5.5. Internet uzilsa

- Baho yoki «o'tkazish» internetsiz ham saqlanadi: telefonda **«N ta baho telefonda saqlangan, hali yuborilmagan»** ogohlantirishi chiqadi. Aloqa tiklanganda (har 15 soniyada urinadi) o'zi yuboriladi; «Yuborish» tugmasi bilan darhol yuborish ham mumkin.
- Har bir baho bir marta qo'llanadi — ikki marta yuborilsa ham ikki marta yozilmaydi. Bahoning vaqti — telefonda qo'yilgan vaqt.
- **Random, boshqa talaba va darsni yakunlash serversiz ishlamaydi** — avval saqlangan baholar yuborilishi kerak.
- Server qabul qilmagan baho (masalan, javob allaqachon baholangan) talaba ismi bilan ko'rsatiladi.
- Telefonda yuborilmagan baho bo'lsa, «Chiqish» bosilganda ogohlantiriladi.

### 5.6. Tarix va bahoni tuzatish

Telefonda **Tarix** (yoki admin panelda «Darslar tarixi») → dars → talaba → yangi ball va **sabab**. O'qituvchi 24 soat ichida tuzata oladi, keyin faqat admin. Har bir tuzatish tarixda saqlanadi (kim, qachon, eski → yangi, sabab).

---

## 6. Admin panel imkoniyatlari

| Bo'lim | Nima qilinadi |
| --- | --- |
| Bosh sahifa | Bugungi darslar, ko'rsatkichlar |
| Guruhlar | Guruhlar, kurslar, talabalar va ularning navbat holati, import |
| Fanlar | Fanlar va mavzular (tartib) |
| Savollar | Savollar banki, A–D variantlar, import |
| Darslar tarixi | Har dars tafsiloti, bahoni tuzatish va tuzatishlar tarixi |
| Jurnal | Talaba × dars jadvali, **Excel (xlsx) va CSV eksport** (kelmagan — «K») |
| Hisobotlar | Kurs bo'yicha o'rtacha, ballar taqsimoti, mavzular, eng qiyin savollar |
| Foydalanuvchilar | (faqat admin) o'qituvchilar, bloklash, parolni tiklash |
| Sozlamalar | Random qoidalari (admin), mavzu, parol |

Baholarga bog'langan yozuvlar (talaba, savol, fan) o'chirilmaydi — tarix buzilmasligi uchun. O'rniga arxivlash yoki nofaol qilish ishlatiladi.

---

## 7. Mobil ilova (APK)

### 7.1. Android SDK o'rnatish (bir marta)

1. **Android Studio**'ni o'rnating: https://developer.android.com/studio (o'rnatish ustasida «Android SDK», «Android SDK Platform», «Android Virtual Device» belgilangan bo'lsin). SDK odatda `C:\Users\<siz>\AppData\Local\Android\Sdk` ga tushadi.
2. Android Studio → **More Actions → SDK Manager → SDK Tools**: «Android SDK Command-line Tools (latest)» ni belgilang → Apply.
3. Terminalda:

```bash
D:\flutter\bin\flutter.bat config --android-sdk "C:\Users\Mahkamov_Sh\AppData\Local\Android\Sdk"
```

```bash
D:\flutter\bin\flutter.bat doctor --android-licenses
```

(hammasiga `y`), keyin tekshirish:

```bash
D:\flutter\bin\flutter.bat doctor
```

«Android toolchain» yonida yashil belgi bo'lishi kerak.

### 7.2. APK yig'ish

```bash
cd D:\OSPanel\domains\LessonLoop.loc\mobile
D:\flutter\bin\flutter.bat build apk --release
```

Tayyor fayl: `mobile\build\app\outputs\flutter-apk\app-release.apk`. Birinchi yig'ish bir necha daqiqa davom etadi (Gradle va kutubxonalar yuklab olinadi, internet kerak).

### 7.3. Telefonga o'rnatish

APK'ni telefonga o'tkazing (USB, Telegram, flesh) → oching → «Noma'lum manbalardan o'rnatishga ruxsat» → O'rnatish. Ilova kameraga ruxsat so'raydi (QR uchun).

Ilova ichki tarmoqdagi `http://` serverga ulanishga sozlangan. Hozircha APK debug kaliti bilan imzolanadi — ichki tarqatish uchun yetarli; Google Play uchun alohida kalit (keystore) kerak bo'ladi.

---

## 8. Zaxira nusxa (backup)

- Server ishlab turganda **kuniga bir marta** baza avtomatik saqlanadi: `backend\storage\app\backups\lessonloop-YYYY-MM-DD_HHMM.zip`. Kompyuter kechasi o'chiq bo'lsa ham — yoqilgan kuni nusxa olinadi. 30 kundan eskilari o'chiriladi.
- Qo'lda nusxa olish:

```bash
D:\php83\php.exe D:\OSPanel\domains\LessonLoop.loc\backend\artisan lessonloop:backup
```

- Boshqa papka (masalan, flesh yoki boshqa disk): `backend\.env` ga `LESSONLOOP_BACKUP_DIR=E:\LessonLoop-backups`, muddat: `LESSONLOOP_BACKUP_DAYS=60`.
- **Muhim:** nusxalarni vaqti-vaqti bilan boshqa joyga (flesh, bulut) ko'chirib turing — kompyuter disk buzilsa, bir xil diskdagi nusxa ham yo'qoladi.

**Tiklash** (serverni to'xtatib, zip ichidagi `.sql` faylni chiqarib):

```bash
D:\OSPanel\modules\database\MySQL-8.0-Win10\bin\mysql.exe -u root lessonloop < lessonloop-2026-10-20_1153.sql
```

(parol bo'lsa `-p` qo'shiladi). Keyin `deploy\start.bat`.

---

## 9. Muammolar va yechimlar

| Belgisi | Sabab va yechim |
| --- | --- |
| start.bat: «MySQL ishlamayapti» | OpenServer'ni yoqing, MySQL-8.0 yashil bo'lsin |
| start.bat: «Port 8000 band» | `php artisan serve` yoki boshqa dastur ochiq — yoping yoki `deploy\config.ps1` da `HttpPort` |
| start.bat: «dist yo'q» | `deploy\build.bat` ni ishga tushiring |
| Telefon: «Serverga ulanib bo'lmadi» | Telefon va server bitta Wi-Fi'dami? Server manzili to'g'rimi (`http://IP:8000/api`)? Firewall (1.4)? Server kompyuterning brauzerida `http://IP:8000/admin/` ochiladimi? |
| Doska: yuqorida «Aloqa yo'q», qizil «Server bilan aloqa yo'q» | Doska kompyuteri serverga ulana olmayapti yoki 8080 port yopiq (firewall). Aloqa tiklanganda o'zi davom etadi |
| Doska: «Pult uzildi» | Telefondan 45 soniya signal yo'q: ilova ochiqmi, telefon Wi-Fi'dami? Ilovani ochsangiz o'zi tiklanadi |
| Doska kodi qabul qilinmaydi | Kod 5 daqiqa amal qiladi — doskadagi yangi kodni kiriting |
| Telefonda «N ta baho yuborilmagan» | Internet/Wi-Fi'ni tekshiring; tiklanganda o'zi ketadi |
| IP o'zgardi | Routerda doimiy IP bering; telefonda Kirish → «Server» yoki Sozlamalar → Server manzili |
| Sahifa ochiladi, lekin xato | `deploy\runtime\logs\` (nginx, php, reverb) va `backend\storage\logs\laravel.log` |

---

## 10. Yangilash

Yangi versiya chiqqanda (server kompyuterda):

```bash
cd D:\OSPanel\domains\LessonLoop.loc
git pull
```

```bash
cd backend && D:\php83\composer.bat install && cd ..
```

(Faqat server vazifasini bajaradigan, dasturlash qilinmaydigan kompyuterda `install --no-dev` — testlar va hujjat generatori o'rnatilmaydi.)

Keyin `deploy\stop.bat` → `deploy\build.bat` → `deploy\start.bat` (baza migratsiyalari start'da o'zi bajariladi). Mobil ilova o'zgargan bo'lsa — APK qayta yig'iladi (7.2) va telefonlarga qayta o'rnatiladi; ma'lumotlar telefonda saqlanib qoladi.

---

## 11. Dasturchilar uchun

- **API hujjati:** `http://<server IP>:8000/docs/` (barcha 74 endpoint, misollar bilan), `backend\public\docs\openapi.yaml`, Postman: `collection.json`. Yangilash:

```bash
cd backend
set DB_CONNECTION=sqlite& set DB_DATABASE=:memory:& D:\php83\php.exe artisan scribe:generate
```

- **Loyiha rejasi va holati:** `PLAN.md`.
- **Ishlab chiqish rejimi** (alohida terminallarda): `php artisan serve` (8000), `php artisan reverb:start` (8080), `npm --prefix board run dev` (5173), `npm --prefix admin run dev` (5174) — `PLAN.md` «Lokal muhit».
- **Testlar:**

```bash
D:\php83\php.exe backend\artisan test --compact
```

```bash
npm --prefix board test
```

```bash
npm --prefix admin test
```

```bash
cd mobile && D:\flutter\bin\flutter.bat test
```

- Mobil ilovaga yangi matn qo'shilganda `tr('...')` ichida o'zbek lotinida yoziladi; kirill avtomatik, rus tarjimasi `mobile\lib\core\strings_ru.dart` ga qo'shiladi (test tarjimasiz matnni ushlaydi).
- Server konfiguratsiyasi keshlanmaydi: aks holda `php artisan test` haqiqiy bazani tozalab yuborishi mumkin edi. `php artisan config:cache` **ishlatmang**.
