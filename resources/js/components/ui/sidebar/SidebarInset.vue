<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
// import { ScrollArea } from '@/components/ui/scroll-area';
import { cn } from '@/lib/utils'

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()
</script>

<template>
  <main
    data-slot="sidebar-inset"
    :class="cn(
      'bg-background relative flex w-full flex-1 flex-col overflow-hidden',
      'md:peer-data-[variant=inset]:m-3 md:peer-data-[variant=inset]:ml-0 md:peer-data-[variant=inset]:rounded-xl md:peer-data-[variant=inset]:border md:peer-data-[variant=inset]:peer-data-[state=collapsed]:ml-0',
      props.class,
    )"
    style="max-height: calc(100vh - 1.5rem)"
  >
    <!-- <ScrollArea class="h-full w-full"> -->
      <slot />
    <!-- </ScrollArea> -->
  </main>
</template>
