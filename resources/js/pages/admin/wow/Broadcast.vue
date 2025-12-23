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

