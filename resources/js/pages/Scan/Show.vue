<script setup lang="ts">
// import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Spinner } from '@/components/ui/spinner'
import { Head, router } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { create, show } from '@/routes/scan'
import { type BreadcrumbItem } from '@/types'

interface Scan {
    id: number
    url: string
    cms_type: string | null
    status: 'pending' | 'processing' | 'completed' | 'failed'
    failed_step: string | null
    lighthouse_performance: number | null
    lighthouse_accessibility: number | null
    lighthouse_seo: number | null
    lighthouse_average: number | null
    cta_score: number | null
    cta_details: Record<string, unknown> | null
    cta_count: number | null
    form_friction_score: number | null
    form_details: Record<string, unknown> | null
    form_count: number | null
    trust_signals: Record<string, unknown> | null
    trust_signal_count: number | null
    mobile_issues: Record<string, unknown> | null
    mobile_issue_count: number | null
    readability_score: number | null
    image_issues: Record<string, unknown> | null
    image_issue_count: number | null
    schema_detected: boolean | null
    screenshot_url: string | null
    overall_score: number | null
    created_at: string
    updated_at: string
}

interface Props {
    scan: Scan
}

const props = defineProps<Props>()

// const breadcrumbs: BreadcrumbItem[] = [
//     { title: 'Scans', href: create.url() },
//     { title: props.scan.url, href: show.url(props.scan.id) },
// ]

const pollingInterval = ref<ReturnType<typeof setInterval> | null>(null)

const isLoading = computed(() => ['pending', 'processing'].includes(props.scan.status))

function startPolling() {
    if (pollingInterval.value) return

    pollingInterval.value = setInterval(() => {
        router.reload({ only: ['scan'] })
    }, 2000)
}

function stopPolling() {
    if (pollingInterval.value) {
        clearInterval(pollingInterval.value)
        pollingInterval.value = null
    }
}

onMounted(() => {
    if (isLoading.value) {
        startPolling()
    }
})

onUnmounted(() => {
    stopPolling()
})

// Watch for status changes to stop polling when completed
router.on('success', () => {
    if (!isLoading.value) {
        stopPolling()
    }
})

function getScoreColor(score: number | null): string {
    if (score === null) return 'text-muted-foreground'
    if (score >= 90) return 'text-green-500'
    if (score >= 50) return 'text-yellow-500'
    return 'text-red-500'
}

function getScoreBgColor(score: number | null): string {
    if (score === null) return 'bg-muted'
    if (score >= 90) return 'bg-green-500/10'
    if (score >= 50) return 'bg-yellow-500/10'
    return 'bg-red-500/10'
}

function formatScore(score: number | null): string {
    if (score === null) return '-'
    return Math.round(score).toString()
}
</script>

