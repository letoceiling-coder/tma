<template>
    <div class="welcome-page space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-semibold text-foreground">Приветствие / Баннер</h1>
                <p class="text-muted-foreground mt-1">Настройка приветственного сообщения для команды /start</p>
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

        <!-- Form -->
        <div v-if="!loading" class="space-y-6">
            <!-- Текст приветствия -->
            <div class="bg-card rounded-lg border border-border p-6 space-y-4">
                <div>
                    <label class="text-sm font-medium mb-2 block">Текст приветствия</label>
                    <textarea
                        v-model="welcomeText"
                        rows="6"
                        placeholder="Добро пожаловать в WOW Spin!&#10;&#10;Крути рулетку, зови друзей и выигрывай подарки каждый день 🎁"
                        class="w-full px-4 py-3 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent resize-none"
                    ></textarea>
                    <p class="text-xs text-muted-foreground mt-1">
                        Поддерживается HTML разметка. Максимум 4096 символов.
                    </p>
                </div>
            </div>

            <!-- Баннер -->
            <div class="bg-card rounded-lg border border-border p-6 space-y-4">
                <div>
                    <label class="text-sm font-medium mb-2 block">URL баннера (картинки)</label>
                    <div class="flex gap-2">
                        <input
                            v-model="welcomeBannerUrl"
                            type="text"
                            placeholder="https://..."
                            class="flex-1 h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                        />
                        <button
                            @click="openMediaSelector"
                            type="button"
                            class="h-10 px-4 bg-accent/10 text-accent border border-accent/40 hover:bg-accent/20 rounded-lg inline-flex items-center justify-center gap-2 transition-colors"
                            title="Выбрать из медиатеки"
                        >
                            📁
                        </button>
                    </div>
                    <p class="text-xs text-muted-foreground mt-1">
                        URL изображения для баннера. Будет отправлено перед текстовым сообщением.
                    </p>
                    <div v-if="welcomeBannerUrl" class="mt-3">
                        <img
                            :src="welcomeBannerUrl"
                            alt="Баннер"
                            class="max-w-md h-auto object-contain rounded border border-border"
                            @error="handleImageError"
                        />
                    </div>
                </div>
            </div>

            <!-- Inline кнопки -->
            <div class="bg-card rounded-lg border border-border p-6 space-y-4">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-sm font-medium">Inline кнопки</label>
                        <button
                            @click="addButton"
                            type="button"
                            :disabled="welcomeButtons.length >= 5"
                            class="h-8 px-3 text-sm bg-accent/10 text-accent border border-accent/40 hover:bg-accent/20 rounded-lg inline-flex items-center justify-center gap-2 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            + Добавить кнопку
                        </button>
                    </div>
                    <p class="text-xs text-muted-foreground mb-4">
                        Максимум 5 кнопок. Кнопки будут отображаться под сообщением.
                    </p>

                    <div v-if="welcomeButtons.length === 0" class="text-sm text-muted-foreground py-4 text-center border border-dashed border-border rounded-lg">
                        Нет кнопок. Нажмите "Добавить кнопку" для создания.
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-for="(button, index) in welcomeButtons"
                            :key="index"
                            class="p-4 border border-border rounded-lg space-y-3"
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium">Кнопка #{{ index + 1 }}</span>
                                <button
                                    @click="removeButton(index)"
                                    type="button"
                                    class="h-8 w-8 flex items-center justify-center rounded-lg hover:bg-destructive/10 text-destructive transition-colors"
                                    title="Удалить кнопку"
                                >
                                    ✕
                                </button>
                            </div>
                            <div>
                                <label class="text-xs font-medium mb-1 block">Текст кнопки</label>
                                <input
                                    v-model="button.label"
                                    type="text"
                                    placeholder="Наш канал"
                                    maxlength="64"
                                    class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                                />
                            </div>
                            <div>
                                <label class="text-xs font-medium mb-1 block">URL</label>
                                <input
                                    v-model="button.url"
                                    type="url"
                                    placeholder="https://t.me/WowSpin_news"
                                    maxlength="500"
                                    class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Кнопка сохранения -->
            <div class="flex justify-end">
                <button
                    @click="saveSettings"
                    :disabled="saving || !isFormValid"
                    class="h-11 px-6 bg-accent/10 backdrop-blur-xl text-accent border border-accent/40 hover:bg-accent/20 rounded-2xl shadow-lg shadow-accent/10 inline-flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <span>{{ saving ? 'Сохранение...' : 'Сохранить' }}</span>
                </button>
            </div>
        </div>

        <!-- Media Selector Modal -->
        <div
            v-if="showMediaModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm"
            @click.self="closeMediaModal"
        >
            <div
                class="bg-background border border-border rounded-lg shadow-2xl w-full max-w-6xl h-[90vh] flex flex-col"
                @click.stop
            >
                <div class="flex items-center justify-between p-4 border-b border-border">
                    <h3 class="text-lg font-semibold">Выберите баннер</h3>
                    <button
                        @click="closeMediaModal"
                        class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-muted transition-colors"
                    >
                        ✕
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto min-h-0">
                    <Media
                        :selectionMode="true"
                        :countFile="1"
                        :selectedFiles="selectedMediaFile ? [selectedMediaFile] : []"
                        @file-selected="handleMediaFileSelected"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted, computed } from 'vue'
