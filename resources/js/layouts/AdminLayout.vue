<template>
    <div class="flex h-screen w-full overflow-hidden bg-background">
        <Sidebar />
        <div class="flex flex-1 flex-col overflow-hidden min-w-0">
            <Header />
            <main class="flex-1 overflow-y-auto bg-background p-4 sm:p-6 lg:p-8">
                <div class="max-w-[1600px] mx-auto">
                    <router-view />
                </div>
            </main>
        </div>
    </div>
</template>

<script>
import Sidebar from '../components/admin/Sidebar.vue';
import Header from '../components/admin/Header.vue';

export default {
    name: 'AdminLayout',
    components: {
        Sidebar,
        Header,
    },
};
</script>

