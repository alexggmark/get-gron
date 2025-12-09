<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarSeparator,
} from '@/components/ui/sidebar';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import { type NavItem } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Globe, LayoutGrid, Search, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppLogo from './AppLogo.vue';

interface ScanSummary {
    id: number;
    url: string;
    status: 'pending' | 'processing' | 'completed' | 'failed';
    overall_score: number | null;
    created_at: string;
}

const page = usePage();

const userScans = computed(() => (page.props.userScans as ScanSummary[]) || []);
const isAuthenticated = computed(() => !!page.props.auth?.user);

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

function formatUrl(fullUrl: string): string {
    try {
        const parsed = new URL(fullUrl);
        return parsed.hostname.replace('www.', '');
    } catch {
        return fullUrl;
    }
}

function getStatusColor(status: string): string {
    switch (status) {
        case 'completed': return 'text-green-500';
        case 'failed': return 'text-red-500';
        case 'pending':
        case 'processing': return 'text-blue-500';
        default: return 'text-muted-foreground';
    }
}

function deleteScan(scanId: number, event: Event) {
    event.preventDefault();
    event.stopPropagation();

    if (!confirm('Are you sure you want to delete this scan?')) {
        return;
    }

    router.delete(`/dashboard/scan/${scanId}`);
}

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />

            <template v-if="isAuthenticated">
                <SidebarSeparator />

                <SidebarGroup>
                    <SidebarGroupLabel>New Scan</SidebarGroupLabel>
                    <SidebarGroupContent>
                        <form @submit.prevent="submitScan" class="flex gap-2 px-2">
                            <Input
                                v-model="url"
                                type="url"
                                placeholder="https://example.com"
                                class="h-8 text-xs"
                                :disabled="isSubmitting"
                            />
                            <Button
                                type="submit"
                                size="sm"
                                class="h-8 px-2"
                                :disabled="!url || isSubmitting"
                            >
                                <Spinner v-if="isSubmitting" class="size-4" />
                                <Search v-else class="size-4" />
                            </Button>
                        </form>
                    </SidebarGroupContent>
                </SidebarGroup>

                <SidebarGroup v-if="userScans.length > 0">
                    <SidebarGroupLabel>Recent Scans</SidebarGroupLabel>
                    <SidebarGroupContent>
                        <SidebarMenu>
                            <SidebarMenuItem v-for="scan in userScans" :key="scan.id" class="group/scan">
                                <SidebarMenuButton as-child size="sm">
                                    <Link :href="`/dashboard/scan/${scan.id}`" class="flex items-center gap-2">
                                        <Globe class="size-4 shrink-0" :class="getStatusColor(scan.status)" />
                                        <span class="truncate flex-1">{{ formatUrl(scan.url) }}</span>
                                        <span
                                            v-if="scan.status === 'completed' && scan.overall_score !== null"
                                            class="text-xs font-medium group-hover/scan:hidden"
                                            :class="scan.overall_score >= 70 ? 'text-green-500' : scan.overall_score >= 50 ? 'text-yellow-500' : 'text-red-500'"
                                        >
                                            {{ scan.overall_score }}
                                        </span>
                                        <Spinner
                                            v-else-if="['pending', 'processing'].includes(scan.status)"
                                            class="size-3 group-hover/scan:hidden"
                                        />
                                        <button
                                            @click="deleteScan(scan.id, $event)"
                                            class="hidden group-hover/scan:block cursor-pointer p-1 hover:bg-destructive/10 rounded text-muted-foreground hover:text-destructive"
                                            title="Delete scan"
                                        >
                                            <Trash2 class="size-3" />
                                        </button>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>
            </template>
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
