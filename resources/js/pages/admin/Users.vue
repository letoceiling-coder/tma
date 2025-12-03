<template>
    <div class="users-page space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-semibold text-foreground">Пользователи</h1>
                <p class="text-muted-foreground mt-1">Управление пользователями системы</p>
            </div>
            <button
                @click="showCreateModal = true"
                class="h-11 px-6 bg-accent/10 backdrop-blur-xl text-accent border border-accent/40 hover:bg-accent/20 rounded-2xl shadow-lg shadow-accent/10 inline-flex items-center justify-center gap-2"
            >
                <span>+</span>
                <span>Создать пользователя</span>
            </button>
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
                            <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase">Имя</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase">Роли</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase">Дата создания</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-muted-foreground uppercase">Действия</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="user in users" :key="user.id" class="hover:bg-muted/10">
                            <td class="px-6 py-4 text-sm text-foreground">{{ user.id }}</td>
                            <td class="px-6 py-4 text-sm font-medium text-foreground">{{ user.name }}</td>
                            <td class="px-6 py-4 text-sm text-foreground">{{ user.email }}</td>
                            <td class="px-6 py-4 text-sm text-foreground">
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="role in user.roles"
                                        :key="role.id"
                                        class="px-2 py-1 text-xs rounded-md bg-accent/10 text-accent"
                                    >
                                        {{ role.name }}
                                    </span>
                                    <span v-if="user.roles.length === 0" class="text-muted-foreground text-xs">Нет ролей</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-muted-foreground">
                                {{ new Date(user.created_at).toLocaleDateString('ru-RU') }}
                            </td>
                            <td class="px-6 py-4 text-sm text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button
                                        @click="editUser(user)"
                                        class="px-3 py-1 text-xs bg-blue-500 hover:bg-blue-600 text-white rounded transition-colors"
                                    >
                                        Редактировать
                                    </button>
                                    <button
                                        @click="deleteUser(user)"
                                        :disabled="user.id === currentUserId"
                                        class="px-3 py-1 text-xs bg-red-500 hover:bg-red-600 text-white rounded transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
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
        <div v-if="!loading && users.length === 0" class="bg-card rounded-lg border border-border p-12 text-center">
            <p class="text-muted-foreground">Пользователи не найдены</p>
        </div>

        <!-- Create/Edit Modal -->
        <div v-if="showCreateModal || showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm">
            <div class="bg-background border border-border rounded-lg shadow-2xl w-full max-w-md p-6">
                <h3 class="text-lg font-semibold mb-4">
                    {{ showEditModal ? 'Редактировать пользователя' : 'Создать пользователя' }}
                </h3>
                <form @submit.prevent="saveUser" class="space-y-4">
                    <div>
                        <label class="text-sm font-medium mb-1 block">Имя</label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            class="w-full h-10 px-3 border border-border rounded bg-background"
                        />
                    </div>
                    <div>
                        <label class="text-sm font-medium mb-1 block">Email</label>
                        <input
                            v-model="form.email"
                            type="email"
                            required
                            class="w-full h-10 px-3 border border-border rounded bg-background"
                        />
                    </div>
                    <div>
                        <label class="text-sm font-medium mb-1 block">
                            Пароль
                            <span v-if="showEditModal" class="text-xs text-muted-foreground">(оставьте пустым, чтобы не менять)</span>
                        </label>
                        <input
                            v-model="form.password"
                            type="password"
                            :required="!showEditModal"
                            :minlength="8"
                            class="w-full h-10 px-3 border border-border rounded bg-background"
                        />
                    </div>
                    <div>
                        <label class="text-sm font-medium mb-1 block">Роли</label>
                        <div class="space-y-2 max-h-48 overflow-y-auto border border-border rounded p-2">
                            <label
                                v-for="role in allRoles"
                                :key="role.id"
                                class="flex items-center gap-2 cursor-pointer hover:bg-muted/10 p-2 rounded"
                            >
                                <input
                                    type="checkbox"
                                    :value="role.id"
                                    v-model="form.roles"
                                    class="w-4 h-4"
                                />
                                <span class="text-sm">{{ role.name }}</span>
                            </label>
                        </div>
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
import { ref, onMounted, computed } from 'vue'
import { apiGet, apiPost, apiPut, apiDelete } from '../../utils/api'
import { useStore } from 'vuex'
import Swal from 'sweetalert2'

