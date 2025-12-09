<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { ScrollArea } from '@/components/ui/scroll-area';
import { cn } from '@/lib/utils'

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()
</script>

<template>
  <div
    data-slot="sidebar-content"
    data-sidebar="content"
    :class="cn('flex min-h-0 flex-1 flex-col gap-2 group-data-[collapsible=icon]:overflow-hidden overflow-hidden', props.class)"
  >
    <ScrollArea class="h-full w-full rounded-lg px-1 border">
      <slot />
    </ScrollArea>
  </div>
</template>
