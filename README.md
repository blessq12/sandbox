# Sandbox - Система тестирования API через сценарии

**Sandbox** - это мощная CLI система для тестирования API приложений через YAML сценарии. Позволяет быстро создавать, запускать и анализировать тесты для любых веб-приложений.

## 🚀 Что это такое?

Sandbox - это инструмент для:
- **Тестирования API** через HTTP запросы
- **Автоматизации тестов** с помощью YAML сценариев  
- **Генерации тестовых данных** с помощью Faker
- **Анализа результатов** с детальными отчетами
- **Управления проектами** и их тестированием

## 🏗️ Архитектура

Система построена по принципам **чистой архитектуры** с четким разделением слоев:

```
src/
├── Application/Console/     # CLI команды и ConsoleKernel
├── Application/Orchestrator/ # Оркестрация выполнения сценариев
├── Domain/Scenario/         # Доменные объекты (Scenario, FakerResolver)
├── Infrastructure/          # Реализация интерфейсов
│   ├── Project/            # ProjectRegistry (управление проектами)
│   ├── Scenario/           # ScenarioLoader (загрузка YAML)
│   ├── Runner/             # HttpRunner, CliRunner (исполнители)
│   ├── Storage/            # RunStorage (сохранение результатов)
│   └── Server/             # ServerManager (HTTP серверы)
└── Shared/                 # Общие утилиты
```

## 📦 Установка

1. **Клонируй репозиторий:**
```bash
git clone <repository-url>
cd sandbox
```

2. **Установи зависимости:**
```bash
composer install
```

3. **Сделай исполняемым:**
```bash
chmod +x sandbox
```

## 🎯 Быстрый старт

### 1. Добавь проект
```bash
./sandbox project:add myapi /path/to/your/project --title "My API"
```

### 2. Создай тест-кейс
```yaml
# tests/myapi/scenarios/auth.yaml
steps:
  - type: http
    method: POST
    path: /api/auth/login
    headers:
      Accept: application/json
      Content-Type: application/json
    body:
      email: faker.email:user_email
      password: faker.password:user_password
    extract:
      token: data.response.access_token
```

### 3. Запусти тест
```bash
./sandbox scenario:run myapi auth --save-report
```

## 📋 Команды

### Управление проектами
```bash
# Список всех команд
./sandbox list

# Список проектов
./sandbox project:list

# Добавить проект
./sandbox project:add NAME PATH [options]
  --title "Название проекта"
  --entry-point "public/index.php"
  --no-link                    # Не создавать symlink
  --force                      # Перезаписать существующие файлы

# Список сценариев проекта
./sandbox scenario:list PROJECT
```

### Выполнение тестов
```bash
# Запустить один сценарий
./sandbox scenario:run PROJECT SCENARIO [--save-report]

# Запустить все сценарии проекта
./sandbox scenario:run-all PROJECT [--save-reports]
```

## 📁 Структура проекта

```
sandbox/
├── projects/                 # Symlinks на тестируемые проекты
│   ├── myapi -> /path/to/myapi
│   └── another -> /path/to/another
├── config/projects/          # YAML конфиги проектов
│   ├── myapi.yaml
│   └── another.yaml
├── tests/                    # Тест-кейсы
│   ├── myapi/
│   │   ├── scenarios/        # YAML сценарии
│   │   │   ├── auth.yaml
│   │   │   ├── crud.yaml
│   │   │   └── upload.yaml
│   │   └── routes/           # Маршруты (опционально)
│   └── another/
├── result/                   # Результаты тестов
│   └── PROJECT_SCENARIO_TIMESTAMP/
│       ├── result.json
│       └── step_N_body.json
└── quick-templates/          # Шаблоны для быстрого создания
    ├── auth.yaml
    ├── crud.yaml
    ├── upload.yaml
    └── quick-create.sh
```

## 📝 Создание тест-кейсов

### Структура YAML сценария

