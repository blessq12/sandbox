#!/bin/bash

# Быстрое создание тест-кейсов для Sandbox
# Использование: ./quick-create.sh PROJECT SCENARIO_TYPE

PROJECT=$1
SCENARIO_TYPE=$2

if [ -z "$PROJECT" ] || [ -z "$SCENARIO_TYPE" ]; then
    echo "Использование: $0 PROJECT SCENARIO_TYPE"
    echo ""
    echo "Доступные типы:"
    echo "  auth      - Авторизация (регистрация + логин)"
    echo "  crud      - CRUD операции"
    echo "  upload    - Загрузка файлов"
    echo "  flow      - Полный API flow"
    echo ""
    echo "Примеры:"
    echo "  $0 myapi auth"
    echo "  $0 myapi crud"
    echo "  $0 myapi upload"
    echo "  $0 myapi flow"
    exit 1
fi

# Проверяем что проект существует
if [ ! -d "tests/$PROJECT" ]; then
    echo "❌ Проект $PROJECT не найден. Сначала добавьте проект:"
    echo "   ./sandbox project:add $PROJECT /path/to/project"
    exit 1
fi

# Копируем шаблон
TEMPLATE_FILE="quick-templates/$SCENARIO_TYPE.yaml"
SCENARIO_FILE="tests/$PROJECT/scenarios/$SCENARIO_TYPE.yaml"

if [ ! -f "$TEMPLATE_FILE" ]; then
    echo "❌ Шаблон $SCENARIO_TYPE не найден"
    echo "Доступные: auth, crud, upload, flow"
    exit 1
fi

cp "$TEMPLATE_FILE" "$SCENARIO_FILE"
echo "✅ Создан сценарий: $SCENARIO_FILE"

echo ""
echo "🚀 Теперь можно запустить:"
echo "   ./sandbox scenario:run $PROJECT $SCENARIO_TYPE"
echo "   ./sandbox scenario:run $PROJECT $SCENARIO_TYPE --save-report"
