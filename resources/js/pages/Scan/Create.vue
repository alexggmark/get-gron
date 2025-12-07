<script setup lang="ts">
// import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { useForm } from '@inertiajs/vue3'
import { Head } from '@inertiajs/vue3'
import { create, store } from '@/routes/scan'
import { type BreadcrumbItem } from '@/types'

defineProps()

// const breadcrumbs: BreadcrumbItem[] = [
//     { title: 'Scans', href: create.url() },
//     { title: 'New Scan', href: create.url() },
// ]

const form = useForm({
    url: '',
})

function submit() {
    form.post(store.url())
}
</script>

<template>
    <Head title="New Scan" />

    <!-- <AppLayout :breadcrumbs="breadcrumbs"> -->
        <div class="flex items-center justify-center min-h-[calc(100vh-8rem)] p-6">
            <Card class="w-full max-w-lg">
                <CardHeader>
                    <CardTitle>Analyze Your Website</CardTitle>
                    <CardDescription>
                        Enter your website URL to get a comprehensive analysis of performance, CTA effectiveness, and conversion optimization.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-6">
                        <div class="space-y-2">
                            <Label for="url">Website URL</Label>
                            <Input
                                id="url"
                                v-model="form.url"
                                type="url"
                                placeholder="https://example.com"
                                required
                                :class="{ 'border-destructive': form.errors.url }"
                            />
                            <p v-if="form.errors.url" class="text-sm text-destructive">
                                {{ form.errors.url }}
                            </p>
                        </div>

                        <Button type="submit" class="w-full" :disabled="form.processing">
                            <span v-if="form.processing">Analyzing...</span>
                            <span v-else>Start Scan</span>
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </div>
    <!-- </AppLayout> -->
</template>
