<template>
    <div class="wow-users-page space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-semibold text-foreground">Пользователи WOW</h1>
                <p class="text-muted-foreground mt-1">Управление пользователями рулетки</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-card rounded-lg border border-border p-4 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="text-sm font-medium mb-1 block">Поиск</label>
                    <input
                        v-model="filters.search"
                        type="text"
                        placeholder="Username, Telegram ID..."
                        @input="handleSearch"
                        class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                    />
                </div>
                <div>
                    <label class="text-sm font-medium mb-1 block">Дата от</label>
                    <input
                        v-model="filters.date_from"
                        type="date"
                        @change="fetchUsers"
                        class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                    />
                </div>
                <div>
                    <label class="text-sm font-medium mb-1 block">Дата до</label>
                    <input
                        v-model="filters.date_to"
                        type="date"
                        @change="fetchUsers"
                        class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                    />
                </div>
                <div>
                    <label class="text-sm font-medium mb-1 block">На странице</label>
                    <select
                        v-model="perPage"
                        @change="fetchUsers"
                        class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                    >
                        <option :value="15">15</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                        <option :value="100">100</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex items-center justify-center py-12">
            <p class="text-muted-foreground">Загрузка пользователей...</p>
        </div>

        <!-- Error State -->
        <div v-if="error" class="p-4 bg-destructive/10 border border-destructive/20 rounded-lg">
            <p class="text-destructive">{{ error }}</p>
        </div>

        <!-- Users Table -->
        <div v-if="!loading && users.length > 0" class="bg-card rounded-lg border border-border overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-muted/30 border-b border-border">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase">Telegram ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase">Username</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase">Билеты</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase">Прокруты</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase">Выигрыши</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase">Приглашения</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-muted-foreground uppercase">Действия</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="user in users" :key="user.id" class="hover:bg-muted/10">
                            <td class="px-6 py-4 text-sm text-foreground">{{ user.id }}</td>
                            <td class="px-6 py-4 text-sm text-foreground">{{ user.telegram_id }}</td>
                            <td class="px-6 py-4 text-sm font-medium text-foreground">
                                {{ user.username || '—' }}
                            </td>
                            <td class="px-6 py-4 text-sm text-foreground">
                                <span class="px-2 py-1 bg-blue-500/10 text-blue-600 rounded">
                                    {{ user.tickets_available || 0 }}/3
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-foreground">{{ user.spins_count || 0 }}</td>
                            <td class="px-6 py-4 text-sm text-foreground">{{ user.total_wins || 0 }}</td>
                            <td class="px-6 py-4 text-sm text-foreground">{{ user.invites_count || 0 }}</td>
                            <td class="px-6 py-4 text-sm text-right">
                                <button
                                    @click="viewUser(user)"
                                    class="px-3 py-1 text-xs bg-blue-500 hover:bg-blue-600 text-white rounded transition-colors"
                                >
                                    Подробнее
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="pagination.last_page > 1" class="px-6 py-4 border-t border-border flex items-center justify-between">
                <div class="text-sm text-muted-foreground">
                    Показано {{ pagination.from }} - {{ pagination.to }} из {{ pagination.total }}
                </div>
                <div class="flex gap-2">
                    <button
                        @click="changePage(pagination.current_page - 1)"
                        :disabled="pagination.current_page === 1"
                        class="px-4 py-2 text-sm border border-border rounded-lg hover:bg-muted/10 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Назад
                    </button>
                    <button
                        @click="changePage(pagination.current_page + 1)"
                        :disabled="pagination.current_page === pagination.last_page"
                        class="px-4 py-2 text-sm border border-border rounded-lg hover:bg-muted/10 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Вперед
                    </button>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-if="!loading && users.length === 0" class="bg-card rounded-lg border border-border p-12 text-center">
            <p class="text-muted-foreground">Пользователи не найдены</p>
        </div>

        <!-- User Detail Modal -->
        <div v-if="selectedUser" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="bg-background border border-border rounded-lg shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
                <div class="p-6 border-b border-border">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold">Пользователь #{{ selectedUser.id }}</h3>
                        <button
                            @click="selectedUser = null"
                            class="text-muted-foreground hover:text-foreground"
                        >
                            ✕
                        </button>
                    </div>
                </div>
                <div class="p-6 space-y-6">
                    <!-- User Info -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-muted-foreground">Telegram ID</p>
                            <p class="text-sm font-medium">{{ selectedUser.telegram_id }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Username</p>
                            <p class="text-sm font-medium">{{ selectedUser.username || '—' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Билеты</p>
                            <p class="text-sm font-medium">{{ selectedUser.tickets_available || 0 }}/3</p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Звёзды</p>
                            <p class="text-sm font-medium">{{ selectedUser.stars_balance || 0 }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Всего прокрутов</p>
                            <p class="text-sm font-medium">{{ selectedUser.spins_count || 0 }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Выигрышей</p>
                            <p class="text-sm font-medium">{{ selectedUser.total_wins || 0 }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue'
import { apiGet } from '../../../utils/api'

export default {
    name: 'WowUsers',
    setup() {
        const loading = ref(false)
        const error = ref(null)
        const users = ref([])
        const selectedUser = ref(null)
        const perPage = ref(15)
        const pagination = ref({
            current_page: 1,
            last_page: 1,
            total: 0,
            from: 0,
            to: 0,
        })
        const filters = ref({
            search: '',
            date_from: '',
            date_to: '',
        })
        let searchTimeout = null

        const fetchUsers = async (page = 1) => {
            loading.value = true
            error.value = null
            try {
                const params = {
                    page,
                    per_page: perPage.value,
                }
                
                if (filters.value.search) {
                    params.search = filters.value.search
                }
                if (filters.value.date_from) {
                    params.date_from = filters.value.date_from
                }
                if (filters.value.date_to) {
                    params.date_to = filters.value.date_to
                }

                const response = await apiGet('/wow/users', params)
                if (!response.ok) {
                    throw new Error('Ошибка загрузки пользователей')
                }
                const data = await response.json()
                
                users.value = data.data || []
                pagination.value = {
                    current_page: data.current_page || 1,
                    last_page: data.last_page || 1,
                    total: data.total || 0,
                    from: data.from || 0,
                    to: data.to || 0,
                }
            } catch (err) {
                error.value = err.message || 'Ошибка загрузки пользователей'
            } finally {
                loading.value = false
            }
        }

        const handleSearch = () => {
            if (searchTimeout) {
                clearTimeout(searchTimeout)
            }
            searchTimeout = setTimeout(() => {
                fetchUsers(1)
            }, 500)
        }

        const changePage = (page) => {
            fetchUsers(page)
        }

        const viewUser = async (user) => {
            try {
                const response = await apiGet(`/wow/users/${user.id}`)
                if (!response.ok) {
                    throw new Error('Ошибка загрузки данных пользователя')
                }
                const data = await response.json()
                selectedUser.value = data.data
            } catch (err) {
                console.error('Error loading user details:', err)
            }
        }

        onMounted(() => {
            fetchUsers()
        })

        return {
            loading,
            error,
            users,
            selectedUser,
            pagination,
            perPage,
            filters,
            fetchUsers,
            handleSearch,
            changePage,
            viewUser,
        }
    },
}
</script>

