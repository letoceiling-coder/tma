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

            <!-- Баннер / Карта -->
            <div class="bg-card rounded-lg border border-border p-6 space-y-4">
                <div>
                    <label class="text-sm font-medium mb-2 block">URL баннера (картинки карты)</label>
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
                        URL изображения для баннера/карты. Будет отправлено перед текстовым сообщением.
                    </p>
                    
                    <!-- Превью карты с возможностью управления домиками -->
                    <div v-if="welcomeBannerUrl" class="mt-4">
                        <div class="relative w-full overflow-hidden rounded-lg border border-border bg-background" style="min-height: 400px;">
                            <!-- Карта фото - отображается по ширине экрана полностью -->
                            <img
                                :src="welcomeBannerUrl"
                                alt="Карта"
                                ref="mapImageRef"
                                class="w-full h-auto object-contain"
                                style="display: block; max-width: 100%; height: auto;"
                                @load="onMapImageLoad"
                                @error="handleImageError"
                            />
                            
                            <!-- Домики на карте -->
                            <template v-if="houses && houses.length">
                                <div
                                    v-for="(house, index) in houses"
                                    :key="index"
                                    class="absolute cursor-move transition-all hover:scale-110"
                                    :style="{
                                        left: house.x + '%',
                                        top: house.y + '%',
                                        transform: 'translate(-50%, -50%)',
                                        zIndex: house.active ? 20 : 10
                                    }"
                                    @mousedown="startDrag(index, $event)"
                                    @click="selectHouse(index)"
                                >
                                    <div
                                        class="w-8 h-8 rounded-full border-2 flex items-center justify-center text-xs font-bold"
                                        :class="house.active ? 'bg-accent border-accent text-accent-foreground' : 'bg-white border-gray-400 text-gray-700'"
                                    >
                                        {{ index + 1 }}
                                    </div>
                                </div>
                            </template>
                            
                            <!-- Кнопка добавления домика -->
                            <button
                                v-if="!isDragging"
                                @click="addHouse"
                                type="button"
                                class="absolute top-4 right-4 px-3 py-2 bg-accent/90 text-accent-foreground rounded-lg text-sm font-medium hover:bg-accent transition-colors shadow-lg"
                                :disabled="!houses || houses.length >= 20"
                            >
                                + Добавить домик
                            </button>
                            
                            <!-- Информация о выбранном домике -->
                            <div
                                v-if="selectedHouseIndex !== null && houses[selectedHouseIndex]"
                                class="absolute bottom-4 left-4 right-4 p-3 bg-background/95 backdrop-blur-sm rounded-lg border border-border shadow-lg"
                            >
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium">Домик #{{ selectedHouseIndex + 1 }}</span>
                                    <button
                                        @click="removeHouse(selectedHouseIndex)"
                                        type="button"
                                        class="text-destructive hover:text-destructive/80 text-sm"
                                    >
                                        Удалить
                                    </button>
                                </div>
                                <div class="grid grid-cols-2 gap-2 text-xs">
                                    <div>
                                        <label class="text-muted-foreground">X:</label>
                                        <input
                                            v-model.number="houses[selectedHouseIndex].x"
                                            type="number"
                                            min="0"
                                            max="100"
                                            step="0.1"
                                            class="w-full px-2 py-1 border border-border rounded bg-background text-xs"
                                            @input="updateHousePosition(selectedHouseIndex)"
                                        />
                                    </div>
                                    <div>
                                        <label class="text-muted-foreground">Y:</label>
                                        <input
                                            v-model.number="houses[selectedHouseIndex].y"
                                            type="number"
                                            min="0"
                                            max="100"
                                            step="0.1"
                                            class="w-full px-2 py-1 border border-border rounded bg-background text-xs"
                                            @input="updateHousePosition(selectedHouseIndex)"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Список домиков -->
                        <div v-if="houses && houses.length > 0" class="mt-4 p-3 bg-muted/50 rounded-lg">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium">Домики на карте ({{ houses.length }})</span>
                                <button
                                    @click="clearAllHouses"
                                    type="button"
                                    class="text-xs text-destructive hover:text-destructive/80"
                                >
                                    Очистить все
                                </button>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="(house, index) in houses"
                                    :key="index"
                                    @click="selectHouse(index)"
                                    type="button"
                                    class="px-2 py-1 rounded text-xs border transition-colors"
                                    :class="house.active ? 'bg-accent text-accent-foreground border-accent' : 'bg-background border-border hover:bg-muted'"
                                >
                                    Домик {{ index + 1 }} ({{ Math.round(house.x) }}%, {{ Math.round(house.y) }}%)
                                </button>
                            </div>
                        </div>
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
                            :disabled="!welcomeButtons || welcomeButtons.length >= 5"
                            class="h-8 px-3 text-sm bg-accent/10 text-accent border border-accent/40 hover:bg-accent/20 rounded-lg inline-flex items-center justify-center gap-2 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            + Добавить кнопку
                        </button>
                    </div>
                    <p class="text-xs text-muted-foreground mb-4">
                        Максимум 5 кнопок. Кнопки будут отображаться под сообщением.
                    </p>

                    <div v-if="!welcomeButtons || welcomeButtons.length === 0" class="text-sm text-muted-foreground py-4 text-center border border-dashed border-border rounded-lg">
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
import { ref, onMounted, computed, nextTick } from 'vue'
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
        const mapImageRef = ref(null)
        const houses = ref([])
        const selectedHouseIndex = ref(null)
        const isDragging = ref(false)
        const dragState = ref({
            houseIndex: null,
            startX: 0,
            startY: 0,
            startLeft: 0,
            startTop: 0
        })

        const isFormValid = computed(() => {
            // Проверяем, что все кнопки заполнены правильно
            if (Array.isArray(welcomeButtons.value) && welcomeButtons.value.length > 0) {
                return welcomeButtons.value.every(button => 
                    button && 
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
                
                // Безопасная инициализация welcomeButtons
                if (Array.isArray(data.welcome_buttons)) {
                    welcomeButtons.value = data.welcome_buttons.map(btn => ({ ...btn }))
                } else {
                    welcomeButtons.value = []
                }
                
                // Загружаем домики из данных
                if (data.houses && Array.isArray(data.houses)) {
                    houses.value = data.houses.map(h => ({ ...h, active: false }))
                } else {
                    houses.value = []
                }
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
                    welcome_buttons: (Array.isArray(welcomeButtons.value) && welcomeButtons.value.length > 0) ? welcomeButtons.value : null,
                    houses: (Array.isArray(houses.value) && houses.value.length > 0) ? houses.value.map(h => ({ x: h.x, y: h.y })) : null,
                })

                if (!response.ok) {
                    const errorData = await response.json()
                    let errorMessage = errorData.message || 'Ошибка сохранения настроек'
                    
                    // Если есть ошибки валидации, показываем их
                    if (errorData.errors) {
                        const errorMessages = Object.values(errorData.errors).flat()
                        if (errorMessages.length > 0) {
                            errorMessage = errorMessages.join('\n')
                        }
                    }
                    
                    throw new Error(errorMessage)
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
            if (!Array.isArray(welcomeButtons.value)) {
                welcomeButtons.value = []
            }
            if (welcomeButtons.value.length < 5) {
                welcomeButtons.value.push({
                    label: '',
                    url: '',
                })
            }
        }

        const removeButton = (index) => {
            if (Array.isArray(welcomeButtons.value)) {
                welcomeButtons.value.splice(index, 1)
            }
        }

        const openMediaSelector = () => {
            showMediaModal.value = true
        }

        const closeMediaModal = () => {
            showMediaModal.value = false
            selectedMediaFile.value = null
        }

        const handleMediaFileSelected = (file) => {
            if (file) {
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

        // Функции для работы с домиками
        const onMapImageLoad = () => {
            // Изображение загружено, можно инициализировать домики
        }

        const addHouse = () => {
            if (!Array.isArray(houses.value)) {
                houses.value = []
            }
            if (houses.value.length < 20) {
                houses.value.push({
                    x: 50,
                    y: 50,
                    active: false
                })
            }
        }

        const removeHouse = (index) => {
            if (Array.isArray(houses.value) && index >= 0 && index < houses.value.length) {
                houses.value.splice(index, 1)
                if (selectedHouseIndex.value === index) {
                    selectedHouseIndex.value = null
                }
            }
        }

        const clearAllHouses = () => {
            houses.value = []
            selectedHouseIndex.value = null
        }

        const selectHouse = (index) => {
            if (Array.isArray(houses.value) && index >= 0 && index < houses.value.length) {
                // Деактивируем все домики
                houses.value.forEach(h => h.active = false)
                // Активируем выбранный
                houses.value[index].active = true
                selectedHouseIndex.value = index
            }
        }

        const updateHousePosition = (index) => {
            if (Array.isArray(houses.value) && index >= 0 && index < houses.value.length) {
                const house = houses.value[index]
                // Ограничиваем значения от 0 до 100
                house.x = Math.max(0, Math.min(100, house.x || 0))
                house.y = Math.max(0, Math.min(100, house.y || 0))
            }
        }

        const startDrag = (index, event) => {
            if (!Array.isArray(houses.value) || index < 0 || index >= houses.value.length) {
                return
            }
            isDragging.value = true
            dragState.value.houseIndex = index
            dragState.value.startX = event.clientX
            dragState.value.startY = event.clientY
            const house = houses.value[index]
            dragState.value.startLeft = house.x
            dragState.value.startTop = house.y

            const onMouseMove = (e) => {
                if (!isDragging.value || dragState.value.houseIndex !== index) return
                
                const rect = mapImageRef.value?.getBoundingClientRect()
                if (!rect) return

                const deltaX = ((e.clientX - dragState.value.startX) / rect.width) * 100
                const deltaY = ((e.clientY - dragState.value.startY) / rect.height) * 100

                house.x = Math.max(0, Math.min(100, dragState.value.startLeft + deltaX))
                house.y = Math.max(0, Math.min(100, dragState.value.startTop + deltaY))
            }

            const onMouseUp = () => {
                isDragging.value = false
                dragState.value.houseIndex = null
                document.removeEventListener('mousemove', onMouseMove)
                document.removeEventListener('mouseup', onMouseUp)
            }

            document.addEventListener('mousemove', onMouseMove)
            document.addEventListener('mouseup', onMouseUp)
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
            mapImageRef,
            houses,
            selectedHouseIndex,
            isDragging,
            fetchSettings,
            saveSettings,
            addButton,
            removeButton,
            openMediaSelector,
            closeMediaModal,
            handleMediaFileSelected,
            handleImageError,
            onMapImageLoad,
            addHouse,
            removeHouse,
            clearAllHouses,
            selectHouse,
            updateHousePosition,
            startDrag,
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

