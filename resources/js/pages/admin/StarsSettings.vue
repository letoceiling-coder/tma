<template>
    <div class="stars-settings-page">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-foreground">Telegram Stars</h1>
            <p class="text-muted-foreground mt-1">Настройки оплаты через Telegram Stars</p>
        </div>

        <div class="bg-card rounded-lg border border-border p-6">
            <form @submit.prevent="saveSettings" class="space-y-6">
                <div class="space-y-4">
                    <div>
                        <label 
                            for="stars_per_ticket_purchase" 
                            class="block text-sm font-medium text-foreground mb-2"
                        >
                            Стоимость прокрута в звёздах
                            <span class="text-destructive">*</span>
                        </label>
                        <input
                            id="stars_per_ticket_purchase"
                            v-model.number="formData.stars_per_ticket_purchase"
                            type="number"
                            min="1"
                            max="5000"
                            placeholder="50"
                            class="w-full px-4 py-2 bg-input border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-ring focus:border-transparent"
                            :class="{ 'border-destructive': errors.stars_per_ticket_purchase }"
                        />
                        <p 
                            v-if="errors.stars_per_ticket_purchase" 
                            class="mt-1 text-sm text-destructive"
                        >
                            {{ errors.stars_per_ticket_purchase }}
                        </p>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Укажите, сколько Telegram Stars нужно списывать за покупку прокрутов. 
                            Если не указано — будет использоваться дефолтное значение 50.
                            <br>
                            <span class="text-xs">Это значение используется при создании инвойса через Telegram Bot API.</span>
                        </p>
                    </div>

                    <div class="bg-muted/50 rounded-lg p-4">
                        <h3 class="text-sm font-semibold text-foreground mb-2">Текущее значение</h3>
                        <p class="text-sm text-muted-foreground">
                            Сейчас за покупку 20 прокрутов списывается 
                            <span class="font-semibold text-foreground">{{ currentValue || 50 }}</span> звёзд
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-4 pt-4 border-t border-border">
                    <button
                        type="submit"
                        :disabled="isSaving"
                        class="px-6 py-2 bg-primary text-primary-foreground rounded-lg hover:bg-primary/90 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span v-if="!isSaving">Сохранить</span>
                        <span v-else>Сохранение...</span>
                    </button>
                    <button
                        type="button"
                        @click="resetForm"
                        :disabled="isSaving"
                        class="px-6 py-2 bg-muted text-muted-foreground rounded-lg hover:bg-muted/80 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Отменить
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue';
import axios from 'axios';

export default {
    name: 'StarsSettings',
    setup() {
        const formData = ref({
            stars_per_ticket_purchase: 50,
        });
        const currentValue = ref(null);
        const errors = ref({});
        const isSaving = ref(false);

        const loadSettings = async () => {
            try {
                const response = await axios.get('/api/v1/wow/wheel/settings');
                if (response.data?.settings) {
                    const settings = response.data.settings;
                    formData.value.stars_per_ticket_purchase = settings.stars_per_ticket_purchase ?? 50;
                    currentValue.value = settings.stars_per_ticket_purchase ?? 50;
                }
            } catch (error) {
                console.error('Failed to load settings:', error);
            }
        };

        const saveSettings = async () => {
            errors.value = {};
            isSaving.value = true;

            // Валидация на клиенте
            if (!formData.value.stars_per_ticket_purchase || formData.value.stars_per_ticket_purchase < 1) {
                errors.value.stars_per_ticket_purchase = 'Значение должно быть не менее 1';
                isSaving.value = false;
                return;
            }

            if (formData.value.stars_per_ticket_purchase > 5000) {
                errors.value.stars_per_ticket_purchase = 'Значение не должно превышать 5000';
                isSaving.value = false;
                return;
            }

            try {
                const response = await axios.put('/api/v1/wow/wheel/settings', {
                    stars_per_ticket_purchase: formData.value.stars_per_ticket_purchase,
                });

                if (response.data?.success) {
                    currentValue.value = formData.value.stars_per_ticket_purchase;
                    alert('Настройки успешно сохранены');
                } else {
                    throw new Error(response.data?.message || 'Ошибка при сохранении');
                }
            } catch (error) {
                console.error('Failed to save settings:', error);
                if (error.response?.data?.errors) {
                    errors.value = error.response.data.errors;
                } else {
                    alert('Ошибка при сохранении настроек: ' + (error.response?.data?.message || error.message));
                }
            } finally {
                isSaving.value = false;
            }
        };

        const resetForm = async () => {
            await loadSettings();
        };

        onMounted(() => {
            loadSettings();
        });

        return {
            formData,
            currentValue,
            errors,
            isSaving,
            saveSettings,
            resetForm,
        };
    },
};
</script>
