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
| Open Server Panel 6 (6.4.6) | `G:\OSPanel` | Modullar: **Nginx**, **PHP-8.3**, **MySQL-8.4** |
| Node.js | `C:\Program Files\nodejs` | Admin panel va doskani yig'ish uchun |
| Loyiha | `D:\OSPanel\domains\LessonLoop.loc` | Git'dan |

OpenServer 6 loyihani `G:\OSPanel\home\lessonloop.loc` orqali ko'radi — bu repo papkasiga bog'lama (junction), kod `D:\` da qoladi. Bog'lamani `deploy\osp6-link.bat` yaratadi (bir marta). Loyiha sozlamalari repo ichidagi `.osp\` papkasida:

- `.osp\project.ini` — PHP-8.3, Nginx, port **8000**, barcha tarmoq manzillarida tinglash, ochiq papka `backend\public`, taxalluslar (`server_aliases`);
- `.osp\nginx\lessonloop.loc.conf` — Laravel uchun nginx qoidalari.

> **Eski OpenServer 5 (`D:\OSPanel`) ni ochmang.** Ikkalasi bir vaqtda ishlasa portlar to'qnashadi. Eski baza `D:\OSPanel\userdata\MySQL-8.0-Win10` da tegilmagan holda qoldi; LessonLoop bazasi 2026-10-22 da MySQL-8.4 ga ko'chirildi.
>
> OpenServer 5 o'z papkasidagi `php.exe`, `mysql.exe`, `mysqldump.exe` va `D:\php83` dagi exe'larni imzosiz «o'ram» fayllar bilan almashtirgan: panel o'chiq bo'lsa ular hech narsa qilmaydi. Shuning uchun LessonLoop endi faqat OpenServer 6 ning rasmiy PHP 8.3 (`G:\OSPanel\modules\PHP-8.3`) va MySQL 8.4 dan foydalanadi.

### 1.2. OpenServer 6 sozlamasi

1. Panel ikonasi (soat yonida) → chap tugma → **Модули** → **Nginx**, **PHP-8.3**, **MySQL-8.4** → «Включить».
2. **Проекты** ro'yxatida `lessonloop.loc` bo'lishi kerak. Ko'rinmasa: `deploy\osp6-link.bat` ni ishga tushiring va panelni qayta yoqing (Выход → qayta ochish).
3. Panel sozlamalarida «Автозапуск» va modullarni avtomatik yoqishni belgilang — shunda Windows yoqilganda hammasi o'zi ishga tushadi.

**Kompyuter IP'si o'zgarsa:** `.osp\project.ini` dagi `server_aliases` qatoriga yangi IP yoziladi (masalan, `server_aliases = localhost 127.0.0.1 karoll-pc 192.168.31.94`) va **panel to'liq qayta yoqiladi** (Выход → qayta ochish; faqat Nginx'ni qayta yoqish yetmaydi). Aks holda IP orqali kelgan so'rovlarni panelning standart sahifasi («Как вы здесь оказались?») ushlab qoladi. Eng yaxshisi — routerda server kompyuterga doimiy IP berish (DHCP reservation).

### 1.3. Sozlamalar (`backend\.env`)

`deploy\env.production.example` faylida server uchun o'zgartiriladigan qiymatlar bor. `backend\.env` faylini Bloknotda oching:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=http://192.168.31.94:8000      ← server kompyuter IP manzili
LOG_LEVEL=warning
DB_HOST=mysql-8.4                      ← OpenServer 6 dagi MySQL moduli nomi
DB_PASSWORD=                           ← MySQL paroli (qo'yilgan bo'lsa)
```

Server IP manzilini bilish: `deploy\start.bat` ishga tushganda o'zi ko'rsatadi, yoki `cmd` da `ipconfig` → «IPv4 Address».

### 1.4. Admin panel va doskani yig'ish

`deploy\build.bat` ni ikki marta bosing. 1–2 daqiqa davom etadi, oxirida «Tayyor» chiqadi. Natija `backend\public\admin` va `backend\public\board` ga yoziladi — ularni OpenServer'ning nginx'i o'zi beradi. Buni faqat birinchi marta va har yangilanishdan keyin qilish kerak.