<template>
    <Head :title="`Scan - ${scan.url}`" />

    <!-- <AppLayout :breadcrumbs="breadcrumbs"> -->
        <div class="p-6 space-y-6 flex justify-center">
            <div class="max-w-3xl flex flex-col gap-3">
                <!-- Header -->
                <div class="flex items-start justify-between">
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight">{{ scan.url }}</h1>
                        <p class="text-muted-foreground">
                            <span v-if="scan.cms_type" class="capitalize">{{ scan.cms_type }} &middot; </span>
                            Scanned {{ new Date(scan.created_at).toLocaleDateString() }}
                        </p>
                    </div>
                    <div
                        :class="[
                            'px-3 py-1 rounded-full text-sm font-medium capitalize',
                            scan.status === 'completed' ? 'bg-green-500/10 text-green-500' : '',
                            scan.status === 'failed' ? 'bg-red-500/10 text-red-500' : '',
                            ['pending', 'processing'].includes(scan.status) ? 'bg-blue-500/10 text-blue-500' : '',
                        ]"
                    >
                        {{ scan.status }}
                    </div>
                </div>

                <!-- Loading State -->
                <Card v-if="isLoading" class="py-12">
                    <CardContent class="flex flex-col items-center justify-center text-center">
                        <Spinner class="size-8 mb-4" />
                        <h3 class="text-lg font-medium">Analyzing your website...</h3>
                        <p class="text-muted-foreground mt-1">
                            This may take a minute. We're checking performance, CTAs, forms, and more.
                        </p>
                    </CardContent>
                </Card>

                <!-- Failed State -->
                <Card v-else-if="scan.status === 'failed'" class="border-destructive">
                    <CardHeader>
                        <CardTitle class="text-destructive">Scan Failed</CardTitle>
                        <CardDescription>
                            We couldn't complete the scan for this website. Please check the URL and try again.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p class="text-sm text-muted-foreground">
                            Failed during: <span class="font-medium text-destructive">{{ scan.failed_step ?? 'unknown step' }}</span>
                        </p>
                    </CardContent>
                </Card>

                <!-- Results Dashboard -->
                <template v-else>
                    <!-- Overall Score -->
                    <Card v-if="scan.overall_score !== null">
                        <CardHeader>
                            <CardTitle>Overall Score</CardTitle>
                            <CardDescription>Combined score across all metrics</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="flex items-center gap-4">
                                <div
                                    :class="[
                                        'size-24 rounded-full flex items-center justify-center text-3xl font-bold',
                                        getScoreBgColor(scan.overall_score),
                                        getScoreColor(scan.overall_score),
                                    ]"
                                >
                                    {{ formatScore(scan.overall_score) }}
                                </div>
                                <div class="flex-1">
                                    <div class="h-3 bg-muted rounded-full overflow-hidden">
                                        <div
                                            class="h-full transition-all duration-500"
                                            :class="[
                                                scan.overall_score >= 90 ? 'bg-green-500' : '',
                                                scan.overall_score >= 50 && scan.overall_score < 90 ? 'bg-yellow-500' : '',
                                                scan.overall_score < 50 ? 'bg-red-500' : '',
                                            ]"
                                            :style="{ width: `${scan.overall_score}%` }"
                                        />
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Screenshot -->
                    <Card v-if="scan.screenshot_url">
                        <CardHeader>
                            <CardTitle>Page Screenshot</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <img
                                :src="scan.screenshot_url"
                                :alt="`Screenshot of ${scan.url}`"
                                class="rounded-lg border w-full"
                            />
                        </CardContent>
                    </Card>

                    <!-- Lighthouse Scores -->
                    <div class="grid gap-4 md:grid-cols-3">
                        <Card>
                            <CardHeader class="pb-2">
                                <CardDescription>Performance</CardDescription>
                                <CardTitle :class="['text-3xl', getScoreColor(scan.lighthouse_performance)]">
                                    {{ formatScore(scan.lighthouse_performance) }}
                                </CardTitle>
                            </CardHeader>
                        </Card>
                        <Card>
                            <CardHeader class="pb-2">
                                <CardDescription>Accessibility</CardDescription>
                                <CardTitle :class="['text-3xl', getScoreColor(scan.lighthouse_accessibility)]">
                                    {{ formatScore(scan.lighthouse_accessibility) }}
                                </CardTitle>
                            </CardHeader>
                        </Card>
                        <Card>
                            <CardHeader class="pb-2">
                                <CardDescription>SEO</CardDescription>
                                <CardTitle :class="['text-3xl', getScoreColor(scan.lighthouse_seo)]">
                                    {{ formatScore(scan.lighthouse_seo) }}
                                </CardTitle>
                            </CardHeader>
                        </Card>
                    </div>

                    <!-- CTA Analysis -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Call-to-Action Analysis</CardTitle>
                            <CardDescription>
                                {{ scan.cta_count ?? 0 }} CTAs found on the page
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="flex items-center gap-4">
                                <div
                                    :class="[
                                        'size-16 rounded-full flex items-center justify-center text-xl font-bold',
                                        getScoreBgColor(scan.cta_score),
                                        getScoreColor(scan.cta_score),
                                    ]"
                                >
                                    {{ formatScore(scan.cta_score) }}
                                </div>
                                <div class="flex-1">
                                    <p class="font-medium">CTA Score</p>
                                    <p class="text-sm text-muted-foreground">
                                        Measures visibility, placement, and effectiveness of your CTAs
                                    </p>
                                </div>
                            </div>
                            <div v-if="scan.cta_details" class="mt-4 p-4 bg-muted rounded-lg">
                                <pre class="text-xs overflow-auto">{{ JSON.stringify(scan.cta_details, null, 2) }}</pre>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Form Analysis -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Form Friction Analysis</CardTitle>
                            <CardDescription>
                                {{ scan.form_count ?? 0 }} forms found on the page
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="flex items-center gap-4">
                                <div
                                    :class="[
                                        'size-16 rounded-full flex items-center justify-center text-xl font-bold',
                                        getScoreBgColor(scan.form_friction_score),
                                        getScoreColor(scan.form_friction_score),
                                    ]"
                                >
                                    {{ formatScore(scan.form_friction_score) }}
                                </div>
                                <div class="flex-1">
                                    <p class="font-medium">Form Friction Score</p>
                                    <p class="text-sm text-muted-foreground">
                                        Lower friction means easier form completion for users
                                    </p>
                                </div>
                            </div>
                            <div v-if="scan.form_details" class="mt-4 p-4 bg-muted rounded-lg">
                                <pre class="text-xs overflow-auto">{{ JSON.stringify(scan.form_details, null, 2) }}</pre>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Additional Metrics Grid -->
                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                        <Card>
                            <CardHeader class="pb-2">
                                <CardDescription>Trust Signals</CardDescription>
                                <CardTitle class="text-2xl">
                                    {{ scan.trust_signal_count ?? 0 }}
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
                                <CardTitle :class="['text-2xl', (scan.mobile_issue_count ?? 0) > 0 ? 'text-yellow-500' : 'text-green-500']">
                                    {{ scan.mobile_issue_count ?? 0 }}
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
                                <CardTitle :class="['text-2xl', (scan.image_issue_count ?? 0) > 0 ? 'text-yellow-500' : 'text-green-500']">
                                    {{ scan.image_issue_count ?? 0 }}
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
                                <CardTitle :class="['text-2xl', getScoreColor(scan.readability_score)]">
                                    {{ formatScore(scan.readability_score) }}
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
                                        scan.schema_detected ? 'bg-green-500' : 'bg-red-500',
                                    ]"
                                />
                                <span>
                                    {{ scan.schema_detected ? 'Schema markup detected' : 'No schema markup found' }}
                                </span>
                            </div>
                        </CardContent>
                    </Card>
                </template>
            </div>
        </div>
    <!-- </AppLayout> -->
</template>
