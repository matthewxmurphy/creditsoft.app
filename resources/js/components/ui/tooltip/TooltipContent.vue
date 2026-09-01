<script setup lang="ts">
import type { TooltipContentEmits, TooltipContentProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { TooltipArrow, TooltipContent, TooltipPortal, useForwardPropsEmits } from "reka-ui"
import { cn } from "@/lib/utils"

defineOptions({
  inheritAttrs: false,
})

const props = withDefaults(defineProps<TooltipContentProps & { class?: HTMLAttributes["class"], variant?: 'default' | 'card' | 'light-card' }>(), {
  sideOffset: 4,
  variant: 'default',
})

const emits = defineEmits<TooltipContentEmits>()

const delegatedProps = reactiveOmit(props, "class")
const forwarded = useForwardPropsEmits(delegatedProps, emits)
</script>

<template>
  <TooltipPortal>
    <TooltipContent
      data-slot="tooltip-content"
      v-bind="{ ...forwarded, ...$attrs }"
      :class="cn(
        'animate-in fade-in-0 zoom-in-95 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2 w-fit text-xs text-balance',
        props.variant === 'card'
          ? 'rounded-2xl border border-stone-700/70 bg-[linear-gradient(180deg,rgba(23,20,18,0.97),rgba(17,14,12,0.97))] px-3 py-2 text-stone-50 shadow-[0_20px_60px_rgba(15,23,42,0.45)] backdrop-blur'
          : props.variant === 'light-card'
            ? 'rounded-2xl border border-stone-200 bg-white px-3 py-2 text-stone-800 shadow-[0_20px_60px_rgba(28,25,23,0.18)] backdrop-blur'
            : 'bg-foreground text-background rounded-md px-3 py-1.5',
        props.class,
        'z-[1000]',
      )"
    >
      <slot />

      <TooltipArrow :class="cn(
        'z-[1000] size-2.5 translate-y-[calc(-50%_-_2px)] rotate-45 rounded-[2px]',
        props.variant === 'card'
          ? 'border-l border-t border-stone-700/70 bg-[#171412] fill-[#171412]'
          : props.variant === 'light-card'
            ? 'border-l border-t border-stone-200 bg-white fill-white'
            : 'bg-foreground fill-foreground',
      )" />
    </TooltipContent>
  </TooltipPortal>
</template>
