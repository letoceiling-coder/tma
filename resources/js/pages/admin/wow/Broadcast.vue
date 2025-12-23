<template>
    <div class="broadcast-page space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-semibold text-foreground">Рассылки</h1>
                <p class="text-muted-foreground mt-1">Управление автоматическими рассылками от Telegram-бота</p>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex items-center justify-center py-12">
            <div class="text-muted-foreground">Загрузка...</div>
        </div>

        <!-- Error State -->
        <div v-if="error" class="p-4 bg-destructive/10 border border-destructive/20 rounded-lg">
            <p class="text-destructive">{{ error }}</p>
        </div>

        <!-- Success Message -->
        <div v-if="successMessage" class="p-4 bg-green-500/10 border border-green-500/20 rounded-lg">
            <p class="text-green-600">{{ successMessage }}</p>
        </div>

        <!-- Form -->
        <div v-if="!loading" class="space-y-6">
            <!-- Включить рассылки -->
            <div class="bg-card rounded-lg border border-border p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-medium mb-1">Включить рассылки</h3>
                        <p class="text-sm text-muted-foreground">
                            Включает или выключает систему автоматических рассылок
                        </p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input
                            v-model="broadcastEnabled"
                            @change="saveSettings"
                            type="checkbox"
                            class="sr-only peer"
                        />
                        <div
                            class="w-11 h-6 bg-muted peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-accent/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-border after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-accent"
                        ></div>
                    </label>
                </div>
            </div>

            <!-- Текст рассылки -->
            <div class="bg-card rounded-lg border border-border p-6 space-y-4">
                <div>
                    <label class="text-sm font-medium mb-2 block">Текст рассылки</label>
                    <textarea
                        v-model="broadcastMessageText"
                        @change="saveSettings"
                        rows="6"
                        placeholder="Привет! У тебя есть новые возможности. Проверь приложение!"
                        class="w-full px-4 py-3 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent resize-none"
                    ></textarea>
                    <p class="text-xs text-muted-foreground mt-1">
                        Текст, который будет отправляться пользователям от имени бота. Поддерживается простой текст и emoji.
                        <br>
                        Доступные переменные: <code class="bg-muted px-1 rounded">{{username}}</code>, <code class="bg-muted px-1 rounded">{{tickets_count}}</code>
                    </p>
                </div>
            </div>

            <!-- Периодичность рассылки -->
            <div class="bg-card rounded-lg border border-border p-6">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <h3 class="text-base font-medium mb-1">Периодичность рассылки (в часах)</h3>
                        <p class="text-sm text-muted-foreground">
                            Интервал между отправками сообщений с момента регистрации или последнего события. Указывается в часах
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <input
                            v-model.number="broadcastIntervalHours"
                            @change="saveSettings"
                            type="number"
                            min="1"
                            max="24"
                            step="1"
                            class="w-24 h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent text-center"
                        />
                        <span class="text-sm text-muted-foreground">часов</span>
                    </div>
                </div>
            </div>

            <!-- Триггер рассылки -->
            <div class="bg-card rounded-lg border border-border p-6">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <h3 class="text-base font-medium mb-1">Триггер рассылки</h3>
                        <p class="text-sm text-muted-foreground">
                            Событие, после которого будет начинаться отсчёт до рассылки
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <select
                            v-model="broadcastTrigger"
                            @change="saveSettings"
                            class="w-64 h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                        >
                            <option value="after_registration">После регистрации</option>
                            <option value="after_last_spin">После последнего прокрута</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Кнопка сохранения -->
            <div class="flex justify-end">
                <button
                    @click="saveSettings"
                    :disabled="saving"
                    class="h-11 px-6 bg-accent/10 backdrop-blur-xl text-accent border border-accent/40 hover:bg-accent/20 rounded-2xl shadow-lg shadow-accent/10 inline-flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <span>{{ saving ? 'Сохранение...' : 'Сохранить настройки' }}</span>
                </button>
            </div>

            <!-- Разовая рассылка -->
            <div class="bg-card rounded-lg border border-border p-6 space-y-4">
                <div>
                    <h3 class="text-base font-medium mb-2">Разовая рассылка</h3>
                    <p class="text-sm text-muted-foreground mb-4">
                        Отправить сообщение всем пользователям с telegram_id прямо сейчас. Сообщение будет отправлено независимо от настроек периодической рассылки.
                    </p>
                    
                    <div v-if="manualBroadcastAt" class="mb-4 p-3 bg-muted/30 rounded-lg">
                        <div class="text-sm font-medium mb-1">Последняя разовая рассылка:</div>
                        <div class="text-sm text-muted-foreground">
                            {{ new Date(manualBroadcastAt).toLocaleString('ru-RU') }}
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="text-sm font-medium mb-2 block">Текст разовой рассылки</label>
                        <textarea
                            v-model="manualBroadcastText"
                            rows="4"
                            placeholder="Введите текст сообщения для разовой рассылки..."
                            class="w-full px-4 py-3 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent resize-none"
                        ></textarea>
                        <p class="text-xs text-muted-foreground mt-1">
                            Доступные переменные: <code class="bg-muted px-1 rounded">{{username}}</code>, <code class="bg-muted px-1 rounded">{{tickets_count}}</code>
                        </p>
                    </div>

                    <button
                        @click="confirmManualBroadcast"
                        :disabled="!manualBroadcastText || sendingManualBroadcast"
                        class="h-11 px-6 bg-orange-500/10 backdrop-blur-xl text-orange-600 border border-orange-500/40 hover:bg-orange-500/20 rounded-2xl shadow-lg shadow-orange-500/10 inline-flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span>{{ sendingManualBroadcast ? 'Отправка...' : 'Отправить разовую рассылку' }}</span>
                    </button>

                    <!-- Результат разовой рассылки -->
                    <div v-if="manualBroadcastResult" class="mt-4 p-4 rounded-lg" :class="manualBroadcastResult.success ? 'bg-green-500/10 border border-green-500/20' : 'bg-destructive/10 border border-destructive/20'">
                        <div :class="manualBroadcastResult.success ? 'text-green-600' : 'text-destructive'" class="font-medium mb-2">
                            {{ manualBroadcastResult.success ? '✓ Разовая рассылка завершена' : '✗ Ошибка при рассылке' }}
                        </div>
                        <div v-if="manualBroadcastResult.stats" class="text-sm text-muted-foreground space-y-1">
                            <div>Всего пользователей: {{ manualBroadcastResult.stats.total }}</div>
                            <div class="text-green-600">Успешно отправлено: {{ manualBroadcastResult.stats.sent }}</div>
                            <div v-if="manualBroadcastResult.stats.failed > 0" class="text-destructive">Ошибок: {{ manualBroadcastResult.stats.failed }}</div>
                            <div v-if="manualBroadcastResult.stats.skipped > 0" class="text-yellow-600">Пропущено: {{ manualBroadcastResult.stats.skipped }}</div>
                        </div>
                        <div v-if="manualBroadcastResult.errors && manualBroadcastResult.errors.length > 0" class="mt-2 text-xs text-muted-foreground">
                            <div class="font-medium mb-1">Ошибки:</div>
                            <div class="max-h-32 overflow-y-auto space-y-1">
                                <div v-for="(error, index) in manualBroadcastResult.errors" :key="index">{{ error }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Тестовая отправка -->
            <div class="bg-card rounded-lg border border-border p-6 space-y-4">
                <div>
                    <h3 class="text-base font-medium mb-2">Тестовая отправка</h3>
                    <p class="text-sm text-muted-foreground mb-4">
                        Выберите пользователя для тестовой отправки сообщения и проверки работоспособности рассылки
                    </p>
                    
                    <!-- Поиск пользователя -->
                    <div class="mb-4">
                        <label class="text-sm font-medium mb-2 block">Поиск пользователя</label>
                        <input
                            v-model="userSearch"
                            @input="searchUsers"
                            type="text"
                            placeholder="Поиск по ID, username или имени..."
                            class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                        />
                    </div>

                    <!-- Список пользователей -->
                    <div v-if="users.length > 0" class="mb-4 max-h-64 overflow-y-auto border border-border rounded-lg">
                        <div
                            v-for="user in users"
                            :key="user.id"
                            @click="selectUser(user)"
                            class="px-4 py-3 border-b border-border last:border-b-0 cursor-pointer hover:bg-muted/10 transition-colors"
                            :class="selectedUser?.id === user.id ? 'bg-accent/10' : ''"
                        >
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="text-sm font-medium">{{ user.name || 'Без имени' }}</div>
                                    <div class="text-xs text-muted-foreground">
                                        ID: {{ user.id }} | Telegram ID: {{ user.telegram_id }} | @{{ user.username || 'нет username' }}
                                    </div>
                                </div>
                                <div v-if="selectedUser?.id === user.id" class="text-accent">✓</div>
                            </div>
                        </div>
                    </div>
                    <div v-else-if="userSearch && !loadingUsers" class="mb-4 text-sm text-muted-foreground text-center py-4">
                        Пользователи не найдены
                    </div>
                    <div v-else-if="!userSearch" class="mb-4 text-sm text-muted-foreground text-center py-4">
                        Введите поисковый запрос для поиска пользователей
                    </div>

                    <!-- Выбранный пользователь -->
                    <div v-if="selectedUser" class="mb-4 p-3 bg-muted/30 rounded-lg">
                        <div class="text-sm font-medium mb-1">Выбранный пользователь:</div>
                        <div class="text-sm text-muted-foreground">
                            {{ selectedUser.name || 'Без имени' }} (ID: {{ selectedUser.id }}, Telegram: {{ selectedUser.telegram_id }})
                        </div>
                    </div>

                    <!-- Тестовое сообщение -->
                    <div class="mb-4">
                        <label class="text-sm font-medium mb-2 block">Тестовое сообщение (опционально)</label>
                        <textarea
                            v-model="testMessage"
                            rows="3"
                            placeholder="Оставьте пустым, чтобы использовать текст из настроек"
                            class="w-full px-4 py-3 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent resize-none"
                        ></textarea>
                        <p class="text-xs text-muted-foreground mt-1">
                            Если оставить пустым, будет использован текст из настроек рассылки
                        </p>
                    </div>

                    <!-- Кнопка отправки -->
                    <button
                        @click="sendTestMessage"
                        :disabled="!selectedUser || sendingTest"
                        class="h-11 px-6 bg-blue-500/10 backdrop-blur-xl text-blue-600 border border-blue-500/40 hover:bg-blue-500/20 rounded-2xl shadow-lg shadow-blue-500/10 inline-flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span>{{ sendingTest ? 'Отправка...' : 'Отправить тестовое сообщение' }}</span>
                    </button>

                    <!-- Результат тестовой отправки -->
                    <div v-if="testResult" class="mt-4 p-4 rounded-lg" :class="testResult.success ? 'bg-green-500/10 border border-green-500/20' : 'bg-destructive/10 border border-destructive/20'">
                        <div :class="testResult.success ? 'text-green-600' : 'text-destructive'" class="font-medium mb-1">
                            {{ testResult.success ? '✓ Сообщение отправлено успешно' : '✗ Ошибка отправки' }}
                        </div>
                        <div class="text-sm text-muted-foreground" v-if="testResult.message">
                            {{ testResult.message }}
                        </div>
                        <div class="text-sm text-muted-foreground mt-2" v-if="testResult.sent_message">
                            <div class="font-medium">Отправленное сообщение:</div>
                            <div class="mt-1 p-2 bg-background rounded border border-border">{{ testResult.sent_message }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const loading = ref(true)
const saving = ref(false)
const error = ref(null)
const successMessage = ref(null)

const broadcastEnabled = ref(false)
const broadcastMessageText = ref('')
const broadcastIntervalHours = ref(24)
const broadcastTrigger = ref('after_registration')
const manualBroadcastAt = ref(null)

// Тестовая отправка
const userSearch = ref('')
const users = ref([])
const loadingUsers = ref(false)
const selectedUser = ref(null)
const testMessage = ref('')
const sendingTest = ref(false)
const testResult = ref(null)

// Разовая рассылка
const manualBroadcastText = ref('')
const sendingManualBroadcast = ref(false)
const manualBroadcastResult = ref(null)

let searchTimeout = null

const fetchSettings = async () => {
    loading.value = true
    error.value = null
    successMessage.value = null
    try {
        const response = await axios.get('/api/v1/wow/wheel/settings')
        if (response.data.settings) {
            const settings = response.data.settings
            broadcastEnabled.value = settings.broadcast_enabled ?? false
            broadcastMessageText.value = settings.broadcast_message_text ?? ''
            broadcastIntervalHours.value = settings.broadcast_interval_hours ?? 24
            broadcastTrigger.value = settings.broadcast_trigger ?? 'after_registration'
            manualBroadcastAt.value = settings.manual_broadcast_at || null
        }
    } catch (err) {
        error.value = err.response?.data?.message || 'Ошибка загрузки настроек'
    } finally {
        loading.value = false
    }
}

const saveSettings = async () => {
    saving.value = true
    error.value = null
    successMessage.value = null
    try {
        const response = await axios.put('/api/v1/wow/wheel/settings', {
            broadcast_enabled: broadcastEnabled.value,
            broadcast_message_text: broadcastMessageText.value,
            broadcast_interval_hours: broadcastIntervalHours.value,
            broadcast_trigger: broadcastTrigger.value,
        })
        
        if (response.data.success) {
            successMessage.value = 'Настройки успешно сохранены'
            // Обновляем настройки из ответа
            if (response.data.settings) {
                const settings = response.data.settings
                broadcastEnabled.value = settings.broadcast_enabled ?? false
                broadcastMessageText.value = settings.broadcast_message_text ?? ''
                broadcastIntervalHours.value = settings.broadcast_interval_hours ?? 24
                broadcastTrigger.value = settings.broadcast_trigger ?? 'after_registration'
            }
            setTimeout(() => {
                successMessage.value = null
            }, 3000)
        }
    } catch (err) {
        error.value = err.response?.data?.message || 'Ошибка сохранения настроек'
    } finally {
        saving.value = false
    }
}

const searchUsers = async () => {
    if (searchTimeout) {
        clearTimeout(searchTimeout)
    }
    
    if (!userSearch.value || userSearch.value.length < 2) {
        users.value = []
        return
    }

    searchTimeout = setTimeout(async () => {
        loadingUsers.value = true
        try {
            const response = await axios.get('/api/v1/wow/users', {
                params: {
                    search: userSearch.value,
                    per_page: 20
                }
            })
            users.value = response.data.data || []
        } catch (err) {
            console.error('Error searching users:', err)
            users.value = []
        } finally {
            loadingUsers.value = false
        }
    }, 500)
}

const selectUser = (user) => {
    selectedUser.value = user
    testResult.value = null
}

const sendTestMessage = async () => {
    if (!selectedUser.value) {
        return
    }

    sendingTest.value = true
    testResult.value = null
    error.value = null

    try {
        const response = await axios.post('/api/v1/wow/wheel/test-broadcast', {
            user_id: selectedUser.value.id,
            message: testMessage.value || null
        })

        if (response.data.success) {
            testResult.value = {
                success: true,
                message: response.data.message,
                sent_message: response.data.sent_message
            }
        } else {
            testResult.value = {
                success: false,
                message: response.data.message || 'Ошибка отправки',
                error_code: response.data.error_code,
                error_description: response.data.error_description
            }
        }
    } catch (err) {
        testResult.value = {
            success: false,
            message: err.response?.data?.message || 'Ошибка отправки сообщения'
        }
    } finally {
        sendingTest.value = false
    }
}

const confirmManualBroadcast = async () => {
    if (!manualBroadcastText.value || !manualBroadcastText.value.trim()) {
        error.value = 'Введите текст сообщения для разовой рассылки'
        return
    }

    // Подтверждение
    if (!confirm('Вы уверены, что хотите отправить сообщение всем пользователям прямо сейчас?')) {
        return
    }

    await sendManualBroadcast()
}

const sendManualBroadcast = async () => {
    sendingManualBroadcast.value = true
    manualBroadcastResult.value = null
    error.value = null

    try {
        const response = await axios.post('/api/v1/wow/wheel/manual-broadcast', {
            message: manualBroadcastText.value
        })

        if (response.data.success) {
            manualBroadcastResult.value = {
                success: true,
                message: response.data.message,
                stats: response.data.stats,
                errors: response.data.errors || []
            }
            // Обновляем время последней разовой рассылки
            manualBroadcastAt.value = new Date().toISOString()
        } else {
            manualBroadcastResult.value = {
                success: false,
                message: response.data.message || 'Ошибка при рассылке'
            }
        }
    } catch (err) {
        manualBroadcastResult.value = {
            success: false,
            message: err.response?.data?.message || 'Ошибка отправки рассылки'
        }
        error.value = err.response?.data?.message || 'Ошибка отправки рассылки'
    } finally {
        sendingManualBroadcast.value = false
    }
}

onMounted(() => {
    fetchSettings()
})
</script>

<style scoped>
code {
    font-family: 'Courier New', monospace;
    font-size: 0.875em;
}
</style>

