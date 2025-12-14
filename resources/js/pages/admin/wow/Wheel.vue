<template>
    <div class="wheel-page space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-semibold text-foreground">Редактор рулетки</h1>
                <p class="text-muted-foreground mt-1">Настройка секторов рулетки и вероятностей выпадения</p>
            </div>
            <div class="flex items-center gap-4">
                <div
                    :class="[
                        'px-4 py-2 rounded-lg text-sm font-medium',
                        probabilityValid
                            ? 'bg-green-500/10 text-green-600'
                            : 'bg-red-500/10 text-red-600'
                    ]"
                >
                    Вероятность: {{ totalProbability.toFixed(2) }}%
                    <span v-if="!probabilityValid" class="ml-2">(должно быть 100%)</span>
                </div>
                <button
                    @click="saveAllSectors"
                    :disabled="!probabilityValid || saving"
                    class="h-11 px-6 bg-accent/10 backdrop-blur-xl text-accent border border-accent/40 hover:bg-accent/20 rounded-2xl shadow-lg shadow-accent/10 inline-flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <span>{{ saving ? 'Сохранение...' : 'Сохранить все' }}</span>
                </button>
            </div>
        </div>

        <!-- Настройки рулетки -->
        <div v-if="!loading" class="bg-card rounded-lg border border-border p-6 space-y-6">
            <h2 class="text-xl font-semibold mb-4">Настройки рулетки</h2>
            
            <!-- Режим "Всегда пусто" -->
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-base font-medium mb-1">Режим "Всегда пусто"</h3>
                    <p class="text-sm text-muted-foreground">
                        При включении колесо всегда будет останавливаться на пустом секторе
                    </p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input
                        v-model="alwaysEmptyMode"
                        @change="saveSettings"
                        type="checkbox"
                        class="sr-only peer"
                    />
                    <div
                        class="w-11 h-6 bg-muted peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-accent/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-border after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-accent"
                    ></div>
                </label>
            </div>

            <!-- Период восстановления билетов -->
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <h3 class="text-base font-medium mb-1">Период восстановления билета</h3>
                    <p class="text-sm text-muted-foreground">
                        Через сколько часов пользователь получает новый билет (от 1 до 24 часов)
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <input
                        v-model.number="ticketRestoreHours"
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

            <!-- Username администратора -->
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <h3 class="text-base font-medium mb-1">Username администратора</h3>
                    <p class="text-sm text-muted-foreground">
                        Telegram username администратора для генерации ссылок (без @)
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <input
                        v-model="adminUsername"
                        @change="saveSettings"
                        type="text"
                        placeholder="admin_username"
                        class="w-64 h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                    />
                </div>
            </div>

            <!-- Количество стартовых билетов -->
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <h3 class="text-base font-medium mb-1">Стартовые билеты</h3>
                    <p class="text-sm text-muted-foreground">
                        Количество билетов, которые получает новый пользователь при первом входе (от 0 до 100). Если не задано — будет использовано значение по умолчанию: 1
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <input
                        v-model.number="initialTicketsCount"
                        @change="saveSettings"
                        type="number"
                        min="0"
                        max="100"
                        step="1"
                        placeholder="1"
                        class="w-24 h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent text-center"
                    />
                    <span class="text-sm text-muted-foreground">билетов</span>
                </div>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex items-center justify-center py-12">
            <p class="text-muted-foreground">Загрузка секторов...</p>
        </div>

        <!-- Error State -->
        <div v-if="error" class="p-4 bg-destructive/10 border border-destructive/20 rounded-lg">
            <p class="text-destructive">{{ error }}</p>
        </div>

        <!-- Sectors Grid -->
        <div v-if="!loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div
                v-for="sector in sectors"
                :key="sector.id"
                class="bg-card rounded-lg border border-border p-6 space-y-4"
            >
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold">Сектор #{{ sector.sector_number }}</h3>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input
                            v-model="sector.is_active"
                            type="checkbox"
                            class="w-4 h-4"
                        />
                        <span class="text-sm">Активен</span>
                    </label>
                </div>

                <div>
                    <label class="text-sm font-medium mb-1 block">Тип приза</label>
                    <select
                        v-model="sector.prize_type_id"
                        @change="onPrizeTypeChange(sector)"
                        class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                    >
                        <option :value="null">Выберите тип приза</option>
                        <option v-for="prizeType in prizeTypes" :key="prizeType.id" :value="prizeType.id">
                            {{ prizeType.name }} ({{ getTypeLabel(prizeType.type) }})
                        </option>
                    </select>
                    <p v-if="sector.prize_type_id" class="text-xs text-muted-foreground mt-1">
                        {{ getSelectedPrizeTypeInfo(sector.prize_type_id) }}
                    </p>
                </div>

                <div>
                    <label class="text-sm font-medium mb-1 block">
                        Вероятность (%)
                        <span class="text-xs text-muted-foreground">(0-100)</span>
                    </label>
                    <input
                        v-model.number="sector.probability_percent"
                        type="number"
                        min="0"
                        max="100"
                        step="0.01"
                        placeholder="0.00"
                        class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                    />
                    <div class="mt-1 h-2 bg-muted rounded-full overflow-hidden">
                        <div
                            class="h-full bg-accent transition-all"
                            :style="{ width: `${sector.probability_percent}%` }"
                        ></div>
                    </div>
                </div>

                <div>
                    <label class="text-sm font-medium mb-1 block">URL иконки</label>
                    <div class="flex gap-2">
                        <input
                            v-model="sector.icon_url"
                            type="text"
                            placeholder="https://..."
                            class="flex-1 h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                        />
                        <button
                            @click="openMediaSelector(sector)"
                            type="button"
                            class="h-10 px-4 bg-accent/10 text-accent border border-accent/40 hover:bg-accent/20 rounded-lg inline-flex items-center justify-center gap-2 transition-colors"
                            title="Выбрать из медиатеки"
                        >
                            📁
                        </button>
                    </div>
                    <div v-if="sector.icon_url" class="mt-2">
                        <img
                            :src="sector.icon_url"
                            :alt="`Сектор ${sector.sector_number}`"
                            class="w-16 h-16 object-contain rounded"
                            @error="handleImageError"
                        />
                    </div>
                </div>
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
                    <h3 class="text-lg font-semibold">Выберите иконку</h3>
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
    name: 'Wheel',
    components: {
        Media,
    },
    setup() {
        const loading = ref(false)
        const saving = ref(false)
        const savingSettings = ref(false)
        const error = ref(null)
        const sectors = ref([])
        const prizeTypes = ref([])
        const alwaysEmptyMode = ref(false)
        const ticketRestoreHours = ref(3)
        const adminUsername = ref('')
        const initialTicketsCount = ref(1)
        const showMediaModal = ref(false)
        const currentSector = ref(null)
        const selectedMediaFile = ref(null)

        const totalProbability = computed(() => {
            return sectors.value.reduce((sum, sector) => {
                return sum + (parseFloat(sector.probability_percent) || 0)
            }, 0)
        })

        const probabilityValid = computed(() => {
            return Math.abs(totalProbability.value - 100) < 0.01
        })

        const fetchPrizeTypes = async () => {
            try {
                const response = await apiGet('/wow/prize-types')
                if (response.ok) {
                    const data = await response.json()
                    prizeTypes.value = data.data || []
                }
            } catch (err) {
                console.error('Ошибка загрузки типов призов:', err)
            }
        }

        const fetchSectors = async () => {
            loading.value = true
            error.value = null
            try {
                const response = await apiGet('/wow/wheel')
                if (!response.ok) {
                    throw new Error('Ошибка загрузки секторов')
                }
                const data = await response.json()
                sectors.value = (data.data || []).map(sector => ({
                    ...sector,
                    probability_percent: parseFloat(sector.probability_percent) || 0,
                    prize_value: sector.prize_value || 0,
                    prize_type_id: sector.prize_type_id || null,
                }))
                
                // Загружаем настройки
                if (data.settings) {
                    alwaysEmptyMode.value = data.settings.always_empty_mode || false
                    ticketRestoreHours.value = data.settings.ticket_restore_hours || 3
                    adminUsername.value = data.settings.admin_username || ''
                    initialTicketsCount.value = data.settings.initial_tickets_count || 1
                }
            } catch (err) {
                error.value = err.message || 'Ошибка загрузки секторов'
            } finally {
                loading.value = false
            }
        }

        const getTypeLabel = (type) => {
            const labels = {
                money: 'Деньги',
                ticket: 'Билет',
                gift: 'Подарок',
                secret_box: 'Секретный бокс',
                empty: 'Пусто',
                sponsor_gift: 'Подарок от спонсора',
            }
            return labels[type] || type
        }

        const getSelectedPrizeTypeInfo = (prizeTypeId) => {
            const prizeType = prizeTypes.value.find(pt => pt.id === prizeTypeId)
            if (!prizeType) return ''
            let info = `Тип: ${getTypeLabel(prizeType.type)}`
            if (prizeType.value) {
                info += `, Значение: ${prizeType.value}`
            }
            if (prizeType.action !== 'none') {
                info += `, Действие: ${prizeType.action === 'add_ticket' ? 'Добавить билет' : prizeType.action}`
            }
            return info
        }

        const onPrizeTypeChange = (sector) => {
            const prizeType = prizeTypes.value.find(pt => pt.id === sector.prize_type_id)
            if (prizeType) {
                // Автоматически заполняем поля из типа приза
                sector.prize_type = prizeType.type
                sector.prize_value = prizeType.value || 0
                sector.icon_url = prizeType.icon_url || sector.icon_url
            }
        }

        const saveAllSectors = async () => {
            if (!probabilityValid.value) {
                await Swal.fire({
                    title: 'Ошибка',
                    text: 'Сумма вероятностей должна быть равна 100%',
                    icon: 'error',
                    confirmButtonText: 'ОК'
                })
                return
            }

            saving.value = true
            error.value = null
            try {
                const sectorsData = sectors.value.map(sector => {
                    // Извлекаем строковое значение prize_type
                    // Если это объект (с полем type), используем его, иначе используем как строку
                    let prizeType = sector.prize_type;
                    if (prizeType && typeof prizeType === 'object') {
                        prizeType = prizeType.type || prizeType.prize_type || null;
                    }
                    
                    return {
                        id: sector.id,
                        prize_type: prizeType || 'empty', // Fallback на 'empty' если не определен
                        prize_value: sector.prize_value || 0,
                        icon_url: sector.icon_url || null,
                        probability_percent: parseFloat(sector.probability_percent) || 0,
                        is_active: sector.is_active !== false,
                        prize_type_id: sector.prize_type_id || null,
                    };
                })

                const response = await apiPost('/wow/wheel/bulk-update', {
                    sectors: sectorsData,
                })

                if (!response.ok) {
                    const errorData = await response.json()
                    throw new Error(errorData.message || 'Ошибка сохранения секторов')
                }

                const data = await response.json()
                
                await Swal.fire({
                    title: 'Секторы сохранены',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                })

                await fetchSectors()
            } catch (err) {
                error.value = err.message || 'Ошибка сохранения секторов'
                await Swal.fire({
                    title: 'Ошибка',
                    text: err.message || 'Ошибка сохранения секторов',
                    icon: 'error',
                    confirmButtonText: 'ОК'
                })
            } finally {
                saving.value = false
            }
        }

        const handleImageError = (event) => {
            event.target.style.display = 'none'
        }

        const openMediaSelector = (sector) => {
            currentSector.value = sector
            selectedMediaFile.value = null
            showMediaModal.value = true
        }

        const closeMediaModal = () => {
            showMediaModal.value = false
            currentSector.value = null
            selectedMediaFile.value = null
        }

        const handleMediaFileSelected = (file) => {
            if (currentSector.value && file) {
                // Устанавливаем URL выбранного файла
                // MediaResource возвращает url в формате '/upload/obshhaia/filename.png'
                const url = file.url || (file.metadata?.path ? '/' + file.metadata.path : '')
                currentSector.value.icon_url = url || ''
                selectedMediaFile.value = file
                
                // Закрываем модальное окно после выбора
                setTimeout(() => {
                    closeMediaModal()
                }, 300)
            }
        }

        const saveSettings = async () => {
            savingSettings.value = true
            try {
                const response = await apiPost('/wow/wheel/settings', {
                    always_empty_mode: alwaysEmptyMode.value,
                    ticket_restore_hours: ticketRestoreHours.value,
                    admin_username: adminUsername.value,
                    initial_tickets_count: initialTicketsCount.value,
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
                await Swal.fire({
                    title: 'Ошибка',
                    text: err.message || 'Ошибка сохранения настроек',
                    icon: 'error',
                    confirmButtonText: 'ОК'
                })
                // Откатываем значение при ошибке
                await fetchSectors()
            } finally {
                savingSettings.value = false
            }
        }

        onMounted(async () => {
            await fetchPrizeTypes()
            await fetchSectors()
        })

        return {
            loading,
            saving,
            savingSettings,
            error,
            sectors,
            alwaysEmptyMode,
            ticketRestoreHours,
            adminUsername,
            totalProbability,
            probabilityValid,
            saveAllSectors,
            saveSettings,
            handleImageError,
            showMediaModal,
            currentSector,
            selectedMediaFile,
            openMediaSelector,
            closeMediaModal,
            handleMediaFileSelected,
            prizeTypes,
            getTypeLabel,
            getSelectedPrizeTypeInfo,
            onPrizeTypeChange,
        }
    },
}
</script>

