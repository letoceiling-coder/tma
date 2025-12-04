<template>
    <div class="documentation-page">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-foreground">Документация</h1>
            <p class="text-muted-foreground mt-1">Техническая документация по системе WOW Рулетка</p>
        </div>

        <div class="space-y-6">
            <!-- Навигация по разделам -->
            <div class="bg-card rounded-lg border border-border p-4">
                <h2 class="text-lg font-semibold mb-4">Разделы документации</h2>
                <nav class="flex flex-wrap gap-2">
                    <button
                        v-for="section in sections"
                        :key="section.id"
                        @click="activeSection = section.id"
                        :class="[
                            'px-4 py-2 rounded-lg text-sm font-medium transition-colors',
                            activeSection === section.id
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-muted text-muted-foreground hover:bg-muted/80'
                        ]"
                    >
                        {{ section.title }}
                    </button>
                </nav>
            </div>

            <!-- Содержимое документации -->
            <div class="bg-card rounded-lg border border-border p-6">
                <div v-html="currentContent" class="prose prose-sm max-w-none dark:prose-invert"></div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'Documentation',
    data() {
        return {
            activeSection: 'database',
            sections: [
                { id: 'database', title: 'База данных' },
                { id: 'api', title: 'API Endpoints' },
                { id: 'admin', title: 'Админ-панель' },
                { id: 'frontend', title: 'Frontend' },
                { id: 'telegram', title: 'Telegram интеграция' },
                { id: 'notifications', title: 'Уведомления' },
                { id: 'commands', title: 'Команды' },
            ],
            documentation: {
                database: `
                    <h2>Структура базы данных</h2>
                    <h3>Таблица: users</h3>
                    <p>Расширенная таблица пользователей с поддержкой Telegram:</p>
                    <ul>
                        <li><strong>telegram_id</strong> (bigint, unique) - ID пользователя в Telegram</li>
                        <li><strong>username</strong> (string) - Username пользователя</li>
                        <li><strong>avatar_url</strong> (string) - URL аватара</li>
                        <li><strong>stars_balance</strong> (integer, default: 0) - Баланс Telegram Stars</li>
                        <li><strong>tickets_available</strong> (integer, default: 0) - Доступные билеты для прокрута</li>
                        <li><strong>last_spin_at</strong> (timestamp) - Время последнего прокрута</li>
                        <li><strong>invited_by</strong> (foreign key to users) - Кто пригласил пользователя</li>
                        <li><strong>total_spins</strong> (integer, default: 0) - Всего прокрутов</li>
                        <li><strong>total_wins</strong> (integer, default: 0) - Всего выигрышей</li>
                    </ul>

                    <h3>Таблица: channels</h3>
                    <p>Обязательные Telegram-каналы для подписки:</p>
                    <ul>
                        <li><strong>username</strong> (string, unique) - @username канала</li>
                        <li><strong>title</strong> (string) - Название канала</li>
                        <li><strong>is_active</strong> (boolean, default: true) - Активен ли канал</li>
                        <li><strong>priority</strong> (integer, default: 0) - Приоритет для сортировки</li>
                    </ul>

                    <h3>Таблица: wheel_sectors</h3>
                    <p>Конфигурация секторов рулетки:</p>
                    <ul>
                        <li><strong>sector_number</strong> (integer, unique, 1-12) - Номер сектора</li>
                        <li><strong>prize_type</strong> (enum: money, ticket, secret_box, empty) - Тип приза</li>
                        <li><strong>prize_value</strong> (integer, default: 0) - Значение приза (для денег: 300, 500, 1000 и т.д.)</li>
                        <li><strong>icon_url</strong> (string, nullable) - URL иконки сектора</li>
                        <li><strong>probability_percent</strong> (decimal 5,2, default: 0) - Вероятность выпадения (0-100%)</li>
                        <li><strong>is_active</strong> (boolean, default: true) - Активен ли сектор</li>
                    </ul>
                    <p><strong>Важно:</strong> Сумма всех probability_percent должна быть равна 100%.</p>

                    <h3>Таблица: wheel_settings</h3>
                    <p>Глобальные настройки рулетки:</p>
                    <ul>
                        <li><strong>always_empty_mode</strong> (boolean, default: false) - Режим "всегда пусто" (колесо всегда останавливается на пустом секторе)</li>
                        <li><strong>ticket_restore_hours</strong> (integer, default: 3) - Период восстановления билета в часах (от 1 до 24)</li>
                    </ul>
                    <p><strong>Примечание:</strong> В таблице всегда одна запись (id = 1). Настройки можно изменить через админ панель.</p>

                    <h3>Таблица: spins</h3>
                    <p>История прокрутов рулетки:</p>
                    <ul>
                        <li><strong>user_id</strong> (foreign key to users) - Пользователь</li>
                        <li><strong>spin_time</strong> (timestamp) - Время прокрута</li>
                        <li><strong>prize_type</strong> (enum) - Тип выигранного приза</li>
                        <li><strong>prize_value</strong> (integer) - Значение приза</li>
                        <li><strong>sector_id</strong> (foreign key to wheel_sectors) - Сектор, на котором остановилось колесо</li>
                    </ul>

                    <h3>Таблица: referrals</h3>
                    <p>Реферальные связи между пользователями:</p>
                    <ul>
                        <li><strong>inviter_id</strong> (foreign key to users) - Кто пригласил</li>
                        <li><strong>invited_id</strong> (foreign key to users) - Кого пригласили</li>
                        <li><strong>invited_at</strong> (timestamp) - Когда был приглашен</li>
                    </ul>
                    <p><strong>Ограничение:</strong> Уникальная пара (inviter_id, invited_id) - один пользователь не может быть приглашен одним человеком дважды.</p>

                    <h3>Таблица: user_tickets</h3>
                    <p>История билетов пользователей:</p>
                    <ul>
                        <li><strong>user_id</strong> (foreign key to users) - Пользователь</li>
                        <li><strong>tickets_count</strong> (integer, default: 1) - Количество билетов</li>
                        <li><strong>restored_at</strong> (timestamp, nullable) - Время восстановления</li>
                        <li><strong>source</strong> (enum: free, star_exchange) - Источник билета</li>
                    </ul>

                    <h3>Таблица: star_exchanges</h3>
                    <p>История обмена Telegram Stars на билеты:</p>
                    <ul>
                        <li><strong>user_id</strong> (foreign key to users) - Пользователь</li>
                        <li><strong>stars_amount</strong> (integer) - Количество потраченных звёзд</li>
                        <li><strong>tickets_received</strong> (integer) - Полученных билетов (обычно 20)</li>
                        <li><strong>transaction_id</strong> (string, unique, nullable) - ID транзакции от Telegram</li>
                        <li><strong>status</strong> (enum: pending, completed, failed) - Статус транзакции</li>
                    </ul>

                    <h3>Таблица: leaderboard_snapshots</h3>
                    <p>Снимки лидерборда за каждый месяц:</p>
                    <ul>
                        <li><strong>user_id</strong> (foreign key to users) - Пользователь</li>
                        <li><strong>month</strong> (integer, 1-12) - Месяц</li>
                        <li><strong>year</strong> (integer) - Год</li>
                        <li><strong>invites_count</strong> (integer, default: 0) - Количество приглашений</li>
                        <li><strong>rank</strong> (integer, nullable) - Позиция в рейтинге</li>
                        <li><strong>prize_amount</strong> (integer, default: 0) - Размер приза</li>
                        <li><strong>prize_paid</strong> (boolean, default: false) - Выплачен ли приз</li>
                    </ul>
                    <p><strong>Ограничение:</strong> Уникальная пара (user_id, month, year).</p>

                    <h3>Seeder для начальных данных</h3>
                    <p>Для создания 12 секторов рулетки с начальными настройками выполните:</p>
                    <pre><code>php artisan db:seed --class=WheelSectorSeeder</code></pre>
                    <p>Seeder создаст 12 секторов с распределением вероятностей:</p>
                    <ul>
                        <li>7 секторов "Пусто" - 63% вероятности</li>
                        <li>4 сектора с денежными призами (300₽, 500₽, 1000₽) - 26% вероятности</li>
                        <li>1 сектор с билетом - 5% вероятности</li>
                        <li>Общая вероятность: 100%</li>
                    </ul>
                `,
                api: `
                    <h2>API Endpoints</h2>
                    <p>Все API endpoints используют префикс <code>/api</code>. Для защиты используется middleware <code>telegram.initdata</code>, который проверяет валидность Telegram initData.</p>

                    <h3>Проверка подписки на каналы</h3>
                    <ul>
                        <li><strong>GET /api/check-subscription/{channelUsername}</strong> - Проверка подписки на конкретный канал
                            <ul>
                                <li>Требует: заголовок <code>X-Telegram-Init-Data</code> или query параметр <code>initData</code></li>
                                <li>Возвращает: <code>{is_subscribed: bool, status: string}</code></li>
                            </ul>
                        </li>
                        <li><strong>GET /api/check-all-subscriptions</strong> - Проверка подписки на все обязательные каналы
                            <ul>
                                <li>Возвращает: <code>{all_subscribed: bool, channels: array}</code></li>
                                <li>Каждый канал содержит: <code>username</code>, <code>title</code>, <code>is_subscribed</code>, <code>status</code></li>
                            </ul>
                        </li>
                    </ul>

                    <h3>Инициализация пользователя</h3>
                    <ul>
                        <li><strong>POST /api/user/init</strong> - Инициализация/регистрация пользователя WOW при первом запуске Mini App
                            <ul>
                                <li>Требует: заголовок <code>X-Telegram-Init-Data</code> или query параметр <code>initData</code></li>
                                <li>Создает нового пользователя или обновляет существующего на основе данных из Telegram</li>
                                <li>Устанавливает начальные значения: 3 билета, 0 звёзд, обнуляет статистику</li>
                                <li>Автоматически обновляет данные из Telegram: имя, username, аватар</li>
                                <li>Возвращает: <code>{success: bool, user: object, is_new_user: bool}</code></li>
                                <li><strong>Рекомендуется вызывать</strong> сразу после успешной проверки подписки на каналы</li>
                                <li>Альтернатива: пользователь автоматически создается при первом запросе к <code>/api/user/tickets</code> или <code>/api/spin</code></li>
                            </ul>
                        </li>
                    </ul>
                    <p><strong>Процесс запуска приложения:</strong></p>
                    <ol>
                        <li>Пользователь открывает Mini App в Telegram</li>
                        <li>Проверка подписки на обязательные каналы (<code>/api/check-all-subscriptions</code>)</li>
                        <li>Инициализация пользователя (<code>/api/user/init</code>) - создание/обновление записи в БД</li>
                        <li>Загрузка конфигурации рулетки (<code>/api/wheel-config</code>)</li>
                        <li>Загрузка билетов пользователя (<code>/api/user/tickets</code>)</li>
                    </ol>

                    <h3>Рулетка</h3>
                    <ul>
                        <li><strong>GET /api/wheel-config</strong> - Получение конфигурации рулетки (публичный доступ)
                            <ul>
                                <li>Возвращает: список секторов с вероятностями и настройками</li>
                                <li>Формат: <code>{sectors: array, total_probability: number}</code></li>
                            </ul>
                        </li>
                        <li><strong>POST /api/spin</strong> - Запуск прокрута рулетки (требует middleware)
                            <ul>
                                <li>Требует: доступный билет (проверяется автоматически)</li>
                                <li>Возвращает: <code>{success: bool, spin_id: int, sector: object, rotation: number, tickets_available: int, prize_awarded: bool}</code></li>
                                <li>Автоматически: уменьшает количество билетов, создает запись в БД, начисляет приз</li>
                            </ul>
                        </li>
                    </ul>

                    <h3>Билеты</h3>
                    <ul>
                        <li><strong>GET /api/user/tickets</strong> - Получение текущего количества билетов
                            <ul>
                                <li>Возвращает: <code>{tickets_available: int, max_tickets: int, last_spin_at: timestamp}</code></li>
                                <li>Автоматически проверяет и восстанавливает билеты (каждые 2-4 часа)</li>
                                <li>Максимум билетов: 3</li>
                            </ul>
                        </li>
                        <li><strong>POST /api/tickets/restore</strong> - Ручное восстановление билетов (админ, в разработке)</li>
                    </ul>

                    <h3>Telegram Stars Exchange</h3>
                    <ul>
                        <li><strong>POST /api/stars/exchange/initiate</strong> - Инициировать обмен звёзд на билеты
                            <ul>
                                <li>Параметры: <code>{stars_amount: int, tickets_amount: int}</code> (по умолчанию 50 звёзд = 20 билетов)</li>
                                <li>Возвращает: <code>{exchange_id, stars_amount, tickets_amount}</code></li>
                                <li>Создает запись со статусом <code>pending</code></li>
                            </ul>
                        </li>
                        <li><strong>POST /api/stars/exchange/confirm</strong> - Подтверждение обмена (после успешной оплаты)
                            <ul>
                                <li>Параметры: <code>{exchange_id: int, transaction_id: string}</code></li>
                                <li>Начисляет билеты пользователю, обновляет статус на <code>completed</code></li>
                                <li>Создает запись в <code>user_tickets</code> с источником <code>star_exchange</code></li>
                            </ul>
                        </li>
                        <li><strong>GET /api/stars/exchange/history</strong> - История обменов пользователя
                            <ul>
                                <li>Возвращает последние 50 обменов</li>
                            </ul>
                        </li>
                        <li><strong>POST /api/stars/exchange/webhook</strong> - Webhook от Telegram (обработка транзакций)
                            <ul>
                                <li>Вызывается автоматически Telegram после подтверждения оплаты</li>
                                <li>Обрабатывает транзакцию и начисляет билеты</li>
                            </ul>
                        </li>
                    </ul>
                    <p><strong>Процесс обмена:</strong></p>
                    <ol>
                        <li>Фронтенд вызывает <code>initiateExchange</code> → получает <code>exchange_id</code></li>
                        <li>Фронтенд открывает Telegram Stars Invoice через <code>window.Telegram.WebApp.openInvoice()</code></li>
                        <li>Пользователь подтверждает оплату в Telegram</li>
                        <li>Telegram отправляет webhook → обрабатывается автоматически, или фронтенд вызывает <code>confirmExchange</code></li>
                        <li>Билеты начисляются пользователю</li>
                    </ol>

                    <h3>Реферальная система</h3>
                    <ul>
                        <li><strong>GET /api/referral/link</strong> - Получение реферальной ссылки пользователя
                            <ul>
                                <li>Возвращает: <code>{referral_link: string, telegram_id: int}</code></li>
                                <li>Формат ссылки: <code>{app_url}?ref={telegram_id}</code></li>
                            </ul>
                        </li>
                        <li><strong>POST /api/referral/register</strong> - Регистрация по реферальной ссылке
                            <ul>
                                <li>Параметры: <code>{referrer_id: int}</code> - telegram_id пригласившего</li>
                                <li>Создает запись в таблице <code>referrals</code></li>
                                <li>Обновляет <code>invited_by</code> у пользователя</li>
                                <li>Проверяет, что пользователь не приглашает сам себя</li>
                            </ul>
                        </li>
                        <li><strong>GET /api/referral/stats</strong> - Статистика приглашений пользователя
                            <ul>
                                <li>Возвращает: <code>{total_invites: int, current_month_invites: int}</code></li>
                            </ul>
                        </li>
                    </ul>

                    <h3>Лидерборд</h3>
                    <ul>
                        <li><strong>GET /api/leaderboard</strong> - Получение ТОП пользователей по приглашениям
                            <ul>
                                <li>Параметры: <code>month</code> (1-12, по умолчанию текущий), <code>year</code> (по умолчанию текущий), <code>limit</code> (по умолчанию 10, максимум 50)</li>
                                <li>Возвращает: <code>{leaderboard: array, month: int, year: int}</code></li>
                                <li>Каждый элемент содержит: <code>rank</code>, <code>telegram_id</code>, <code>username</code>, <code>avatar_url</code>, <code>invites_count</code>, <code>prize_amount</code></li>
                                <li>Призы: 1 место - 1500₽, 2 место - 1000₽, 3 место - 500₽</li>
                            </ul>
                        </li>
                    </ul>

                    <h3>Telegram Service</h3>
                    <p>Сервис <code>App\Services\TelegramService</code> предоставляет следующие методы:</p>
                    <ul>
                        <li><strong>validateInitData(string $initData, string $botToken): bool</strong> - Валидация подписи initData</li>
                        <li><strong>parseInitData(string $initData): ?array</strong> - Парсинг initData и извлечение данных</li>
                        <li><strong>getTelegramId(string $initData): ?int</strong> - Получение telegram_id из initData</li>
                    </ul>

                    <h3>Middleware</h3>
                    <ul>
                        <li><strong>telegram.initdata</strong> - Проверяет наличие и валидность Telegram initData
                            <ul>
                                <li>В режиме разработки (<code>APP_DEBUG=true</code>) разрешает запросы без валидации</li>
                                <li>Добавляет <code>telegram_init_data</code> в request для использования в контроллерах</li>
                            </ul>
                        </li>
                    </ul>
                `,
                admin: `
                    <h2>Админ-панель</h2>
                    <p>Админ-панель доступна по адресу <code>/admin</code> и требует авторизации.</p>

                    <h3>Разделы WOW Рулетка</h3>
                    <ul>
                        <li><strong>Каналы</strong> (<code>/admin/wow/channels</code>) - Управление обязательными каналами для подписки
                            <ul>
                                <li>Добавление/удаление каналов</li>
                                <li>Настройка приоритета (для сортировки)</li>
                                <li>Активация/деактивация каналов</li>
                                <li>Редактирование username и названия канала</li>
                                <li><strong>API:</strong> <code>GET/POST/PUT/DELETE /api/v1/wow/channels</code></li>
                            </ul>
                        </li>
                        <li><strong>Рулетка</strong> (<code>/admin/wow/wheel</code>) - Редактор секторов рулетки
                            <ul>
                                <li>Настройка каждого из 12 секторов</li>
                                <li>Установка типа приза (money, ticket, secret_box, empty)</li>
                                <li>Установка значения приза (для денежных призов)</li>
                                <li>Загрузка URL иконок секторов</li>
                                <li>Настройка вероятности выпадения (0-100%)</li>
                                <li>Валидация: сумма вероятностей должна быть = 100%</li>
                                <li>Массовое обновление всех секторов</li>
                                <li>Визуальная индикация вероятности каждого сектора</li>
                                <li><strong>API:</strong> <code>GET /api/v1/wow/wheel</code>, <code>POST /api/v1/wow/wheel/bulk-update</code></li>
                            </ul>
                        </li>
                        <li><strong>Пользователи WOW</strong> (<code>/admin/wow/users</code>) - Управление пользователями рулетки
                            <ul>
                                <li>Список всех пользователей с Telegram ID</li>
                                <li>Фильтры: поиск, дата регистрации</li>
                                <li>Просмотр профилей с детальной информацией</li>
                                <li>Баланс билетов и звёзд</li>
                                <li>Статистика: всего прокрутов, выигрышей, приглашений</li>
                                <li>История последних прокрутов</li>
                                <li>Реферальная статистика (кто пригласил, кого пригласил)</li>
                                <li>Пагинация результатов</li>
                                <li><strong>API:</strong> <code>GET /api/v1/wow/users</code>, <code>GET /api/v1/wow/users/{id}</code></li>
                            </ul>
                        </li>
                        <li><strong>Рефералы</strong> (<code>/admin/wow/referrals</code>) - Управление реферальной системой
                            <ul>
                                <li>Список всех реферальных связей</li>
                                <li>Фильтры по дате и поиску</li>
                                <li>Статистика: всего рефералов, активных приглашающих</li>
                                <li>Топ приглашающий пользователь</li>
                                <li>Информация о пригласившем и приглашенном</li>
                            </ul>
                        </li>
                        <li><strong>Статистика</strong> (<code>/admin/wow/statistics</code>) - Аналитика и метрики
                            <ul>
                                <li>Всего звёзд получено через обмены</li>
                                <li>Всего прокрутов выполнено</li>
                                <li>Распределение призов по типам (с визуализацией)</li>
                                <li>Количество активных пользователей (с прокрутами)</li>
                                <li>Общее количество пользователей</li>
                            </ul>
                        </li>
                    </ul>

                    <h3>Документация</h3>
                    <p>Этот раздел содержит техническую документацию по системе. Документация обновляется автоматически при добавлении новых функций.</p>
                `,
                frontend: `
                    <h2>Frontend (React)</h2>
                    <p>React приложение находится в директории <code>/frontend</code>.</p>

                    <h3>Структура</h3>
                    <ul>
                        <li><strong>src/pages/</strong> - Страницы приложения
                            <ul>
                                <li>Index.tsx - Главная страница (рулетка)</li>
                                <li>Friends.tsx - Реферальная система</li>
                                <li>Leaderboard.tsx - Лидерборд</li>
                                <li>HowToPlay.tsx - Инструкция (карусель из 3 слайдов)</li>
                            </ul>
                        </li>
                        <li><strong>src/components/</strong> - Компоненты
                            <ul>
                                <li>WheelComponent.tsx - Компонент рулетки</li>
                                <li>ChannelSubscriptionCheck.tsx - Проверка подписки</li>
                                <li>SpinResultPopup.tsx - Попап с результатом прокрута</li>
                                <li>SecretGiftPopup.tsx - Попап обмена звёзд</li>
                            </ul>
                        </li>
                        <li><strong>src/hooks/</strong> - React хуки
                            <ul>
                                <li>useChannelSubscription.ts - Хук для проверки подписки</li>
                                <li>useTelegramWebApp.ts - Хук для работы с Telegram WebApp API</li>
                            </ul>
                        </li>
                    </ul>

                    <h3>Механика рулетки</h3>
                    <ul>
                        <li>Колесо состоит из 12 секторов (по 30° каждый)</li>
                        <li>Результат определяется на сервере до начала анимации</li>
                        <li>Анимация вращения: 4 секунды, минимум 5 полных оборотов</li>
                        <li>Вероятность выигрыша настраивается в админ-панели</li>
                    </ul>

                    <h3>Система билетов</h3>
                    <ul>
                        <li>Максимум 3 билета у пользователя</li>
                        <li>Один билет = одна попытка прокрута</li>
                        <li>Восстановление билета: каждые 2-4 часа (настраивается)</li>
                        <li>Таймер отображается, если билетов меньше 3</li>
                    </ul>
                `,
                telegram: `
                    <h2>Интеграция с Telegram</h2>

                    <h3>Аутентификация</h3>
                    <ul>
                        <li>Использование <code>initData</code> от Telegram WebApp</li>
                        <li>Валидация подписи через <code>initDataUnsafe.hash</code></li>
                        <li>Автоматическое создание/обновление пользователя при входе</li>
                    </ul>

                    <h3>Проверка подписки на каналы</h3>
                    <ul>
                        <li>Использование Telegram Bot API метода <code>getChatMember</code></li>
                        <li>Бот должен быть администратором каналов</li>
                        <li>Кэширование результатов проверки</li>
                    </ul>

                    <h3>Telegram Stars Exchange</h3>
                    <ul>
                        <li>Интеграция с Telegram Stars Exchange API</li>
                        <li>Обмен 50 звёзд на 20 билетов</li>
                        <li>Обработка webhook'ов для подтверждения транзакций</li>
                    </ul>

                    <h3>Уведомления</h3>
                    <p>Система уведомлений реализована через <code>TelegramNotificationService</code>:</p>
                    <ul>
                        <li><strong>Отправка через Telegram Bot API</strong> - метод <code>sendMessage</code></li>
                        <li><strong>Типы уведомлений:</strong>
                            <ul>
                                <li><code>notifyNewTicket()</code> - Новый билет доступен (автоматически при восстановлении)</li>
                                <li><code>notifyWin()</code> - Выигрыш приза (автоматически после прокрута с выигрышем)</li>
                                <li><code>notifyReminder()</code> - Напоминание о прокрутах (через cron команду)</li>
                            </ul>
                        </li>
                        <li><strong>Автоматические уведомления:</strong>
                            <ul>
                                <li>При восстановлении билета (если билетов было меньше 3)</li>
                                <li>При выигрыше приза (деньги, билет, секретный бокс)</li>
                            </ul>
                        </li>
                        <li><strong>Команды для уведомлений:</strong>
                            <ul>
                                <li><code>php artisan wow:restore-tickets</code> - Восстановление билетов (запускается каждые 3 часа)</li>
                                <li><code>php artisan wow:send-reminders</code> - Отправка напоминаний (запускается ежедневно в 10:00)</li>
                            </ul>
                        </li>
                    </ul>

                    <h3>Настройка бота</h3>
                    <ul>
                        <li>Токен бота в <code>.env</code>: <code>TELEGRAM_BOT_TOKEN</code></li>
                        <li>Бот должен быть администратором всех обязательных каналов</li>
                        <li>Настройка вебхуков для обработки событий</li>
                        <li>Для отправки уведомлений бот должен иметь возможность отправлять сообщения пользователям</li>
                    </ul>

                    <h3>Расписание команд (Cron)</h3>
                    <p>Добавьте в crontab на сервере:</p>
                    <pre><code>* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1</code></pre>
                    <p>Команды автоматически выполняются по расписанию:</p>
                    <ul>
                        <li><code>wow:restore-tickets</code> - каждые 3 часа</li>
                        <li><code>wow:send-reminders</code> - ежедневно в 10:00</li>
                    </ul>
                `,
                notifications: `
                    <h2>Система уведомлений</h2>
                    <p>Система автоматических уведомлений через Telegram Bot API.</p>

                    <h3>TelegramNotificationService</h3>
                    <p>Сервис <code>App\Services\TelegramNotificationService</code> предоставляет методы для отправки уведомлений:</p>
                    <ul>
                        <li><strong>sendNotification(int $telegramId, string $message, array $options): bool</strong>
                            <ul>
                                <li>Базовый метод для отправки сообщения пользователю</li>
                                <li>Поддерживает HTML форматирование</li>
                                <li>Возвращает true при успешной отправке</li>
                            </ul>
                        </li>
                        <li><strong>notifyNewTicket(User $user): bool</strong>
                            <ul>
                                <li>Уведомление о новом билете</li>
                                <li>Вызывается автоматически при восстановлении билета</li>
                            </ul>
                        </li>
                        <li><strong>notifyWin(User $user, int $prizeValue, string $prizeType): bool</strong>
                            <ul>
                                <li>Уведомление о выигрыше</li>
                                <li>Поддерживает типы: money, ticket, secret_box</li>
                                <li>Вызывается автоматически после прокрута с выигрышем</li>
                            </ul>
                        </li>
                        <li><strong>notifyReminder(User $user): bool</strong>
                            <ul>
                                <li>Напоминание о доступных билетах</li>
                                <li>Отправляется только если у пользователя есть билеты</li>
                                <li>Используется в команде <code>wow:send-reminders</code></li>
                            </ul>
                        </li>
                    </ul>

                    <h3>Автоматические уведомления</h3>
                    <ul>
                        <li><strong>При восстановлении билета:</strong>
                            <ul>
                                <li>Проверяется при каждом запросе <code>GET /api/user/tickets</code></li>
                                <li>Если прошло 2-4 часа с последнего прокрута и билетов меньше 3</li>
                                <li>Автоматически отправляется уведомление <code>notifyNewTicket</code></li>
                            </ul>
                        </li>
                        <li><strong>При выигрыше приза:</strong>
                            <ul>
                                <li>После успешного прокрута с выигрышем (не "empty")</li>
                                <li>Автоматически отправляется уведомление <code>notifyWin</code></li>
                                <li>Содержит информацию о типе и размере приза</li>
                            </ul>
                        </li>
                    </ul>

                    <h3>Обработка ошибок</h3>
                    <ul>
                        <li>Все ошибки логируются в Laravel Log</li>
                        <li>Если пользователь заблокировал бота, ошибка обрабатывается корректно</li>
                        <li>Неудачная отправка не блокирует работу приложения</li>
                    </ul>
                `,
                commands: `
                    <h2>Консольные команды</h2>
                    <p>Система включает несколько Artisan команд для автоматизации задач.</p>

                    <h3>wow:restore-tickets</h3>
                    <p><strong>Описание:</strong> Восстановление билетов пользователям (каждые 2-4 часа)</p>
                    <p><strong>Использование:</strong> <code>php artisan wow:restore-tickets</code></p>
                    <p><strong>Логика:</strong></p>
                    <ul>
                        <li>Находит всех пользователей с билетами меньше 3</li>
                        <li>Проверяет, прошло ли достаточно времени с последнего прокрута</li>
                        <li>Восстанавливает 1 билет (до максимума 3)</li>
                        <li>Отправляет уведомление <code>notifyNewTicket</code> при восстановлении</li>
                    </ul>
                    <p><strong>Расписание:</strong> Каждые 3 часа (настраивается в <code>routes/console.php</code>)</p>

                    <h3>wow:send-reminders</h3>
                    <p><strong>Описание:</strong> Отправка напоминаний пользователям о доступных билетах</p>
                    <p><strong>Использование:</strong> <code>php artisan wow:send-reminders</code></p>
                    <p><strong>Логика:</strong></p>
                    <ul>
                        <li>Находит пользователей с билетами, которые не крутили более 24 часов</li>
                        <li>Отправляет напоминание через <code>notifyReminder</code></li>
                        <li>Пропускает пользователей без билетов</li>
                    </ul>
                    <p><strong>Расписание:</strong> Ежедневно в 10:00 (настраивается в <code>routes/console.php</code>)</p>

                    <h3>Настройка расписания</h3>
                    <p>Расписание команд настраивается в файле <code>routes/console.php</code>:</p>
                    <pre><code>Schedule::command('wow:restore-tickets')
    ->everyThreeHours()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('wow:send-reminders')
    ->dailyAt('10:00')
    ->withoutOverlapping()
    ->runInBackground();</code></pre>

                    <h3>Настройка Cron</h3>
                    <p>Для автоматического выполнения команд добавьте в crontab:</p>
                    <pre><code>* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1</code></pre>
                    <p>Это запустит планировщик каждую минуту, который проверит, какие команды нужно выполнить.</p>

                    <h3>Конфигурация</h3>
                    <p>Интервал восстановления билетов настраивается в админ панели:</p>
                    <ul>
                        <li>Перейдите в раздел <strong>WOW Рулетка</strong> → <strong>Рулетка</strong></li>
                        <li>В блоке "Настройки рулетки" найдите поле "Период восстановления билета"</li>
                        <li>Установите значение от 1 до 24 часов</li>
                        <li>Настройка сохраняется автоматически при изменении</li>
                    </ul>
                    <p><strong>По умолчанию:</strong> 3 часа.</p>
                    <p><strong>Примечание:</strong> Настройка хранится в таблице <code>wheel_settings</code> в поле <code>ticket_restore_hours</code>.</p>
                `,
            },
        };
    },
    computed: {
        currentContent() {
            return this.documentation[this.activeSection] || '<p>Раздел в разработке</p>';
        },
    },
};
</script>

<style scoped>
.documentation-page {
    min-height: 100vh;
}

.prose {
    color: inherit;
}

.prose h2 {
    margin-top: 2rem;
    margin-bottom: 1rem;
    font-size: 1.5rem;
    font-weight: 700;
}

.prose h3 {
    margin-top: 1.5rem;
    margin-bottom: 0.75rem;
    font-size: 1.25rem;
    font-weight: 600;
}

.prose ul {
    margin: 1rem 0;
    padding-left: 1.5rem;
}

.prose li {
    margin: 0.5rem 0;
}

.prose code {
    background-color: rgba(0, 0, 0, 0.05);
    padding: 0.125rem 0.25rem;
    border-radius: 0.25rem;
    font-family: 'Courier New', monospace;
    font-size: 0.875em;
}

.dark .prose code {
    background-color: rgba(255, 255, 255, 0.1);
}
</style>

