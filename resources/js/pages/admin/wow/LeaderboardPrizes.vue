<template>
    <div class="leaderboard-prizes-page space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-semibold text-foreground">Призы лидерборда</h1>
                <p class="text-muted-foreground mt-1">Настройка призов за места в рейтинге</p>
            </div>
            <button
                @click="savePrizes"
                :disabled="saving"
                class="px-6 py-2 bg-primary text-primary-foreground rounded-lg hover:bg-primary/90 transition-colors disabled:opacity-50"
            >
                {{ saving ? 'Сохранение...' : 'Сохранить' }}
            </button>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex items-center justify-center py-12">
            <p class="text-muted-foreground">Загрузка призов...</p>
        </div>

        <!-- Error State -->
        <div v-if="error" class="p-4 bg-destructive/10 border border-destructive/20 rounded-lg">
            <p class="text-destructive">{{ error }}</p>
        </div>

        <!-- Prizes List -->
        <div v-if="!loading && prizes.length > 0" class="space-y-4">
            <div v-for="prize in prizes" :key="prize.id" class="bg-card rounded-lg border border-border p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Rank -->
                    <div>
                        <label class="text-sm font-medium mb-2 block">Место</label>
                        <div class="flex items-center h-10 px-4 border border-border rounded-lg bg-muted/30">
                            <span class="text-lg font-bold">{{ prize.rank }}</span>
                            <span class="ml-2 text-sm text-muted-foreground">место</span>
                        </div>
                    </div>

                    <!-- Prize Amount -->
                    <div>
                        <label class="text-sm font-medium mb-2 block">Сумма приза (₽)</label>
                        <input
                            v-model.number="prize.prize_amount"
                            type="number"
                            min="0"
                            step="100"
                            class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                        />
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="text-sm font-medium mb-2 block">Описание</label>
                        <input
                            v-model="prize.prize_description"
                            type="text"
                            placeholder="Например: Золото"
                            class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                        />
                    </div>
                </div>

                <!-- Active Toggle -->
                <div class="mt-4 flex items-center">
                    <input
                        v-model="prize.is_active"
                        type="checkbox"
                        :id="`prize-active-${prize.id}`"
                        class="w-4 h-4 border-border rounded focus:ring-2 focus:ring-accent"
                    />
                    <label :for="`prize-active-${prize.id}`" class="ml-2 text-sm text-muted-foreground">
                        Активен
                    </label>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue'
import { apiGet, apiPost } from '../../../utils/api'
import Swal from 'sweetalert2'

export default {
    name: 'LeaderboardPrizes',
    setup() {
        const loading = ref(false)
        const saving = ref(false)
        const error = ref(null)
        const prizes = ref([])

        const fetchPrizes = async () => {
            loading.value = true
            error.value = null
            try {
                const response = await apiGet('/wow/leaderboard-prizes')
                if (!response.ok) {
                    throw new Error('Ошибка загрузки призов')
                }
                const data = await response.json()
                prizes.value = data.data || []
            } catch (err) {
                error.value = err.message || 'Ошибка загрузки призов'
            } finally {
                loading.value = false
            }
        }

        const savePrizes = async () => {
            saving.value = true
            try {
                const response = await apiPost('/wow/leaderboard-prizes/bulk-update', {
                    prizes: prizes.value.map(p => ({
                        id: p.id,
                        prize_amount: p.prize_amount,
                        prize_description: p.prize_description,
                        is_active: p.is_active,
                    }))
                })

                if (!response.ok) {
                    const errorData = await response.json()
                    throw new Error(errorData.message || 'Ошибка сохранения призов')
                }

                await Swal.fire({
                    title: 'Успешно!',
                    text: 'Призы сохранены',
                    icon: 'success',
                    confirmButtonText: 'OK'
                })

                fetchPrizes() // Обновляем список
            } catch (err) {
                await Swal.fire({
                    title: 'Ошибка',
                    text: err.message || 'Не удалось сохранить призы',
                    icon: 'error',
                    confirmButtonText: 'OK'
                })
            } finally {
                saving.value = false
            }
        }

        onMounted(() => {
            fetchPrizes()
        })

        return {
            loading,
            saving,
            error,
            prizes,
            fetchPrizes,
            savePrizes,
        }
    },
}
</script>

