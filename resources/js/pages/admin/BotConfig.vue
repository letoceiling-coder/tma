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

            <!-- Приветственное сообщение -->
            <div class="bg-card rounded-lg border border-border p-6">
                <h2 class="text-xl font-semibold mb-4">Приветственное сообщение</h2>
                <p class="text-sm text-muted-foreground mb-4">Настройка сообщения, которое отправляется при команде /start</p>
                
                <div class="space-y-4">
                    <div>
                        <label class="flex items-center gap-2 cursor-pointer mb-2">
                            <input
                                v-model="form.welcome_message.enabled"
                                type="checkbox"
                                class="w-4 h-4"
                            />
                            <span class="text-sm font-medium">Включить приветственное сообщение</span>
                        </label>
                    </div>

                    <div v-if="form.welcome_message.enabled">
                        <label class="text-sm font-medium mb-2 block">Текст сообщения (HTML)</label>
                        
                        <!-- Панель инструментов редактора -->
                        <div class="flex flex-wrap gap-2 mb-2 p-2 bg-muted/30 rounded-lg border border-border">
                            <button
                                type="button"
                                @click="formatText('bold')"
                                class="px-3 py-1 text-sm bg-background border border-border rounded hover:bg-accent/10"
                                title="Жирный (Ctrl+B)"
                            >
                                <strong>B</strong>
                            </button>
                            <button
                                type="button"
                                @click="formatText('italic')"
                                class="px-3 py-1 text-sm bg-background border border-border rounded hover:bg-accent/10"
                                title="Курсив (Ctrl+I)"
                            >
                                <em>I</em>
                            </button>
                            <button
                                type="button"
                                @click="formatText('underline')"
                                class="px-3 py-1 text-sm bg-background border border-border rounded hover:bg-accent/10"
                                title="Подчеркнутый"
                            >
                                <u>U</u>
                            </button>
                            <button
                                type="button"
                                @click="formatText('strikethrough')"
                                class="px-3 py-1 text-sm bg-background border border-border rounded hover:bg-accent/10"
                                title="Зачеркнутый"
                            >
                                <s>S</s>
                            </button>
                            <button
                                type="button"
                                @click="formatText('code')"
                                class="px-3 py-1 text-sm bg-background border border-border rounded hover:bg-accent/10 font-mono"
                                title="Код"
                            >
                                &lt;/&gt;
                            </button>
                            <button
                                type="button"
                                @click="formatText('pre')"
                                class="px-3 py-1 text-sm bg-background border border-border rounded hover:bg-accent/10 font-mono"
                                title="Блок кода"
                            >
                                { }
                            </button>
                            <button
                                type="button"
                                @click="formatText('link')"
                                class="px-3 py-1 text-sm bg-background border border-border rounded hover:bg-accent/10"
                                title="Ссылка"
                            >
                                🔗
                            </button>
                            <button
                                type="button"
                                @click="formatText('spoiler')"
                                class="px-3 py-1 text-sm bg-background border border-border rounded hover:bg-accent/10"
                                title="Спойлер"
                            >
                                👁️
                            </button>
                            <button
                                type="button"
                                @click="formatText('blockquote')"
                                class="px-3 py-1 text-sm bg-background border border-border rounded hover:bg-accent/10"
                                title="Цитата"
                            >
                                "
                            </button>
                        </div>

                        <!-- Редактор -->
                        <textarea
                            ref="editorRef"
                            v-model="form.welcome_message.text"
                            @keydown="handleKeydown"
                            rows="6"
                            placeholder="Введите текст приветственного сообщения..."
                            class="w-full px-4 py-3 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent font-mono text-sm"
                        ></textarea>
                        
                        <!-- Предпросмотр -->
                        <div class="mt-2 p-3 bg-muted/20 rounded-lg border border-border">
                            <p class="text-xs text-muted-foreground mb-1">Предпросмотр:</p>
                            <div class="text-sm" v-html="previewHtml"></div>
                        </div>
                        
                        <p class="text-xs text-muted-foreground mt-1">
                            Поддерживаемые HTML теги: &lt;b&gt;, &lt;i&gt;, &lt;u&gt;, &lt;s&gt;, &lt;code&gt;, &lt;pre&gt;, &lt;a&gt;, &lt;tg-spoiler&gt;, &lt;blockquote&gt;
                        </p>
                    </div>

                    <!-- Настройки кнопки Mini App -->
                    <div v-if="form.welcome_message.enabled" class="mt-4 p-4 bg-muted/20 rounded-lg border border-border">
                        <h3 class="text-sm font-semibold mb-3">Кнопка Mini App</h3>
                        
                        <div class="space-y-3">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input
                                    v-model="form.welcome_message.mini_app_button.enabled"
                                    type="checkbox"
                                    class="w-4 h-4"
                                />
                                <span class="text-sm">Показать кнопку Mini App</span>
                            </label>

                            <div v-if="form.welcome_message.mini_app_button.enabled" class="space-y-3 pl-6">
                                <div>
                                    <label class="text-sm font-medium mb-1 block">Текст кнопки</label>
                                    <input
                                        v-model="form.welcome_message.mini_app_button.text"
                                        type="text"
                                        maxlength="64"
                                        placeholder="🚀 Открыть приложение"
                                        class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                                    />
                                    <p class="text-xs text-muted-foreground mt-1">Максимум 64 символа</p>
                                </div>

                                <div>
                                    <label class="text-sm font-medium mb-1 block">URL Mini App</label>
                                    <input
                                        v-model="form.welcome_message.mini_app_button.url"
                                        type="url"
                                        placeholder="https://t.me/your_bot/app"
                                        class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                                    />
                                    <p class="text-xs text-muted-foreground mt-1">URL вашего Mini App</p>
                                </div>
                            </div>
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

        const editorRef = ref(null)

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

        // Предпросмотр HTML
        const previewHtml = computed(() => {
            let html = form.value.welcome_message.text || ''
            // Экранируем HTML для безопасного отображения в предпросмотре
            // Но оставляем валидные Telegram теги
            return html
        })

        // Форматирование текста
        const formatText = (type) => {
            if (!editorRef.value) return

            const textarea = editorRef.value
            const start = textarea.selectionStart
            const end = textarea.selectionEnd
            const selectedText = form.value.welcome_message.text.substring(start, end)
            const before = form.value.welcome_message.text.substring(0, start)
            const after = form.value.welcome_message.text.substring(end)

            let formattedText = ''
            let newCursorPos = start

            switch (type) {
                case 'bold':
                    formattedText = selectedText ? `<b>${selectedText}</b>` : '<b></b>'
                    newCursorPos = start + (selectedText ? 3 : 3)
                    break
                case 'italic':
                    formattedText = selectedText ? `<i>${selectedText}</i>` : '<i></i>'
                    newCursorPos = start + (selectedText ? 3 : 3)
                    break
                case 'underline':
                    formattedText = selectedText ? `<u>${selectedText}</u>` : '<u></u>'
                    newCursorPos = start + (selectedText ? 3 : 3)
                    break
                case 'strikethrough':
                    formattedText = selectedText ? `<s>${selectedText}</s>` : '<s></s>'
                    newCursorPos = start + (selectedText ? 3 : 3)
                    break
                case 'code':
                    formattedText = selectedText ? `<code>${selectedText}</code>` : '<code></code>'
                    newCursorPos = start + (selectedText ? 6 : 6)
                    break
                case 'pre':
                    formattedText = selectedText ? `<pre>${selectedText}</pre>` : '<pre></pre>'
                    newCursorPos = start + (selectedText ? 5 : 5)
                    break
                case 'link':
                    const url = prompt('Введите URL:', 'https://example.com')
                    if (url) {
                        formattedText = `<a href="${url}">${selectedText || 'ссылка'}</a>`
                        newCursorPos = start + formattedText.length - (selectedText ? 0 : 7)
                    } else {
                        return
                    }
                    break
                case 'spoiler':
                    formattedText = selectedText ? `<tg-spoiler>${selectedText}</tg-spoiler>` : '<tg-spoiler></tg-spoiler>'
                    newCursorPos = start + (selectedText ? 12 : 12)
                    break
                case 'blockquote':
                    formattedText = selectedText ? `<blockquote>${selectedText}</blockquote>` : '<blockquote></blockquote>'
                    newCursorPos = start + (selectedText ? 12 : 12)
                    break
            }

            form.value.welcome_message.text = before + formattedText + after

            // Восстанавливаем позицию курсора
            setTimeout(() => {
                textarea.focus()
                textarea.setSelectionRange(newCursorPos, newCursorPos)
            }, 0)
        }

        // Обработка горячих клавиш
        const handleKeydown = (event) => {
            if (event.ctrlKey || event.metaKey) {
                switch (event.key) {
                    case 'b':
                        event.preventDefault()
                        formatText('bold')
                        break
                    case 'i':
                        event.preventDefault()
                        formatText('italic')
                        break
                    case 'u':
                        event.preventDefault()
                        formatText('underline')
                        break
                }
            }
        }

        const fetchConfig = async () => {
            loading.value = true
            error.value = null
            try {
                const url = '/api/v1/settings/bot/';
                console.log('BotConfig - Loading config, URL:', url);
                console.log('BotConfig - axios.defaults.baseURL:', axios.defaults?.baseURL);
                console.log('BotConfig - window.location:', window.location.href);
                console.log('BotConfig - Full URL will be:', axios.defaults?.baseURL ? axios.defaults.baseURL + url : url);
                const response = await axios.get(url)
                if (response.data) {
                    form.value = {
                        ...response.data,
                        webhook: {
                            secret_token: response.data.webhook?.secret_token || '',
                            allowed_updates: response.data.webhook?.allowed_updates || ['message', 'callback_query'],
                            max_connections: response.data.webhook?.max_connections || 40,
                        },
                        welcome_message: {
                            enabled: response.data.welcome_message?.enabled ?? true,
                            text: response.data.welcome_message?.text || '<b>Добро пожаловать!</b>',
                            mini_app_button: {
                                enabled: response.data.welcome_message?.mini_app_button?.enabled ?? true,
                                text: response.data.welcome_message?.mini_app_button?.text || '🚀 Открыть приложение',
                                url: response.data.welcome_message?.mini_app_button?.url || '',
                            },
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
                const url = '/api/v1/settings/bot/';
                console.log('BotConfig - Saving config, URL:', url);
                console.log('BotConfig - Full URL will be:', axios.defaults?.baseURL ? axios.defaults.baseURL + url : url);
                const response = await axios.post(url, form.value)
                
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
                const url = '/api/v1/settings/bot/test-connection';
                console.log('BotConfig - Testing connection, URL:', url);
                console.log('BotConfig - Full URL will be:', axios.defaults?.baseURL ? axios.defaults.baseURL + url : url);
                const response = await axios.post(url)
                
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
                const url = '/api/v1/settings/bot/webhook-info';
                console.log('BotConfig - Getting webhook info, URL:', url);
                console.log('BotConfig - Full URL will be:', axios.defaults?.baseURL ? axios.defaults.baseURL + url : url);
                const response = await axios.get(url)
                
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
            editorRef,
            previewHtml,
            formatText,
            handleKeydown,
            saveConfig,
            testConnection,
            getWebhookInfo,
        }
    },
}
</script>

