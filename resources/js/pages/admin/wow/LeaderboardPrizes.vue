<template>
    <div class="leaderboard-prizes-page space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-semibold text-foreground">Призы лидерборда</h1>
                <p class="text-muted-foreground mt-1">Настройка призов за места в рейтинге</p>
            </div>
            <div class="flex gap-3">
                <button
                    @click="showAddPrizeDialog = true"
                    class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors"
                >
                    + Добавить место
                </button>
                <button
                    @click="savePrizes"
                    :disabled="saving"
                    class="px-6 py-2 bg-primary text-primary-foreground rounded-lg hover:bg-primary/90 transition-colors disabled:opacity-50"
                >
                    {{ saving ? 'Сохранение...' : 'Сохранить' }}
                </button>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex items-center justify-center py-12">
            <p class="text-muted-foreground">Загрузка призов...</p>
        </div>

        <!-- Error State -->
        <div v-if="error" class="p-4 bg-destructive/10 border border-destructive/20 rounded-lg">
            <p class="text-destructive">{{ error }}</p>
        </div>

        <!-- Leaderboard Period Setting -->
        <div v-if="!loading" class="bg-card rounded-lg border border-border p-6">
            <h2 class="text-xl font-semibold mb-4">Настройки лидерборда</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="text-sm font-medium mb-2 block">Период отображения</label>
                    <select
                        v-model.number="leaderboardPeriodMonths"
                        class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                    >
                        <option :value="1">1 месяц</option>
                        <option :value="2">2 месяца</option>
                        <option :value="3">3 месяца</option>
                        <option :value="4">4 месяца</option>
                        <option :value="5">5 месяцев</option>
                        <option :value="6">6 месяцев</option>
                        <option :value="12">12 месяцев</option>
                    </select>
                    <p class="text-xs text-muted-foreground mt-2">
                        Выберите период, за который будут учитываться рефералы в лидерборде
                    </p>
                </div>
            </div>
        </div>

        <!-- Empty State for Prizes -->
        <div v-if="!loading && prizes.length === 0" class="bg-card rounded-lg border border-border p-12 text-center">
            <p class="text-muted-foreground mb-4">Призы за места не настроены</p>
            <p class="text-sm text-muted-foreground">
                Выполните команду: <code class="bg-muted px-2 py-1 rounded">php artisan db:seed --class=LeaderboardPrizeSeeder</code>
            </p>
        </div>

        <!-- Prizes List -->
        <div v-if="!loading && prizes.length > 0" class="space-y-4">
            <div v-for="prize in prizes" :key="prize.id" class="bg-card rounded-lg border border-border p-6">
                <div class="flex items-start justify-between mb-4">
                    <h3 class="text-lg font-semibold">Место {{ prize.rank }}</h3>
                    <button
                        @click="deletePrize(prize.id)"
                        class="px-4 py-2 bg-destructive text-destructive-foreground rounded-lg hover:bg-destructive/90 transition-colors text-sm"
                    >
                        Удалить
                    </button>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Rank -->
                    <div>
                        <label class="text-sm font-medium mb-2 block">Место</label>
                        <input
                            v-model.number="prize.rank"
                            type="number"
                            min="1"
                            class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                            @change="updatePrizeRank(prize)"
                        />
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

        <!-- Add Prize Dialog -->
        <div v-if="showAddPrizeDialog" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="showAddPrizeDialog = false">
            <div class="bg-card rounded-lg border border-border p-6 w-full max-w-md">
                <h2 class="text-xl font-semibold mb-4">Добавить место</h2>
                <div class="space-y-4">
                    <div>
                        <label class="text-sm font-medium mb-2 block">Место</label>
                        <input
                            v-model.number="newPrize.rank"
                            type="number"
                            min="1"
                            class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                        />
                    </div>
                    <div>
                        <label class="text-sm font-medium mb-2 block">Сумма приза (₽)</label>
                        <input
                            v-model.number="newPrize.prize_amount"
                            type="number"
                            min="0"
                            step="100"
                            class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                        />
                    </div>
                    <div>
                        <label class="text-sm font-medium mb-2 block">Описание</label>
                        <input
                            v-model="newPrize.prize_description"
                            type="text"
                            placeholder="Например: 1 место"
                            class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                        />
                    </div>
                    <div class="flex items-center">
                        <input
                            v-model="newPrize.is_active"
                            type="checkbox"
                            id="new-prize-active"
                            class="w-4 h-4 border-border rounded focus:ring-2 focus:ring-accent"
                        />
                        <label for="new-prize-active" class="ml-2 text-sm text-muted-foreground">
                            Активен
                        </label>
                    </div>
                </div>
                <div class="flex gap-3 mt-6">
                    <button
                        @click="addPrize"
                        class="flex-1 px-4 py-2 bg-primary text-primary-foreground rounded-lg hover:bg-primary/90 transition-colors"
                    >
                        Добавить
                    </button>
                    <button
                        @click="showAddPrizeDialog = false"
                        class="flex-1 px-4 py-2 bg-muted text-muted-foreground rounded-lg hover:bg-muted/80 transition-colors"
                    >
                        Отмена
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue'
import { apiGet, apiPost, apiDelete } from '../../../utils/api'
import Swal from 'sweetalert2'

