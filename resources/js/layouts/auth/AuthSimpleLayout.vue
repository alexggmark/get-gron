<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';
import { Link } from '@inertiajs/vue3';
import LoaderImages from '@/components/LoaderImages.vue';
import { ref, onMounted } from 'vue';

defineProps<{
    title?: string;
    description?: string;
}>();

const isVisible = ref(false);
const isVisible2 = ref(false);

onMounted(() => {
    // Small delay to ensure smooth animation
    requestAnimationFrame(() => {
        isVisible.value = true;
    });
    setTimeout(() => {
        requestAnimationFrame(() => {
            isVisible2.value = true;
        });
    }, 120)
});
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10"
    >
        <Link
            :href="home()"
            class="flex flex-col items-center gap-2 font-medium transition-all duration-500 ease-out"
            :class="isVisible2 ? 'translate-y-0 opacity-100' : 'translate-y-10 opacity-0'"
        >
            <div class="max-w-40 h-24 mb-4">
                <LoaderImages />
            </div>
            <span class="sr-only">{{ title }}</span>
            <h1 class="text-4xl font-medium mb-1">Grøn</h1>
        </Link>
        <div
            class="w-full max-w-96 transition-all duration-500 ease-out rounded-lg bg-sidebar border p-6"
            :class="isVisible ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0'"
        >
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-4">
                    <div class="space-y-2 text-center">
                        <h1 class="text-lg font-medium">{{ title }}</h1>
                        <p class="text-center text-sm text-muted-foreground">
                            {{ description }}
                        </p>
                    </div>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
