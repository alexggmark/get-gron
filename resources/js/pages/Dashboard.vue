<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import LoaderImages from '@/components/LoaderImages.vue';
import { Input } from '@/components/ui/input';
import { Head, router } from '@inertiajs/vue3';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Search } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

interface Scan {
    id: number;
    url: string;
    status: 'pending' | 'processing' | 'completed' | 'failed';
    current_step: string | null;
    failed_step: string | null;
    lighthouse_performance: number | null;
    lighthouse_accessibility: number | null;
    lighthouse_seo: number | null;
    lighthouse_average: number | null;
    cta_score: number | null;
    cta_details: Record<string, unknown>[] | null;
    cta_count: number | null;
    form_friction_score: number | null;
    form_details: Record<string, unknown>[] | null;
    form_count: number | null;
    trust_signals: Record<string, unknown>[] | null;
    trust_signal_count: number | null;
    mobile_issues: Record<string, unknown>[] | null;
    mobile_issue_count: number | null;
    readability_score: number | null;
    image_issues: Record<string, unknown>[] | null;
    image_issue_count: number | null;
    schema_detected: boolean | null;
    screenshot_url: string | null;
    overall_score: number | null;
    created_at: string;
    updated_at: string;
}

interface Props {
    selectedScan: Scan | null;
}

const props = defineProps<Props>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => {
    const items: BreadcrumbItem[] = [
        {
            title: 'Dashboard',
            href: dashboard().url,
        },
    ];

    if (props.selectedScan) {
        items.push({
            title: formatUrl(props.selectedScan.url),
            href: `/dashboard/scan/${props.selectedScan.id}`,
        });
    }

    return items;
});

const pollingInterval = ref<ReturnType<typeof setInterval> | null>(null);
const dotsInterval = ref<ReturnType<typeof setInterval> | null>(null);
const dots = ref('.');

const isLoading = computed(() =>
    props.selectedScan && ['pending', 'processing'].includes(props.selectedScan.status)
);

function startDotsAnimation() {
    if (dotsInterval.value) return;
    dotsInterval.value = setInterval(() => {
        dots.value = dots.value.length >= 3 ? '' : dots.value + '.';
    }, 400);
}

function stopDotsAnimation() {
    if (dotsInterval.value) {
        clearInterval(dotsInterval.value);
        dotsInterval.value = null;
        dots.value = '.';
    }
}

function startPolling() {
    if (pollingInterval.value) return;

    pollingInterval.value = setInterval(() => {
        router.reload({ only: ['selectedScan', 'userScans'] });
    }, 2000);
}

function stopPolling() {
    if (pollingInterval.value) {
        clearInterval(pollingInterval.value);
        pollingInterval.value = null;
    }
}

watch(() => props.selectedScan?.status, (newStatus) => {
    if (newStatus && ['pending', 'processing'].includes(newStatus)) {
        startPolling();
        startDotsAnimation();
    } else {
        stopPolling();
        stopDotsAnimation();
    }
}, { immediate: true });

onMounted(() => {
    if (isLoading.value) {
        startPolling();
        startDotsAnimation();
    }
});

onUnmounted(() => {
    stopPolling();
    stopDotsAnimation();
});

function formatUrl(fullUrl: string): string {
    try {
        const parsed = new URL(fullUrl);
        return parsed.hostname.replace('www.', '');
    } catch {
        return fullUrl;
    }
}

function getScoreColor(score: number | null): string {
    if (score === null) return 'text-muted-foreground';
    if (score >= 90) return 'text-green-500';
    if (score >= 50) return 'text-yellow-500';
    return 'text-red-500';
}

function getScoreBgColor(score: number | null): string {
    if (score === null) return 'bg-muted';
    if (score >= 90) return 'bg-green-500/10';
    if (score >= 50) return 'bg-yellow-500/10';
    return 'bg-red-500/10';
}

function formatScore(score: number | null): string {
    if (score === null) return '-';
    return Math.round(score).toString();
}

const url = ref('');
const isSubmitting = ref(false);

