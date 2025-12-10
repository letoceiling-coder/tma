<template>
    <div class="prize-types-page space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-semibold text-foreground">Типы призов</h1>
                <p class="text-muted-foreground mt-1">Управление типами призов для рулетки</p>
            </div>
            <button
                @click="showCreateModal = true"
                class="h-11 px-6 bg-accent/10 backdrop-blur-xl text-accent border border-accent/40 hover:bg-accent/20 rounded-2xl shadow-lg shadow-accent/10 inline-flex items-center justify-center gap-2"
            >
                <span>+</span>
                <span>Добавить тип приза</span>
            </button>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex items-center justify-center py-12">
            <p class="text-muted-foreground">Загрузка типов призов...</p>
        </div>

        <!-- Error State -->
        <div v-if="error" class="p-4 bg-destructive/10 border border-destructive/20 rounded-lg">
            <p class="text-destructive">{{ error }}</p>
        </div>

        <!-- Prize Types Table -->
        <div v-if="!loading && prizeTypes.length > 0" class="bg-card rounded-lg border border-border overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-muted/30 border-b border-border">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase">Название</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase">Тип</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase">Значение</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase">Действие</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase">Статус</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-muted-foreground uppercase">Действия</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="prizeType in prizeTypes" :key="prizeType.id" class="hover:bg-muted/10">
                            <td class="px-6 py-4 text-sm font-medium text-foreground">{{ prizeType.name }}</td>
                            <td class="px-6 py-4 text-sm text-foreground">
                                <span class="px-2 py-1 text-xs rounded-md bg-blue-500/10 text-blue-600">
                                    {{ getTypeLabel(prizeType.type) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-foreground">{{ prizeType.value || '-' }}</td>
                            <td class="px-6 py-4 text-sm text-foreground">
                                <span v-if="prizeType.action !== 'none'" class="px-2 py-1 text-xs rounded-md bg-green-500/10 text-green-600">
                                    {{ getActionLabel(prizeType.action) }}
                                </span>
                                <span v-else class="text-muted-foreground">-</span>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <span
                                    :class="[
                                        'px-2 py-1 text-xs rounded-md',
                                        prizeType.is_active
                                            ? 'bg-green-500/10 text-green-600'
                                            : 'bg-gray-500/10 text-gray-600'
                                    ]"
                                >
                                    {{ prizeType.is_active ? 'Активен' : 'Неактивен' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button
                                        @click="editPrizeType(prizeType)"
                                        class="px-3 py-1 text-xs bg-blue-500 hover:bg-blue-600 text-white rounded transition-colors"
                                    >
                                        Редактировать
                                    </button>
                                    <button
                                        @click="deletePrizeType(prizeType)"
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
        <div v-if="!loading && prizeTypes.length === 0" class="bg-card rounded-lg border border-border p-12 text-center">
            <p class="text-muted-foreground">Типы призов не найдены. Добавьте первый тип приза.</p>
        </div>

        <!-- Create/Edit Modal -->
        <div v-if="showCreateModal || showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm" @click.self="closeModal">
            <div class="bg-background border border-border rounded-lg shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto p-6">
                <h3 class="text-lg font-semibold mb-4">
                    {{ showEditModal ? 'Редактировать тип приза' : 'Добавить тип приза' }}
                </h3>
                <form @submit.prevent="savePrizeType" class="space-y-4">
                    <div>
                        <label class="text-sm font-medium mb-1 block">Название <span class="text-red-500">*</span></label>
                        <input
                            v-model="form.name"
                            type="text"
                            placeholder="Деньги 300 рублей"
                            required
                            class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                        />
                    </div>

                    <div>
                        <label class="text-sm font-medium mb-1 block">Тип <span class="text-red-500">*</span></label>
                        <select
                            v-model="form.type"
                            required
                            class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                        >
                            <option value="money">Деньги</option>
                            <option value="ticket">Билет</option>
                            <option value="gift">Подарок</option>
                            <option value="secret_box">Секретный бокс</option>
                            <option value="empty">Пусто</option>
                            <option value="sponsor_gift">Подарок от спонсора</option>
                        </select>
                    </div>

                    <div>
                        <label class="text-sm font-medium mb-1 block">Значение</label>
                        <input
                            v-model.number="form.value"
                            type="number"
                            min="0"
                            placeholder="300"
                            class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                        />
                        <p class="text-xs text-muted-foreground mt-1">Сумма денег, количество билетов и т.д.</p>
                    </div>

                    <div>
                        <label class="text-sm font-medium mb-1 block">Текст сообщения</label>
                        <textarea
                            v-model="form.message"
                            rows="3"
                            placeholder="Поздравляем! Вы выиграли..."
                            class="w-full px-4 py-2 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                        ></textarea>
                        <p class="text-xs text-muted-foreground mt-1">Сообщение, которое увидит пользователь после выигрыша</p>
                    </div>

                    <div>
                        <label class="text-sm font-medium mb-1 block">Действие</label>
                        <select
                            v-model="form.action"
                            class="w-full h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                        >
                            <option value="none">Нет действия</option>
                            <option value="add_ticket">Добавить билет</option>
                        </select>
                        <p class="text-xs text-muted-foreground mt-1">Дополнительное действие при выигрыше (например, начисление билета)</p>
                    </div>

                    <div>
                        <label class="text-sm font-medium mb-1 block">URL иконки</label>
                        <div class="flex gap-2">
                            <input
                                v-model="form.icon_url"
                                type="text"
                                placeholder="https://..."
                                class="flex-1 h-10 px-4 border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-accent"
                            />
                            <button
                                @click="openMediaSelector"
                                type="button"
                                class="h-10 px-4 bg-accent/10 text-accent border border-accent/40 hover:bg-accent/20 rounded-lg inline-flex items-center justify-center gap-2 transition-colors"
                                title="Выбрать из медиатеки"
                            >
                                📁
                            </button>
                        </div>
                        <div v-if="form.icon_url" class="mt-2">
                            <img
                                :src="form.icon_url"
                                alt="Иконка"
                                class="w-16 h-16 object-contain rounded"
                                @error="handleImageError"
                            />
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            id="is_active"
                            class="w-4 h-4"
                        />
                        <label for="is_active" class="text-sm font-medium cursor-pointer">Активен</label>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4">
                        <button
                            type="button"
                            @click="closeModal"
                            class="px-4 py-2 text-sm border border-border rounded-lg hover:bg-muted transition-colors"
                        >
                            Отмена
                        </button>
                        <button
                            type="submit"
                            :disabled="saving"
                            class="px-4 py-2 text-sm bg-accent text-white rounded-lg hover:bg-accent/90 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            {{ saving ? 'Сохранение...' : 'Сохранить' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Media Selector Modal -->
        <div
            v-if="showMediaModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm"
            @click.self="closeMediaModal"
        >
            <div
                class="bg-background border border-border rounded-lg shadow-2xl w-full max-w-6xl h-[90vh] flex flex-col"
                @click.stop
            >
                <div class="flex items-center justify-between p-4 border-b border-border">
                    <h3 class="text-lg font-semibold">Выберите иконку</h3>
                    <button
                        @click="closeMediaModal"
                        class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-muted transition-colors"
                    >
                        ✕
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto min-h-0">
                    <Media
                        :selectionMode="true"
                        :countFile="1"
                        :selectedFiles="selectedMediaFile ? [selectedMediaFile] : []"
                        @file-selected="handleMediaFileSelected"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue'
import { apiGet, apiPost, apiPut, apiDelete } from '../../../utils/api'
import Swal from 'sweetalert2'
import Media from '../Media.vue'

export default {
    name: 'PrizeTypes',
    components: {
        Media,
    },
    setup() {
        const loading = ref(false)
        const saving = ref(false)
        const error = ref(null)
        const prizeTypes = ref([])
        const showCreateModal = ref(false)
        const showEditModal = ref(false)
        const showMediaModal = ref(false)
        const selectedMediaFile = ref(null)
        const currentPrizeType = ref(null)

        const form = ref({
            name: '',
            type: 'empty',
            value: 0,
            message: '',
            action: 'none',
            icon_url: '',
            is_active: true,
        })

        const getTypeLabel = (type) => {
            const labels = {
                money: 'Деньги',
                ticket: 'Билет',
                gift: 'Подарок',
                secret_box: 'Секретный бокс',
                empty: 'Пусто',
                sponsor_gift: 'Подарок от спонсора',
            }
            return labels[type] || type
        }

        const getActionLabel = (action) => {
            const labels = {
                none: 'Нет действия',
                add_ticket: 'Добавить билет',
            }
            return labels[action] || action
        }

        const fetchPrizeTypes = async () => {
            loading.value = true
            error.value = null
            try {
                const response = await apiGet('/wow/prize-types')
                if (!response.ok) {
                    throw new Error('Ошибка загрузки типов призов')
                }
                const data = await response.json()
                prizeTypes.value = data.data || []
            } catch (err) {
                error.value = err.message || 'Ошибка загрузки типов призов'
            } finally {
                loading.value = false
            }
        }

        const resetForm = () => {
            form.value = {
                name: '',
                type: 'empty',
                value: 0,
                message: '',
                action: 'none',
                icon_url: '',
                is_active: true,
            }
            currentPrizeType.value = null
        }

        const closeModal = () => {
            showCreateModal.value = false
            showEditModal.value = false
            resetForm()
        }

        const editPrizeType = (prizeType) => {
            currentPrizeType.value = prizeType
            form.value = {
                name: prizeType.name,
                type: prizeType.type,
                value: prizeType.value || 0,
                message: prizeType.message || '',
                action: prizeType.action || 'none',
                icon_url: prizeType.icon_url || '',
                is_active: prizeType.is_active !== false,
            }
            showEditModal.value = true
        }

        const savePrizeType = async () => {
            saving.value = true
            try {
                let response
                if (showEditModal.value && currentPrizeType.value) {
                    response = await apiPut(`/wow/prize-types/${currentPrizeType.value.id}`, form.value)
                } else {
                    response = await apiPost('/wow/prize-types', form.value)
                }

                if (!response.ok) {
                    const errorData = await response.json()
                    throw new Error(errorData.message || 'Ошибка сохранения типа приза')
                }

                await Swal.fire({
                    title: 'Успешно',
                    text: showEditModal.value ? 'Тип приза обновлен' : 'Тип приза создан',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                })

                closeModal()
                await fetchPrizeTypes()
            } catch (err) {
                await Swal.fire({
                    title: 'Ошибка',
                    text: err.message || 'Ошибка сохранения типа приза',
                    icon: 'error',
                    confirmButtonText: 'ОК'
                })
            } finally {
                saving.value = false
            }
        }

        const deletePrizeType = async (prizeType) => {
            const result = await Swal.fire({
                title: 'Удалить тип приза?',
                text: `Вы уверены, что хотите удалить "${prizeType.name}"?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Да, удалить',
                cancelButtonText: 'Отмена',
            })

            if (result.isConfirmed) {
                try {
                    const response = await apiDelete(`/wow/prize-types/${prizeType.id}`)
                    if (!response.ok) {
                        const errorData = await response.json()
                        throw new Error(errorData.message || 'Ошибка удаления типа приза')
                    }

                    await Swal.fire({
                        title: 'Успешно',
                        text: 'Тип приза удален',
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end'
                    })

                    await fetchPrizeTypes()
                } catch (err) {
                    await Swal.fire({
                        title: 'Ошибка',
                        text: err.message || 'Ошибка удаления типа приза',
                        icon: 'error',
                        confirmButtonText: 'ОК'
                    })
                }
            }
        }

        const openMediaSelector = () => {
            selectedMediaFile.value = null
            showMediaModal.value = true
        }

        const closeMediaModal = () => {
            showMediaModal.value = false
            selectedMediaFile.value = null
        }

        const handleMediaFileSelected = (file) => {
            if (file) {
                const url = file.url || (file.metadata?.path ? '/' + file.metadata.path : '')
                form.value.icon_url = url || ''
                selectedMediaFile.value = file
                setTimeout(() => {
                    closeMediaModal()
                }, 300)
            }
        }

        const handleImageError = (event) => {
            event.target.style.display = 'none'
        }

        onMounted(() => {
            fetchPrizeTypes()
        })

        return {
            loading,
            saving,
            error,
            prizeTypes,
            showCreateModal,
            showEditModal,
            showMediaModal,
            form,
            getTypeLabel,
            getActionLabel,
            editPrizeType,
            savePrizeType,
            deletePrizeType,
            closeModal,
            openMediaSelector,
            closeMediaModal,
            handleMediaFileSelected,
            handleImageError,
        }
    },
}
</script>