### 1.5. Brandmauer (firewall)

Telefon va doska kompyuteri serverga ulana olishi uchun portlar ochiladi. **Administrator** sifatida PowerShell oching (Pusk → PowerShell → o'ng tugma → «Запуск от имени администратора») va:

```bash
powershell -ExecutionPolicy Bypass -File D:\OSPanel\domains\LessonLoop.loc\deploy\firewall.ps1
```

8000 (sayt va API) va 8080 (real vaqt — doska yangilanishi) portlariga ruxsat beriladi. Windows'da Wi-Fi tarmog'i **«Частная» (Private)** bo'lishi kerak: Параметры → Сеть и Интернет → Wi-Fi → tarmoq → «Частная сеть».

### 1.6. Avtomatik ishga tushirish (ixtiyoriy)

OpenServer 6 o'zi avtomatik yonadi (1.2, 3-qadam). Reverb va rejalashtiruvchi ham yonishi uchun oddiy PowerShell'da:

```bash
powershell -ExecutionPolicy Bypass -File D:\OSPanel\domains\LessonLoop.loc\deploy\autostart.ps1
```

Windows'ga kirilgandan 1 daqiqa o'tib `start.bat` o'zi ishga tushadi. O'chirish: o'sha buyruq oxiriga `-Remove` qo'shiladi.

---

## 2. Serverni yoqish va o'chirish

Sayt qismini (API, `/admin/`, `/board/`, `/docs/`) **OpenServer 6** beradi. Ikki narsa veb-so'rov emas, ular uchun alohida oyna kerak: **Reverb** (doska real vaqtda yangilanishi, 8080) va **rejalashtiruvchi** (eski doska sessiyalarini tozalash, kunlik zaxira nusxa).

1. **OpenServer 6** yoqilgan bo'lsin (Nginx, PHP-8.3, MySQL-8.4 — yashil).
2. `deploy\start.bat` ni ishga tushiring. U bazani yangilaydi (migratsiyalar), Reverb va rejalashtiruvchini yoqadi va manzillarni ko'rsatadi:

```
LessonLoop ishga tushdi:
  Admin panel:  http://192.168.31.94:8000/admin/
  Doska:        http://192.168.31.94:8000/board/
  Telefon (server manzili): http://192.168.31.94:8000/api
Kuzatilmoqda. To'xtatish: Ctrl+C yoki deploy\stop.bat
```

3. **Oynani yopmang** (kichraytirib qo'ying). U Reverb va rejalashtiruvchini kuzatadi: biri to'xtasa, 5 soniyada qayta yoqadi. Hodisalar `deploy\runtime\logs\supervisor.log` da.
4. O'chirish: oynada **Ctrl+C** yoki `deploy\stop.bat` (OpenServer 6 ishlayveradi).

start.bat xabar berishi mumkin:
- «MySQL ... ishlamayapti» — OpenServer 6 da MySQL-8.4 modulini yoqing;
- «Port 8000 javob bermayapti» — Nginx va PHP-8.3 modullari hamda `lessonloop.loc` loyihasi yoqilganini tekshiring.

> OpenServer 6 bo'lmagan kompyuterda `deploy\config.ps1` da `WebServer = 'Own'` qo'yilsa, start.bat saytni ham o'zi beradi (alohida nginx + 4 ta php-cgi).

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
G:\OSPanel\modules\PHP-8.3\php.exe D:\OSPanel\domains\LessonLoop.loc\backend\artisan lessonloop:backup
```

- Boshqa papka (masalan, flesh yoki boshqa disk): `backend\.env` ga `LESSONLOOP_BACKUP_DIR=E:\LessonLoop-backups`, muddat: `LESSONLOOP_BACKUP_DAYS=60`.
- **Muhim:** nusxalarni vaqti-vaqti bilan boshqa joyga (flesh, bulut) ko'chirib turing — kompyuter disk buzilsa, bir xil diskdagi nusxa ham yo'qoladi.

**Tiklash** (serverni to'xtatib, zip ichidagi `.sql` faylni chiqarib):

```bash
G:\OSPanel\modules\MySQL-8.4\bin\mysql.exe -h mysql-8.4 -u root lessonloop < lessonloop-2026-10-20_1153.sql
```

(parol bo'lsa `-p` qo'shiladi). Keyin `deploy\start.bat`.

---

## 9. Muammolar va yechimlar

| Belgisi | Sabab va yechim |
| --- | --- |
| start.bat: «MySQL ... ishlamayapti» | OpenServer 6 da MySQL-8.4 modulini yoqing |
| start.bat: «Port 8000 javob bermayapti» | OpenServer 6 da Nginx, PHP-8.3 va `lessonloop.loc` loyihasi yoqilganmi? |
| Brauzerda «Как вы здесь оказались?» | So'rov kelgan manzil (IP) loyiha taxalluslarida yo'q — 1.2 dagi «Kompyuter IP'si o'zgarsa» |
| `/admin/` ochiladi, lekin bo'sh yoki 404 | `deploy\build.bat` ni ishga tushiring |
| Telefon: «Serverga ulanib bo'lmadi» | Telefon va server bitta Wi-Fi'dami? Server manzili to'g'rimi (`http://IP:8000/api`)? Firewall (1.5)? Server kompyuterning brauzerida `http://IP:8000/admin/` ochiladimi? |
| Doska: yuqorida «Aloqa yo'q», qizil «Server bilan aloqa yo'q» | Doska kompyuteri serverga ulana olmayapti yoki 8080 port yopiq (firewall). Aloqa tiklanganda o'zi davom etadi |
| Doska: «Pult uzildi» | Telefondan 45 soniya signal yo'q: ilova ochiqmi, telefon Wi-Fi'dami? Ilovani ochsangiz o'zi tiklanadi |
| Doska kodi qabul qilinmaydi | Kod 5 daqiqa amal qiladi — doskadagi yangi kodni kiriting |
| Telefonda «N ta baho yuborilmagan» | Internet/Wi-Fi'ni tekshiring; tiklanganda o'zi ketadi |
| IP o'zgardi | Routerda doimiy IP bering; `.osp\project.ini` taxalluslariga yangi IP va panelni qayta yoqish (1.2); telefonda Kirish → «Server» yoki Sozlamalar → Server manzili |
| Sahifa ochiladi, lekin xato | `backend\storage\logs\laravel.log`, `G:\OSPanel\logs\` (Nginx, PHP-8.3, MySQL-8.4, general.log) va `deploy\runtime\logs\` (reverb, scheduler) |

---

## 10. Yangilash

Yangi versiya chiqqanda (server kompyuterda):

```bash
cd D:\OSPanel\domains\LessonLoop.loc
git pull
```

```bash
cd backend && G:\OSPanel\modules\PHP-8.3\composer.bat install && cd ..
```

(Faqat server vazifasini bajaradigan, dasturlash qilinmaydigan kompyuterda `install --no-dev` — testlar va hujjat generatori o'rnatilmaydi.)

Keyin `deploy\stop.bat` → `deploy\build.bat` → `deploy\start.bat` (baza migratsiyalari start'da o'zi bajariladi). Mobil ilova o'zgargan bo'lsa — APK qayta yig'iladi (7.2) va telefonlarga qayta o'rnatiladi; ma'lumotlar telefonda saqlanib qoladi.

---

## 11. Dasturchilar uchun

- **API hujjati:** `http://<server IP>:8000/docs/` (barcha 74 endpoint, misollar bilan), `backend\public\docs\openapi.yaml`, Postman: `collection.json`. Yangilash:

```bash
cd backend
set DB_CONNECTION=sqlite& set DB_DATABASE=:memory:& G:\OSPanel\modules\PHP-8.3\php.exe artisan scribe:generate
```

- **Loyiha rejasi va holati:** `PLAN.md`.
- **Ishlab chiqish rejimi** (alohida terminallarda): `php artisan serve` (8000), `php artisan reverb:start` (8080), `npm --prefix board run dev` (5173), `npm --prefix admin run dev` (5174) — `PLAN.md` «Lokal muhit».
- **Testlar:**

```bash
G:\OSPanel\modules\PHP-8.3\php.exe backend\artisan test --compact
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
