# Доменное API

Базовый URL: `http://localhost:8080` (или `APP_URL`).

Авторизация — Bearer Sanctum token (см. [AUTH.md](AUTH.md) и пакет fuisic-auth).

OpenAPI/Swagger: `/api/documentation`

## Публичные эндпоинты

| Метод | URI | Описание |
|-------|-----|----------|
| GET | `card_set` | Список наборов карточек |
| GET | `card_set/{card_set}` | Набор карточек |
| GET | `card_set/{card_set}/cards` | Карточки набора |
| GET | `section`, `section/{section}` | Разделы |
| GET | `user`, `user/{user}` | Пользователи |
| GET | `card`, `card/{card}` | Карточки |
| GET | `task/{task}` | Задание: редактору — целиком, остальным — как при прохождении |
| GET | `test`, `test/{test}` | Тесты |
| GET | `test/{test}/tasks` | Задания теста для прохождения (без ответов) |
| POST | `test/{test}/answers` | Проверка ответов (публично) |
| GET | `filters/classification` | Фильтр: классификация |
| GET | `filters/difficulty` | Фильтр: сложность |

## Защищённые эндпоинты (auth:sanctum)

| Метод | URI | Описание |
|-------|-----|----------|
| POST | `card_set` | Создать набор |
| PUT | `card_set/{card_set}` | Обновить |
| DELETE | `card_set/{card_set}` | Удалить |
| POST/PUT/DELETE | `section`, `section/{section}` | CRUD разделов |
| POST/PUT/DELETE | `user`, `user/{user}` | CRUD пользователей (`users.manage`); созданный через `POST user` получает роль student, роли меняются только в админке |
| POST/PUT/DELETE | `card`, `card/{card}` | CRUD карточек |
| GET | `task` | Задания тестов, которые пользователь может редактировать (с ответами) |
| POST/PUT/DELETE | `task`, `task/{task}` | CRUD заданий (см. [Вопросы теста](#вопросы-теста)) |
| POST/PUT/DELETE | `test`, `test/{test}` | CRUD тестов |
| POST | `test/{test}/answers` | Проверка ответов (auth) |
| GET | `teacher_verification` | Последняя заявка учителя на «Проверенного учителя» (404 — заявок не было) |
| POST | `teacher_verification` | Подать заявку (только роль teacher; 409 — есть заявка на рассмотрении или статус уже подтверждён) |
| GET/PUT | `settings` | Мои настройки / изменить (частично), см. [Настройки](#настройки-пользователя) |
| GET/POST | `children` | Мои дети / создать ребёнка (права `children.view` / `children.manage`, см. [AUTH.md](AUTH.md#родитель-и-дети)) |
| GET/PUT/DELETE | `children/{child}` | Ребёнок: просмотр, профиль, удаление |
| PUT | `children/{child}/password` | Сброс пароля ребёнку |

URI задаются enum `App\Enums\Uri`.

## Профиль пользователя

`PUT /user/{user}` — свой профиль правит каждый, чужой — право `users.manage` (администраторов — только admin). Поля: `name` (обязательно), `email` (обязателен, если у пользователя нет логина), `password`, `avatar_path`; логин, роли и родители здесь не меняются.

- Смена email (в том числе первый email у ребёнка со входом по логину) сбрасывает `email_verified_at` в `null` и ставит письмо подтверждения на новый адрес в очередь fuisic-auth (`SendVerificationEmailJob`). В ответе — `email_verified_at: null`.
- Пока новый адрес не подтверждён, `POST /login` по нему — 403 (как после регистрации), по старому — 401; ссылка из письма на старый адрес новый не подтверждает. Повторно письмо — `POST /email/verify/resend`. Вход по логину (`login` без `@`) не затронут.
- Уже выданные токены продолжают работать.
- Сохранение без смены email подтверждение не трогает и письма не шлёт. Удаление email (только у аккаунта с логином) сбрасывает подтверждение без письма.
- То же при смене email в админке (`/admin/user`) — правило в модели `User` (хуки `updating` / `updated`), а не в контроллере.

## Вопросы теста

Задача (`task`) — вопрос одного из типов (`App\Enums\TaskType`, описание типа — `App\Support\Tasks\*`):

| `type` | Ответ задаётся |
|---|---|
| `single` | `options`: 2–10 вариантов, ровно один `is_correct` |
| `multiple` | `options`: 2–10 вариантов, хотя бы один `is_correct` |
| `text` | `settings.answers`: 1–20 допустимых ответов |
| `number` | `settings`: `value`, `tolerance` (null — точно), `tolerance_type` `absolute`/`percent`, `units` (пусто — без единиц); «0,5» = «0.5» |

Общие поля: `problem_statement` (формулы — текст в `$…$`; обязательно, если нет картинки), `image_path` (путь из `POST /files` с `purpose=task_image`), `points` (0–100, по умолчанию 1), `explanation` (разбор после сдачи), `shuffle_options`. У варианта — `text` и/или `image_path`, `is_correct`; порядок — по массиву.

- `POST /task` / `PUT /task/{task}` — вопрос целиком. Не переданное поле не меняется; `options` заменяют все варианты (с `id` — изменить, без — добавить, отсутствующие удаляются; у варианта с `id` без `image_path` картинка остаётся). Смена типа на `text`/`number` удаляет варианты.
- Старый формат студии `{test_id, problem_statement, answer}` работает: без `type` создаётся `text` с одним ответом; `answer` без `settings` при изменении — единственный ответ `text` или значение `number` (допуск и единицы сохраняются); тот же `answer` ничего не меняет. В ответе редактора `answer` — правильный ответ строкой (устарело).
- Правильные ответы (`settings`, `is_correct`, `answer`) и `explanation` отдаются только редактору (автор, `catalog.manage` для каталога, admin): `GET /task`, `GET /task/{task}`, ответы `POST/PUT`. При прохождении (`GET /test/{test}/tasks`, `GET /task/{task}` не редактору) — `ShortTask`: `id`, `type`, `problem_statement`, `image_url`, `points`, `options` (`id`, `text`, `image_url`; при `shuffle_options` — в случайном порядке).
- Проверка ответов — `POST /test/{test}/answers`, см. [Проверка ответов](#проверка-ответов).
- Админка: `/admin/task` — просмотр вопросов (тип, настройки, варианты) и правка условия, баллов, разбора, перемешивания; тип и варианты — через API.

## Проверка ответов

`POST /test/{test}/answers` — `{time, answers: [...]}`, по одному элементу на вопрос (`task_id` не повторяется, иначе 422). Проверка только на сервере — методом типа вопроса (`App\Support\Tasks\QuestionType::check`).

| `type` | Поле ответа | Как проверяется |
|---|---|---|
| `single` | `option_id`: id варианта | верно, если выбран верный вариант |
| `multiple` | `option_ids`: id вариантов (`[]` — ничего не выбрано) | доля = (выбрано верных − выбрано неверных) / всего верных, не меньше 0; повторы считаются один раз, id не из этого вопроса не учитываются |
| `text` | `text`: строка | совпадает с любым из `settings.answers` без учёта регистра, краевых и повторных пробелов (в т.ч. неразрывных), «ё» = «е» |
| `number` | `value`: число или строка | см. ниже |

**Число** (`value` строкой): десятичный разделитель — запятая или точка («0,5» = «0.5»), пробел или неразрывный пробел — разделитель разрядов по 3 цифры («1 000» = «1000»; «1 0000» — не число), знак `+`/`-`/`−`, запись «1,5e3». После числа — необязательная единица («9,8 м/с²», «2,5кН»).
- Допуск: `tolerance` при `tolerance_type=absolute` — |ответ − value| ≤ tolerance, при `percent` — ≤ |value| × tolerance / 100; граница входит; `tolerance: null` — точное совпадение.
- Единицы: у вопроса заданы `units` — ответ с единицей не из списка неверен, **ответ без единицы принимается**; у вопроса без `units` ответ с единицей неверен. Единицы сравниваются без учёта регистра и пробелов, «ё» = «е», «м/с²» = «м/с^2» = «м/с2», «Н·м» = «Н*м» = «Н⋅м».

Ответ другого формата, пустой или не число — неверен (0 баллов). Устаревшее поле `answer` (строка, клиенты до fuisic-front#29) работает для всех типов, если нет поля своего типа: `single`/`multiple` — id вариантов через запятую, `text`/`number` — как `text`/`value`. Неверный тип поля (`option_ids` не массив, `text` не строка и т. п.) — 422.

Ответ:

| Поле | Значение |
|---|---|
| `total_score` | сумма `score`, до 2 знаков (целое — без дробной части: `4`, не `4.0`) |
| `max_score` | сумма `points` всех вопросов теста, в том числе без ответа |
| `results[]` | `task` (`ShortTask`), `answer` (ответ строкой), `correct_answer`, `is_correct`, `score` (до 2 знаков), `max_score` (`points` вопроса), `status`: `correct` / `partial` (часть баллов, `multiple`) / `incorrect` |

Вопрос не из этого теста — `task: null`, `correct_answer: null`, `max_score: 0`, `status: incorrect`. Разбор (`explanation`) здесь не отдаётся; `correct_answer` пока получает любой, кто может открыть тест — ограничит сохранение попыток (fuisic-back#62).

## Проверенный учитель

Учитель (роль `teacher`) подаёт заявку: `full_name`, `workplace_type` (`Школа` / `Учебный центр` / `Частная практика`), `workplace_name`, `subjects` (1–10 строк), `link` и `comment` — необязательно. Сканы документов не принимаются.

- Статусы (`status`): `pending` → `approved` / `rejected`; `approved` → `revoked`. Новая заявка — если заявок не было или последняя `rejected` / `revoked`; одна `pending` за раз.
- Решение принимают в админке (`/admin/teacher-verification`, право `teachers.verify`); `reviewer_comment` виден учителю, при отказе и отзыве обязателен. Кто проверял — учителю не отдаётся.
- Одобрение выдаёт пользователю прямое право `catalog.submit`, отзыв — забирает. Письмо о решении уходит через очередь (`App\Notifications\TeacherVerificationDecided`).
- `GET /me`: `teacher_verified` и `teacher_verification: {status, reviewer_comment, submitted_at, reviewed_at} | null` (последняя заявка). У автора материалов (`user` в наборах и тестах) — `teacher_verified`.

## Настройки пользователя

`GET /settings` и `PUT /settings` — только свои (id в URI нет). `PUT` — частичное обновление: меняются только переданные поля, пустое тело ничего не меняет. То же, что `GET /settings`, отдаётся в `GET /me` полем `settings`.

| Поле | Тип | По умолчанию | Валидация |
|------|-----|--------------|-----------|
| `timezone` | string | `UTC` | IANA (`timezone:all_with_bc`: принимаются и устаревшие имена вроде `Asia/Calcutta`), с учётом регистра |
| `timezone_set` | bool, только чтение | `false` | `false`, пока клиент не передал пояс: приложению стоит отправить пояс устройства |
| `new_cards_per_day` | int | `20` | `0…new_cards_per_day_max` |
| `new_cards_per_day_max` | int, только чтение | `200` | потолок из `App\Support\RepetitionLimits` |
| `reminder_hour` | int | `19` | `0…23`, час по `timezone` |
| `email_reminders` | bool | `false` | согласие на email-напоминания |

- Хранение — таблица `user_settings` (1:1, строка создаётся при первом `PUT`; до этого — значения по умолчанию из модели `UserSetting`).
- Лимиты повторений (потолок новых карточек в день, наборов в повторениях — пока без ограничения) задаются только в `App\Support\RepetitionLimits`; фактический лимит новых — `min(настройка, потолок)`.
- «День» повторений — с 04:00 до 04:00 по `timezone`: `App\Support\LocalDay::current($tz)` / `$user->settings->currentDay()` возвращает локальную дату и границы в UTC (в дни перехода времени — 23 или 25 часов).

## Auth API

Не дублируется здесь — см. [fuisic-auth/docs/API.md](https://github.com/Johny314/fuisic-auth/blob/main/docs/API.md):

- `/register`, `/login`, `/logout`, `/me`
- `/email/verify/*`, `/password/*`
- `/oauth/*`, `/passkeys/*`

## Admin

Backpack CRUD: `/admin/*` (session auth, роль admin).

- `/admin/teacher-verification` — очередь заявок учителей (фильтр `?status=pending|approved|rejected|revoked|all`, по умолчанию `pending`), одобрение / отказ / отзыв статуса; закрыта правом `teachers.verify`.

## Health

```
GET /up
```

Laravel health check — 200 OK.