export default {
    name: 'Users',
    setup() {
        const store = useStore()
        const loading = ref(false)
        const saving = ref(false)
        const error = ref(null)
        const users = ref([])
        const allRoles = ref([])
        const showCreateModal = ref(false)
        const showEditModal = ref(false)
        const form = ref({
            id: null,
            name: '',
            email: '',
            password: '',
            roles: []
        })

        const currentUserId = computed(() => store.getters.user?.id)

        const fetchUsers = async () => {
            loading.value = true
            error.value = null
            try {
                const response = await apiGet('/users')
                if (!response.ok) {
                    throw new Error('Ошибка загрузки пользователей')
                }
                const data = await response.json()
                // Обрабатываем пагинацию или обычный массив
                if (data.data && Array.isArray(data.data)) {
                    users.value = data.data
                } else if (Array.isArray(data)) {
                    users.value = data
                } else {
                    users.value = []
                }
            } catch (err) {
                error.value = err.message || 'Ошибка загрузки пользователей'
            } finally {
                loading.value = false
            }
        }

        const fetchRoles = async () => {
            try {
                const response = await apiGet('/roles')
                if (!response.ok) {
                    throw new Error('Ошибка загрузки ролей')
                }
                const data = await response.json()
                allRoles.value = data.data || []
            } catch (err) {
                console.error('Error fetching roles:', err)
            }
        }

        const editUser = (user) => {
            form.value = {
                id: user.id,
                name: user.name,
                email: user.email,
                password: '',
                roles: user.roles.map(r => r.id)
            }
            showEditModal.value = true
        }

        const deleteUser = async (user) => {
            const result = await Swal.fire({
                title: 'Удалить пользователя?',
                html: `Вы уверены, что хотите удалить пользователя <strong>"${user.name}"</strong>?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Да, удалить',
                cancelButtonText: 'Отмена',
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
            })

            if (!result.isConfirmed) return

            try {
                const response = await apiDelete(`/users/${user.id}`)
                if (!response.ok) {
                    const errorData = await response.json()
                    throw new Error(errorData.message || 'Ошибка удаления пользователя')
                }
                await Swal.fire({
                    title: 'Пользователь удален',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                })
                await fetchUsers()
            } catch (err) {
                Swal.fire({
                    title: 'Ошибка',
                    text: err.message || 'Ошибка удаления пользователя',
                    icon: 'error',
                    confirmButtonText: 'ОК'
                })
            }
        }

        const saveUser = async () => {
            saving.value = true
            error.value = null
            try {
                const userData = {
                    name: form.value.name,
                    email: form.value.email,
                    roles: form.value.roles
                }
                if (form.value.password) {
                    userData.password = form.value.password
                }

                let response
                if (showEditModal.value) {
                    response = await apiPut(`/users/${form.value.id}`, userData)
                } else {
                    response = await apiPost('/users', userData)
                }

                if (!response.ok) {
                    const errorData = await response.json()
                    throw new Error(errorData.message || 'Ошибка сохранения пользователя')
                }

                await Swal.fire({
                    title: showEditModal.value ? 'Пользователь обновлен' : 'Пользователь создан',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                })

                closeModal()
                await fetchUsers()
            } catch (err) {
                error.value = err.message || 'Ошибка сохранения пользователя'
                Swal.fire({
                    title: 'Ошибка',
                    text: err.message || 'Ошибка сохранения пользователя',
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
                name: '',
                email: '',
                password: '',
                roles: []
            }
        }

        onMounted(async () => {
            await Promise.all([fetchUsers(), fetchRoles()])
        })

        return {
            loading,
            saving,
            error,
            users,
            allRoles,
            showCreateModal,
            showEditModal,
            form,
            currentUserId,
            editUser,
            deleteUser,
            saveUser,
            closeModal
        }
    }
}
</script>
