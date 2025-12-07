<template>
    <div class="wins-page space-y-6">
        <div>
            <h1 class="text-3xl font-semibold text-foreground">Выигрыши</h1>
            <p class="text-muted-foreground mt-1">История всех выигрышей пользователей</p>
        </div>

        <!-- Filters -->
        <div class="bg-card rounded-lg border border-border p-6 space-y-4">
            <h2 class="text-lg font-semibold mb-4">Фильтры</h2>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="text-sm font-medium mb-1 block">Дата от</label>
                    <input
                        v-model="filters.date_from"
                        type="date"
                        class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                    />
                </div>
                <div>
                    <label class="text-sm font-medium mb-1 block">Дата до</label>
                    <input
                        v-model="filters.date_to"
                        type="date"
                        class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                    />
                </div>
                <div>
                    <label class="text-sm font-medium mb-1 block">Пользователь</label>
                    <input
                        v-model="filters.user_search"
                        type="text"
                        placeholder="Telegram ID или username"
                        class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                    />
                </div>
                <div>
                    <label class="text-sm font-medium mb-1 block">Тип приза</label>
                    <select
                        v-model="filters.prize_type"
                        class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                    >
                        <option value="all">Все</option>
                        <option value="money">Деньги</option>
                        <option value="ticket">Билет</option>
                        <option value="secret_box">Секретный бокс</option>
                        <option value="empty">Пусто</option>
                    </select>
                </div>
            </div>
            <div class="flex gap-2">
                <button
                    @click="applyFilters"
                    class="h-10 px-6 bg-accent/10 backdrop-blur-xl text-accent border border-accent/40 hover:bg-accent/20 rounded-2xl shadow-lg shadow-accent/10 inline-flex items-center justify-center gap-2"
                >
                    Применить
                </button>
                <button
                    @click="resetFilters"
                    class="h-10 px-6 bg-muted text-muted-foreground border border-border hover:bg-muted/80 rounded-2xl inline-flex items-center justify-center gap-2"
                >
                    Сбросить
                </button>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-card rounded-lg border border-border overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Дата</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Telegram ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Username</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Тип приза</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Сумма</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Сектор</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="win in wins" :key="win.id" class="hover:bg-muted/30">
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                {{ formatDate(win.spin_time) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                {{ win.user?.telegram_id || '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                {{ win.user?.username || '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span :class="getPrizeTypeClass(win.prize_type)">
                                    {{ getPrizeTypeLabel(win.prize_type) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span v-if="win.prize_type === 'money'">{{ win.prize_value }} ₽</span>
                                <span v-else-if="win.prize_type === 'ticket'">{{ win.prize_value }} билет(ов)</span>
                                <span v-else>-</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                #{{ win.sector_number || win.sector?.sector_number || '-' }}
                            </td>
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
            <p class="text-muted-foreground">Загрузка выигрышей...</p>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue'
import { apiGet } from '../../../utils/api'

export default {
    name: 'Wins',
    setup() {
        const loading = ref(false)
        const wins = ref([])
        const pagination = ref({
            current_page: 1,
            last_page: 1,
            per_page: 50,
            total: 0,
        })
        const filters = ref({
            date_from: '',
            date_to: '',
            user_search: '',
            prize_type: 'all',
        })

        const fetchWins = async (page = 1) => {
            loading.value = true
            try {
                const params = new URLSearchParams({
                    page: page.toString(),
                    per_page: pagination.value.per_page.toString(),
                })

                if (filters.value.date_from) {
                    params.append('date_from', filters.value.date_from)
                }
                if (filters.value.date_to) {
                    params.append('date_to', filters.value.date_to)
                }
                if (filters.value.user_search) {
                    params.append('user_search', filters.value.user_search)
                }
                if (filters.value.prize_type !== 'all') {
                    params.append('prize_type', filters.value.prize_type)
                }

                const response = await apiGet(`/wow/wins?${params.toString()}`)
                if (response.ok) {
                    const data = await response.json()
                    wins.value = data.data || []
                    pagination.value = data.pagination || pagination.value
                }
            } catch (err) {
                console.error('Error loading wins:', err)
            } finally {
                loading.value = false
            }
        }

        const applyFilters = () => {
            fetchWins(1)
        }

        const resetFilters = () => {
            filters.value = {
                date_from: '',
                date_to: '',
                user_search: '',
                prize_type: 'all',
            }
            fetchWins(1)
        }

        const loadPage = (page) => {
            fetchWins(page)
        }

        const formatDate = (date) => {
            if (!date) return '-'
            return new Date(date).toLocaleString('ru-RU')
        }

        const getPrizeTypeLabel = (type) => {
            const labels = {
                money: 'Деньги',
                ticket: 'Билет',
                secret_box: 'Секретный бокс',
                empty: 'Пусто',
            }
            return labels[type] || type
        }

        const getPrizeTypeClass = (type) => {
            const classes = {
                money: 'px-2 py-1 rounded bg-green-500/10 text-green-600 text-xs font-medium',
                ticket: 'px-2 py-1 rounded bg-blue-500/10 text-blue-600 text-xs font-medium',
                secret_box: 'px-2 py-1 rounded bg-purple-500/10 text-purple-600 text-xs font-medium',
                empty: 'px-2 py-1 rounded bg-gray-500/10 text-gray-600 text-xs font-medium',
            }
            return classes[type] || ''
        }

        onMounted(() => {
            fetchWins()
        })

        return {
            loading,
            wins,
            pagination,
            filters,
            fetchWins,
            applyFilters,
            resetFilters,
            loadPage,
            formatDate,
            getPrizeTypeLabel,
            getPrizeTypeClass,
        }
    },
}
</script>

