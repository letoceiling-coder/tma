<template>
    <div class="bot-config-page space-y-6">
        <div>
            <h1 class="text-3xl font-semibold text-foreground">Настройки бота</h1>
            <p class="text-muted-foreground mt-1">Настройка подключения Telegram бота и webhook</p>
        </div>

        <div v-if="loading" class="flex items-center justify-center py-12">
            <p class="text-muted-foreground">Загрузка настроек...</p>
        </div>

        <div v-if="error" class="p-4 bg-destructive/10 border border-destructive/20 rounded-lg">
            <p class="text-destructive">{{ error }}</p>
        </div>

        <div v-if="successMessage" class="p-4 bg-green-500/10 border border-green-500/20 rounded-lg">
            <p class="text-green-600">{{ successMessage }}</p>
        </div>

        <form v-if="!loading" @submit.prevent="saveConfig" class="space-y-6">
            <div class="bg-card rounded-lg border border-border p-6">
                <h2 class="text-xl font-semibold mb-4">Основные настройки</h2>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Токен бота</label>
                        <input
                            v-model="form.bot_token"
                            type="text"
                            class="w-full px-4 py-2 border border-border rounded-lg bg-background"
                            placeholder="1234567890:ABCdefGHIjklMNOpqrsTUVwxyz"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">Webhook URL</label>
                        <input
                            v-model="form.webhook_url"
                            type="url"
                            class="w-full px-4 py-2 border border-border rounded-lg bg-background"
                            placeholder="https://example.com/api/telegram/webhook"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">Mini App URL</label>
                        <input
                            v-model="form.mini_app_url"
                            type="url"
                            class="w-full px-4 py-2 border border-border rounded-lg bg-background"
                            placeholder="https://example.com"
                        />
                    </div>
                </div>
            </div>

            <div class="bg-card rounded-lg border border-border p-6">
                <h2 class="text-xl font-semibold mb-4">Приветственное сообщение</h2>
                
                <div class="space-y-4">
                    <div class="flex items-center gap-2">
                        <input
                            v-model="form.welcome_message.enabled"
                            type="checkbox"
                            id="welcome_enabled"
                            class="w-4 h-4"
                        />
                        <label for="welcome_enabled" class="text-sm font-medium">Включить приветственное сообщение</label>
                    </div>

                    <div v-if="form.welcome_message.enabled">
                        <label class="block text-sm font-medium mb-2">Текст сообщения (HTML)</label>
                        <textarea
                            v-model="form.welcome_message.text"
                            rows="5"
                            class="w-full px-4 py-2 border border-border rounded-lg bg-background"
                            placeholder="<b>Добро пожаловать!</b>"
                        ></textarea>
                    </div>

                    <div v-if="form.welcome_message.enabled" class="space-y-3">
                        <div class="flex items-center gap-2">
                            <input
                                v-model="form.welcome_message.mini_app_button.enabled"
                                type="checkbox"
                                id="button_enabled"
                                class="w-4 h-4"
                            />
                            <label for="button_enabled" class="text-sm font-medium">Добавить кнопку Mini App</label>
                        </div>

                        <div v-if="form.welcome_message.mini_app_button.enabled" class="space-y-3 pl-6">
                            <div>
                                <label class="block text-sm font-medium mb-2">Текст кнопки</label>
                                <input
                                    v-model="form.welcome_message.mini_app_button.text"
                                    type="text"
                                    class="w-full px-4 py-2 border border-border rounded-lg bg-background"
                                    placeholder="🚀 Открыть приложение"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-2">URL Mini App</label>
                                <input
                                    v-model="form.welcome_message.mini_app_button.url"
                                    type="url"
                                    class="w-full px-4 py-2 border border-border rounded-lg bg-background"
                                    placeholder="https://example.com"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex gap-4">
                <button
                    type="submit"
                    class="px-6 py-2 bg-accent text-white rounded-lg hover:bg-accent/90"
                >
                    Сохранить
                </button>
                <button
                    type="button"
                    @click="testConnection"
                    class="px-6 py-2 bg-muted text-foreground rounded-lg hover:bg-muted/80"
                >
                    Проверить подключение
                </button>
                <button
                    type="button"
                    @click="getWebhookInfo"
                    class="px-6 py-2 bg-muted text-foreground rounded-lg hover:bg-muted/80"
                >
                    Информация о webhook
                </button>
            </div>
        </form>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const loading = ref(false)
const error = ref(null)
const successMessage = ref(null)

const form = ref({
    bot_token: '',
    webhook_url: '',
    mini_app_url: '',
    welcome_message: {
        enabled: true,
        text: '<b>Добро пожаловать!</b>',
        mini_app_button: {
            enabled: true,
            text: '🚀 Открыть приложение',
            url: '',
        },
    },
})

const fetchConfig = async () => {
    loading.value = true
    error.value = null
    try {
        const response = await axios.get('/api/v1/settings/bot')
        if (response.data) {
            form.value = {
                bot_token: response.data.bot_token || '',
                webhook_url: response.data.webhook_url || '',
                mini_app_url: response.data.mini_app_url || '',
                welcome_message: response.data.welcome_message || form.value.welcome_message,
            }
        }
    } catch (err) {
        error.value = err.response?.data?.message || 'Ошибка загрузки настроек'
    } finally {
        loading.value = false
    }
}

const saveConfig = async () => {
    loading.value = true
    error.value = null
    successMessage.value = null
    try {
        await axios.post('/api/v1/settings/bot', form.value)
        successMessage.value = 'Настройки сохранены'
        
        // Автоматически устанавливаем webhook при сохранении токена
        if (form.value.bot_token) {
            try {
                await axios.post('/api/v1/settings/bot/set-webhook', {
                    url: form.value.webhook_url || undefined,
                })
            } catch (err) {
                console.error('Ошибка установки webhook:', err)
            }
        }
    } catch (err) {
        error.value = err.response?.data?.message || 'Ошибка сохранения настроек'
    } finally {
        loading.value = false
    }
}

const testConnection = async () => {
    loading.value = true
    error.value = null
    try {
        const response = await axios.post('/api/v1/settings/bot/test-connection')
        if (response.data.success) {
            successMessage.value = 'Подключение успешно! Бот: ' + response.data.bot.username
        } else {
            error.value = response.data.error || 'Ошибка подключения'
        }
    } catch (err) {
        error.value = err.response?.data?.error || 'Ошибка проверки подключения'
    } finally {
        loading.value = false
    }
}

const getWebhookInfo = async () => {
    loading.value = true
    error.value = null
    try {
        const response = await axios.get('/api/v1/settings/bot/webhook-info')
        if (response.data.ok) {
            const info = response.data.result
            successMessage.value = `Webhook: ${info.url || 'не установлен'}, Ожидающих обновлений: ${info.pending_update_count || 0}`
        } else {
            error.value = response.data.description || 'Ошибка получения информации'
        }
    } catch (err) {
        error.value = err.response?.data?.error || 'Ошибка получения информации о webhook'
    } finally {
        loading.value = false
    }
}

onMounted(() => {
    fetchConfig()
})
</script>