export default {
    name: 'LeaderboardPrizes',
    setup() {
        const loading = ref(false)
        const saving = ref(false)
        const error = ref(null)
        const prizes = ref([])
        const leaderboardPeriodMonths = ref(1)
        const showAddPrizeDialog = ref(false)
        const newPrize = ref({
            rank: 1,
            prize_amount: 0,
            prize_description: '',
            is_active: true,
        })

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
                leaderboardPeriodMonths.value = data.leaderboard_period_months || 1
            } catch (err) {
                error.value = err.message || 'Ошибка загрузки призов'
            } finally {
                loading.value = false
            }
        }

        const savePrizes = async () => {
            saving.value = true
            try {
                // Сохраняем призы только если они есть
                if (prizes.value.length > 0) {
                    const prizesResponse = await apiPost('/wow/leaderboard-prizes/bulk-update', {
                        prizes: prizes.value.map(p => ({
                            id: p.id,
                            prize_amount: p.prize_amount,
                            prize_description: p.prize_description,
                            is_active: p.is_active,
                        }))
                    })

                    if (!prizesResponse.ok) {
                        const errorData = await prizesResponse.json()
                        throw new Error(errorData.message || 'Ошибка сохранения призов')
                    }
                }

                // Сохраняем период лидерборда
                const periodResponse = await apiPost('/wow/leaderboard-prizes/update-period', {
                    leaderboard_period_months: leaderboardPeriodMonths.value
                })

                if (!periodResponse.ok) {
                    const errorData = await periodResponse.json()
                    throw new Error(errorData.message || 'Ошибка сохранения периода')
                }

                await Swal.fire({
                    title: 'Успешно!',
                    text: 'Настройки сохранены',
                    icon: 'success',
                    confirmButtonText: 'OK'
                })

                fetchPrizes() // Обновляем список
            } catch (err) {
                await Swal.fire({
                    title: 'Ошибка',
                    text: err.message || 'Не удалось сохранить настройки',
                    icon: 'error',
                    confirmButtonText: 'OK'
                })
            } finally {
                saving.value = false
            }
        }

        const addPrize = async () => {
            try {
                const response = await apiPost('/wow/leaderboard-prizes', newPrize.value)

                if (!response.ok) {
                    const errorData = await response.json()
                    throw new Error(errorData.message || 'Ошибка создания приза')
                }

                await Swal.fire({
                    title: 'Успешно!',
                    text: 'Место добавлено',
                    icon: 'success',
                    confirmButtonText: 'OK'
                })

                showAddPrizeDialog.value = false
                newPrize.value = {
                    rank: 1,
                    prize_amount: 0,
                    prize_description: '',
                    is_active: true,
                }
                fetchPrizes()
            } catch (err) {
                await Swal.fire({
                    title: 'Ошибка',
                    text: err.message || 'Не удалось добавить место',
                    icon: 'error',
                    confirmButtonText: 'OK'
                })
            }
        }

        const deletePrize = async (id) => {
            const result = await Swal.fire({
                title: 'Удалить место?',
                text: 'Это действие нельзя отменить',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Да, удалить',
                cancelButtonText: 'Отмена',
            })

            if (!result.isConfirmed) return

            try {
                const response = await apiDelete(`/wow/leaderboard-prizes/${id}`)

                if (!response.ok) {
                    const errorData = await response.json()
                    throw new Error(errorData.message || 'Ошибка удаления приза')
                }

                await Swal.fire({
                    title: 'Успешно!',
                    text: 'Место удалено',
                    icon: 'success',
                    confirmButtonText: 'OK'
                })

                fetchPrizes()
            } catch (err) {
                await Swal.fire({
                    title: 'Ошибка',
                    text: err.message || 'Не удалось удалить место',
                    icon: 'error',
                    confirmButtonText: 'OK'
                })
            }
        }

        const updatePrizeRank = async (prize) => {
            // Проверяем, что место уникально
            const existingPrize = prizes.value.find(p => p.id !== prize.id && p.rank === prize.rank)
            if (existingPrize) {
                await Swal.fire({
                    title: 'Ошибка',
                    text: 'Место с таким номером уже существует',
                    icon: 'error',
                    confirmButtonText: 'OK'
                })
                fetchPrizes() // Обновляем список
                return
            }

            // Обновляем через bulk-update
            try {
                const response = await apiPost('/wow/leaderboard-prizes/bulk-update', {
                    prizes: [{
                        id: prize.id,
                        rank: prize.rank,
                        prize_amount: prize.prize_amount,
                        prize_description: prize.prize_description,
                        is_active: prize.is_active,
                    }]
                })

                if (!response.ok) {
                    throw new Error('Ошибка обновления места')
                }
            } catch (err) {
                await Swal.fire({
                    title: 'Ошибка',
                    text: err.message || 'Не удалось обновить место',
                    icon: 'error',
                    confirmButtonText: 'OK'
                })
                fetchPrizes() // Обновляем список
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
            leaderboardPeriodMonths,
            showAddPrizeDialog,
            newPrize,
            fetchPrizes,
            savePrizes,
            addPrize,
            deletePrize,
            updatePrizeRank,
        }
    },
}
</script>

