<template>
    <div class="statistics-page space-y-6">
        <div>
            <h1 class="text-3xl font-semibold text-foreground">Статистика</h1>
            <p class="text-muted-foreground mt-1">Аналитика и метрики WOW Рулетка</p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-card rounded-lg border border-border p-6">
                <p class="text-sm text-muted-foreground mb-2">Всего звёзд получено</p>
                <p class="text-2xl font-bold">{{ stats.totalStars }}</p>
            </div>
            <div class="bg-card rounded-lg border border-border p-6">
                <p class="text-sm text-muted-foreground mb-2">Всего прокрутов</p>
                <p class="text-2xl font-bold">{{ stats.totalSpins }}</p>
            </div>
            <div class="bg-card rounded-lg border border-border p-6">
                <p class="text-sm text-muted-foreground mb-2">Активных пользователей</p>
                <p class="text-2xl font-bold">{{ stats.activeUsers }}</p>
            </div>
            <div class="bg-card rounded-lg border border-border p-6">
                <p class="text-sm text-muted-foreground mb-2">Всего пользователей</p>
                <p class="text-2xl font-bold">{{ stats.totalUsers }}</p>
            </div>
        </div>

        <!-- Prize Distribution -->
        <div class="bg-card rounded-lg border border-border p-6">
            <h2 class="text-xl font-semibold mb-4">Распределение призов</h2>
            <div v-if="stats.prizeDistribution && stats.prizeDistribution.length > 0" class="space-y-4">
                <div
                    v-for="prize in stats.prizeDistribution"
                    :key="prize.prize_type"
                    class="flex items-center justify-between"
                >
                    <span class="text-sm font-medium">{{ getPrizeTypeLabel(prize.prize_type) }}</span>
                    <div class="flex items-center gap-4">
                        <div class="w-48 h-4 bg-muted rounded-full overflow-hidden">
                            <div
                                class="h-full bg-accent transition-all"
                                :style="{ width: `${(prize.count / stats.totalSpins) * 100}%` }"
                            ></div>
                        </div>
                        <span class="text-sm text-muted-foreground w-20 text-right">
                            {{ prize.count }} ({{ ((prize.count / stats.totalSpins) * 100).toFixed(1) }}%)
                        </span>
                    </div>
                </div>
            </div>
            <p v-else class="text-muted-foreground">Нет данных</p>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex items-center justify-center py-12">
            <p class="text-muted-foreground">Загрузка статистики...</p>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue'
import { apiGet } from '../../../utils/api'

export default {
    name: 'Statistics',
    setup() {
        const loading = ref(false)
        const stats = ref({
            totalStars: 0,
            totalSpins: 0,
            activeUsers: 0,
            totalUsers: 0,
            prizeDistribution: [],
        })

        const fetchStatistics = async () => {
            loading.value = true
            try {
                // Загружаем данные пользователей для расчета статистики
                const usersResponse = await apiGet('/wow/users', { per_page: 100 })
                if (usersResponse.ok) {
                    const usersData = await usersResponse.json()
                    const users = usersData.data || []
                    
                    stats.value.totalUsers = users.length
                    stats.value.activeUsers = users.filter(u => u.spins_count > 0).length
                    stats.value.totalSpins = users.reduce((sum, u) => sum + (u.spins_count || 0), 0)
                    
                    // Подсчитываем распределение призов (упрощенная версия)
                    // В реальном приложении нужен отдельный API endpoint для статистики
                    stats.value.prizeDistribution = [
                        { prize_type: 'money', count: users.reduce((sum, u) => sum + (u.total_wins || 0), 0) },
                        { prize_type: 'empty', count: stats.value.totalSpins - users.reduce((sum, u) => sum + (u.total_wins || 0), 0) },
                    ]
                }
            } catch (err) {
                console.error('Error loading statistics:', err)
            } finally {
                loading.value = false
            }
        }

        const getPrizeTypeLabel = (type) => {
            const labels = {
                money: 'Денежные призы',
                ticket: 'Билеты',
                secret_box: 'Секретный бокс',
                empty: 'Пусто',
            }
            return labels[type] || type
        }

        onMounted(() => {
            fetchStatistics()
        })

        return {
            loading,
            stats,
            getPrizeTypeLabel,
        }
    },
}
</script>

