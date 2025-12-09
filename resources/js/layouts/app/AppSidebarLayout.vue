<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import { ScrollArea } from '@/components/ui/scroll-area';
import type { BreadcrumbItemType } from '@/types';
import { ref, onMounted } from 'vue';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const isVisible = ref(false);

onMounted(() => {
    // Small delay to ensure smooth animation
    requestAnimationFrame(() => {
        isVisible.value = true;
    });
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <ScrollArea
                class="transition-all duration-500 ease-out"
                :class="isVisible ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0'"
            >
                <slot />
            </ScrollArea>
        </AppContent>
    </AppShell>
</template>