function submitScan() {
    if (!url.value || isSubmitting.value) return;

    isSubmitting.value = true;
    router.post('/dashboard/scan', { url: url.value }, {
        onSuccess: () => {
            url.value = '';
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
}
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-3 overflow-x-auto rounded-xl p-4">
            <!-- No scan selected - show welcome state -->
            <template v-if="!selectedScan">
                <div class="flex flex-1 items-center justify-center">
                    <div class="text-center max-w-md">
                        <div class="flex justify-center">
                            <div class="max-w-72">
                                <!-- <LoaderImages /> -->
                                <img src="/storage/assets/Wink black.png" />
                            </div>
                        </div>

                        <h2 class="text-2xl font-bold tracking-tight mb-2">Scan a Website</h2>
                        <p class="text-muted-foreground mb-6">
                            Enter a URL to analyze its performance, CTAs, forms, and more.
                        </p>
                        <form @submit.prevent="submitScan" class="flex gap-2">
                            <Input
                                v-model="url"
                                type="url"
                                placeholder="https://example.com"
                                class="flex-1"
                                :disabled="isSubmitting"
                            />
                            <Button
                                type="submit"
                                :disabled="!url || isSubmitting"
                            >
                            Scan
                                <Spinner v-if="isSubmitting" class="size-4" />
                                <Search v-else class="size-4" />
                            </Button>
                        </form>
                    </div>
                </div>
            </template>

            <!-- Scan selected - show results -->
            <template v-else>
                <div class="max-w-2xl mx-auto w-full space-y-2 pt-4">
                    <!-- Header -->
                    <div class="flex items-end justify-between">
                        <div class="my-2">
                            <h1 class="text-xl font-bold tracking-tight mb-1">{{ selectedScan.url }}</h1>
                            <p class="text-muted-foreground text-sm">
                                Scanned {{ new Date(selectedScan.created_at).toLocaleDateString() }}
                            </p>
                        </div>
                        <div
                            :class="[
                                'px-3 py-1 my-2 rounded-full text-xs font-medium capitalize',
                                selectedScan.status === 'completed' ? 'bg-green-500/10 text-green-500' : '',
                                selectedScan.status === 'failed' ? 'bg-red-500/10 text-red-500' : '',
                                ['pending', 'processing'].includes(selectedScan.status) ? 'bg-blue-500/10 text-blue-500' : '',
                            ]"
                        >
                            {{ selectedScan.status }}
                        </div>
                    </div>

                    <!-- Loading State -->
                    <Card v-if="isLoading" class="py-12">
                        <CardContent class="flex flex-col items-center justify-center text-center">
                            <div class="max-w-56 h-40">
                                <LoaderImages />
                            </div>
                            <h3 class="text-lg font-medium">Analysing your website{{ dots }}</h3>
                            <Transition name="fade" mode="out-in">
                                <p :key="selectedScan.current_step ?? 'starting'" class="text-muted-foreground mt-1">
                                    {{ selectedScan.current_step || 'Starting analysis' }}
                                </p>
                            </Transition>
                        </CardContent>
                    </Card>

                    <!-- Failed State -->
                    <Card v-else-if="selectedScan.status === 'failed'" class="border-destructive">
                        <CardHeader>
                            <CardTitle class="text-destructive">Scan Failed</CardTitle>
                            <CardDescription>
                                We couldn't complete the scan for this website. Please check the URL and try again.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <p class="text-sm text-muted-foreground">
                                Failed during: <span class="font-medium text-destructive">{{ selectedScan.failed_step ?? 'unknown step' }}</span>
                            </p>
                        </CardContent>
                    </Card>

                    <!-- Results Dashboard -->
                    <template v-else>
                        <!-- Overall Score -->
                        <Card v-if="selectedScan.overall_score !== null">
                            <CardHeader>
                                <CardTitle>Overall Score</CardTitle>
                                <CardDescription>Combined score across all metrics</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div class="flex items-center gap-3">
                                    <div
                                        :class="[
                                            'size-24 rounded-full flex items-center justify-center text-2xl font-bold',
                                            getScoreBgColor(selectedScan.overall_score),
                                            getScoreColor(selectedScan.overall_score),
                                        ]"
                                    >
                                        {{ formatScore(selectedScan.overall_score) }}
                                    </div>
                                    <div class="flex-1">
                                        <div class="h-3 bg-muted rounded-full overflow-hidden">
                                            <div
                                                class="h-full transition-all duration-500"
                                                :class="[
                                                    selectedScan.overall_score >= 90 ? 'bg-green-500' : '',
                                                    selectedScan.overall_score >= 50 && selectedScan.overall_score < 90 ? 'bg-yellow-500' : '',
                                                    selectedScan.overall_score < 50 ? 'bg-red-500' : '',
                                                ]"
                                                :style="{ width: `${selectedScan.overall_score}%` }"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Lighthouse Scores -->
                        <div class="grid gap-2 md:grid-cols-3">
                            <Card>
                                <CardHeader class="pb-2">
                                    <CardDescription>Performance</CardDescription>
                                    <CardTitle :class="['text-2xl', getScoreColor(selectedScan.lighthouse_performance)]">
                                        {{ formatScore(selectedScan.lighthouse_performance) }}
                                    </CardTitle>
                                </CardHeader>
                            </Card>
                            <Card>
                                <CardHeader class="pb-2">
                                    <CardDescription>Accessibility</CardDescription>
                                    <CardTitle :class="['text-2xl', getScoreColor(selectedScan.lighthouse_accessibility)]">
                                        {{ formatScore(selectedScan.lighthouse_accessibility) }}
                                    </CardTitle>
                                </CardHeader>
                            </Card>
                            <Card>
                                <CardHeader class="pb-2">
                                    <CardDescription>SEO</CardDescription>
                                    <CardTitle :class="['text-2xl', getScoreColor(selectedScan.lighthouse_seo)]">
                                        {{ formatScore(selectedScan.lighthouse_seo) }}
                                    </CardTitle>
                                </CardHeader>
                            </Card>
                        </div>

                        <!-- Screenshot -->
                        <Card v-if="selectedScan.screenshot_url">
                            <CardHeader>
                                <CardTitle>Page Screenshot</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <ScrollArea class="h-96 w-full rounded-lg p-1.5 border">
                                    <img
                                        :src="selectedScan.screenshot_url"
                                        :alt="`Screenshot of ${selectedScan.url}`"
                                        class="border w-full"
                                    />
                                </ScrollArea>
                            </CardContent>
                        </Card>

                        <!-- CTA Analysis -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Call-to-Action Analysis</CardTitle>
                                <CardDescription>
                                    {{ selectedScan.cta_count ?? 0 }} CTAs found on the page
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div class="flex items-center gap-3">
                                    <div
                                        :class="[
                                            'size-16 rounded-full flex items-center justify-center text-xl font-bold',
                                            getScoreBgColor(selectedScan.cta_score),
                                            getScoreColor(selectedScan.cta_score),
                                        ]"
                                    >
                                        {{ formatScore(selectedScan.cta_score) }}
                                    </div>
                                    <div class="flex-1">
                                        <p class="font-medium">CTA Score</p>
                                        <p class="text-sm text-muted-foreground">
                                            Measures visibility, placement, and effectiveness of your CTAs
                                        </p>
                                    </div>
                                </div>
                                <ScrollArea class="h-96 w-full rounded-lg border mt-4 p-1.5">
                                    <div v-if="selectedScan.cta_details && selectedScan.cta_details.length > 0" class="space-y-2">
                                        <div
                                            v-for="(cta, index) in selectedScan.cta_details"
                                            :key="index"
                                            class="flex items-center gap-3 p-3 bg-muted rounded-md"
                                        >
                                            <div class="flex-1 min-w-0">
                                                <p class="font-medium truncate">{{ cta.text }}</p>
                                                <p class="text-xs text-muted-foreground">
                                                    &lt;{{ cta.element }}&gt;
                                                    <span v-if="cta.x !== undefined"> · {{ cta.x }}, {{ cta.y }} · {{ cta.width }}×{{ cta.height }}px</span>
                                                </p>
                                            </div>
                                            <div v-if="cta.issues && cta.issues.length > 0" class="flex flex-wrap gap-1">
                                                <span
                                                    v-for="(issue, i) in cta.issues"
                                                    :key="i"
                                                    class="px-2 py-0.5 text-xs bg-yellow-500/10 text-yellow-600 rounded"
                                                >
                                                    {{ issue }}
                                                </span>
                                            </div>
                                            <div v-else class="px-2 py-0.5 text-xs bg-green-500/10 text-green-600 rounded">
                                                No issues
                                            </div>
                                        </div>
                                    </div>
                                </ScrollArea>
                            </CardContent>
                        </Card>

                        <!-- Form Analysis -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Form Friction Analysis</CardTitle>
                                <CardDescription>
                                    {{ selectedScan.form_count ?? 0 }} forms found on the page
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div class="flex items-center gap-3">
                                    <div
                                        :class="[
                                            'size-16 rounded-full flex items-center justify-center text-xl font-bold',
                                            getScoreBgColor(selectedScan.form_friction_score),
                                            getScoreColor(selectedScan.form_friction_score),
                                        ]"
                                    >
                                        {{ formatScore(selectedScan.form_friction_score) }}
                                    </div>
                                    <div class="flex-1">
                                        <p class="font-medium">Form Friction Score</p>
                                        <p class="text-sm text-muted-foreground">
                                            Lower friction means easier form completion for users
                                        </p>
                                    </div>
                                </div>
                                <div v-if="selectedScan.form_details && selectedScan.form_details.length > 0" class="mt-4 p-4 bg-muted rounded-lg">
                                    <pre class="text-xs overflow-auto">{{ JSON.stringify(selectedScan.form_details, null, 2) }}</pre>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Additional Metrics Grid -->
                        <div class="grid gap-2 md:grid-cols-2 lg:grid-cols-4">
                            <Card>
                                <CardHeader class="pb-2">
                                    <CardDescription>Trust Signals</CardDescription>
                                    <CardTitle class="text-2xl">
                                        {{ selectedScan.trust_signal_count ?? 0 }}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p class="text-xs text-muted-foreground">
                                        Badges, testimonials, security indicators found
                                    </p>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader class="pb-2">
                                    <CardDescription>Mobile Issues</CardDescription>
                                    <CardTitle :class="['text-2xl', (selectedScan.mobile_issue_count ?? 0) > 0 ? 'text-yellow-500' : 'text-green-500']">
                                        {{ selectedScan.mobile_issue_count ?? 0 }}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p class="text-xs text-muted-foreground">
                                        Problems affecting mobile user experience
                                    </p>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader class="pb-2">
                                    <CardDescription>Image Issues</CardDescription>
                                    <CardTitle :class="['text-2xl', (selectedScan.image_issue_count ?? 0) > 0 ? 'text-yellow-500' : 'text-green-500']">
                                        {{ selectedScan.image_issue_count ?? 0 }}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p class="text-xs text-muted-foreground">
                                        Missing alt text, large images, etc.
                                    </p>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader class="pb-2">
                                    <CardDescription>Readability</CardDescription>
                                    <CardTitle :class="['text-2xl', getScoreColor(selectedScan.readability_score)]">
                                        {{ formatScore(selectedScan.readability_score) }}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p class="text-xs text-muted-foreground">
                                        How easy your content is to read
                                    </p>
                                </CardContent>
                            </Card>
                        </div>

                        <!-- Schema Detection -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Structured Data</CardTitle>
                                <CardDescription>Schema.org markup detection</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div class="flex items-center gap-2">
                                    <div
                                        :class="[
                                            'size-3 rounded-full',
                                            selectedScan.schema_detected ? 'bg-green-500' : 'bg-red-500',
                                        ]"
                                    />
                                    <span>
                                        {{ selectedScan.schema_detected ? 'Schema markup detected' : 'No schema markup found' }}
                                    </span>
                                </div>
                            </CardContent>
                        </Card>
                    </template>
                </div>
            </template>
        </div>
    </AppLayout>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
