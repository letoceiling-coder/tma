<template>
    <div class="channels-page space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-semibold text-foreground">Каналы</h1>
                <p class="text-muted-foreground mt-1">Управление обязательными каналами для подписки</p>
            </div>
            <button
                @click="showCreateModal = true"
                class="h-11 px-6 bg-accent/10 backdrop-blur-xl text-accent border border-accent/40 hover:bg-accent/20 rounded-2xl shadow-lg shadow-accent/10 inline-flex items-center justify-center gap-2"
            >
                <span>+</span>
                <span>Добавить канал</span>
            </button>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex items-center justify-center py-12">
            <p class="text-muted-foreground">Загрузка каналов...</p>
        </div>

        <!-- Error State -->
        <div v-if="error" class="p-4 bg-destructive/10 border border-destructive/20 rounded-lg">
            <p class="text-destructive">{{ error }}</p>
        </div>

        <!-- Channels Table -->
        <div v-if="!loading && channels.length > 0" class="bg-card rounded-lg border border-border overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-muted/30 border-b border-border">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase">Username</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase">Название</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase">Внешняя ссылка</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase">Приоритет</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase">Статус</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-muted-foreground uppercase">Действия</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="channel in channels" :key="channel.id" class="hover:bg-muted/10">
                            <td class="px-6 py-4 text-sm font-medium text-foreground">@{{ channel.username }}</td>
                            <td class="px-6 py-4 text-sm text-foreground">{{ channel.title }}</td>
                            <td class="px-6 py-4 text-sm text-foreground">
                                <span v-if="channel.external_url" class="text-blue-500 truncate max-w-xs block" :title="channel.external_url">
                                    {{ channel.external_url }}
                                </span>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                            <td class="px-6 py-4 text-sm text-foreground">{{ channel.priority }}</td>
                            <td class="px-6 py-4 text-sm">
                                <span
                                    :class="[
                                        'px-2 py-1 text-xs rounded-md',
                                        channel.is_active
                                            ? 'bg-green-500/10 text-green-600'
                                            : 'bg-gray-500/10 text-gray-600'
                                    ]"
                                >
                                    {{ channel.is_active ? 'Активен' : 'Неактивен' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button
                                        @click="editChannel(channel)"
                                        class="px-3 py-1 text-xs bg-blue-500 hover:bg-blue-600 text-white rounded transition-colors"
                                    >
                                        Редактировать
                                    </button>
                                    <button
                                        @click="deleteChannel(channel)"
                                        class="px-3 py-1 text-xs bg-red-500 hover:bg-red-600 text-white rounded transition-colors"
                                    >
                                        Удалить
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Empty State -->
        <div v-if="!loading && channels.length === 0" class="bg-card rounded-lg border border-border p-12 text-center">
            <p class="text-muted-foreground">Каналы не найдены. Добавьте первый канал.</p>
        </div>

        <!-- Create/Edit Modal -->
        <div v-if="showCreateModal || showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm">
            <div class="bg-background border border-border rounded-lg shadow-2xl w-full max-w-md p-6">
                <h3 class="text-lg font-semibold mb-4">
                    {{ showEditModal ? 'Редактировать канал' : 'Добавить канал' }}
                </h3>
                <form @submit.prevent="saveChannel" class="space-y-4">
                    <div>
                        <label class="text-sm font-medium mb-1 block">Username (без @)</label>
                        <input
                            v-model="form.username"
                            type="text"
                            placeholder="channel_username"
                            required
                            class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                        />
                    </div>
                    <div>
                        <label class="text-sm font-medium mb-1 block">Название канала</label>
                        <input
                            v-model="form.title"
                            type="text"
                            placeholder="Название канала"
                            required
                            class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                        />
                    </div>
                    <div>
                        <label class="text-sm font-medium mb-1 block">Внешняя ссылка (опционально)</label>
                        <input
                            v-model="form.external_url"
                            type="url"
                            placeholder="https://t.me/channel?utm_source=..."
                            class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                        />
                        <p class="text-xs text-muted-foreground mt-1">Должна начинаться с https://t.me/</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium mb-1 block">Приоритет</label>
                        <input
                            v-model.number="form.priority"
                            type="number"
                            min="0"
                            placeholder="0"
                            class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                        />
                        <p class="text-xs text-muted-foreground mt-1">Чем выше приоритет, тем выше канал в списке</p>
                    </div>
                    <div>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                v-model="form.is_active"
                                type="checkbox"
                                class="w-4 h-4"
                            />
                            <span class="text-sm">Активен</span>
                        </label>
                    </div>
                    <div class="flex gap-2 pt-4">
                        <button
                            type="button"
                            @click="closeModal"
                            class="flex-1 h-10 px-4 border border-border bg-background/50 hover:bg-accent/10 rounded-lg transition-colors"
                        >
                            Отмена
                        </button>
                        <button
                            type="submit"
                            :disabled="saving"
                            class="flex-1 h-10 px-4 bg-accent/10 backdrop-blur-xl text-accent border border-accent/40 hover:bg-accent/20 rounded-lg transition-colors disabled:opacity-50"
                        >
                            {{ saving ? 'Сохранение...' : 'Сохранить' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue'
import { apiGet, apiPost, apiPut, apiDelete } from '../../../utils/api'
import Swal from 'sweetalert2'

export default {
    name: 'Channels',
    setup() {
        const loading = ref(false)
        const saving = ref(false)
        const error = ref(null)
        const channels = ref([])
        const showCreateModal = ref(false)
        const showEditModal = ref(false)
        const form = ref({
            id: null,
            username: '',
            title: '',
            external_url: '',
            priority: 0,
            is_active: true,
        })

        const fetchChannels = async () => {
            loading.value = true
            error.value = null
            try {
                const response = await apiGet('/wow/channels')
                if (!response.ok) {
                    throw new Error('Ошибка загрузки каналов')
                }
                const data = await response.json()
                channels.value = data.data || []
            } catch (err) {
                error.value = err.message || 'Ошибка загрузки каналов'
            } finally {
                loading.value = false
            }
        }

        const editChannel = (channel) => {
            form.value = {
                id: channel.id,
                username: channel.username,
                title: channel.title,
                external_url: channel.external_url || '',
                priority: channel.priority,
                is_active: channel.is_active,
            }
            showEditModal.value = true
        }

        const deleteChannel = async (channel) => {
            const result = await Swal.fire({
                title: 'Удалить канал?',
                html: `Вы уверены, что хотите удалить канал <strong>"@${channel.username}"</strong>?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Да, удалить',
                cancelButtonText: 'Отмена',
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
            })

            if (!result.isConfirmed) return

            try {
                const response = await apiDelete(`/wow/channels/${channel.id}`)
                if (!response.ok) {
                    const errorData = await response.json()
                    throw new Error(errorData.message || 'Ошибка удаления канала')
                }
                await Swal.fire({
                    title: 'Канал удален',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                })
                await fetchChannels()
            } catch (err) {
                Swal.fire({
                    title: 'Ошибка',
                    text: err.message || 'Ошибка удаления канала',
                    icon: 'error',
                    confirmButtonText: 'ОК'
                })
            }
        }

        const saveChannel = async () => {
            saving.value = true
            error.value = null
            try {
                const channelData = {
                    username: form.value.username.replace('@', ''), // Убираем @ если есть
                    title: form.value.title,
                    external_url: form.value.external_url || null,
                    priority: form.value.priority || 0,
                    is_active: form.value.is_active,
                }

                let response
                if (showEditModal.value) {
                    response = await apiPut(`/wow/channels/${form.value.id}`, channelData)
                } else {
                    response = await apiPost('/wow/channels', channelData)
                }

                if (!response.ok) {
                    const errorData = await response.json()
                    throw new Error(errorData.message || 'Ошибка сохранения канала')
                }

                await Swal.fire({
                    title: showEditModal.value ? 'Канал обновлен' : 'Канал создан',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                })
                
                closeModal()
                await fetchChannels()
            } catch (err) {
                error.value = err.message || 'Ошибка сохранения канала'
                Swal.fire({
                    title: 'Ошибка',
                    text: err.message || 'Ошибка сохранения канала',
                    icon: 'error',
                    confirmButtonText: 'ОК'
                })
            } finally {
                saving.value = false
            }
        }

        const closeModal = () => {
            showCreateModal.value = false
            showEditModal.value = false
            form.value = {
                id: null,
                username: '',
                title: '',
                external_url: '',
                priority: 0,
                is_active: true,
            }
            error.value = null
        }

        onMounted(() => {
            fetchChannels()
        })

        return {
            loading,
            saving,
            error,
            channels,
            showCreateModal,
            showEditModal,
            form,
            editChannel,
            deleteChannel,
            saveChannel,
            closeModal,
        }
    },
}
</script>

