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
                        v-model="sector.prize_type"
                        class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                    >
                        <option value="empty">Пусто</option>
                        <option value="money">Деньги</option>
                        <option value="ticket">Билет</option>
                        <option value="secret_box">Секретный бокс</option>
                    </select>
                </div>

                <div v-if="sector.prize_type === 'money'">
                    <label class="text-sm font-medium mb-1 block">Сумма (₽)</label>
                    <input
                        v-model.number="sector.prize_value"
                        type="number"
                        min="0"
                        placeholder="300"
                        class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                    />
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
                    <input
                        v-model="sector.icon_url"
                        type="text"
                        placeholder="https://..."
                        class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                    />
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
    </div>
</template>

<script>
import { ref, onMounted, computed } from 'vue'
import { apiGet, apiPost } from '../../../utils/api'
import Swal from 'sweetalert2'

export default {
    name: 'Wheel',
    setup() {
        const loading = ref(false)
        const saving = ref(false)
        const error = ref(null)
        const sectors = ref([])

        const totalProbability = computed(() => {
            return sectors.value.reduce((sum, sector) => {
                return sum + (parseFloat(sector.probability_percent) || 0)
            }, 0)
        })

        const probabilityValid = computed(() => {
            return Math.abs(totalProbability.value - 100) < 0.01
        })

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
                }))
            } catch (err) {
                error.value = err.message || 'Ошибка загрузки секторов'
            } finally {
                loading.value = false
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
                const sectorsData = sectors.value.map(sector => ({
                    id: sector.id,
                    prize_type: sector.prize_type,
                    prize_value: sector.prize_value || 0,
                    icon_url: sector.icon_url || null,
                    probability_percent: parseFloat(sector.probability_percent) || 0,
                    is_active: sector.is_active !== false,
                }))

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

        onMounted(() => {
            fetchSectors()
        })

        return {
            loading,
            saving,
            error,
            sectors,
            totalProbability,
            probabilityValid,
            saveAllSectors,
            handleImageError,
        }
    },
}
</script>

