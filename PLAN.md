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
| Doska (`board/`) | React 19 + TypeScript + Vite, laravel-echo + pusher-js, qrcode, Vitest | Faza 3 da qilindi |
| Web admin (`admin/`) | React 19 + TypeScript + Vite, react-router, TanStack Query, Vitest | Faza 4 da qilindi |
| Mobil | Flutter | Hali boshlanmagan |

## Lokal muhit

- **PHP 8.3:** `D:\php83\php.exe`. OpenServer 5'da eng yangi PHP 8.1; OpenServer o'z papkasidagi PHP'ni o'z wrapper'i bilan almashtirib yuboradi, shuning uchun PHP 8.3 OpenServer'dan tashqarida turadi.
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

### Faza 3 — Doska web sahifasi ✅
- `board/` — React + Vite. Ekranlar: D1 QR (`lessonloop://pair?code=…`) va 6 xonali kod (muddati tugasa avtomatik yangilanadi), D2 kutish, D3 random baraban, D4 savol (+ ixtiyoriy taymer), D5 to'g'ri javob, D6 baho (o'tdi / qoniqarsiz), D7 test variantlari (to'g'ri va talaba tanlovi), D8 natijalar, D10 tanaffus va qora ekran.
- D9: server bilan aloqa uzilsa oxirgi ekran saqlanadi, ustida ogohlantirish; qayta ulanganda holat serverdan qayta olinadi. Qo'shimcha himoya — har 30 soniyada holatni so'rab turadi.
- Light/dark (T tugmasi, kompyuterda eslab qolinadi), to'liq ekran (F), shriftlar lokal (internetsiz ishlaydi), o'lchamlar ekranga moslashadi.
- 14 ta Vitest testi; backend + Reverb bilan brauzerda end-to-end tekshirilgan (D1–D10, light va dark).

**Ochiq masalalar:**
- D9 dizaynidagi «Pult uzildi» (telefon aloqasi yo'qolgani) hali yo'q — buning uchun telefon backend'ga heartbeat yuborishi kerak; Faza 5 (Flutter) bilan birga qilinadi.
- Birinchi sinovda doska bir marta WebSocket xabarini olmadi (keyingi barcha urinishlarda keldi, sababi topilmadi); 30 soniyalik zaxira so'rov buni yopadi, lekin kuzatib borish kerak.
- Yopilgan brauzer oynalaridan qolgan doska sessiyalarini tozalovchi rejalashtirilgan buyruq kerak.

### Faza 4 — Web admin panel ✅
- `admin/` — React + Vite, react-router, TanStack Query. Sahifalar: W1 kirish (+ vaqtinchalik parolni almashtirish), W2 bosh sahifa, W3 guruhlar va guruh kurslari (fan + o'qituvchi biriktirish), W4 fanlar va mavzular (tartibni o'zgartirish), W5 talabalar (kurs bo'yicha navbat holati, import: oldin tekshirish, keyin saqlash, shablon), W6 savollar banki va muharrir (A–D variantlar, import), W7 darslar tarixi va dars tafsiloti (bahoni sabab bilan tuzatish, tuzatish tarixi), W8 jurnal (xlsx/csv eksport), W9 hisobotlar, W10 foydalanuvchilar (faqat admin; vaqtinchalik parol bir marta ko'rsatiladi, bloklash), W11 sozlamalar (random qoidalari, mavzu, parol), W12 404, kutilmagan xato, internet uzilishi va server ishlamay qolishi holatlari.
- Backend'ga qo'shildi: `GET /courses/{course}/report` (darslar bo'yicha o'rtacha, ballar taqsimoti, qamrov, mavzular, eng qiyin savollar), dars tafsilotida guruh va fan nomi, CORS preflight keshi (2 soat) va `Content-Disposition` sarlavhasi (eksport fayl nomi uchun).
- Barcha sahifalarda yuklanish, bo'sh holat, xato va validatsiya xabarlari; light/dark.
- 6 ta Vitest testi (kirish xatolari, API xato xabarlari, import oqimi, savol muharriri); brauzerda backend bilan tekshirilgan.

**Jami: 71 ta API route (broadcasting auth bilan), 126 ta backend testi (423 tekshiruv), 14 ta doska va 6 ta admin testi — hammasi o'tadi.**

**Ochiq masalalar:**
- `artisan serve` bir oqimli, admin sahifalari 1–3 soniyada ochiladi. Ishga tushirishda (Faza 6) Apache/Nginx + PHP-FPM orqali ishlatish kerak.
- Kichik ekranda (< 860px) keng jadvallar gorizontal suriladi; telefon uchun alohida ko'rinish yo'q (o'qituvchi telefonda Flutter ilovasidan foydalanadi).

## Keyingi reja

Tartib: avval darsda ishlatish uchun kerak bo'lgan narsalar (doska ekrani), keyin admin panel, oxirida mobil ilova. Har bir bosqich oxirida testlar o'tishi va shu fayl yangilanishi shart.


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
