<template>
    <div class="leaderboard-page space-y-6">
        <div>
            <h1 class="text-3xl font-semibold text-foreground">Топ пользователей</h1>
            <p class="text-muted-foreground mt-1">Рейтинг пользователей по выигрышам и активности</p>
        </div>

        <!-- Filters -->
        <div class="bg-card rounded-lg border border-border p-6 space-y-4">
            <h2 class="text-lg font-semibold mb-4">Фильтры и сортировка</h2>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="text-sm font-medium mb-1 block">Период</label>
                    <select
                        v-model="filters.period"
                        @change="fetchLeaderboard"
                        class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                    >
                        <option value="all">Всё время</option>
                        <option value="week">Неделя</option>
                        <option value="day">День</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium mb-1 block">Сортировка</label>
                    <select
                        v-model="filters.sort_by"
                        @change="fetchLeaderboard"
                        class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                    >
                        <option value="wins_count">Количество выигрышей</option>
                        <option value="total_wins_amount">Сумма выигрышей</option>
                        <option value="spins_count">Количество прокрутов</option>
                        <option value="invites_count">Количество приглашенных</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium mb-1 block">Порядок</label>
                    <select
                        v-model="filters.sort_order"
                        @change="fetchLeaderboard"
                        class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                    >
                        <option value="desc">По убыванию</option>
                        <option value="asc">По возрастанию</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium mb-1 block">На странице</label>
                    <select
                        v-model="perPage"
                        @change="fetchLeaderboard"
                        class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                    >
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                        <option :value="100">100</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-card rounded-lg border border-border overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Место</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Telegram ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Username</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Выигрыши</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Сумма выигрышей</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Прокруты</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Приглашенных</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="(user, index) in users" :key="user.id" class="hover:bg-muted/30">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold">
                                <span v-if="index < 3" class="text-2xl">{{ getMedal(index) }}</span>
                                <span v-else>{{ index + 1 }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">{{ user.telegram_id || '-' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">{{ user.username || '-' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">{{ user.wins_count || 0 }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span v-if="user.total_wins_amount">{{ user.total_wins_amount }} ₽</span>
                                <span v-else>-</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">{{ user.spins_count || 0 }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">{{ user.invites_count || 0 }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="pagination.last_page > 1" class="px-6 py-4 border-t border-border flex items-center justify-between">
                <div class="text-sm text-muted-foreground">
                    Показано {{ (pagination.current_page - 1) * pagination.per_page + 1 }} - 
                    {{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }} 
                    из {{ pagination.total }}
                </div>
                <div class="flex gap-2">
                    <button
                        @click="loadPage(pagination.current_page - 1)"
                        :disabled="pagination.current_page === 1"
                        class="h-10 px-4 border border-border rounded-lg hover:bg-muted disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Назад
                    </button>
                    <button
                        @click="loadPage(pagination.current_page + 1)"
                        :disabled="pagination.current_page === pagination.last_page"
                        class="h-10 px-4 border border-border rounded-lg hover:bg-muted disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Вперед
                    </button>
                </div>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex items-center justify-center py-12">
            <p class="text-muted-foreground">Загрузка лидерборда...</p>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue'
import { apiGet } from '../../../utils/api'

export default {
    name: 'Leaderboard',
    setup() {
        const loading = ref(false)
        const users = ref([])
        const pagination = ref({
            current_page: 1,
            last_page: 1,
            per_page: 50,
            total: 0,
        })
        const filters = ref({
            period: 'all',
            sort_by: 'wins_count',
            sort_order: 'desc',
        })
        const perPage = ref(50)

        const fetchLeaderboard = async (page = 1) => {
            loading.value = true
            try {
                const params = new URLSearchParams({
                    page: page.toString(),
                    per_page: perPage.value.toString(),
                    period: filters.value.period,
                    sort_by: filters.value.sort_by,
                    sort_order: filters.value.sort_order,
                })

                const response = await apiGet(`/wow/leaderboard?${params.toString()}`)
                if (response.ok) {
                    const data = await response.json()
                    users.value = data.data || []
                    pagination.value = data.pagination || pagination.value
                }
            } catch (err) {
                console.error('Error loading leaderboard:', err)
            } finally {
                loading.value = false
            }
        }

        const loadPage = (page) => {
            fetchLeaderboard(page)
        }

        const getMedal = (index) => {
            const medals = ['🥇', '🥈', '🥉']
            return medals[index] || ''
        }

        onMounted(() => {
            fetchLeaderboard()
        })

        return {
            loading,
            users,
            pagination,
            filters,
            perPage,
            fetchLeaderboard,
            loadPage,
            getMedal,
        }
    },
}
</script>

