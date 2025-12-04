<template>
    <div class="bot-config-page space-y-6">
        <div>
            <h1 class="text-3xl font-semibold text-foreground">Настройки бота</h1>
            <p class="text-muted-foreground mt-1">Настройка подключения Telegram бота и webhook</p>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex items-center justify-center py-12">
            <p class="text-muted-foreground">Загрузка настроек...</p>
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
        <form v-if="!loading" @submit.prevent="saveConfig" class="space-y-6">
            <!-- Основные настройки -->
            <div class="bg-card rounded-lg border border-border p-6">
                <h2 class="text-xl font-semibold mb-4">Основные настройки</h2>
                
                <div class="space-y-4">
                    <div>
                        <label class="text-sm font-medium mb-1 block">Bot Token</label>
                        <input
                            v-model="form.bot_token"
                            type="password"
                            placeholder="123456789:ABCdefGHIjklMNOpqrsTUVwxyz"
                            class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                        />
                        <p class="text-xs text-muted-foreground mt-1">Токен бота от @BotFather</p>
                    </div>

                    <div>
                        <label class="text-sm font-medium mb-1 block">Bot Username</label>
                        <input
                            v-model="form.bot_username"
                            type="text"
                            placeholder="my_bot"
                            class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                        />
                        <p class="text-xs text-muted-foreground mt-1">Username бота без @</p>
                    </div>

                    <div>
                        <label class="text-sm font-medium mb-1 block">Webhook URL</label>
                        <input
                            v-model="form.webhook_url"
                            type="url"
                            placeholder="https://your-domain.com/api/telegram/webhook"
                            class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                        />
                        <p class="text-xs text-muted-foreground mt-1">URL для получения обновлений</p>
                    </div>

                    <div>
                        <label class="text-sm font-medium mb-1 block">Mini App URL</label>
                        <input
                            v-model="form.mini_app_url"
                            type="url"
                            placeholder="https://your-domain.com"
                            class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                        />
                    </div>
                </div>
            </div>

            <!-- Webhook настройки -->
            <div class="bg-card rounded-lg border border-border p-6">
                <h2 class="text-xl font-semibold mb-4">Webhook настройки</h2>
                
                <div class="space-y-4">
                    <div>
                        <label class="text-sm font-medium mb-1 block">Secret Token</label>
                        <input
                            v-model="form.webhook.secret_token"
                            type="password"
                            placeholder="your_secret_token"
                            class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                        />
                        <p class="text-xs text-muted-foreground mt-1">Секретный токен для проверки webhook</p>
                    </div>

                    <div>
                        <label class="text-sm font-medium mb-1 block">Max Connections</label>
                        <input
                            v-model.number="form.webhook.max_connections"
                            type="number"
                            min="1"
                            max="100"
                            class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                        />
                    </div>

                    <div>
                        <label class="text-sm font-medium mb-1 block">Allowed Updates</label>
                        <div class="space-y-2">
                            <label v-for="update in availableUpdates" :key="update" class="flex items-center gap-2 cursor-pointer">
                                <input
                                    v-model="form.webhook.allowed_updates"
                                    type="checkbox"
                                    :value="update"
                                    class="w-4 h-4"
                                />
                                <span class="text-sm">{{ update }}</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Дополнительные настройки -->
            <div class="bg-card rounded-lg border border-border p-6">
                <h2 class="text-xl font-semibold mb-4">Дополнительные настройки</h2>
                
                <div class="space-y-4">
                    <div>
                        <label class="text-sm font-medium mb-1 block">Admin IDs</label>
                        <input
                            v-model="adminIdsInput"
                            type="text"
                            placeholder="123456789,987654321"
                            class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                        />
                        <p class="text-xs text-muted-foreground mt-1">ID администраторов через запятую</p>
                    </div>

                    <div>
                        <label class="text-sm font-medium mb-1 block">Required Channels</label>
                        <input
                            v-model="requiredChannelsInput"
                            type="text"
                            placeholder="@channel1,@channel2"
                            class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                        />
                        <p class="text-xs text-muted-foreground mt-1">Обязательные каналы через запятую</p>
                    </div>

                    <div class="space-y-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                v-model="form.notifications.enabled"
                                type="checkbox"
                                class="w-4 h-4"
                            />
                            <span class="text-sm">Включить уведомления</span>
                        </label>

                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                v-model="form.rate_limiting.enabled"
                                type="checkbox"
                                class="w-4 h-4"
                            />
                            <span class="text-sm">Включить rate limiting</span>
                        </label>

                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                v-model="form.validation.enabled"
                                type="checkbox"
                                class="w-4 h-4"
                            />
                            <span class="text-sm">Включить валидацию</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Действия -->
            <div class="flex gap-4">
                <button
                    type="submit"
                    :disabled="saving"
                    class="h-11 px-6 bg-accent/10 backdrop-blur-xl text-accent border border-accent/40 hover:bg-accent/20 rounded-2xl shadow-lg shadow-accent/10 inline-flex items-center justify-center gap-2 disabled:opacity-50"
                >
                    {{ saving ? 'Сохранение...' : 'Сохранить настройки' }}
                </button>

                <button
                    type="button"
                    @click="testConnection"
                    :disabled="testing"
                    class="h-11 px-6 bg-blue-500/10 text-blue-600 border border-blue-500/40 hover:bg-blue-500/20 rounded-2xl inline-flex items-center justify-center gap-2 disabled:opacity-50"
                >
                    {{ testing ? 'Проверка...' : 'Тест подключения' }}
                </button>

                <button
                    type="button"
                    @click="getWebhookInfo"
                    :disabled="loadingWebhook"
                    class="h-11 px-6 bg-green-500/10 text-green-600 border border-green-500/40 hover:bg-green-500/20 rounded-2xl inline-flex items-center justify-center gap-2 disabled:opacity-50"
                >
                    {{ loadingWebhook ? 'Загрузка...' : 'Информация о webhook' }}
                </button>
            </div>
        </form>

        <!-- Webhook Info Modal -->
        <div v-if="showWebhookInfo" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm">
            <div class="bg-background border border-border rounded-lg shadow-2xl w-full max-w-2xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold">Информация о webhook</h3>
                    <button
                        @click="showWebhookInfo = false"
                        class="text-muted-foreground hover:text-foreground"
                    >
                        ✕
                    </button>
                </div>
                <div v-if="webhookInfo" class="space-y-2">
                    <div class="p-4 bg-muted/30 rounded-lg">
                        <pre class="text-sm whitespace-pre-wrap">{{ JSON.stringify(webhookInfo, null, 2) }}</pre>
                    </div>
                </div>
                <div v-else class="text-muted-foreground">
                    Загрузка информации...
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'

