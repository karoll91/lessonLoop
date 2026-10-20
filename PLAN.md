# LessonLoop — loyiha rejasi va holati

> Oxirgi yangilanish: 2026-10-20. Har bir bosqich tugaganda shu fayl yangilanadi.

LessonLoop — o'qituvchi har darsda o'tilgan mavzularni talabalar bilan takrorlashi uchun tizim: adolatli random so'rov, 0–100 ball, savollar banki, elektron doskada talabalar ekrani va telefondan boshqaruv (pult).

## Hujjatlar

| Hujjat | Joyi |
| --- | --- |
| PRD (talablar, qoidalar R1–R8, ma'lumotlar modeli) | https://claude.ai/code/artifact/eac39d2d-2191-4bea-bf05-d9c4458e467d |
| Design (UI Kit, Doska + pult D1–D10, Mobil M1–M13, Web W1–W12) | https://claude.ai/artifact/NbXN7yFxPtyDcBjCPDQMv8 |
| Kod (GitHub, `main`) | https://github.com/karoll91/lessonLoop |
| Eski prototip | `Index.html` |
| Backend | `backend/` (Laravel) |

## Texnologiyalar

| Qatlam | Tanlov | Izoh |
| --- | --- | --- |
| Backend | Laravel 13, PHP 8.3 | PRD'da Laravel 11 edi — o'rnatish paytidagi barqaror versiya olindi |
| Baza | MySQL 8.0 (OpenServer), baza `lessonloop` | Testlar SQLite xotirada |
| Real-time | Laravel Reverb (WebSocket, port 8080) | Doska ↔ telefon sinxroni |
| Auth | Sanctum token | Mobil va web uchun bitta API |
| Excel | maatwebsite/excel 4.0 | Import va jurnal eksporti |
| Doska (`board/`) | React 19 + TypeScript + Vite, laravel-echo + pusher-js, qrcode, Vitest | Faza 3 da qilindi |
| Web admin (`admin/`) | React 19 + TypeScript + Vite, react-router, TanStack Query, Vitest | Faza 4 da qilindi |
| Mobil (`mobile/`) | Flutter 3.47 (Dart 3.13), Riverpod 3, Dio, mobile_scanner | Faza 5 da qilindi |

## Lokal muhit

- **PHP 8.3:** `D:\php83\php.exe`. OpenServer 5'da eng yangi PHP 8.1; OpenServer o'z papkasidagi PHP'ni o'z wrapper'i bilan almashtirib yuboradi, shuning uchun PHP 8.3 OpenServer'dan tashqarida turadi.
- **Flutter 3.47.7:** `D:\flutter` (rasmiy zip, PATH'ga qo'shilmagan: `D:\flutter\bin\flutter.bat`). Android SDK hali o'rnatilmagan — APK uchun Android Studio kerak.
- **Composer:** `D:\php83\composer.bat` (OpenServer'ning `composer.phar` faylidan foydalanadi).
- **MySQL:** OpenServer panelidan MySQL-8.0 yoqiladi (port 3306, root, parolsiz).
- **Demo ma'lumot:** `php artisan migrate:fresh --seed` — login `admin`, parol `password` (faqat lokal).

Ishga tushirish (loyiha ildizida, har biri alohida terminalda; `.claude/launch.json` da ham bor):

```bash
/d/php83/php.exe backend/artisan serve --host=0.0.0.0 --port=8000
/d/php83/php.exe backend/artisan reverb:start --host=0.0.0.0 --port=8080
npm --prefix board run dev -- --port 5173 --host 0.0.0.0
npm --prefix admin run dev -- --port 5174 --host 0.0.0.0
```

Doska: brauzerda `http://<kompyuter-IP>:5173` → to'liq ekran uchun **F**, mavzu uchun **T**. Doska tokeni brauzerda saqlanadi, qayta yuklanganda o'sha sessiya tiklanadi. Sozlamalar `board/.env` da (`board/.env.example` nusxasi): API manzili, Reverb kaliti, doska nomi.

Admin panel: `http://localhost:5174` (demo: `admin` / `password`). Sozlamalar `admin/.env` da (`admin/.env.example` nusxasi): API va doska manzili.

Testlar:

```bash
/d/php83/php.exe backend/artisan test --compact
npm --prefix board test
npm --prefix admin test
(cd mobile && /d/flutter/bin/flutter test)
```

Mobil ilovani brauzerda sinash: `cd mobile && /d/flutter/bin/flutter build web`, keyin `.claude/launch.json` dagi `mobile-web` (port 5180). Telefonda server manzili login ekranida kiritiladi: `http://<kompyuter-IP>:8000/api`.

## Asosiy qarorlar

- `GROUPS` MySQL 8'da zaxiralangan so'z — jadval `study_groups` (model `StudyGroup`).
- Guruh + fan + o'qituvchi bog'i — `courses`. Dars raqami (`sequence`) va cooldown kurs ichida sanaladi.
- `answers` jadvali — random qoidalarining yagona manbai: har bir «talabaga savol berildi» yozuvi.
- Random server tomonida (`Random\Randomizer`, testlarda seed bilan).
- Doska login qilmaydi: maxfiy token bilan ochiladi va 6 xonali kod bilan telefonga ulanadi. To'g'ri javob va baho doskaga faqat o'qituvchi ochganda yuboriladi.
- Baholarga bog'langan yozuvni o'chirish 409 qaytaradi (cascade baho tarixini o'chirmasligi uchun). O'rniga arxivlash yoki nofaol qilish ishlatiladi.
- Ruxsatlar: admin — hamma narsa; o'qituvchi — o'z kurslari, o'z fanlarining mavzu va savollari.

## Bajarilgan ishlar

### Faza 0 — Tahlil va design ✅
- PRD (8 bo'lim, random oqim diagrammasi, 10 ta aniqlashtiruvchi savol).
- Design: 3 variant → A «Sokin akademik» tanlandi; UI Kit, 13 mobil, 12 web, 10 doska+pult ekrani; light/dark, barcha holatlar.

### Faza 1 — Backend yadrosi ✅
- 13 jadval migratsiyasi, modellar, factory'lar, demo seeder.
- **StudentPicker** — R1–R6: bir darsda bir marta, 60+ dan keyin 3 dars dam, 60 dan past va hech so'ralmaganlar ×2 vazn, kelmaganlar chiqarilmaydi, hovuz bo'sh bo'lsa cooldown eng oldin tugaydiganlar qaytadi.
- **QuestionPicker** — R7: faqat dars mavzularidan, darsda takrorlanmaydi, oxirgi 4 darsdagilar oxirida.
- **LessonRunner** — dars boshlash (davomat bilan), tanlash, boshqa talaba, baholash (testda variant bo'yicha avtomatik), «javob bermadi» = 0, «kelmagan» = baho yo'q (R8), yakunlash.
- **Doska + pult** — ulanish kodi, boshqaruvni boshqa telefonga o'tkazish, buyruqlar (draw, show_question, reveal_answer, show_score, blackout, pause, resume, finish), ko'rsatish sozlamalari, Reverb orqali `board.{token}` va o'qituvchining shaxsiy kanali. End-to-end tekshirilgan.

### Faza 1b — Admin CRUD, import, jurnal ✅
- CRUD: guruhlar (arxivlash), fanlar, mavzular (tartiblash), talabalar, savollar (A–D variantlar, bitta to'g'ri), kurslar.
- Import: talabalar va savollar — xlsx / xls / csv (`,` yoki `;`) / matn; dry-run oldindan ko'rish; dublikat va xato qatorlar raqami bilan; xlsx shablonlar.
- Jurnal: talaba × dars JSON matritsasi; xlsx va csv eksport (kelmagan — «K»).

### Faza 2 — Backend'dagi bo'shliqlar ✅
- **Navbat holati**: `GET /groups/{id}/students?course_id=` — har talaba uchun navbatda / dam olmoqda (necha dars qoldi) / qarzdor / hech so'ralmagan, filtr va sanoqlar. Qoidalar `QueueStatusResolver` da, random ham shundan foydalanadi.
- **Darslar tarixi** `GET /lessons` (kurs, holat, sana filtrlari; o'rtacha ball, qarzdorlar soni) va **dashboard** `GET /dashboard`.
- **Bahoni tahrirlash** `PATCH /answers/{id}`: o'qituvchi 24 soat ichida, admin istalgan vaqtda; har o'zgarish `answer_edits` jadvalida sababi bilan.
- **Sozlamalar** `GET/PATCH/DELETE /settings`: o'tish bali, cooldown, vaznlar, standart savollar soni, tahrir muddati — `settings` jadvalida, `.env` qiymatlari default.
- **Foydalanuvchilar** `/users`: yaratish (vaqtinchalik parol bir marta ko'rsatiladi), bloklash (barcha qurilmalardan chiqaradi), parolni tiklash; `POST /me/password` — birinchi kirishda parolni almashtirish.

**Jami: 69 ta API route, 122 ta test (393 tekshiruv) — hammasi o'tadi.**

### Faza 3 — Doska web sahifasi ✅
- `board/` — React + Vite. Ekranlar: D1 QR (`lessonloop://pair?code=…`) va 6 xonali kod (muddati tugasa avtomatik yangilanadi), D2 kutish, D3 random baraban, D4 savol (+ ixtiyoriy taymer), D5 to'g'ri javob, D6 baho (o'tdi / qoniqarsiz), D7 test variantlari (to'g'ri va talaba tanlovi), D8 natijalar, D10 tanaffus va qora ekran.
- D9: server bilan aloqa uzilsa oxirgi ekran saqlanadi, ustida ogohlantirish; qayta ulanganda holat serverdan qayta olinadi. Qo'shimcha himoya — har 30 soniyada holatni so'rab turadi.
- Light/dark (T tugmasi, kompyuterda eslab qolinadi), to'liq ekran (F), shriftlar lokal (internetsiz ishlaydi), o'lchamlar ekranga moslashadi.
- 14 ta Vitest testi; backend + Reverb bilan brauzerda end-to-end tekshirilgan (D1–D10, light va dark).

**Ochiq masalalar:**
- ~~D9 dizaynidagi «Pult uzildi» (telefon aloqasi yo'qolgani) hali yo'q~~ — Faza 5b'da qilindi.
- Birinchi sinovda doska bir marta WebSocket xabarini olmadi (keyingi barcha urinishlarda keldi, sababi topilmadi); 30 soniyalik zaxira so'rov buni yopadi, lekin kuzatib borish kerak.
- ~~Yopilgan brauzer oynalaridan qolgan doska sessiyalarini tozalovchi rejalashtirilgan buyruq kerak~~ — `BoardSession` `Prunable`: 24 soat (`LESSONLOOP_BOARD_PRUNE_HOURS`) ko'rinmagan sessiyalar har kuni `model:prune` bilan o'chadi (`schedule:run` ishga tushirilgan bo'lishi kerak, Faza 6).

### Faza 4 — Web admin panel ✅
- `admin/` — React + Vite, react-router, TanStack Query. Sahifalar: W1 kirish (+ vaqtinchalik parolni almashtirish), W2 bosh sahifa, W3 guruhlar va guruh kurslari (fan + o'qituvchi biriktirish), W4 fanlar va mavzular (tartibni o'zgartirish), W5 talabalar (kurs bo'yicha navbat holati, import: oldin tekshirish, keyin saqlash, shablon), W6 savollar banki va muharrir (A–D variantlar, import), W7 darslar tarixi va dars tafsiloti (bahoni sabab bilan tuzatish, tuzatish tarixi), W8 jurnal (xlsx/csv eksport), W9 hisobotlar, W10 foydalanuvchilar (faqat admin; vaqtinchalik parol bir marta ko'rsatiladi, bloklash), W11 sozlamalar (random qoidalari, mavzu, parol), W12 404, kutilmagan xato, internet uzilishi va server ishlamay qolishi holatlari.
- Backend'ga qo'shildi: `GET /courses/{course}/report` (darslar bo'yicha o'rtacha, ballar taqsimoti, qamrov, mavzular, eng qiyin savollar), dars tafsilotida guruh va fan nomi, CORS preflight keshi (2 soat) va `Content-Disposition` sarlavhasi (eksport fayl nomi uchun).
- Barcha sahifalarda yuklanish, bo'sh holat, xato va validatsiya xabarlari; light/dark.
- 6 ta Vitest testi (kirish xatolari, API xato xabarlari, import oqimi, savol muharriri); brauzerda backend bilan tekshirilgan.

**Jami: 71 ta API route (broadcasting auth bilan), 126 ta backend testi (423 tekshiruv), 14 ta doska va 6 ta admin testi — hammasi o'tadi.**

**Ochiq masalalar:**
- `artisan serve` bir oqimli, admin sahifalari 1–3 soniyada ochiladi. Ishga tushirishda (Faza 6) Apache/Nginx + PHP-FPM orqali ishlatish kerak.
- Kichik ekranda (< 860px) keng jadvallar gorizontal suriladi; telefon uchun alohida ko'rinish yo'q (o'qituvchi telefonda Flutter ilovasidan foydalanadi).

### Faza 5 — Flutter mobil ilova (o'qituvchi + pult) ✅
- `mobile/` — Flutter (Android, iOS, web, Windows). Ekranlar: M1 splash + onboarding + mavzu tanlash, M2 kirish (server manzilini o'zgartirish, vaqtinchalik parolni almashtirish), M3 bosh sahifa (davom etayotgan dars, ko'rsatkichlar, bugungi darslar, doska holati), M4/M10 guruhlar va talabalar navbat holati bo'yicha filtr bilan, talaba kartasi (dam olish progressi, baholar), M5 dars sozlash (kurs va mavzular → savollar soni → davomat), M6–M8 random baraban, savol (javob standart yashirin), test variantlari, slider + tez tugmalar bilan baholash, o'tkazish (javob bermadi / yo'q ekan / boshqa talaba), M9 xulosa, M11 savollar banki + yangi savol, M12 tarix + bahoni sabab bilan tuzatish, M13 sozlamalar (mavzu, random qoidalari — admin o'zgartiradi, server, parol, chiqish).
- Pult: QR (`lessonloop://pair?code=…`) yoki 6 xonali kod bilan ulanish; darsning har qadami doskaga yuboriladi (random, savol, javob, baho, yakun), menyuda tanaffus, qora ekran va doska sozlamalari (ism, javob, bahoni ko'rsatish).
- 12 ta Flutter testi (pair kodi, modellar, API xatolari, kirish ekrani); `flutter analyze` toza; brauzerda backend va doska bilan to'liq dars end-to-end tekshirilgan (light va dark).

**Ochiq masalalar:**
- Offline baholash va sinxron (`POST /sync`, `client_uuid`) — hali yo'q; internet uzilsa xato xabari chiqadi.
- ~~Telefon doska ulanishini xotirada saqlaydi~~ va ~~heartbeat / «Pult uzildi» yo'q~~ — Faza 5b'da qilindi.
- Android APK yig'ilmagan (Android SDK yo'q); kamera bilan QR faqat haqiqiy telefonda tekshiriladi.
- Til tanlash (kirill, rus) va Manrope shrifti qo'shilmagan — hozircha lotin o'zbek va tizim shrifti.

## Keyingi reja

Tartib: avval darsda ishlatish uchun kerak bo'lgan narsalar (doska ekrani), keyin admin panel, oxirida mobil ilova. Har bir bosqich oxirida testlar o'tishi va shu fayl yangilanishi shart.


### Faza 5b — Mobil ilovani yakunlash
- [ ] Android SDK + APK, haqiqiy telefonda QR va Wi-Fi orqali sinov.
- [x] Doska ulanishini saqlash va qayta tiklash, telefon heartbeat + «Pult uzildi» (D9).
  - Telefon doska id'sini `SharedPreferences`'da saqlaydi va ilova qayta ochilganda `GET /boards/{board}/controller-state` (faqat ulangan telefon, `BoardSessionPolicy::control`) orqali qayta tiklaydi; 403/404 bo'lsa (boshqa telefon olib qo'ygan yoki doska yopilgan) unutadi, internet bo'lmasa 15 soniyadan keyin qayta urinadi.
  - Ulangan telefon har 15 soniyada `POST /boards/{board}/heartbeat` yuboradi → `board_sessions.controller_seen_at` (yangi ustun; `last_seen_at` doskaning o'zi uchun qoldi). Heartbeat 403/404 qaytarsa telefon doskani unutadi.
  - Doska holatida `controller_online`, `controller_seen_seconds_ago` (nisbiy — doska kompyuteri soati noto'g'ri bo'lsa ham ishlaydi) va `controller_timeout` (45 s, `LESSONLOOP_CONTROLLER_TIMEOUT`). Har bir heartbeat doskaga kichik `board.controller-seen` hodisasini yuboradi; pult uzilgan bo'lsa — to'liq `board.updated`.
  - Doska 45 soniya heartbeat kelmasa oxirgi ekran ustida «Pult uzildi» kartasini va yuqorida «Pult uzildi» belgisini ko'rsatadi; server bilan aloqa yo'q bo'lsa faqat server ogohlantirishi chiqadi.
  - Testlar: +6 backend (132 ta, 446 tekshiruv), +7 doska Vitest (21 ta), +5 Flutter (17 ta); `flutter analyze` toza. 73 ta API route.
  - Ochiq: haqiqiy telefonda ilova fonga o'tganda heartbeat to'xtaydi — doska «Pult uzildi» ko'rsatadi, telefon qaytganda o'zi tiklanadi (kutilgan xatti-harakat, lekin sinab ko'rish kerak). Brauzerda backend + Reverb bilan tekshirildi: birinchi heartbeat to'liq `board.updated`, keyingilari `board.controller-seen`, 45 soniyadan keyin «Pult uzildi».
- [ ] Offline baholash va sinxron (`POST /sync`, idempotent `client_uuid`) — PRD'dagi 2-bosqich.
- [ ] Kirill va rus tillari, Manrope shrifti.

### Faza 6 — Ishga tushirish
- [ ] OpenServer domenlari: `api.lessonloop.loc`, `board.lessonloop.loc`, `admin.lessonloop.loc`.
- [ ] Kunlik MySQL zaxira nusxasi, `.env` production sozlamalari.
- [ ] API hujjati (OpenAPI yoki Scribe).

## PRD'dagi javobsiz savollar

Quyidagilar taxmin bilan qilingan; javobingizga qarab o'zgaradi:

1. Talabalar ilovaga kiradimi? — yo'q, faqat o'qituvchi.
2. «4 darsda 1 marta» har fan uchun alohidami? — ha, kurs guruh + fan bo'yicha.
3. 60 dan past olgan talaba keyingi darsda majburan so'raladimi? — yo'q, ×2 vazn bilan randomga qaytadi.
4. Bir talabaga bir darsda bir nechta savol? — yo'q, 1 talaba = 1 savol.
5. Ballar HEMIS'ga eksport qilinadimi? — manda hemis API yo'q imkoni bo'lsa qilinadi