import { apiGet, apiPost } from '../../../utils/api'
import Swal from 'sweetalert2'
import Media from '../Media.vue'

export default {
    name: 'Welcome',
    components: {
        Media,
    },
    setup() {
        const loading = ref(false)
        const saving = ref(false)
        const error = ref(null)
        const welcomeText = ref('')
        const welcomeBannerUrl = ref('')
        const welcomeButtons = ref([])
        const showMediaModal = ref(false)
        const selectedMediaFile = ref(null)

        const isFormValid = computed(() => {
            // Проверяем, что все кнопки заполнены правильно
            if (welcomeButtons.value.length > 0) {
                return welcomeButtons.value.every(button => 
                    button.label && button.label.trim() && 
                    button.url && button.url.trim() &&
                    isValidUrl(button.url)
                )
            }
            return true
        })

        const isValidUrl = (url) => {
            try {
                new URL(url)
                return true
            } catch {
                return false
            }
        }

        const fetchSettings = async () => {
            loading.value = true
            error.value = null
            try {
                const response = await apiGet('/wow/welcome')
                if (!response.ok) {
                    throw new Error('Ошибка загрузки настроек')
                }
                const data = await response.json()
                welcomeText.value = data.welcome_text || ''
                welcomeBannerUrl.value = data.welcome_banner_url || ''
                welcomeButtons.value = (data.welcome_buttons || []).map(btn => ({ ...btn }))
            } catch (err) {
                error.value = err.message || 'Ошибка загрузки настроек'
            } finally {
                loading.value = false
            }
        }

        const saveSettings = async () => {
            if (!isFormValid.value) {
                await Swal.fire({
                    title: 'Ошибка валидации',
                    text: 'Проверьте правильность заполнения кнопок',
                    icon: 'error',
                    confirmButtonText: 'ОК'
                })
                return
            }

            saving.value = true
            error.value = null
            try {
                const response = await apiPost('/wow/welcome', {
                    welcome_text: welcomeText.value || null,
                    welcome_banner_url: welcomeBannerUrl.value || null,
                    welcome_buttons: welcomeButtons.value.length > 0 ? welcomeButtons.value : null,
                })

                if (!response.ok) {
                    const errorData = await response.json()
                    throw new Error(errorData.message || 'Ошибка сохранения настроек')
                }

                await Swal.fire({
                    title: 'Настройки сохранены',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                })
            } catch (err) {
                error.value = err.message || 'Ошибка сохранения настроек'
                await Swal.fire({
                    title: 'Ошибка',
                    text: err.message || 'Ошибка сохранения настроек',
                    icon: 'error',
                    confirmButtonText: 'ОК'
                })
            } finally {
                saving.value = false
            }
        }

        const addButton = () => {
            if (welcomeButtons.value.length < 5) {
                welcomeButtons.value.push({
                    label: '',
                    url: '',
                })
            }
        }

        const removeButton = (index) => {
            welcomeButtons.value.splice(index, 1)
        }

        const openMediaSelector = () => {
            showMediaModal.value = true
        }

        const closeMediaModal = () => {
            showMediaModal.value = false
            selectedMediaFile.value = null
        }

        const handleMediaFileSelected = (files) => {
            if (files && files.length > 0) {
                const file = files[0]
                const url = file.url || (file.metadata?.path ? '/' + file.metadata.path : '')
                welcomeBannerUrl.value = url || ''
                selectedMediaFile.value = file
                
                // Закрываем модальное окно после выбора
                setTimeout(() => {
                    closeMediaModal()
                }, 300)
            }
        }

        const handleImageError = (event) => {
            event.target.style.display = 'none'
        }

        onMounted(() => {
            fetchSettings()
        })

        return {
            loading,
            saving,
            error,
            welcomeText,
            welcomeBannerUrl,
            welcomeButtons,
            showMediaModal,
            selectedMediaFile,
            isFormValid,
            fetchSettings,
            saveSettings,
            addButton,
            removeButton,
            openMediaSelector,
            closeMediaModal,
            handleMediaFileSelected,
            handleImageError,
        }
    },
}
</script>

<style scoped>
.welcome-page {
    max-width: 1200px;
    margin: 0 auto;
}
</style>