```yaml
steps:
  - type: http                    # Тип шага: http или cli
    method: GET|POST|PUT|DELETE   # HTTP метод
    path: /api/endpoint           # Путь к эндпоинту
    headers:                      # Заголовки запроса
      Accept: application/json
      Content-Type: application/json
      Authorization: "Bearer {{token}}"
    body:                         # Тело запроса
      field: value
      email: faker.email:user_email
    extract:                      # Извлечение данных из ответа
      token: data.response.access_token
      user_id: data.response.uuid
```

### Типы шагов

#### HTTP шаги
```yaml
- type: http
  method: POST
  path: /api/v1/users
  headers:
    Accept: application/json
    Content-Type: application/json
  body:
    name: faker.firstName:user_name
    email: faker.email:user_email
  extract:
    user_id: data.response.id
```

#### Загрузка файлов (multipart)
```yaml
- type: http
  method: POST
  path: /api/upload
  headers:
    Content-Type: multipart/form-data
  body:
    file: "file:/absolute/path/to/file.png"
    description: faker.sentence:upload_desc
```

#### CLI шаги
```yaml
- type: cli
  command: "php artisan migrate"
  args: ["--force"]
```

### Переменные и контекст

#### Извлечение данных
```yaml
extract:
  token: data.response.access_token
  user_id: data.response.user.id
  items: data.response.items[*].id
```

#### Использование переменных
```yaml
body:
  email: "{{user_email}}"
  user_id: "{{user_id}}"
headers:
  Authorization: "Bearer {{token}}"
```

#### Faker генерация
```yaml
body:
  # Генерирует и сохраняет в переменную
  email: faker.email:user_email
  password: faker.password:user_password
  phone: phone_number:user_phone
  name: first_name:user_name
  surname: surname:user_surname
  company: faker.company:company_name
```

### Доступные Faker алиасы

- `email` → `faker.email()`
- `password` → `faker.password()`
- `phone_number` → `+7XXXXXXXXXX`
- `surname` → русские фамилии
- `first_name` → русские имена
- `company` → `faker.company()`
- `userName` → `faker.userName()`
- `city`, `country`, `address` → географические данные

### Сложные схемы данных

```yaml
body:
  # Генерация объекта
  user: 
    _faker: object
    schema:
      name: faker.firstName
      email: faker.email
      age: faker.numberBetween

  # Генерация массива
  items:
    _faker: array
    of:
      title: faker.sentence
      price: faker.numberBetween
    count: 3
```

## 🚀 Быстрое создание тест-кейсов

### Использование шаблонов
```bash
# Создать тест авторизации
./quick-create.sh myapi auth

# Создать CRUD тест
./quick-create.sh myapi crud

# Создать тест загрузки файлов
./quick-create.sh myapi upload

# Создать полный API flow
./quick-create.sh myapi flow
```

### Доступные шаблоны
- **auth** - Авторизация (регистрация + логин)
- **crud** - CRUD операции (создание, чтение, обновление, удаление)
- **upload** - Загрузка файлов
- **flow** - Полный API flow с переменными

## 📊 Анализ результатов

### Консольный вывод
```
Выполнение сценария: myapi/auth
---------------------------------------

 3/3 [▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓] 100% Шаг 2: http ✓ (55ms)

Результаты выполнения шагов:
----------------------------
 ------- ------ -------- ------- ---------- -------- 
  Шаг     Тип    Статус   Время   HTTP       Ошибка  
 ------- ------ -------- ------- ---------- -------- 
  Шаг 0   http   ✓        194мс   HTTP 200   -       
  Шаг 1   http   ✓        40мс    HTTP 200   -       
  Шаг 2   http   ✓        55мс    HTTP 200   -       
 ------- ------ -------- ------- ---------- -------- 

 -------------------- ---------- 
  Метрика              Значение  
 -------------------- ---------- 
  Общее время          0.303с    
  Шагов выполнено      3         
  Успешных             3         
  Неудачных            0         
  Среднее время шага   96мс      
 -------------------- ---------- 
```

### Детальные отчеты
При использовании `--save-report` создаются файлы:
- `result.json` - полный отчет с метриками
- `step_N_body.json` - сырые ответы каждого шага

