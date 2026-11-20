# Ride Booking API

PHP 8.5, Laravel 13, Sanctum 4, PostgreSQL и PHPUnit 12. Версия HTTP API — `/api/v1`.

## Запуск

Нужны PHP 8.5 с `pdo_pgsql`, Composer и работающий PostgreSQL. Создайте роль `ride_booking` с правом входа и две базы с этой ролью в качестве владельца. Пример из окружения администратора PostgreSQL:

```sh
createuser --pwprompt ride_booking
createdb --owner=ride_booking ride_booking
createdb --owner=ride_booking ride_booking_test
```

Из корня репозитория:

```sh
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

Укажите пароль роли в `.env`, затем примените миграции и запустите сервер:

```sh
php artisan migrate
php artisan serve
```

Проверка сервера и справочника:

```sh
curl -i http://127.0.0.1:8000/up
curl 'http://127.0.0.1:8000/api/v1/cities?q=rezh'
```

## HTTP API

Отправляйте заголовок `Accept: application/json`, а для JSON-тела — `Content-Type: application/json`. Защищённые операции требуют `Authorization: Bearer <token>`. Токен возвращается в `data.token` при регистрации и входе и действует 24 часа. `POST /auth/logout` отзывает текущий токен.

Пути в таблице указаны относительно `/api/v1`.

| Метод и путь | Назначение и входные данные |
| --- | --- |
| `POST /auth/register` | Регистрация. Поля `name`, `email`, `password`. |
| `POST /auth/login` | Вход. Поля `email`, `password`. |
| `GET /me`, `PATCH /me` | Просмотр своего профиля и обновление `name`. |
| `PUT /me/driver` | Профиль водителя. Поля `experience_years` и `vehicle` с данными `make`, `model`, `color`, `passenger_seats`. |
| `GET /cities?q=...` | Поиск города по имени или идентификатору. Без `q` возвращает справочник. |
| `POST /trips` | Публикация. Поля `from`, `to`, необязательный массив `stops`, `departure_at`, `price_kopecks`, `seats`, `luggage_allowed`. |
| `GET /trips` | Поиск. Фильтры `from`, `to`, `date`, `timezone`, `max_price_kopecks`, `seats`, `luggage_allowed` (0/1), `min_rating`, `page`. |
| `GET /trips/{trip}` | Маршрут в порядке следования с координатами, водитель, цена и свободные места. |
| `POST /trips/{trip}/bookings` | Запрос брони. Поле `seats`. |
| `GET /bookings` | Свои брони. |
| `GET /trips/{trip}/bookings` | Брони поездки для её водителя. |
| `GET /trips/{trip}/bookings/{booking}` | Бронь и история для пассажира этой брони и водителя поездки. |
| `POST /trips/{trip}/bookings/{booking}/confirm` | Подтвердить запрос. Доступно водителю поездки. |
| `POST /trips/{trip}/bookings/{booking}/reject` | Отклонить запрос. Доступно водителю поездки. |
| `POST /trips/{trip}/bookings/{booking}/cancel` | Отменить запрос в состоянии `REQUESTED`. Доступно его автору. |
| `GET /trips/{trip}/participants` | Водитель и подтверждённые пассажиры. Доступно участникам. |
| `GET /trips/{trip}/messages` | Последние 50 сообщений в порядке отправки. Для опроса передайте `after_id`. |
| `POST /trips/{trip}/messages` | Сообщение в чате. Поле `body`. |
| `PUT /trips/{trip}/bookings/{booking}/rating` | Оценка водителя от 1 до 5 в поле `score`. Доступно пассажиру подтверждённой брони после отправления. |

`departure_at` при публикации принимает ISO 8601 со смещением, например `2027-01-15T10:00:00+05:00`. Выберите будущую дату. API возвращает время в UTC. Фильтр `date` принимает `YYYY-MM-DD` и определяет сутки в часовом поясе `timezone`, по умолчанию `Asia/Yekaterinburg`. Если заданы обе точки поиска, они должны встречаться в правильном порядке. Точка поиска может быть промежуточной. Цена хранится целым количеством копеек. Для водителя без оценок API возвращает `null`.

### Правила бронирования

Пассажир создаёт `REQUESTED`. Водитель переводит запрос в `CONFIRMED` или `REJECTED`, пассажир может перевести свой запрос в `CANCELLED`. У пассажира может быть одна активная бронь на поездку. Создание запроса проверяет остаток. Места списываются при подтверждении.

При подтверждении PostgreSQL блокирует строку поездки через `SELECT … FOR UPDATE`. После получения блокировки сервис повторно проверяет остаток и выполняет списание, смену статуса и запись аудита в одной транзакции. Конкурирующий запрос видит обновлённый остаток и получает `409 Conflict`, если мест уже недостаточно. Повтор успешного подтверждения возвращает существующий результат без повторного списания. Ограничение БД проверяет `0 ≤ available_seats ≤ total_seats`.

## Тесты

`phpunit.xml` по умолчанию использует `ride_booking_test`. Хост, порт и роль берутся из `.env`. Используйте отдельную тестовую базу с суффиксом `_test`. Тесты пересоздают её схему.

```sh
php artisan test
php artisan test --filter=BookingConcurrencyTest
```

В конкурентном тесте два PHP-процесса отправляют отдельные запросы через HTTP-ядро Laravel. Тест дожидается, пока PostgreSQL зарегистрирует оба запроса в ожидании блокировки. После снятия блокировки проверяются коды `200` и `409`, остаток 0, статусы обеих броней и история.
