<template>
    <div class="referrals-page space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-semibold text-foreground">Рефералы</h1>
                <p class="text-muted-foreground mt-1">Управление реферальной системой</p>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-card rounded-lg border border-border p-6">
                <p class="text-sm text-muted-foreground mb-2">Всего реферальных связей</p>
                <p class="text-2xl font-bold">{{ totalReferrals }}</p>
            </div>
            <div class="bg-card rounded-lg border border-border p-6">
                <p class="text-sm text-muted-foreground mb-2">Активных рефералов</p>
                <p class="text-2xl font-bold">{{ activeReferrals }}</p>
            </div>
            <div class="bg-card rounded-lg border border-border p-6">
                <p class="text-sm text-muted-foreground mb-2">Топ приглашающий</p>
                <p class="text-lg font-semibold">{{ topInviter ? `@${topInviter.username || topInviter.telegram_id}` : '—' }}</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-card rounded-lg border border-border p-4 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="text-sm font-medium mb-1 block">Поиск</label>
                    <input
                        v-model="filters.search"
                        type="text"
                        placeholder="Telegram ID..."
                        @input="handleSearch"
                        class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                    />
                </div>
                <div>
                    <label class="text-sm font-medium mb-1 block">Дата от</label>
                    <input
                        v-model="filters.date_from"
                        type="date"
                        @change="fetchReferrals"
                        class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                    />
                </div>
                <div>
                    <label class="text-sm font-medium mb-1 block">Дата до</label>
                    <input
                        v-model="filters.date_to"
                        type="date"
                        @change="fetchReferrals"
                        class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                    />
                </div>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex items-center justify-center py-12">
            <p class="text-muted-foreground">Загрузка рефералов...</p>
        </div>

        <!-- Referrals Table -->
        <div v-if="!loading && referrals.length > 0" class="bg-card rounded-lg border border-border overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-muted/30 border-b border-border">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase">Пригласивший</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase">Приглашенный</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase">Дата приглашения</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="referral in referrals" :key="referral.id" class="hover:bg-muted/10">
                            <td class="px-6 py-4 text-sm font-medium text-foreground">
                                @{{ referral.inviter?.username || referral.inviter?.telegram_id || '—' }}
                            </td>
                            <td class="px-6 py-4 text-sm font-medium text-foreground">
                                @{{ referral.invited?.username || referral.invited?.telegram_id || '—' }}
                            </td>
                            <td class="px-6 py-4 text-sm text-muted-foreground">
                                {{ new Date(referral.invited_at).toLocaleDateString('ru-RU') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Empty State -->
        <div v-if="!loading && referrals.length === 0" class="bg-card rounded-lg border border-border p-12 text-center">
            <p class="text-muted-foreground">Рефералы не найдены</p>
        </div>
    </div>
</template>

<script>
import { ref, onMounted, computed } from 'vue'
import { apiGet } from '../../../utils/api'

export default {
    name: 'Referrals',
    setup() {
        const loading = ref(false)
        const error = ref(null)
        const referrals = ref([])
        const filters = ref({
            search: '',
            date_from: '',
            date_to: '',
        })
        let searchTimeout = null

        const totalReferrals = computed(() => referrals.value.length)
        const activeReferrals = computed(() => {
            // Подсчитываем уникальных приглашающих
            const uniqueInviters = new Set(referrals.value.map(r => r.inviter_id))
            return uniqueInviters.size
        })
        const topInviter = computed(() => {
            // Находим пользователя с наибольшим количеством приглашений
            const counts = {}
            referrals.value.forEach(r => {
                const id = r.inviter_id
                counts[id] = (counts[id] || 0) + 1
            })
            const topId = Object.keys(counts).reduce((a, b) => counts[a] > counts[b] ? a : b, null)
            return referrals.value.find(r => r.inviter_id == topId)?.inviter || null
        })

        const fetchReferrals = async () => {
            loading.value = true
            error.value = null
            try {
                // Для простоты загружаем всех пользователей и их рефералов
                // В реальном приложении нужен отдельный API endpoint для рефералов
                const response = await apiGet('/wow/users', {
                    per_page: 100,
                })
                if (!response.ok) {
                    throw new Error('Ошибка загрузки данных')
                }
                const data = await response.json()
                
                // Формируем список рефералов из данных пользователей
                const referralsList = []
                data.data?.forEach(user => {
                    if (user.invited_by) {
                        // Нужно загрузить данные пригласившего
                        const inviter = data.data?.find(u => u.id === user.invited_by)
                        if (inviter) {
                            referralsList.push({
                                id: `${inviter.id}-${user.id}`,
                                inviter_id: inviter.id,
                                invited_id: user.id,
                                inviter: inviter,
                                invited: user,
                                invited_at: user.created_at,
                            })
                        }
                    }
                })
                
                referrals.value = referralsList
            } catch (err) {
                error.value = err.message || 'Ошибка загрузки рефералов'
            } finally {
                loading.value = false
            }
        }

        const handleSearch = () => {
            if (searchTimeout) {
                clearTimeout(searchTimeout)
            }
            searchTimeout = setTimeout(() => {
                fetchReferrals()
            }, 500)
        }

        onMounted(() => {
            fetchReferrals()
        })

        return {
            loading,
            error,
            referrals,
            filters,
            totalReferrals,
            activeReferrals,
            topInviter,
            fetchReferrals,
            handleSearch,
        }
    },
}
</script>