### Структура отчета
```json
{
  "project": "myapi",
  "scenario": "auth",
  "result": {
    "ok": true,
    "steps": [...],
    "metrics": {
      "scenario": {
        "total_duration_seconds": 0.303
      },
      "steps": {
        "count": 3,
        "successful": 3,
        "failed": 0,
        "average_duration_ms": 96
      }
    }
  }
}
```

## 🔧 Конфигурация проектов

### YAML конфиг проекта
```yaml
# config/projects/myapi.yaml
name: myapi
title: "My API Project"
projectRoot: /path/to/myapi
entryPoint: public/index.php
tests:
  scenariosDir: tests/myapi/scenarios
  routesDir: tests/myapi/routes
env: {}
added_at: "2025-09-25T13:58:12+00:00"
```

### Настройка сервера
Sandbox автоматически управляет HTTP серверами для тестирования:
- Автоматический запуск/остановка
- Инъекция `baseUrl` в контекст сценариев
- Поддержка разных entry points

## 🎯 Примеры использования

### Полный цикл тестирования
```bash
# 1. Добавить проект
./sandbox project:add ecommerce /path/to/ecommerce --title "E-commerce API"

# 2. Создать тесты
./quick-create.sh ecommerce auth
./quick-create.sh ecommerce crud

# 3. Запустить все тесты
./sandbox scenario:run-all ecommerce --save-reports

# 4. Анализировать результаты
ls -la result/
```

### Сложный сценарий с переменными
```yaml
# tests/ecommerce/scenarios/order_flow.yaml
steps:
  # Регистрация пользователя
  - type: http
    method: POST
    path: /api/auth/register
    body:
      email: faker.email:user_email
      password: faker.password:user_password
    extract:
      user_token: data.response.access_token
      user_id: data.response.uuid

  # Создание товара
  - type: http
    method: POST
    path: /api/products
    headers:
      Authorization: "Bearer {{user_token}}"
    body:
      title: faker.sentence:product_title
      price: faker.numberBetween:product_price
    extract:
      product_id: data.response.id

  # Создание заказа
  - type: http
    method: POST
    path: /api/orders
    headers:
      Authorization: "Bearer {{user_token}}"
    body:
      items:
        - product_id: "{{product_id}}"
          quantity: 2
      total: "{{product_price}}"
    extract:
      order_id: data.response.id

  # Получение заказа
  - type: http
    method: GET
    path: /api/orders/{{order_id}}
    headers:
      Authorization: "Bearer {{user_token}}"
```

## 🛠️ Troubleshooting

### Частые проблемы

**Сервер не запускается:**
- Проверь `entryPoint` в конфиге проекта
- Убедись что путь к проекту корректен
- Проверь права доступа к директории

**401/403 ошибки:**
- Проверь правильность токенов авторизации
- Убедись что API требует авторизацию
- Проверь заголовки `Authorization`

**500 ошибки:**
- Смотри детальные отчеты в `result/`
- Проверь структуру данных в `body`
- Убедись что API принимает такие данные

**Переменные не работают:**
- Проверь правильность `extract` путей
- Убедись что переменные сохраняются между шагами
- Используй `{{variable}}` для подстановки

### Отладка
```bash
# Запустить с детальным отчетом
./sandbox scenario:run myapi auth --save-report

# Посмотреть сырые ответы
cat result/myapi_auth_TIMESTAMP/step_0_body.json

# Проверить конфиг проекта
cat config/projects/myapi.yaml
```

## 🤝 Вклад в проект

1. Fork репозитория
2. Создай feature branch
3. Внеси изменения
4. Добавь тесты
5. Создай Pull Request

## 📄 Лицензия

MIT License

## 🎉 Заключение

Sandbox - это мощный инструмент для тестирования API, который позволяет:
- Быстро создавать тест-кейсы
- Автоматизировать тестирование
- Анализировать результаты
- Интегрироваться в CI/CD

**Начни тестировать свои API уже сегодня!** 🚀
