# LessonLoop — loyiha rejasi va holati

> Oxirgi yangilanish: 2026-10-19. Har bir bosqich tugaganda shu fayl yangilanadi.

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
| Web (doska, admin) | React + TypeScript + Vite | Hali boshlanmagan |
| Mobil | Flutter | Hali boshlanmagan |

## Lokal muhit

- **PHP 8.3:** `D:\php83\php.exe`. OpenServer 5'da eng yangi PHP 8.1; OpenServer o'z papkasidagi PHP'ni o'z wrapper'i bilan almashtirib yuboradi, shuning uchun PHP 8.3 OpenServer'dan tashqarida turadi.
- **Composer:** `D:\php83\composer.bat` (OpenServer'ning `composer.phar` faylidan foydalanadi).
- **MySQL:** OpenServer panelidan MySQL-8.0 yoqiladi (port 3306, root, parolsiz).
- **Demo ma'lumot:** `php artisan migrate:fresh --seed` — login `admin`, parol `password` (faqat lokal).

Ishga tushirish (backend papkasida, uchta alohida terminal):

```bash
/d/php83/php.exe artisan serve --host=0.0.0.0 --port=8000
/d/php83/php.exe artisan reverb:start --host=0.0.0.0 --port=8080
/d/php83/php.exe artisan test --compact
```

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

## Keyingi reja

Tartib: avval darsda ishlatish uchun kerak bo'lgan narsalar (doska ekrani), keyin admin panel, oxirida mobil ilova. Har bir bosqich oxirida testlar o'tishi va shu fayl yangilanishi shart.

### Faza 3 — Doska web sahifasi (React)
- [ ] Vite + React + TypeScript, Laravel Echo (Reverb).
- [ ] D1–D10: QR/kod bilan ulanish, kutish, random baraban, savol, javob, baho, test, yakun, aloqa uzilishi, tanaffus.
- [ ] Qayta yuklanganda `GET /boards/{token}/state` dan tiklanish, avtomatik qayta ulanish.
- [ ] OpenServer'da `board.lessonloop.loc` domeni.

### Faza 4 — Web admin panel (React)
- [ ] W1–W12: kirish, dashboard, guruhlar, fanlar/mavzular, talabalar + import, savollar banki + muharrir, darslar tarixi, jurnal, hisobotlar, foydalanuvchilar, sozlamalar, xato sahifalari.
- [ ] UI Kit komponentlari (Sidebar, Banner, Toast, EmptyState …), light/dark.

### Faza 5 — Flutter mobil ilova (o'qituvchi + pult)
- [ ] M1–M13 va pult ekranlari (D-seriyadagi telefon tomoni).
- [ ] Riverpod, Dio, Echo/Pusher klient, light/dark.
- [ ] Offline baholash va sinxron (`POST /sync`, idempotent `client_uuid`) — PRD'dagi 2-bosqich.

### Faza 6 — Ishga tushirish
- [ ] OpenServer domenlari: `api.lessonloop.loc`, `board.lessonloop.loc`, `admin.lessonloop.loc`.
- [ ] Kunlik MySQL zaxira nusxasi, `.env` production sozlamalari.
- [ ] API hujjati (OpenAPI yoki Scribe).

## PRD'dagi javobsiz savollar

Quyidagilar taxmin bilan qilingan; javobingizga qarab o'zgaradi:

1. Talabalar ilovaga kiradimi? — hozircha yo'q, faqat o'qituvchi.
2. «4 darsda 1 marta» har fan uchun alohidami? — ha, kurs (guruh + fan) bo'yicha.
3. 60 dan past olgan talaba keyingi darsda majburan so'raladimi? — yo'q, ×2 vazn bilan randomga qaytadi.
4. Bir talabaga bir darsda bir nechta savol? — yo'q, 1 talaba = 1 savol.
5. Ballar HEMIS'ga eksport qilinadimi? — hozircha Excel yetarli.