export default {
    name: 'BotConfig',
    setup() {
        const loading = ref(false)
        const saving = ref(false)
        const testing = ref(false)
        const loadingWebhook = ref(false)
        const error = ref(null)
        const successMessage = ref(null)
        const showWebhookInfo = ref(false)
        const webhookInfo = ref(null)

        const availableUpdates = ['message', 'callback_query', 'inline_query', 'pre_checkout_query', 'shipping_query']

        const form = ref({
            bot_token: '',
            bot_username: '',
            webhook_url: '',
            mini_app_url: '',
            admin_ids: [],
            required_channels: [],
            webhook: {
                secret_token: '',
                allowed_updates: ['message', 'callback_query'],
                max_connections: 40,
            },
            notifications: {
                enabled: true,
                queue: 'default',
            },
            rate_limiting: {
                enabled: true,
                cache_driver: 'redis',
            },
            validation: {
                enabled: true,
                auto_truncate: true,
            },
        })

        const adminIdsInput = computed({
            get: () => form.value.admin_ids.join(','),
            set: (value) => {
                form.value.admin_ids = value ? value.split(',').map(id => parseInt(id.trim())).filter(id => !isNaN(id)) : []
            }
        })

        const requiredChannelsInput = computed({
            get: () => form.value.required_channels.join(','),
            set: (value) => {
                form.value.required_channels = value ? value.split(',').map(ch => ch.trim()).filter(ch => ch) : []
            }
        })

        const fetchConfig = async () => {
            loading.value = true
            error.value = null
            try {
                const response = await axios.get('/api/v1/config/bot/')
                if (response.data) {
                    form.value = {
                        ...response.data,
                        webhook: {
                            secret_token: response.data.webhook?.secret_token || '',
                            allowed_updates: response.data.webhook?.allowed_updates || ['message', 'callback_query'],
                            max_connections: response.data.webhook?.max_connections || 40,
                        },
                    }
                }
            } catch (err) {
                error.value = err.response?.data?.message || 'Ошибка загрузки настроек'
            } finally {
                loading.value = false
            }
        }

        const saveConfig = async () => {
            saving.value = true
            error.value = null
            successMessage.value = null

            try {
                const response = await axios.post('/api/v1/config/bot/', form.value)
                
                if (response.data.error) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Внимание',
                        text: response.data.message,
                    })
                } else {
                    successMessage.value = response.data.message || 'Настройки успешно сохранены'
                    Swal.fire({
                        icon: 'success',
                        title: 'Успешно',
                        text: response.data.message || 'Настройки сохранены и webhook зарегистрирован',
                    })
                }
            } catch (err) {
                error.value = err.response?.data?.message || 'Ошибка сохранения настроек'
                Swal.fire({
                    icon: 'error',
                    title: 'Ошибка',
                    text: err.response?.data?.message || 'Ошибка сохранения настроек',
                })
            } finally {
                saving.value = false
            }
        }

        const testConnection = async () => {
            testing.value = true
            try {
                const response = await axios.post('/api/v1/config/bot/test-connection')
                
                if (response.data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Подключение успешно!',
                        html: `
                            <p><strong>ID:</strong> ${response.data.data.id}</p>
                            <p><strong>Username:</strong> @${response.data.data.username || 'N/A'}</p>
                            <p><strong>First Name:</strong> ${response.data.data.first_name || 'N/A'}</p>
                        `,
                    })
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Ошибка подключения',
                        text: response.data.error,
                    })
                }
            } catch (err) {
                Swal.fire({
                    icon: 'error',
                    title: 'Ошибка',
                    text: err.response?.data?.error || 'Ошибка проверки подключения',
                })
            } finally {
                testing.value = false
            }
        }

        const getWebhookInfo = async () => {
            loadingWebhook.value = true
            showWebhookInfo.value = true
            webhookInfo.value = null

            try {
                const response = await axios.get('/api/v1/config/bot/webhook-info')
                
                if (response.data.success) {
                    webhookInfo.value = response.data.data
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Ошибка',
                        text: response.data.error,
                    })
                    showWebhookInfo.value = false
                }
            } catch (err) {
                Swal.fire({
                    icon: 'error',
                    title: 'Ошибка',
                    text: err.response?.data?.error || 'Ошибка получения информации о webhook',
                })
                showWebhookInfo.value = false
            } finally {
                loadingWebhook.value = false
            }
        }

        onMounted(() => {
            fetchConfig()
        })

        return {
            loading,
            saving,
            testing,
            loadingWebhook,
            error,
            successMessage,
            form,
            adminIdsInput,
            requiredChannelsInput,
            availableUpdates,
            showWebhookInfo,
            webhookInfo,
            saveConfig,
            testConnection,
            getWebhookInfo,
        }
    },
}
</script>

